(function ($, Drupal, once) {
  Drupal.behaviors.customAutocomplete = {
    attach: function (context, settings) {
      once('customAutocomplete', 'input.multi-autocomplete', context).forEach(function (input) {
        var $input = $(input);
        var endpointUrl = $input.data('autocomplete-url');
        var hiddenInputName = $input.data('hidden-input-name');

        // Find the hidden input by its name attribute.
        var $hiddenInput = $('input[name="' + hiddenInputName + '"]');

        var $wrapper = $('<div class="custom-autocomplete-wrapper"></div>');
        var $tagsContainer = $('<div class="tags-container"></div>');

        // Change the original input name to avoid duplication.
        $input.attr('name', $input.attr('name') + '-autocomplete');
        $input.before($tagsContainer);
        $input.wrap($wrapper);

        var itemSelected = false;

        function updateHiddenInput() {
          var values = [];
          $tagsContainer.find('.tag').each(function () {
            values.push($(this).text().replace('×', '').trim());
          });
          $hiddenInput.val(values.join(',')).trigger('change');  // Ensure change event is triggered.
        }

        function getCurrentSelections() {
          var selectedValues = [];
          $tagsContainer.find('.tag').each(function () {
            selectedValues.push($(this).text().replace('×', '').trim());
          });
          return selectedValues;
        }

        function sortTags() {
          var tags = $tagsContainer.find('.tag').toArray().sort(function (a, b) {
            return $(a).text().localeCompare($(b).text());
          });
          $tagsContainer.append(tags);
        }

        function addTag(value) {
          var $tag = $('<span class="tag"></span>').text(value);
          var $removeButton = $('<span class="remove-tag">&times;</span>').click(function () {
            $tag.remove();
            updateHiddenInput();
          });

          $tag.append($removeButton);
          $tagsContainer.append($tag);

          sortTags();
          updateHiddenInput();
        }

        // Reinitialize tags from hidden input value on load.
        function reinitializeTags($container, $hiddenInput) {
          if ($hiddenInput.length) { // Check if hidden input exists.
            var values = $hiddenInput.val() ? $hiddenInput.val().split(',') : [];
            values.forEach(function(value) {
              if (value && !$container.find('.tag:contains(' + value + ')').length) {
                var $tag = $('<span class="tag"></span>').text(value.trim());
                var $removeButton = $('<span class="remove-tag">&times;</span>').click(function () {
                  $tag.remove();
                  updateHiddenInput();
                });
                $tag.append($removeButton);
                $container.append($tag);
              }
            });
          }
        }

        $input.autocomplete({
          source: function (request, response) {
            $.ajax({
              url: Drupal.url(endpointUrl),
              dataType: 'json',
              data: {
                q: request.term
              },
              success: function (data) {
                var currentSelections = getCurrentSelections();
                var filteredData = data.filter(function (item) {
                  return currentSelections.indexOf(item.value) === -1;
                });
                response(filteredData);
              }
            });
          },
          select: function (event, ui) {
            var value = ui.item.value;
            addTag(value);
            $input.val('');
            itemSelected = true;
            event.preventDefault();
          },
          focus: function (event, ui) {
            event.preventDefault();
          },
          close: function (event) {
            if (!itemSelected) {
              $input.val('');
            }
          }
        });

        $input.on('keypress', function (e) {
          if (e.which === 13) {
            if (itemSelected) {
              var value = $input.val().trim();
              if (value) {
                addTag(value);
                $input.val('');
                itemSelected = false;
              }
              return false;
            } else {
              return false;
            }
          }
        });

        $(document).on('mousedown', '.ui-menu-item', function (event) {
          itemSelected = true;
        });

        $input.on('autocompleteclose', function(event) {
          var value = $input.val().trim();
          if (value && itemSelected) {
            addTag(value);
            $input.val('');
            itemSelected = false;
          } else {
            $input.val('');
          }
        });

        $input.on('input', function() {
          itemSelected = false;
        });

        $input.on('autocompleteopen', function() {
          itemSelected = false;
        });

        // Reinitialize tags when the page is loaded.
        reinitializeTags($tagsContainer, $hiddenInput);

        // Listen for AJAX events to reinitialize tags after new fields are added.
        $(document).ajaxComplete(function(event, xhr, settings) {
          $('input.multi-autocomplete', context).each(function () {
            var $thisInput = $(this);
            var $thisHiddenInput = $('input[name="' + $thisInput.data('hidden-input-name') + '"]');
            var $thisTagsContainer = $thisInput.siblings('.tags-container');
            reinitializeTags($thisTagsContainer, $thisHiddenInput);
          });
        });
      });
    }
  };
})(jQuery, Drupal, once);
