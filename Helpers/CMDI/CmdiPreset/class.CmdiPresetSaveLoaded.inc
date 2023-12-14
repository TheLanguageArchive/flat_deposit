<?php

class CmdiPresetSaveLoaded
{
  public static function saveLoaded(&$form_state)
  {
    $isSubmitted = isset($form_state['triggering_element']['#ajax']['callback']) && $form_state['triggering_element']['#ajax']['callback'] === 'flat_cmdi_presets_ajax_load_callback';

    if (false === $isSubmitted) {
      return false;
    }

    $cmdi_tree_id = implode('_', $form_state['triggering_element']['#cmdi_tree']);
    $id = (int)$form_state['triggering_element']['#value'];

    $form_state['loaded_cmdi_presets'][$cmdi_tree_id] = [

      'id' => $id,
      'cmdi_tree' => $form_state['triggering_element']['#cmdi_tree'],
    ];

    return true;
  }
}
