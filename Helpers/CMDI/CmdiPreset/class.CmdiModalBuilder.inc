<?php

use Drupal\Core\Url;

class CmdiModalBuilder
{
    public static function build()
    {
      // static::registerJs();

      return static::modal();
    }

    public static function registerJs()
    {
      // adding modal support
      // $module_path = drupal_get_path('module', 'flat_deposit');

      // @FIXME
      // The Assets API has totally changed. CSS, JavaScript, and libraries are now
      // attached directly to render arrays using the #attached property.
      //
      //
      // @see https://www.drupal.org/node/2169605
      // @see https://www.drupal.org/node/2408597
      // drupal_add_js($module_path . '/js/flat-modal.js');

            // @FIXME
      // The Assets API has totally changed. CSS, JavaScript, and libraries are now
      // attached directly to render arrays using the #attached property.
      //
      //
      // @see https://www.drupal.org/node/2169605
      // @see https://www.drupal.org/node/2408597
      // drupal_add_css($module_path . '/css/flat-modal.css');


      // adding templates
      // @FIXME
      // The Assets API has totally changed. CSS, JavaScript, and libraries are now
      // attached directly to render arrays using the #attached property.
      //
      //
      // @see https://www.drupal.org/node/2169605
      // @see https://www.drupal.org/node/2408597
      // drupal_add_js([
      //
      //         'flat_modal_blank' => static::blank(),
      //         'flat_modal_loader' => static::loader(),
      //         'flat_modal_confirm_delete' => static::confirmDelete(),
      //
      //       ], 'setting');

    }

    public static function modal()
    {
      return [
        '#theme' => 'flat_modal',
        '#attached' => [
          'drupalSettings' => [
            'flat_deposit' => [
              'flat_modal' => [
                'flat_modal_blank' => static::blank(),
                'flat_modal_loader' => static::loader(),
                'flat_modal_confirm_delete' => static::confirmDelete(),
              ],
            ],
          ],
        ],
      ];
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('flat_modal');

    }

    public static function blank()
    {
      return [
        '#theme' => 'flat_modal_blank',
      ];
      // @FIXME
      // theme() has been renamed to _theme() and should NEVER be called directly.
      // Calling _theme() directly can alter the expected output and potentially
      // introduce security issues (see https://www.drupal.org/node/2195739). You
      // should use renderable arrays instead.
      //
      //
      // @see https://www.drupal.org/node/2195739
      // return theme('flat_modal_blank');

    }

    public static function loader()
    {
      return [
        '#theme' => 'flat_modal_loader',
      ];
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('flat_modal_loader');

    }

    public static function error()
    {
      return [
        '#theme' => 'flat_modal_error',
      ];
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('flat_modal_error');

    }

    public static function confirm($cmdi_id)
    {
      return [
        '#theme' => 'flat_modal_confirm',
        '#cmdi_id' => $cmdi_id,
        '#title' => 'Provided label already exists',
        '#description' => 'A preset with this label already exists. Would you like to replace it?',
      ];
      // @FIXME
      // theme() has been renamed to _theme() and should NEVER be called directly.
      // Calling _theme() directly can alter the expected output and potentially
      // introduce security issues (see https://www.drupal.org/node/2195739). You
      // should use renderable arrays instead.
      //
      //
      // @see https://www.drupal.org/node/2195739
      // return theme('flat_modal_confirm', [
      //
      //         'cmdi_id' => $cmdi_id,
      //         'title' => 'Provided label already exists',
      //         'description' => 'A preset with this label already exists. Would you like to replace it?',
      //       ]);

    }

    public static function success()
    {
      return [
        '#theme' => 'flat_modal_success',
        '#title' => 'Success',
        '#description' => 'Your preset was successfully saved.',
      ];
      // @FIXME
      // theme() has been renamed to _theme() and should NEVER be called directly.
      // Calling _theme() directly can alter the expected output and potentially
      // introduce security issues (see https://www.drupal.org/node/2195739). You
      // should use renderable arrays instead.
      //
      //
      // @see https://www.drupal.org/node/2195739
      // return theme('flat_modal_success', [
      //
      //         'title' => 'Success',
      //         'description' => 'Your preset was successfully saved.',
      //       ]);

    }

    public static function confirmDelete()
    {
      return [
        '#theme' => 'flat_modal_confirm_delete',
        '#title' => 'Are you sure?',
        '#description' => 'Are you sure you want to delete this preset? This action is permanent!',
        '#url' => Url::fromRoute('flat_deposit.delete_cmdi_template'),
      ];
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('flat_modal_confirm_delete', [
//
//         'title' => 'Are you sure?',
//         'description' => 'Are you sure you want to delete this preset? This action is permanent!',
//         'url' => url('flat_deposit/delete_cmdi_template'),
//       ]);

    }

    public static function successDelete()
    {
      return [
        '#theme' => 'flat_modal_success',
        '#title' => 'Success',
        '#description' => 'Your preset was successfully deleted.',
      ];
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('flat_modal_success', [
//
//         'title' => 'Success',
//         'description' => 'Your preset was successfully deleted.',
//       ]);

    }
}
