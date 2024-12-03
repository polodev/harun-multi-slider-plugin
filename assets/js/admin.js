jQuery(document).ready(function($) {
    var frame;

    // Open media uploader on button click
    $('#upload_image_button').on('click', function(e) {
        e.preventDefault();

        // If the frame already exists, open it
        if (frame) {
            frame.open();
            return;
        }

        // Create the media frame
        frame = wp.media({
            title: 'Select or Upload an Image',
            button: {
                text: 'Use this image'
            },
            multiple: false // Allow only one image
        });

        // When an image is selected, update the input and preview
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#slide_image_id').val(attachment.id); // Store image ID
            $('#slide_image_preview').attr('src', attachment.url).show(); // Show image preview
        });

        // Open the media frame
        frame.open();
    });
});
