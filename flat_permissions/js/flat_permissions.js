(function ($, Drupal, once) {
    Drupal.behaviors.flatPermissions = {
        attach: function (context, settings) {
            // Target all select elements with the class 'custom-select'.
            once('flatPermissions', '.custom-select', context).forEach(function (select) {
                var $select = $(select);
                if (select) {
                    // Iterate over each option in the select element.
                    $(select).find('option').each(function () {
                        var optionValue = $(this).attr('value');
                        if (optionValue) {
                            // Add class to option with the same value as the option.
                            $(this).addClass(optionValue);
                        }
                    });
                }
            });
        }
    };
})(jQuery, Drupal, once);