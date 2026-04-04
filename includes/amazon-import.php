<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import helpers for bookmarklet and API-based lookups.
 */

function book_reviews_get_amazon_import_option($key, $default = '') {
    $value = get_option($key, null);

    if ($value === null || $value === '') {
        if ($default !== '') {
            update_option($key, $default);
        }
        return $default;
    }

    return $value;
}

function book_reviews_get_amazon_import_secret() {
    $secret = get_option('book_reviews_amazon_import_secret', '');

    if (empty($secret)) {
        $secret = wp_generate_password(64, false, false);
        update_option('book_reviews_amazon_import_secret', $secret);
    }

    return $secret;
}

function book_reviews_get_amazon_import_target_url() {
    $default_url = admin_url('admin-post.php?action=book_reviews_amazon_import');
    return book_reviews_get_amazon_import_option('book_reviews_amazon_import_target_url', $default_url);
}

function book_reviews_get_api_import_setting($key, $default = '') {
    $value = get_option($key, null);

    if ($value === null || $value === '') {
        if ($default !== '') {
            update_option($key, $default);
        }
        return $default;
    }

    return $value;
}

function book_reviews_get_tmdb_api_key() {
    return trim((string) get_option('book_reviews_tmdb_api_key', ''));
}

function book_reviews_get_rawg_api_key() {
    return trim((string) get_option('book_reviews_rawg_api_key', ''));
}

function book_reviews_normalize_prefill_data($data) {
    $allowed_media_types = array('book', 'movie', 'music', 'game');
    $media_type = isset($data['media_type']) ? sanitize_text_field($data['media_type']) : 'book';

    return array(
        'media_type' => in_array($media_type, $allowed_media_types, true) ? $media_type : 'book',
        'title' => isset($data['title']) ? sanitize_text_field($data['title']) : '',
        'creator' => isset($data['creator']) ? sanitize_text_field($data['creator']) : '',
        'cover_image_url' => isset($data['cover_image_url']) ? esc_url_raw($data['cover_image_url']) : '',
        'category' => isset($data['category']) ? sanitize_text_field($data['category']) : '',
        'source_url' => isset($data['source_url']) ? esc_url_raw($data['source_url']) : '',
    );
}

function book_reviews_store_import_prefill($data, $source = 'api') {
    $prefill_key = strtolower(wp_generate_password(20, false, false));
    $payload = array(
        'source' => sanitize_key($source),
        'data' => book_reviews_normalize_prefill_data($data),
    );

    set_transient('book_reviews_import_prefill_' . $prefill_key, $payload, 10 * MINUTE_IN_SECONDS);
    return $prefill_key;
}

function book_reviews_get_import_prefill($prefill_key) {
    if (empty($prefill_key)) {
        return null;
    }

    $payload = get_transient('book_reviews_import_prefill_' . $prefill_key);
    if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
        $payload['data'] = book_reviews_normalize_prefill_data($payload['data']);
        return $payload;
    }

    return null;
}

function book_reviews_delete_import_prefill($prefill_key) {
    if (!empty($prefill_key)) {
        delete_transient('book_reviews_import_prefill_' . $prefill_key);
        delete_transient('book_reviews_amazon_prefill_' . $prefill_key);
    }
}

function book_reviews_get_prefill_redirect_url($prefill_key, $source = 'api') {
    return add_query_arg(
        array(
            'page' => 'book-reviews-add',
            'import_prefill' => $prefill_key,
            'import_source' => sanitize_key($source),
            'imported' => 1,
        ),
        admin_url('admin.php')
    );
}

function book_reviews_redirect_to_prefill($data, $source = 'api') {
    $prefill_key = book_reviews_store_import_prefill($data, $source);
    $redirect_url = book_reviews_get_prefill_redirect_url($prefill_key, $source);
    wp_safe_redirect($redirect_url);
    exit;
}

function book_reviews_sideload_media_image($image_url, $title = '') {
    $image_url = esc_url_raw($image_url);
    if (empty($image_url)) {
        return '';
    }

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $downloaded = media_sideload_image($image_url, 0, $title, 'src');
    if (is_wp_error($downloaded)) {
        return '';
    }

    return esc_url_raw($downloaded);
}

function book_reviews_api_request_json($url, $headers = array()) {
    $response = wp_remote_get(
        $url,
        array(
            'timeout' => 15,
            'headers' => $headers,
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status < 200 || $status >= 300) {
        return new WP_Error('book_reviews_api_http_error', 'API request failed with status ' . $status . '.');
    }

    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return new WP_Error('book_reviews_api_parse_error', 'API response could not be parsed.');
    }

    return $decoded;
}

