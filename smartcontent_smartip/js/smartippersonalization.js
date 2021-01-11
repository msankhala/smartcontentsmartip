(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.smartippersonalization = {
    attach: function (context, settings) {
      var url = drupalSettings.path.baseUrl + 'smartip_personalised_content/reactions/' + settings.smart_content_paragraphs.personalization.nid;
      var data = {
      };
      $.ajax({
        url: url,
        type: 'POST',
        data: JSON.stringify(data),
        contentType: "application/json; charset=utf-8",
        dataType: 'json',
        success: function (results) {
          var removables = results.data;
          $('.paragraph--type--smart').each(
            function() {
              var show_default = true;
              var main_paragraph = this;
              $.each(removables, function (key, value) {
                if ($(main_paragraph).find(".paragraph--" + value).length > 0) {
                  show_default = false;
                  $(".paragraph--" + value).show();
                  $(main_paragraph).find('.field--name-field-default').hide();
                }
              });
            }
          );
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);