jQuery(document).ready(function($) {
    
    // Media uploader for cover image
    let mediaUploader;

    $('#upload-cover-button').on('click', function(e) {
        e.preventDefault();

        // Get current media type for dynamic title
        const mediaType = $('#media-type').val() || 'book';
        const mediaTypeLabels = {
            'book': 'Book Cover',
            'movie': 'Movie Poster',
            'music': 'Album Cover',
            'game': 'Game Cover'
        };
        const mediaLabel = mediaTypeLabels[mediaType] || 'Cover';

        // If the uploader object has already been created, reopen it
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Create the media uploader
        mediaUploader = wp.media({
            title: 'Choose ' + mediaLabel + ' Image',
            button: {
                text: 'Use this image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        // When an image is selected, run a callback
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Set the image URL in the hidden field
            $('#cover_image_url').val(attachment.url);
            
            // Update preview
            const preview = '<img src="' + attachment.url + '" style="max-width: 200px; height: auto; display: block; margin-bottom: 10px;">';
            $('#cover-image-preview').html(preview);
            
            // Update button text and show remove button
            $('#upload-cover-button').text('Change Image');
            
            if ($('#remove-cover-button').length === 0) {
                $('#upload-cover-button').after(' <button type="button" class="button" id="remove-cover-button">Remove Image</button>');
                
                // Attach event to the new remove button
                $('#remove-cover-button').on('click', removeCoverImage);
            }
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    // Remove cover image
    function removeCoverImage(e) {
        e.preventDefault();
        $('#cover_image_url').val('');
        $('#cover-image-preview').html('');
        $('#upload-cover-button').text('Upload Image');
        $(this).remove();
    }

    // Attach event to existing remove button if present
    $('#remove-cover-button').on('click', removeCoverImage);

    // Rating star hover effect
    $('.rating-selector').on('mouseenter', '.rating-star', function() {
        const $this = $(this);
        const index = $this.index();
        
        $('.rating-star').each(function(i) {
            if (i <= index) {
                $(this).find('.dashicons').css('color', '#ffc107');
            } else {
                $(this).find('.dashicons').css('color', '#ccc');
            }
        });
    });

    $('.rating-selector').on('mouseleave', function() {
        // Restore selected rating appearance
        const $checked = $('.rating-star input:checked');
        if ($checked.length > 0) {
            const checkedIndex = $checked.parent().index();
            $('.rating-star').each(function(i) {
                if (i <= checkedIndex) {
                    $(this).find('.dashicons').css('color', '#ffc107');
                } else {
                    $(this).find('.dashicons').css('color', '#ccc');
                }
            });
        } else {
            $('.rating-star .dashicons').css('color', '#ccc');
        }
    });

    // Handle rating selection
    $('.rating-star input').on('change', function() {
        const $this = $(this);
        const index = $this.parent().index();
        
        $('.rating-star').each(function(i) {
            if (i <= index) {
                $(this).find('.dashicons').css('color', '#ffc107');
            } else {
                $(this).find('.dashicons').css('color', '#ccc');
            }
        });
    });

    // Initialize rating display on page load
    const $checkedRating = $('.rating-star input:checked');
    if ($checkedRating.length > 0) {
        const checkedIndex = $checkedRating.parent().index();
        $('.rating-star').each(function(i) {
            if (i <= checkedIndex) {
                $(this).find('.dashicons').css('color', '#ffc107');
            }
        });
    }
});