function book_reviews_pick_first_text_value($values) {
    if (!is_array($values)) {
        return '';
    }

    foreach ($values as $value) {
        if (!is_string($value)) {
            continue;
        }

        $text = sanitize_text_field($value);
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function book_reviews_extract_open_library_category($doc) {
    $category = book_reviews_pick_first_text_value($doc['subject'] ?? array());
    if ($category !== '') {
        return $category;
    }

    $category = book_reviews_pick_first_text_value($doc['subject_facet'] ?? array());
    if ($category !== '') {
        return $category;
    }

    $work_key = isset($doc['key']) ? sanitize_text_field($doc['key']) : '';
    if ($work_key === '' || strpos($work_key, '/works/') !== 0) {
        return '';
    }

    $work_data = book_reviews_api_request_json('https://openlibrary.org' . $work_key . '.json');
    if (is_wp_error($work_data)) {
        return '';
    }

    return book_reviews_pick_first_text_value($work_data['subjects'] ?? array());
}

function book_reviews_search_open_library($query) {
    $url = add_query_arg(
        array(
            'q' => $query,
            'limit' => 10,
        ),
        'https://openlibrary.org/search.json'
    );

    $data = book_reviews_api_request_json($url);
    if (is_wp_error($data)) {
        return $data;
    }

    $results = array();
    foreach (($data['docs'] ?? array()) as $doc) {
        $title = isset($doc['title']) ? sanitize_text_field($doc['title']) : '';
        if (empty($title)) {
            continue;
        }

        $creator = '';
        if (!empty($doc['author_name']) && is_array($doc['author_name'])) {
            $creator = sanitize_text_field($doc['author_name'][0]);
        }

        $category = book_reviews_extract_open_library_category($doc);

        $cover_id = !empty($doc['cover_i']) ? absint($doc['cover_i']) : 0;
        $image_url = $cover_id ? 'https://covers.openlibrary.org/b/id/' . $cover_id . '-L.jpg' : '';
        $thumbnail_url = $cover_id ? 'https://covers.openlibrary.org/b/id/' . $cover_id . '-M.jpg' : '';

        $results[] = array(
            'provider' => 'Open Library',
            'media_type' => 'book',
            'title' => $title,
            'creator' => $creator,
            'year' => !empty($doc['first_publish_year']) ? (string) intval($doc['first_publish_year']) : '',
            'category' => $category,
            'cover_image_url' => $image_url,
            'thumbnail_url' => $thumbnail_url ?: $image_url,
        );
    }

    return $results;
}

function book_reviews_fetch_tmdb_movie_details($movie_id, $api_key) {
    $url = add_query_arg(
        array(
            'api_key' => $api_key,
            'append_to_response' => 'credits',
        ),
        'https://api.themoviedb.org/3/movie/' . absint($movie_id)
    );

    return book_reviews_api_request_json($url);
}

function book_reviews_search_tmdb_movies($query) {
    $api_key = book_reviews_get_tmdb_api_key();
    if (empty($api_key)) {
        return new WP_Error('book_reviews_missing_tmdb_key', 'Add a TMDb API key before searching for movies.');
    }

    $url = add_query_arg(
        array(
            'api_key' => $api_key,
            'query' => $query,
            'include_adult' => 'false',
        ),
        'https://api.themoviedb.org/3/search/movie'
    );

    $data = book_reviews_api_request_json($url);
    if (is_wp_error($data)) {
        return $data;
    }

    $results = array();
    foreach (array_slice($data['results'] ?? array(), 0, 8) as $movie) {
        $details = book_reviews_fetch_tmdb_movie_details($movie['id'] ?? 0, $api_key);
        $director = '';
        $category = '';

        if (!is_wp_error($details)) {
            foreach (($details['credits']['crew'] ?? array()) as $crew_member) {
                if (($crew_member['job'] ?? '') === 'Director' && !empty($crew_member['name'])) {
                    $director = sanitize_text_field($crew_member['name']);
                    break;
                }
            }

            if (!empty($details['genres']) && is_array($details['genres'])) {
                $category = sanitize_text_field($details['genres'][0]['name'] ?? '');
            }
        }

        $poster_path = $movie['poster_path'] ?? '';
        $image_url = $poster_path ? 'https://image.tmdb.org/t/p/original' . $poster_path : '';
        $thumbnail_url = $poster_path ? 'https://image.tmdb.org/t/p/w342' . $poster_path : '';
        $release_date = !empty($movie['release_date']) ? sanitize_text_field($movie['release_date']) : '';

        $results[] = array(
            'provider' => 'TMDb',
            'media_type' => 'movie',
            'title' => sanitize_text_field($movie['title'] ?? ''),
            'creator' => $director,
            'year' => $release_date ? substr($release_date, 0, 4) : '',
            'category' => $category,
            'cover_image_url' => $image_url,
            'thumbnail_url' => $thumbnail_url ?: $image_url,
        );
    }

    return $results;
}

function book_reviews_search_musicbrainz_releases($query) {
    $url = add_query_arg(
        array(
            'query' => $query,
            'fmt' => 'json',
            'limit' => 10,
        ),
        'https://musicbrainz.org/ws/2/release/'
    );

    $headers = array(
        'Accept' => 'application/json',
        'User-Agent' => 'MediaReviewsWordPressPlugin/3.1.0',
    );

    $data = book_reviews_api_request_json($url, $headers);
    if (is_wp_error($data)) {
        return $data;
    }

    $results = array();
    foreach (($data['releases'] ?? array()) as $release) {
        $title = sanitize_text_field($release['title'] ?? '');
        if (empty($title)) {
            continue;
        }

        $artist_names = array();
        foreach (($release['artist-credit'] ?? array()) as $artist_credit) {
            if (!empty($artist_credit['name'])) {
                $artist_names[] = sanitize_text_field($artist_credit['name']);
            }
        }

        $release_group = is_array($release['release-group'] ?? null) ? $release['release-group'] : array();
        $category = '';
        if (!empty($release_group['genres']) && is_array($release_group['genres'])) {
            $category = sanitize_text_field($release_group['genres'][0]['name'] ?? '');
        } elseif (!empty($release_group['tags']) && is_array($release_group['tags'])) {
            $category = sanitize_text_field($release_group['tags'][0]['name'] ?? '');
        }

        if (empty($category) && !empty($release_group['id'])) {
            $release_group_details = book_reviews_fetch_musicbrainz_release_group_details($release_group['id']);
            if (is_array($release_group_details)) {
                if (!empty($release_group_details['genres']) && is_array($release_group_details['genres'])) {
                    $category = sanitize_text_field($release_group_details['genres'][0]['name'] ?? '');
                } elseif (!empty($release_group_details['tags']) && is_array($release_group_details['tags'])) {
                    $category = sanitize_text_field($release_group_details['tags'][0]['name'] ?? '');
                }
            }
        }

        $cover_art = array();
        if (!empty($release['id'])) {
            $cover_art = book_reviews_fetch_cover_art_archive_release_art($release['id']);
        }

        if (empty($cover_art['cover_image_url']) && !empty($release_group['id'])) {
            $cover_art = book_reviews_fetch_cover_art_archive_release_group_art($release_group['id']);
        }

        $cover_image_url = $cover_art['cover_image_url'] ?? '';
        $thumbnail_url = $cover_art['thumbnail_url'] ?? '';

        $results[] = array(
            'provider' => 'MusicBrainz / Cover Art Archive',
            'media_type' => 'music',
            'title' => $title,
            'creator' => implode(', ', $artist_names),
            'year' => !empty($release['date']) ? substr(sanitize_text_field($release['date']), 0, 4) : '',
            'category' => $category,
            'cover_image_url' => $cover_image_url,
            'thumbnail_url' => $thumbnail_url ?: $cover_image_url,
        );
    }

    return $results;
}

function book_reviews_fetch_musicbrainz_release_group_details($release_group_id) {
    $release_group_id = sanitize_text_field($release_group_id);
    if (empty($release_group_id)) {
        return null;
    }

    $url = add_query_arg(
        array(
            'fmt' => 'json',
            'inc' => 'genres+tags',
        ),
        'https://musicbrainz.org/ws/2/release-group/' . rawurlencode($release_group_id)
    );

    $headers = array(
        'Accept' => 'application/json',
        'User-Agent' => 'MediaReviewsWordPressPlugin/3.3.0',
    );

    $data = book_reviews_api_request_json($url, $headers);
    return is_wp_error($data) ? null : $data;
}

function book_reviews_fetch_cover_art_archive_image_data($url) {
    $data = book_reviews_api_request_json($url);
    if (is_wp_error($data)) {
        return array(
            'cover_image_url' => '',
            'thumbnail_url' => '',
        );
    }

    $images = $data['images'] ?? array();
    if (!is_array($images) || empty($images)) {
        return array(
            'cover_image_url' => '',
            'thumbnail_url' => '',
        );
    }

    $front_image = null;
    foreach ($images as $image) {
        if (!empty($image['front'])) {
            $front_image = $image;
            break;
        }
    }

    if (!$front_image) {
        $front_image = $images[0];
    }

    $thumbnails = is_array($front_image['thumbnails'] ?? null) ? $front_image['thumbnails'] : array();

    return array(
        'cover_image_url' => esc_url_raw($front_image['image'] ?? ''),
        'thumbnail_url' => esc_url_raw($thumbnails['250'] ?? ($thumbnails['500'] ?? ($front_image['image'] ?? ''))),
    );
}

function book_reviews_fetch_cover_art_archive_release_art($release_id) {
    $release_id = sanitize_text_field($release_id);
    if (empty($release_id)) {
        return array(
            'cover_image_url' => '',
            'thumbnail_url' => '',
        );
    }

    return book_reviews_fetch_cover_art_archive_image_data(
        'https://coverartarchive.org/release/' . rawurlencode($release_id)
    );
}

function book_reviews_fetch_cover_art_archive_release_group_art($release_group_id) {
    $release_group_id = sanitize_text_field($release_group_id);
    if (empty($release_group_id)) {
        return array(
            'cover_image_url' => '',
            'thumbnail_url' => '',
        );
    }

    return book_reviews_fetch_cover_art_archive_image_data(
        'https://coverartarchive.org/release-group/' . rawurlencode($release_group_id)
    );
}

function book_reviews_fetch_rawg_game_details($slug, $api_key) {
    $url = add_query_arg(
        array(
            'key' => $api_key,
        ),
        'https://api.rawg.io/api/games/' . rawurlencode($slug)
    );

    return book_reviews_api_request_json($url);
}

function book_reviews_search_rawg_games($query) {
    $api_key = book_reviews_get_rawg_api_key();
    if (empty($api_key)) {
        return new WP_Error('book_reviews_missing_rawg_key', 'Add a RAWG API key before searching for games.');
    }

    $url = add_query_arg(
        array(
            'key' => $api_key,
            'search' => $query,
            'page_size' => 8,
        ),
        'https://api.rawg.io/api/games'
    );

    $data = book_reviews_api_request_json($url);
    if (is_wp_error($data)) {
        return $data;
    }

    $results = array();
    foreach (($data['results'] ?? array()) as $game) {
        $details = !empty($game['slug']) ? book_reviews_fetch_rawg_game_details($game['slug'], $api_key) : null;
        $developer = '';
        $category = '';

        if (is_array($details)) {
            if (!empty($details['developers']) && is_array($details['developers'])) {
                $developer = sanitize_text_field($details['developers'][0]['name'] ?? '');
            }

            if (!empty($details['genres']) && is_array($details['genres'])) {
                $category = sanitize_text_field($details['genres'][0]['name'] ?? '');
            }
        }

        $results[] = array(
            'provider' => 'RAWG',
            'media_type' => 'game',
            'title' => sanitize_text_field($game['name'] ?? ''),
            'creator' => $developer,
            'year' => !empty($game['released']) ? substr(sanitize_text_field($game['released']), 0, 4) : '',
            'category' => $category,
            'cover_image_url' => esc_url_raw($game['background_image'] ?? ''),
            'thumbnail_url' => esc_url_raw($game['background_image'] ?? ''),
        );
    }

    return $results;
}

function book_reviews_search_api_provider($media_type, $query) {
    switch ($media_type) {
        case 'book':
            return book_reviews_search_open_library($query);
        case 'movie':
            return book_reviews_search_tmdb_movies($query);
        case 'music':
            return book_reviews_search_musicbrainz_releases($query);
        case 'game':
            return book_reviews_search_rawg_games($query);
        default:
            return new WP_Error('book_reviews_invalid_media_type', 'Choose a valid media type before searching.');
    }
}

function book_reviews_build_import_payload($result) {
    return base64_encode(wp_json_encode(book_reviews_normalize_prefill_data($result)));
}

function book_reviews_decode_import_payload($payload) {
    $decoded = base64_decode(wp_unslash($payload), true);
    if ($decoded === false) {
        return null;
    }

    $data = json_decode($decoded, true);
    if (!is_array($data)) {
        return null;
    }

    return book_reviews_normalize_prefill_data($data);
}

function book_reviews_build_amazon_bookmarklet_code($target_url, $secret) {
    $script = <<<'JS'
(async function() {
    const TARGET_URL = __TARGET_URL__;
    const SECRET = __SECRET__;
    const AMAZON_HOST_PATTERN = /(^|\.)amazon\./i;

    function alertAndStop(message) {
        window.alert(message);
        throw new Error(message);
    }

    function readText(selectorList) {
        for (const selector of selectorList) {
            const node = document.querySelector(selector);
            if (node && node.textContent) {
                const text = node.textContent.replace(/\s+/g, ' ').trim();
                if (text) {
                    return text;
                }
            }
        }
        return '';
    }

    function readAttr(selectorList, attribute) {
        for (const selector of selectorList) {
            const node = document.querySelector(selector);
            if (node) {
                const value = node.getAttribute(attribute);
                if (value) {
                    return value.trim();
                }
            }
        }
        return '';
    }

    function parseJsonLd() {
        const scripts = Array.from(document.querySelectorAll('script[type="application/ld+json"]'));
        const entries = [];

        for (const script of scripts) {
            const raw = script.textContent && script.textContent.trim();
            if (!raw) {
                continue;
            }

            try {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    entries.push.apply(entries, parsed);
                } else if (parsed['@graph'] && Array.isArray(parsed['@graph'])) {
                    entries.push.apply(entries, parsed['@graph']);
                } else {
                    entries.push(parsed);
                }
            } catch (error) {}
        }

        return entries;
    }

    function firstJsonLdValue(entries, keys) {
        for (const entry of entries) {
            if (!entry || typeof entry !== 'object') {
                continue;
            }

            for (const key of keys) {
                const value = entry[key];
                if (typeof value === 'string' && value.trim()) {
                    return value.trim();
                }

                if (Array.isArray(value) && value.length > 0) {
                    const first = value[0];
                    if (typeof first === 'string' && first.trim()) {
                        return first.trim();
                    }
                    if (first && typeof first === 'object' && typeof first.url === 'string' && first.url.trim()) {
                        return first.url.trim();
                    }
                }

                if (value && typeof value === 'object') {
                    if (typeof value.name === 'string' && value.name.trim()) {
                        return value.name.trim();
                    }
                    if (typeof value.url === 'string' && value.url.trim()) {
                        return value.url.trim();
                    }
                }
            }
        }

        return '';
    }

    function getBreadcrumbText() {
        return Array.from(document.querySelectorAll('#wayfinding-breadcrumbs_feature_div li, #wayfinding-breadcrumbs_container li, #nav-subnav a'))
            .map((node) => node.textContent.replace(/\s+/g, ' ').trim())
            .filter(Boolean)
            .join(' | ');
    }

    function getDetailText() {
        return Array.from(document.querySelectorAll(
            '#detailBullets_feature_div, #detailBulletsWrapper_feature_div, #productDetails_feature_div, #productDetails_detailBullets_sections1, #bookDetails_container_div, #prodDetails'
        ))
            .map((node) => node.textContent.replace(/\s+/g, ' ').trim())
            .filter(Boolean)
            .join(' | ');
    }

    function inferMediaType(breadcrumbText, detailText, titleText, creatorText) {
        const haystack = [breadcrumbText, detailText, titleText, creatorText]
            .join(' | ')
            .toLowerCase();

        if (/(blu-ray|dvd|4k ultra hd|prime video|movie|film|directed by|actor)/.test(haystack)) {
            return 'movie';
        }

        if (/(vinyl|audio cd|mp3|album|artist|band|soundtrack)/.test(haystack)) {
            return 'music';
        }

        if (/(playstation|xbox|nintendo|switch|video game|gamecube|steam|developer|publisher)/.test(haystack)) {
            return 'game';
        }

        if (/(kindle|paperback|hardcover|board book|author|ebook|audiobook|library binding)/.test(haystack)) {
            return 'book';
        }

        return 'book';
    }

    async function signMessage(secret, message) {
        const encoder = new TextEncoder();
        const key = await crypto.subtle.importKey(
            'raw',
            encoder.encode(secret),
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        );
        const signature = await crypto.subtle.sign('HMAC', key, encoder.encode(message));
        return Array.from(new Uint8Array(signature))
            .map((byte) => byte.toString(16).padStart(2, '0'))
            .join('');
    }

    if (!AMAZON_HOST_PATTERN.test(window.location.hostname)) {
        alertAndStop('This bookmarklet only works on Amazon product pages.');
    }

    const jsonLdEntries = parseJsonLd();
    const title = firstJsonLdValue(jsonLdEntries, ['name']) || readText([
        '#productTitle',
        '#ebooksProductTitle',
        '#title',
        '[data-feature-name="title"] h1',
        'h1.a-size-large'
    ]);

    if (!title) {
        alertAndStop('Could not find a media title on this page. Make sure you are on an Amazon product detail page.');
    }

    const creator = firstJsonLdValue(jsonLdEntries, ['author', 'creator', 'director', 'byArtist']) || readText([
        '#bylineInfo',
        '.author .a-link-normal',
        '.author .contributorNameID',
        '#brand',
        '.contributorNameID',
        '[data-feature-name="bylineInfo"]'
    ]).replace(/^(by|visit the)\s+/i, '').trim();

    const coverImageUrl = firstJsonLdValue(jsonLdEntries, ['image']) || readAttr([
        '#landingImage',
        '#imgBlkFront',
        '#ebooksImgBlkFront',
        '#imgTagWrapperId img',
        '#main-image'
    ], 'src') || readAttr([
        '#landingImage',
        '#imgBlkFront',
        '#ebooksImgBlkFront',
        '#imgTagWrapperId img',
        '#main-image'
    ], 'data-old-hires');

    const canonicalUrl = readAttr(['link[rel="canonical"]'], 'href') || window.location.href;
    const breadcrumbText = getBreadcrumbText();
    const detailText = getDetailText();
    const mediaType = inferMediaType(breadcrumbText, detailText, title, creator);

    const payloadObject = {
        media_type: mediaType,
        title: title,
        creator: creator,
        cover_image_url: coverImageUrl,
        source_url: canonicalUrl
    };

    const payload = btoa(unescape(encodeURIComponent(JSON.stringify(payloadObject))));
    const ts = String(Math.floor(Date.now() / 1000));
    const sig = await signMessage(SECRET, payload + '.' + ts);

    const url = new URL(TARGET_URL);
    url.searchParams.set('payload', payload);
    url.searchParams.set('ts', ts);
    url.searchParams.set('sig', sig);

    window.open(url.toString(), '_blank', 'noopener');
})();
JS;

    $replacements = array(
        '__TARGET_URL__' => wp_json_encode(esc_url_raw($target_url)),
        '__SECRET__' => wp_json_encode($secret),
    );

    $script = str_replace(array_keys($replacements), array_values($replacements), $script);
    return 'javascript:' . preg_replace('/\s+/', ' ', trim($script));
}

function book_reviews_validate_amazon_import_signature($payload, $timestamp, $signature) {
    $secret = book_reviews_get_amazon_import_secret();
    $expected = hash_hmac('sha256', $payload . '.' . $timestamp, $secret);
    return hash_equals($expected, $signature);
}

function book_reviews_decode_amazon_import_payload($payload) {
    $decoded = base64_decode(strtr($payload, ' ', '+'), true);
    if ($decoded === false) {
        return null;
    }

    $data = json_decode($decoded, true);
    if (!is_array($data)) {
        return null;
    }

    return book_reviews_normalize_prefill_data($data);
}

function book_reviews_handle_amazon_import_request() {
    $payload = isset($_GET['payload']) ? wp_unslash($_GET['payload']) : '';
    $timestamp = isset($_GET['ts']) ? absint($_GET['ts']) : 0;
    $signature = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';

    if (empty($payload) || empty($timestamp) || empty($signature)) {
        wp_die('Missing Amazon import data.', 'Amazon Import Error', array('response' => 400));
    }

    if (abs(time() - $timestamp) > 10 * MINUTE_IN_SECONDS) {
        wp_die('This Amazon import link has expired. Please run the bookmarklet again.', 'Amazon Import Error', array('response' => 403));
    }

    if (!book_reviews_validate_amazon_import_signature($payload, $timestamp, $signature)) {
        wp_die('The Amazon import signature is invalid.', 'Amazon Import Error', array('response' => 403));
    }

    $data = book_reviews_decode_amazon_import_payload($payload);
    if (!$data || empty($data['title'])) {
        wp_die('The Amazon import payload is invalid.', 'Amazon Import Error', array('response' => 400));
    }

    $redirect_url = book_reviews_get_prefill_redirect_url(book_reviews_store_import_prefill($data, 'amazon'), 'amazon');

    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url($redirect_url));
        exit;
    }

    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to import media items.', 'Amazon Import Error', array('response' => 403));
    }

    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_book_reviews_amazon_import', 'book_reviews_handle_amazon_import_request');
