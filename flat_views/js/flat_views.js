(function ($, Drupal) {
    Drupal.behaviors.customFacets = {
        attach: function (context, settings) {
            $('.facet-sign', context).once('custom-facets').each(function () {
                var $sign = $(this);
                var $includeCheckbox = $sign.siblings('.facet-include');
                var $excludeCheckbox = $sign.siblings('.facet-exclude');

                // Handle click events to toggle plus and minus signs.
                $includeCheckbox.on('change', function () {
                    if ($(this).is(':checked')) {
                        $sign.text(' + ');
                    } else {
                        $sign.text(' - ');
                    }
                });

                $excludeCheckbox.on('change', function () {
                    if ($(this).is(':checked')) {
                        $sign.text(' - ');
                    } else {
                        $sign.text(' + ');
                    }
                });
            });
        }
    };
})(jQuery, Drupal);
