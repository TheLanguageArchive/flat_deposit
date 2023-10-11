<?php

use Drupal\Core\Form\FormStateInterface;

include_once 'class.CmdiTemplateBuilder.inc';
include_once 'class.CmdiActionsBuilder.inc';
include_once 'class.CmdiModalBuilder.inc';
include_once 'class.CmdiTemplateRebuilder.inc';
include_once 'class.CmdiTemplateSaver.inc';
include_once 'class.CmdiTemplateSaveLoaded.inc';
include_once 'class.CmdiTemplateLoader.inc';

class CmdiTemplateManager
{
    public static function build($component_id, $profile, $cmdi_tree)
    {
        return \CmdiTemplateBuilder::build($component_id, $profile, $cmdi_tree);
    }

    public static function buildActions($profile, $component_id, $cmdi_tree)
    {
        return \CmdiActionsBuilder::build($profile, $component_id, $cmdi_tree);
    }

    public static function modal()
    {
        return \CmdiModalBuilder::build();
    }

    public static function rebuild(&$elements, $cmdi_tree, $profile, $component_id)
    {
        // clearing cache
        \CmdiTemplateData::clear();

        // and rebuilding 'load_template' element
        \CmdiTemplateRebuilder::rebuild($elements, $cmdi_tree, $profile, $component_id);
    }

    public static function save(FormStateInterface $form_state)
    {
        $saver = new \CmdiTemplateSaver($form_state);
        return $saver->save();
    }

    public static function saveLoaded(FormStateInterface $form_state)
    {
        return \CmdiTemplateSaveLoaded::saveLoaded($form_state);
    }

    public static function load(FormStateInterface $form_state)
    {
        return \CmdiTemplateLoader::load($form_state);
    }
}

function flat_cmdi_templates_ajax_save_callback($form, &$form_state)
{
    return $form['template_container'];
}

function flat_cmdi_templates_ajax_load_callback($form, &$form_state)
{
    return $form['template_container'];
}
