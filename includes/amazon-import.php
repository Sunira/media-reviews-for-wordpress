<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Amazon bookmarklet settings and signed handoff helpers.
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

function book_reviews_get_amazon_import_settings() {
    return array(
        'target_url' => book_reviews_get_amazon_import_target_url(),
        'secret' => book_reviews_get_amazon_import_secret(),
    );
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
    $bookmarklet = 'javascript:' . preg_replace('/\s+/', ' ', trim($script));

    return $bookmarklet;
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

    $allowed_media_types = array('book', 'movie', 'music', 'game');
    $media_type = isset($data['media_type']) ? sanitize_text_field($data['media_type']) : 'book';

    return array(
        'media_type' => in_array($media_type, $allowed_media_types, true) ? $media_type : 'book',
        'title' => isset($data['title']) ? sanitize_text_field($data['title']) : '',
        'creator' => isset($data['creator']) ? sanitize_text_field($data['creator']) : '',
        'cover_image_url' => isset($data['cover_image_url']) ? esc_url_raw($data['cover_image_url']) : '',
        'source_url' => isset($data['source_url']) ? esc_url_raw($data['source_url']) : '',
    );
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

    $prefill_key = strtolower(wp_generate_password(20, false, false));
    set_transient('book_reviews_amazon_prefill_' . $prefill_key, $data, 10 * MINUTE_IN_SECONDS);

    $redirect_url = add_query_arg(
        array(
            'page' => 'book-reviews-add',
            'amazon_prefill' => $prefill_key,
            'amazon_imported' => 1,
        ),
        admin_url('admin.php')
    );

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

    $settings = book_reviews_get_amazon_import_settings();
    if (!empty($secret)) {
        $settings['secret'] = $secret;
    }

    $bookmarklet = book_reviews_build_amazon_bookmarklet_code($settings['target_url'], $settings['secret']);
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
                            <input
                                type="url"
                                class="regular-text code"
                                id="amazon-import-target-url"
                                name="amazon_import_target_url"
                                value="<?php echo esc_attr($settings['target_url']); ?>"
                            >
                            <p class="description">Default: <?php echo esc_html(admin_url('admin-post.php?action=book_reviews_amazon_import')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="amazon-import-secret">Shared secret</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                class="regular-text code"
                                id="amazon-import-secret"
                                value="<?php echo esc_attr($settings['secret']); ?>"
                                readonly
                            >
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

            <textarea
                id="book-reviews-bookmarklet-code"
                class="large-text code"
                rows="12"
                readonly
            ><?php echo esc_textarea($bookmarklet); ?></textarea>

            <p class="description">If the button cannot be dragged in your browser, create a bookmark manually and paste the code above into the URL field.</p>
        </div>

        <div class="card" style="max-width: 960px; margin-top: 20px;">
            <h2>What gets imported</h2>
            <ul style="list-style: disc; padding-left: 20px;">
                <li>Title</li>
                <li>Creator when Amazon exposes one</li>
                <li>Cover or poster image URL when available</li>
                <li>Inferred media type: book, movie, music, or game</li>
                <li>Source URL for review context on the add form</li>
            </ul>
            <p class="description">Ratings, review text, status, and category are still intentionally left for manual review in WordPress.</p>
        </div>
    </div>
    <?php
}
