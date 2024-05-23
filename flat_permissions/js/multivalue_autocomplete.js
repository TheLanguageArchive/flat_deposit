(function ($, Drupal, once) {
    Drupal.behaviors.customAutocomplete = {
      attach: function (context, settings) {
        once('customAutocomplete', 'input.multi-autocomplete', context).forEach(function (input) {
          var $input = $(input);
          var $wrapper = $('<div class="custom-autocomplete-wrapper"></div>');
          var $tagsContainer = $('<div class="tags-container"></div>');
          var itemSelected = false;

          // Insert the tags container before the input field
          $input.before($tagsContainer);
          // Wrap the input field
          $input.wrap($wrapper);

          // Logging to check if the script is running
          console.log('Autocomplete behavior attached.');

          $input.autocomplete({
            source: function (request, response) {
              $.ajax({
                url: Drupal.url('permissions/autocomplete/mime_type'),
                dataType: 'json',
                data: {
                  q: request.term
                },
                success: function (data) {
                  response(data);
                }
              });
            },
            select: function (event, ui) {
              var value = ui.item.value;

              // Logging to check if the event is triggered
              console.log('Autocomplete select event triggered with value:', value);

              // Create a new tag element
              var $tag = $('<span class="tag"></span>').text(value);
              var $removeButton = $('<span class="remove-tag">&times;</span>').click(function () {
                $tag.remove();
              });

              $tag.append($removeButton);
              $tagsContainer.append($tag);

              // Clear the input field
              $input.val('');

              // Mark that an item was selected
              itemSelected = true;

              // Prevent form submission on Enter key
              event.preventDefault();
            },
            focus: function (event, ui) {
              // Prevent the value from being inserted into the input field
              event.preventDefault();
            }
          });

          // Handle Enter key to create a tag manually only if an item was selected
          $input.on('keypress', function (e) {
            if (e.which === 13) {
              if (itemSelected) {
                var value = $input.val().trim();
                if (value) {
                  // Logging to check if the Enter key event is triggered
                  console.log('Enter key pressed with value:', value);

                  // Create a new tag element
                  var $tag = $('<span class="tag"></span>').text(value);
                  var $removeButton = $('<span class="remove-tag">&times;</span>').click(function () {
                    $tag.remove();
                  });

                  $tag.append($removeButton);
                  $tagsContainer.append($tag);

                  // Clear the input field
                  $input.val('');

                  // Reset the itemSelected flag
                  itemSelected = false;
                }
                // Prevent form submission
                return false;
              } else {
                // Prevent adding incomplete values
                console.log('Enter key pressed without selecting an item.');
                return false;
              }
            }
          });

          // Logging to ensure autocomplete is being initialized
          $input.on('autocompleteopen', function() {
            console.log('Autocomplete open event triggered.');
          });

          $input.on('autocompletesearch', function() {
            console.log('Autocomplete search event triggered.');
          });

          $input.on('autocompleteresponse', function(event, ui) {
            console.log('Autocomplete response event triggered with data:', ui.content);
          });

          $input.on('autocompletefocus', function(event, ui) {
            console.log('Autocomplete focus event triggered with value:', ui.item.value);
          });

          // Trigger select event on mouse click
          $input.on('autocompleteclose', function(event) {
            var value = $input.val().trim();
            if (value && itemSelected) {
              // Create a new tag element
              var $tag = $('<span class="tag"></span>').text(value);
              var $removeButton = $('<span class="remove-tag">&times;</span>').click(function () {
                $tag.remove();
              });

              $tag.append($removeButton);
              $tagsContainer.append($tag);

              // Clear the input field
              $input.val('');

              // Reset the itemSelected flag
              itemSelected = false;
            }
          });
        });
      }
    };
  })(jQuery, Drupal, once);