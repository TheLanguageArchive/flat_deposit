<?php

use Drupal\Core\Url;

class CmdiPresetActionsBuilder
{
  public static function build($profile, $component_id, $cmdi_tree, $description)
  {
    $cmdi_id = implode('_', $cmdi_tree);
    $available_presets = \CmdiPresetData::getAvailablePresets($profile, $component_id);

    $url = Url::fromRoute('flat_deposit.cmdi_save');
    $path = $url->toString();

    return [

      '#theme' => 'flat_actions',
      '#cmdi_id' => $cmdi_id,
      '#component_id' => $component_id,
      '#available_presets' => $available_presets,
      '#description' => $description,
      '#cmdi_data' => json_encode([

        'cmdi_id' => $cmdi_id,
        'profile' => $profile,
        'component_id' => $component_id,
        'url' => $path,
        'label_name' => 'cmdi_new_preset_label_' . $cmdi_id,
        'save_name' => 'save_cmdi_preset_' . $cmdi_id,
      ]),
    ];
    // @FIXME
    // theme() has been renamed to _theme() and should NEVER be called directly.
    // Calling _theme() directly can alter the expected output and potentially
    // introduce security issues (see https://www.drupal.org/node/2195739). You
    // should use renderable arrays instead.
    //
    //
    // @see https://www.drupal.org/node/2195739
    // return theme('flat_actions', [
    //
    //       'cmdi_id' => $cmdi_id,
    //       'component_id' => $component_id,
    //       'available_templates' => $available_templates,
    //       'cmdi_data' => json_encode([
    //
    //         'cmdi_id' => $cmdi_id,
    //         'profile' => $profile,
    //         'component_id' => $component_id,
    //         'url' => url('flat_deposit/save_check'),
    //         'label_name' => 'cmdi_new_template_label_' . $cmdi_id,
    //         'save_name' => 'save_cmdi_template_' . $cmdi_id,
    //       ]),
    //     ]);

  }
}
