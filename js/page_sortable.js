(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.sfmc_personalization = {
    attach: function (context, settings) {
      if (drupalSettings.sfmc_personalization && drupalSettings.sfmc_personalization.sortable) {
        const $wrapper = $(once('sfmc-personalization-sortable', drupalSettings.sfmc_personalization.sortable.selector, context));
        $wrapper.each(function () {
          new Sortable(this, {
            animation: 150,
            // handle: '> *',
            sort: true,
            forceFallback: false,
            dragoverBubble: true,
          });
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
