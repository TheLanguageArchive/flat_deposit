<?php

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;

class CmdiPresetLoader
{
    public static function getLoadedBlock($id)
    {
        $user = \Drupal::currentUser();
        $row = \CmdiPresetDb::fetch($id, $user->id());

        if (false === $row) {

            // preset not found
            return false;
        }

        $block = json_decode($row['block'], true);

        if (null === $block) {

            // json could not be decoded
            return false;
        }

        return $block;
    }

    public static function load(FormStateInterface $form_state)
    {
        $triggeringElement = $form_state->getTriggeringElement();

        $isSubmitted = null !== $triggeringElement && $triggeringElement['#ajax']['callback'] && $triggeringElement['#ajax']['callback'] === 'flat_cmdi_presets_ajax_load_callback';

        if (false === $isSubmitted) {
            return false;
        }

        $id = (int)$triggeringElement['#value'];
        $target_tree = $triggeringElement['#cmdi_tree'];
        $block = static::getLoadedBlock($id);
        if (false !== $block) {

            // loading block into form_state input array will trigger the formbuilder
            // to create the necessary elements
            $input = $form_state->getUserInput() ?? [];
            NestedArray::setValue($input['template_container']['elements'], $target_tree, $block['state']);
            $form_state->setUserInput($input);

            if (null !== $block['pressed']) {

                // create pressedButtons form state if it doesn't exist
                if (!$form_state->has(['pressedButtons', 'template_container', 'elements'])) {
                    $form_state->set(['pressedButtons', 'template_container', 'elements'], []);
                }

                $pressedButtons = $form_state->get(['pressedButtons', 'template_container', 'elements']);

                // create pressedButtons tree in form state pressedButtons for saved block
                NestedArray::setValue($pressedButtons, $target_tree, $block['pressed']);
                $form_state->set(['pressedButtons', 'template_container', 'elements'], $pressedButtons);
            }
        }
    }
}
