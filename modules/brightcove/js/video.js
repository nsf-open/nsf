/**
 * @file
 * JS for video form.
 */

(function ($) {
  Drupal.behaviors.brightcoveChosen = {
    attach: function () {
      var submitButtons = $('input[type="submit"]');
      var isFileField = false;

      $('input[type="file"]').mousedown(function() {
        isFileField = true;
      });

      // Disable buttons while the uploading is running.
      $(document).ajaxStart(function(event) {
        if (isFileField) {
          submitButtons.prop('disabled', true);
        }
      });

      // Enable buttons after the upload is finished.
      $(document).ajaxStop(function() {
        if (isFileField) {
          submitButtons.prop('disabled', false);
          isFileField = false;
        }
      });
    }
  }
})(jQuery);
