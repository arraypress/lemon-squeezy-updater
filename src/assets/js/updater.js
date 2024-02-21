jQuery(function ($) {

    // Activate license
    $(document.body).on('keypress', '.lemon-squeezy-updater-license-row input', function (e) {
        if (e.which !== 13) {
            return;
        }
        e.preventDefault();

        var $this = $(this);
        var licenseKey = $this.val().trim();

        // Check if the license key input is empty
        if (licenseKey === '') {
            // Apply the input-error class to indicate an error
            $this.addClass('input-error');

            // Remove the class after animation completes (e.g., 0.5 seconds)
            setTimeout(function () {
                $this.removeClass('input-error');
            }, 500); // Adjust timing based on your animation duration

            return; // Exit the function to prevent further execution
        }

        // Find the previous 'tr' that contains the 'data-plugin' attribute directly above the current row
        var plugin = $this.closest('tr').prev().data('plugin');

        var data = {
            action: 'activate_lsq_license', plugin: plugin, license: licenseKey, nonce: lsq_updater_script_vars.nonce
        };

        // Show loading
        $this.parents('tr').first().find('.spinner').addClass('is-active');

        $.post(ajaxurl, data, function (response) {
            $this.parents('tr.lemon-squeezy-updater-license-row').replaceWith(response.html);
        });

    });

    // Deactivate license
    $(document.body).on('click', '.lemon-squeezy-updater-license-row .deactivate', function (e) {

        var $this = $(this);

        // Find the previous 'tr' that contains the 'data-plugin' attribute directly above the current row
        var plugin = $this.closest('tr').prev().data('plugin');

        var data = {
            action: 'deactivate_lsq_license', plugin: plugin, nonce: lsq_updater_script_vars.nonce
        };

        // Show loading
        $this.parents('tr').first().find('.spinner').addClass('is-active');

        $.post(ajaxurl, data, function (response) {
            $this.parents('tr.lemon-squeezy-updater-license-row').replaceWith(response.html);
        });

    });

});
