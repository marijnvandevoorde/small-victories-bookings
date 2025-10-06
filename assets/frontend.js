jQuery(document).ready(function($) {
    $('.timeslot-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const formId = form.closest('.timeslot-form-container').data('form-id');
        const submitButton = form.find('.timeslot-submit-button');
        const messageContainer = form.find('.timeslot-form-message');

        // Get form data - collect all custom fields
        const fieldResponses = {};
        let hasEmptyRequired = false;

        form.find('.timeslot-field input').each(function() {
            const input = $(this);
            const label = input.data('label');
            const value = input.val().trim();

            if (input.prop('required') && !value) {
                hasEmptyRequired = true;
            }

            fieldResponses[label] = value;
        });

        const selectedOptions = [];
        form.find('input[name="options[]"]:checked').each(function() {
            selectedOptions.push($(this).val());
        });

        // Clear previous messages
        messageContainer.removeClass('success error').hide().text('');

        // Validate
        if (hasEmptyRequired) {
            showMessage('error', 'Please fill in all required fields.');
            return;
        }

        if (selectedOptions.length === 0) {
            showMessage('error', 'Please select at least one option.');
            return;
        }

        // Disable submit button
        submitButton.prop('disabled', true).text('Submitting...');

        // Submit via AJAX
        $.ajax({
            url: timeslotAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'timeslot_submit',
                nonce: timeslotAjax.nonce,
                form_id: formId,
                field_responses: fieldResponses,
                options: selectedOptions
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    form[0].reset();

                    // Reload the page after 2 seconds to update availability
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage('error', response.data.message || 'An error occurred. Please try again.');
                    submitButton.prop('disabled', false).text('Submit');
                }
            },
            error: function() {
                showMessage('error', 'A network error occurred. Please try again.');
                submitButton.prop('disabled', false).text('Submit');
            }
        });

        function showMessage(type, text) {
            messageContainer.removeClass('success error').addClass(type).text(text).show();
        }
    });
});