add_action('admin_post_nopriv_book_reviews_amazon_import', 'book_reviews_handle_amazon_import_request');

function book_reviews_render_import_result_card($result) {
    $payload = book_reviews_build_import_payload($result);
    $creator_label = 'Creator';
    if ($result['media_type'] === 'book') {
        $creator_label = 'Author';
    } elseif ($result['media_type'] === 'movie') {
        $creator_label = 'Director';
    } elseif ($result['media_type'] === 'music') {
        $creator_label = 'Artist';
    } elseif ($result['media_type'] === 'game') {
        $creator_label = 'Developer';
    }
    ?>
    <div style="display: grid; grid-template-columns: 96px 1fr; gap: 16px; padding: 16px; border: 1px solid #dcdcde; border-radius: 8px; background: white;">
        <div>
            <?php if (!empty($result['thumbnail_url'])): ?>
                <img src="<?php echo esc_url($result['thumbnail_url']); ?>" alt="" style="width: 96px; height: 144px; object-fit: cover; border-radius: 6px; background: #f6f7f7;">
            <?php else: ?>
                <div style="width: 96px; height: 144px; border-radius: 6px; background: #f6f7f7; display: flex; align-items: center; justify-content: center; color: #8c8f94; font-size: 12px;">No image</div>
            <?php endif; ?>
        </div>
        <div>
            <p style="margin: 0 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: #646970;"><?php echo esc_html($result['provider']); ?></p>
            <h3 style="margin: 0 0 8px; font-size: 18px; line-height: 1.3;"><?php echo esc_html($result['title']); ?></h3>
            <p style="margin: 0 0 6px;"><strong><?php echo esc_html($creator_label); ?>:</strong> <?php echo esc_html($result['creator'] ?: 'Not available'); ?></p>
            <?php if (!empty($result['year'])): ?>
                <p style="margin: 0 0 6px;"><strong>Year:</strong> <?php echo esc_html($result['year']); ?></p>
            <?php endif; ?>
            <?php if (!empty($result['category'])): ?>
                <p style="margin: 0 0 12px;"><strong>Category:</strong> <?php echo esc_html($result['category']); ?></p>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 12px;">
                <?php wp_nonce_field('book_reviews_api_import_action', 'book_reviews_api_import_nonce'); ?>
                <input type="hidden" name="action" value="book_reviews_import_api_result">
                <input type="hidden" name="api_result_payload" value="<?php echo esc_attr($payload); ?>">
                <button type="submit" name="book_reviews_api_import_submit" class="button button-primary">Import into Add New Media</button>
            </form>
        </div>
    </div>
    <?php
}

