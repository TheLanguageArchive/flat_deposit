<?php

use Drupal\Core\Form\FormStateInterface;

module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiCreator');
autoload_cmdicreator();

/**
 * Saving CMDI fieldset block as a preset for reuse
 *
 * @author  Ibrahim Abdullah <ibrahim.abdullah@mpi.nl>
 */
class CmdiPresetSaver
{
    /**
     * @var FormStateInterface Reference to drupal form_state
     */
    private $form_state;

    /**
     * Storing form_state reference
     *
     * @param array $form_state
     */
    public function __construct(FormStateInterface $form_state)
    {
        $this->form_state = $form_state;
    }

    /**
     * Checking whether save cmdi preset ajax action was triggered
     *
     * @return bool
     */
    public function isSubmitted()
    {
        return $this->form_state->has(['triggering_element', '#ajax', 'callback']) && $this->form_state->get(['triggering_element', '#ajax', 'callback']) === 'flat_cmdi_presets_ajax_save_callback';
    }

    /**
     * Get selected profile
     *
     * @return string|null
     */
    public function getProfile()
    {
        if ($this->form_state->has(['complete form', 'select_profile_name', '#value'])) {

            // update_collection.inc does not have the trigger field
            // using this
            return $this->form_state->get(['complete form', 'select_profile_name', '#value']);
        }

        if (!$this->form_state->has(['complete form', 'trigger', 'select_profile_name', '#value'])) {

            // trigger was not found
            return null;
        }

        return $this->form_state->get(['complete form', 'trigger', 'select_profile_name', '#value']);
    }

    /**
     * Get uid
     *
     * @return string
     */
    public function getUid()
    {
        $user = \Drupal::currentUser();
        return $user->id();
    }

    /**
     * Get tree of cmdi block to be saved
     *
     * @return array
     */
    public function getCmdiTree()
    {
        $triggeringElement = $this->form_state->getTriggeringElement() ?? [];
        return $triggeringElement['#cmdi_tree'] ?? [];
    }

    public function getComponentId()
    {
        $triggeringElement = $this->form_state->getTriggeringElement() ?? [];
        return $triggeringElement['#component_id'] ?? [];
    }

    public function getLabel()
    {
        $triggeringElement = $this->form_state->getTriggeringElement() ?? [];
        return $this->form_state->getUserInput([
            'input',
            $triggeringElement['#label_field_name']
        ]);
    }

    /**
     * Generate json encoded string representation of preset block to be saved to database
     *
     * @return string
     */
    public function generate()
    {
        // get component id
        $componentId = $this->getComponentId();

        // tree of the current block in the form
        $tree = $this->getCmdiTree();

        // get pressedButtons for tree
        $pressed = null;

        if ($this->form_state->has(['pressedButtons', 'template_container', 'elements'])) {
            $pressed = \Drupal\Component\Utility\NestedArray::getValue($this->form_state->get(['pressedButtons', 'template_container', 'elements']), $tree);
        }

        // getting form state for tree
        $input = $this->form_state->getUserInput() ?? [];
        $state = \Drupal\Component\Utility\NestedArray::getValue($input['template_container']['elements'], $tree);

        // removing all occurences of flat_cmdi_presets from form state for tree
        $state = drupal_array_unset_recursive($state, 'flat_cmdi_presets');

        // removing the last item from tree, which is the position in the current form
        // this is necessary so later this preset can be loaded in any position
        array_pop($tree);

        return json_encode([

            'tree' => $tree,
            'state' => $state,
            'pressed' => $pressed,
        ]);
    }

    /**
     * Saving preset block to db
     *
     * @return bool
     */
    public function save()
    {
        if (false === $this->isSubmitted()) {
            return false;
        }

        $label = $this->getLabel();

        if (empty($label)) {

            $this->form_state->setErrorByName('flat_cmdi_presets', t('Please provide a label for saving this block as a preset.'));
            return false;
        }

        $profile = $this->getProfile();

        if (null === $profile) {
            return false;
        }

        $block = $this->generate();

        if (null !== $block) {

            \CmdiPresetDb::save($profile, $label, $this->getComponentId(), $block, $this->getUid());
            return true;
        }

        return false;
    }
}
function drupal_array_unset_recursive($haystack, $unsetKey)
{

    if (is_array($haystack)) {

        unset($haystack[$unsetKey]);

        foreach ($haystack as $key => $val) {
            $haystack[$key] = drupal_array_unset_recursive($val, $unsetKey);
            unset($haystack[$unsetKey]);
        }
    }

    return $haystack;
}

function generate_pressed_buttons_state($input, &$form_state)
{

    $pressed = [];
    foreach ($input as $key => $val) {
        if (is_array($val)) {
            $pressed[$key]['base'] = count($val);
        }
    }
}
