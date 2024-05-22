(function ($, Drupal) {
    Drupal.behaviors.multivalueAutocomplete = {
        attach: function (context, settings) {
            $('.multi-autocomplete', context).once('multivalue-autocomplete').each(function () {
                var $input = $(this);
                $input.autocomplete({
                    source: function (request, response) {
                        $.getJSON($input.data('autocomplete-path'), {
                            q: request.term
                        }, response);
                    },
                    search: function () {
                        var term = this.value.split(/,\s*/).pop();
                        if (term.length < 2) {
                            return false;
                        }
                    },
                    focus: function () {
                        return false;
                    },
                    select: function (event, ui) {
                        var terms = this.value.split(/,\s*/);
                        terms.pop();
                        terms.push(ui.item.value);
                        terms.push('');
                        this.value = terms.join(', ');
                        return false;
                    }
                });
            });
        }
    };
})(jQuery, Drupal);