function book_reviews_handle_api_result_import() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to import media items.');
    }

    check_admin_referer('book_reviews_api_import_action', 'book_reviews_api_import_nonce');

    $payload = isset($_POST['api_result_payload']) ? $_POST['api_result_payload'] : '';
    $data = book_reviews_decode_import_payload($payload);
    if (!$data || empty($data['title'])) {
        set_transient(
            'book_reviews_api_lookup_state_' . get_current_user_id(),
            array('error' => 'The selected import result could not be parsed.'),
            5 * MINUTE_IN_SECONDS
        );
        wp_safe_redirect(admin_url('admin.php?page=book-reviews-add'));
        exit;
    }

    if (!empty($data['cover_image_url'])) {
        $local_image_url = book_reviews_sideload_media_image($data['cover_image_url'], $data['title']);
        $data['cover_image_url'] = $local_image_url;
    }

    $data['source_url'] = '';
    book_reviews_redirect_to_prefill($data, 'api');
}
add_action('admin_post_book_reviews_import_api_result', 'book_reviews_handle_api_result_import');

function book_reviews_import_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to manage import settings.');
    }

    $notice = '';
    if (isset($_POST['book_reviews_api_settings_submit'])) {
        check_admin_referer('book_reviews_api_settings_save', 'book_reviews_api_settings_nonce');

        $tmdb_api_key = isset($_POST['tmdb_api_key']) ? sanitize_text_field(wp_unslash($_POST['tmdb_api_key'])) : '';
        $rawg_api_key = isset($_POST['rawg_api_key']) ? sanitize_text_field(wp_unslash($_POST['rawg_api_key'])) : '';

        update_option('book_reviews_tmdb_api_key', $tmdb_api_key);
        update_option('book_reviews_rawg_api_key', $rawg_api_key);
        $notice = 'Import settings saved.';
    }

    ?>
    <div class="wrap">
        <h1>Import Settings</h1>

        <?php if (!empty($notice)): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <div class="card" style="max-width: 960px; margin-top: 20px;">
            <h2>API Credentials</h2>
            <p>The Add New Media screen can look up metadata from Open Library, TMDb, MusicBrainz, Cover Art Archive, and RAWG. API requests happen only during admin-side lookup, and imported images are downloaded into your local WordPress Media Library.</p>
            <form method="post">
                <?php wp_nonce_field('book_reviews_api_settings_save', 'book_reviews_api_settings_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tmdb-api-key">TMDb API key</label></th>
                        <td>
                            <input type="text" class="regular-text code" id="tmdb-api-key" name="tmdb_api_key" value="<?php echo esc_attr(book_reviews_get_tmdb_api_key()); ?>">
                            <p class="description">Required only for movie lookups.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rawg-api-key">RAWG API key</label></th>
                        <td>
                            <input type="text" class="regular-text code" id="rawg-api-key" name="rawg_api_key" value="<?php echo esc_attr(book_reviews_get_rawg_api_key()); ?>">
                            <p class="description">Required only for game lookups.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="book_reviews_api_settings_submit" class="button button-primary">Save API Settings</button>
                </p>
            </form>
        </div>
    </div>
    <?php
}

