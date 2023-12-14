<?php

use Drupal\Core\Form\FormStateInterface;

include_once 'class.CmdiPresetBuilder.inc';
include_once 'class.CmdiPresetActionsBuilder.inc';
include_once 'class.CmdiModalBuilder.inc';
include_once 'class.CmdiPresetRebuilder.inc';
include_once 'class.CmdiPresetSaver.inc';
include_once 'class.CmdiPresetSaveLoaded.inc';
include_once 'class.CmdiPresetLoader.inc';

class CmdiPresetManager
{
    public static function build($component_id, $profile, $cmdi_tree)
    {
        return \CmdiPresetBuilder::build($component_id, $profile, $cmdi_tree);
    }

    public static function buildActions($profile, $component_id, $cmdi_tree, $description)
    {
        return \CmdiPresetActionsBuilder::build($profile, $component_id, $cmdi_tree, $description);
    }

    public static function modal()
    {
        return \CmdiModalBuilder::build();
    }

    public static function rebuild(&$elements, $cmdi_tree, $profile, $component_id)
    {
        // clearing cache
        \CmdiPresetData::clear();

        // and rebuilding 'load_template' element
        \CmdiPresetRebuilder::rebuild($elements, $cmdi_tree, $profile, $component_id);
    }

    public static function save(FormStateInterface $form_state)
    {
        $saver = new \CmdiPresetSaver($form_state);
        return $saver->save();
    }

    public static function saveLoaded(FormStateInterface $form_state)
    {
        return \CmdiPresetSaveLoaded::saveLoaded($form_state);
    }

    public static function load(FormStateInterface $form_state)
    {
        return \CmdiPresetLoader::load($form_state);
    }
}

function flat_cmdi_presets_ajax_save_callback($form, &$form_state)
{
    return $form['template_container'];
}

function flat_cmdi_presets_ajax_load_callback($form, &$form_state)
{
    return $form['template_container'];
}
