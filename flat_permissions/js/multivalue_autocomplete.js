(function ($, Drupal, once) {
  Drupal.behaviors.customAutocomplete = {
    attach: function (context, settings) {
      once('customAutocomplete', 'input.multi-autocomplete', context).forEach(function (input) {
        var $input = $(input);
        console.log('customAutocomplete: Attaching to input:', $input);
        var endpointUrl = $input.data('autocomplete-url');

        // Store reference to the hidden input field before modifying the DOM
        var $hiddenInput = $input.closest('.form-item').find('.hidden-multi-autocomplete');
        console.log('customAutocomplete: Hidden input element:', $hiddenInput);

        var $wrapper = $('<div class="custom-autocomplete-wrapper"></div>');
        var $tagsContainer = $('<div class="tags-container"></div>');

        // Change the original input name to avoid duplication
        $input.attr('name', $input.attr('name') + '-autocomplete');
        $input.before($tagsContainer);
        $input.wrap($wrapper);

        var itemSelected = false;

        function updateHiddenInput() {
          var values = [];
          $tagsContainer.find('.tag').each(function () {
            console.log('customAutocomplete: Tag:', $(this).text());
            values.push($(this).text().replace('×', '').trim());
          });
          $hiddenInput.val(values.join(',')).trigger('change');  // Ensure change event is triggered
        }

        function getCurrentSelections() {
          console.log('customAutocomplete: Getting current selections');
          var selectedValues = [];
          $tagsContainer.find('.tag').each(function () {
            console.log('customAutocomplete: Tag:', $(this).text());
            selectedValues.push($(this).text().replace('×', '').trim());
          });
          return selectedValues;
        }

        function sortTags() {
          console.log('customAutocomplete: Sorting tags');
          var tags = $tagsContainer.find('.tag').toArray().sort(function (a, b) {
            return $(a).text().localeCompare($(b).text());
          });
          $tagsContainer.append(tags);
        }

        function addTag(value) {
          console.log('customAutocomplete: Adding tag:', value);
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

        // Reinitialize tags from hidden input value on load
        function reinitializeTags($container, $hiddenInput) {
          console.log('customAutocomplete: Reinitializing tags from hidden input');
          if ($hiddenInput.length) { // Check if hidden input exists
            var values = $hiddenInput.val() ? $hiddenInput.val().split(',') : [];
            values.forEach(function (value) {
              if (value && !$container.find('.tag:contains(' + value + ')').length) {
                console.log('customAutocomplete: Adding tag:', value);
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
            console.log('customAutocomplete: AJAX request sent to:', Drupal.url(endpointUrl));
            $.ajax({
              url: Drupal.url(endpointUrl),
              dataType: 'json',
              data: {
                q: request.term
              },
              success: function (data) {
                console.log('customAutocomplete: AJAX response:', data);
                var currentSelections = getCurrentSelections();
                console.log('customAutocomplete: Current selections:', currentSelections);
                var filteredData = data.filter(function (item) {
                  return currentSelections.indexOf(item.value) === -1;
                });
                console.log('customAutocomplete: Filtered data:', filteredData);
                response(filteredData);
              }
            });
          },
          select: function (event, ui) {
            console.log('customAutocomplete: Item selected:', ui.item.value);
            var value = ui.item.value;
            addTag(value);
            $input.val('');
            itemSelected = true;
            event.preventDefault();
          },
          focus: function (event, ui) {
            console.log('customAutocomplete: Focusing event received');
            event.preventDefault();
          },
          close: function (event) {
            if (!itemSelected) {
              console.log('customAutocomplete: No item selected. Clearing input.');
              $input.val('');
            }
          }
        });

        $input.on('keypress', function (e) {
          console.log('customAutocomplete: Keypress event received');
          if (e.which === 13) {
            if (itemSelected) {
              console.log('customAutocomplete: Item selected. Adding tag.');
              var value = $input.val().trim();
              if (value) {
                addTag(value);
                $input.val('');
                itemSelected = false;
              }
              return false;
            } else {
              console.log('customAutocomplete: No item selected. Returning false.');
              return false;
            }
          }
        });

        $(document).on('mousedown', '.ui-menu-item', function (event) {
          console.log('customAutocomplete: Mousedown event received');
          itemSelected = true;
        });

        $input.on('autocompleteclose', function (event) {
          console.log('customAutocomplete: Autocomplete closed.');
          var value = $input.val().trim();
          console.log('customAutocomplete: Input value after autocomplete close:', value);
          if (value && itemSelected) {
            console.log('customAutocomplete: Item selected. Adding tag.');
            addTag(value);
            $input.val('');
            itemSelected = false;
          } else {
            console.log('customAutocomplete: No item selected. Clearing input.');
            $input.val('');
          }
        });

        $input.on('input', function () {
          console.log('customAutocomplete: Input event received');
          itemSelected = false;
        });

        $input.on('autocompleteopen', function () {
          console.log('customAutocomplete: Autocomplete opened.');
          itemSelected = false;
        });

        // Reinitialize tags when the page is loaded
        reinitializeTags($tagsContainer, $hiddenInput);

        // Listen for AJAX events to reinitialize tags after new fields are added
        $(document).ajaxComplete(function (event, xhr, settings) {
          console.log('customAutocomplete: AJAX complete event received');
          $('input.multi-autocomplete', context).each(function () {
            var $thisInput = $(this);
            var $thisHiddenInput = $thisInput.closest('.form-item').find('.hidden-multi-autocomplete');
            console.log('customAutocomplete: Reinitializing tags for input:', $thisInput);
            var $thisTagsContainer = $thisInput.siblings('.tags-container');
            reinitializeTags($thisTagsContainer, $thisHiddenInput);
          });
        });
      });
    }
  };
})(jQuery, Drupal, once);