function book_reviews_amazon_import_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to manage Amazon import settings.');
    }

    $notice = '';
    if (isset($_POST['book_reviews_amazon_settings_submit'])) {
        check_admin_referer('book_reviews_amazon_settings_save', 'book_reviews_amazon_settings_nonce');

        $target_url = isset($_POST['amazon_import_target_url']) ? esc_url_raw(wp_unslash($_POST['amazon_import_target_url'])) : '';
        if (empty($target_url)) {
            $target_url = admin_url('admin-post.php?action=book_reviews_amazon_import');
        }

        update_option('book_reviews_amazon_import_target_url', $target_url);

        if (!empty($_POST['regenerate_amazon_secret'])) {
            $secret = wp_generate_password(64, false, false);
            update_option('book_reviews_amazon_import_secret', $secret);
            $notice = 'Amazon bookmarklet settings updated and secret regenerated.';
        } else {
            $secret = book_reviews_get_amazon_import_secret();
            $notice = 'Amazon bookmarklet settings updated.';
        }
    } else {
        $secret = book_reviews_get_amazon_import_secret();
    }

    $target_url = book_reviews_get_amazon_import_target_url();
    $bookmarklet = book_reviews_build_amazon_bookmarklet_code($target_url, $secret);
    ?>
    <div class="wrap">
        <h1>Amazon Bookmarklet Import</h1>

        <?php if (!empty($notice)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($notice); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 960px; margin-top: 20px;">
            <h2>Settings</h2>
            <p>Configure the signed handoff used by the Amazon bookmarklet. The bookmarklet opens the Add New Media screen with imported fields prefilled for review.</p>

            <form method="post">
                <?php wp_nonce_field('book_reviews_amazon_settings_save', 'book_reviews_amazon_settings_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="amazon-import-target-url">WordPress handoff URL</label>
                        </th>
                        <td>
                            <input type="url" class="regular-text code" id="amazon-import-target-url" name="amazon_import_target_url" value="<?php echo esc_attr($target_url); ?>">
                            <p class="description">Default: <?php echo esc_html(admin_url('admin-post.php?action=book_reviews_amazon_import')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="amazon-import-secret">Shared secret</label>
                        </th>
                        <td>
                            <input type="text" class="regular-text code" id="amazon-import-secret" value="<?php echo esc_attr($secret); ?>" readonly>
                            <p class="description">This secret is embedded into the generated bookmarklet and used to sign import requests.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="book_reviews_amazon_settings_submit" class="button button-primary">Save Settings</button>
                    <button type="submit" name="regenerate_amazon_secret" value="1" class="button">Regenerate Secret</button>
                </p>
            </form>
        </div>

        <div class="card" style="max-width: 960px; margin-top: 20px;">
            <h2>Bookmarklet</h2>
            <p>Drag the button to your bookmarks bar or copy the bookmarklet code manually. When you click it on an Amazon product page, it will extract basic metadata and open WordPress with a prefilled media item.</p>

            <p>
                <a href="<?php echo esc_attr($bookmarklet); ?>" class="button button-primary">Amazon to Media Reviews</a>
                <button type="button" class="button" data-copy-target="#book-reviews-bookmarklet-code">Copy Bookmarklet Code</button>
            </p>

            <textarea id="book-reviews-bookmarklet-code" class="large-text code" rows="12" readonly><?php echo esc_textarea($bookmarklet); ?></textarea>

            <p class="description">If the button cannot be dragged in your browser, create a bookmark manually and paste the code above into the URL field.</p>
        </div>
    </div>
    <?php
}
