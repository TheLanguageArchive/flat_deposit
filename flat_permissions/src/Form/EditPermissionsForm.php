<?php

/**
 * @file
 * Contains \Drupal\flat_permissions\Form\EditPermissionsForm.
 *
 * Form to edit access permissions for a Repository Item
 */

namespace Drupal\flat_permissions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class EditPermissionsForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'flat_permissions_edit_permissions_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $user = \Drupal::currentUser();

    $node = \Drupal::routeMatch()->getParameter('node');

    $nid = $node->id();

    $content_type = $node->bundle();
    $model = NULL;
    if ($content_type === 'islandora_object') {
      $model = $node->get('field_model')->referencedEntities()[0]->getName();
    }

    $is_collection = ($model === 'Collection');

    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $policy = $manager->fetchAccessPolicy($nid);

    $policy = $policy ? $manager->addLevels($policy) : null;

    $policy = $policy ? $manager->sortByEffectiveRole($policy) : null;

    $user_input = $form_state->getUserInput();
    if (array_key_exists('radio', $user_input)) {
      $radio_value = $user_input['radio'];
    } else {
      $radio_value = NULL;
    }

    // Using Drupal tempstore to store the number of fieldsets
    $tempstore = \Drupal::service('tempstore.private');

    $store = $tempstore->get('flat_permissions_collection');


    // If tempstore value is not yet set or if the form is reloaded other than through an AJAX call, we set it to 0
    $request = \Drupal::request();
    $is_ajax = $request->isXmlHttpRequest();

    $types_fieldsets_indexes = $store->get('types_fieldsets_indexes');

    if (!$types_fieldsets_indexes || !$is_ajax) {
      $store->set('types_fieldsets_indexes', [1]);
      $types_fieldsets_indexes = [1];
    }

    $form_state->set('types_fieldsets_indexes', $types_fieldsets_indexes);

    $form['#cache'] = ['max-age' => 0];

    $form['#tree'] = true;

    // the effective access rules from higher up in the hierarch will be displayed in this info section, if any
    // rendering is done with the flat-permissions-policy twig template
    $form['info'] = [
      '#type' => 'html_tag',
      '#title' => 'Effective Access Rules',
      '#prefix' => '<h2>Effective Access Rules</h2>
      <p>The nearest access rules that are defined in the collection hierarchy and that are applicable to this item.</p>',
      '#tag' => 'div',
      '#theme' => 'flat_permissions_policy',
      '#attributes' => [
        'class' => ['effective-rules'],
      ],
      '#data' => $policy,
    ];

    $form['rules'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<h2>Define Read Access</h2>
      <p>Define read rules that will apply to this item and anything below it. Defining a rule here will override any rule in the parent collection(s). Read rules defined anywhere lower in the hiearchy will however take precedence over rules defined here.</p>',
    ];

    $form['rules']['all'] = [
      '#type' => 'fieldset',
      '#tree' => true,
      '#attributes' => [
        'class' => ['rule-type-wrapper'],
      ],
    ];

    $form['rules']['all']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule for all files',
      '#return_value' => 'all',
      '#name' => 'radio',
      '#default_value' => $radio_value === 'all' ? 'all' : NULL,
    ];

    $form['rules']['all']['all_fieldset'] = [
      '#type' => 'fieldset',
      '#tree' => true,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'all'],
        ],
      ],
    ];

    $levels = $manager::LEVELS;

    $form['rules']['all']['all_fieldset']['level'] = $this->build_levels('1', 'all', $levels, $form_state);

    $form['rules']['all']['all_fieldset']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Specific users'),
      '#states' => [
        'visible' => [
          [':input[name="all_level_1"]' => ['value' => 'none']],
          [':input[name="all_level_1"]' => ['value' => 'academic']],
        ],
      ],
      '#attributes' => [
        'class' => ['multi-autocomplete'],
        'data-autocomplete-url' => 'permissions/autocomplete/users',
        'data-hidden-input-name' => 'hidden_users_field_all',
      ],
    ];

    $form['rules']['all']['all_fieldset']['hidden-users'] = $this->build_hidden_field('all', 'users', $form_state);

    $form['rules']['types'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => ['rule-type-wrapper'],
      ],
    ];

    $form['rules']['types']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule(s) for specific file and/or mime types',
      '#return_value' => 'types',
      '#name' => 'radio',
      '#default_value' => $radio_value === 'types' ? 'types' : NULL,
    ];

    $form['rules']['types']['types_fieldset'] = [
      '#type' => 'container',
      '#tree' => true,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'types'],
        ],
      ],
    ];

    $form['rules']['types']['types_fieldset']['fieldsets'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<div id="types-fieldsets-wrapper" class="rules-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'types'],
        ],
      ],
    ];

    foreach ($types_fieldsets_indexes as $i) {
      // we only need a "remove" button if there's more than one fieldset
      if (sizeof($types_fieldsets_indexes) == 1) {
        $remove = false;
      } else {
        $remove = true;
      }
      $form['rules']['types']['types_fieldset']['fieldsets'][$i] = $this->build_types_fieldset($i, $remove, $manager, $form_state);
    }

    $form['rules']['types']['types_fieldset']['add_more'] = [
      '#id' => 'types-fieldset-add',
      '#name' => 'types_fieldset_add',
      '#type' => 'submit',
      '#title' => t('Add rule'),
      '#value' => t('Add rule'),
      '#attributes' => [
        'class' => ['add-button'],
      ],
      '#ajax' => [
        'callback' => '::typesCallback',
        'wrapper' => 'types-fieldsets-wrapper',
      ],
      '#submit' => ['::addTypesFieldset'],
      '#limit_validation_errors' => [],
    ];

    if (!$is_collection) {

      // not a collection, should have media in theory so we need to create a "files" rule

      $files_fieldsets_indexes = $store->get('files_fieldsets_indexes');

      if (!$files_fieldsets_indexes || !$is_ajax) {
        $store->set('files_fieldsets_indexes', [1]);
        $files_fieldsets_indexes = [1];
      }

      $form_state->set('files_fieldsets_indexes', $files_fieldsets_indexes);

      $media = $manager->getMediaEntitiesByNodeId($nid);

      $options = [];

      foreach ($media as $key => $m) {
        $name = $m->get('name')->value;
        $options[$key] = ['filename' => $name];
      }

      asort($options);

      $form['rules']['files'] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'class' => 'rule-type-wrapper',
        ],
      ];

      $form['rules']['files']['radio'] = [
        '#type' => 'radio',
        '#title' => 'Rule(s) for specific files',
        '#return_value' => 'files',
        '#name' => 'radio',
        '#default_value' => $radio_value === 'files' ? 'files' : NULL,
      ];

      $form['rules']['files']['files_fieldset'] = [
        '#type' => 'container',
        '#tree' => true,
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'files'],
          ],
        ],
      ];

      $form['rules']['files']['files_fieldset']['fieldsets'] = [
        '#type' => 'container',
        '#tree' => true,
        '#prefix' => '<div id="files-fieldsets-wrapper" class="rules-wrapper">',
        '#suffix' => '</div>',
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'files'],
          ],
        ],
      ];

      foreach ($files_fieldsets_indexes as $i) {
        // we only need a "remove" button if there's more than one fieldset
        if (sizeof($files_fieldsets_indexes) == 1) {
          $remove = false;
        } else {
          $remove = true;
        }
        $form['rules']['files']['files_fieldset']['fieldsets'][$i] = $this->build_files_fieldset($i, $remove, $options, $manager, $form_state);
      }

      $form['rules']['files']['files_fieldset']['add_more'] = [
        '#id' => 'files-fieldset-add',
        '#name' => 'files_fieldset_add',
        '#type' => 'submit',
        '#title' => t('Add rule'),
        '#value' => t('Add rule'),
        '#attributes' => [
          'class' => ['add-button'],
        ],
        '#ajax' => [
          'callback' => '::filesCallback',
          'wrapper' => 'files-fieldsets-wrapper',
        ],
        '#submit' => ['::addFilesFieldset'],
        '#limit_validation_errors' => [],
      ];
    }

    $form['write'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<h2>Define Write Access</h2>
      <p>Define which users have write access to this item and anything below it. Defining write access here will override any write permissions defined in the parent collection(s). Write access defined anywhere lower in the hierarchy will however take precedence over rules defined here.</p>',
    ];

    $form['write']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Users'),
      '#attributes' => [
        'class' => ['multi-autocomplete'],
        'data-autocomplete-url' => 'permissions/autocomplete/users',
        'data-hidden-input-name' => 'hidden_users_field_write',
      ],
    ];

    $form['write']['hidden-users'] = $this->build_hidden_field('write', 'users', $form_state);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $form['#attached']['library'][] = 'flat_permissions/multivalue_autocomplete';
    $form['#attached']['library'][] = 'flat_permissions/flat_permissions';

    return $form;
  }


  /**
   * Ajax callback for updating the mime fieldsets after adding or removing a fieldset
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The mime fieldsets element from the given form array.
   */
  public function typesCallback(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'types_fieldset')) {
      return $form['rules']['types']['types_fieldset']['fieldsets'];
    }
  }


  /**
   * Ajax callback for updating the mime fieldsets after adding or removing a fieldset
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The mime fieldsets element from the given form array.
   */
  public function filesCallback(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'files_fieldset')) {
      return $form['rules']['files']['files_fieldset']['fieldsets'];
    }
  }

  /**
   * Adds a new value to the "indexes", thereby creating a new fieldset in the form and stores the new indexes in the temporary store.
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   *
   * @return void
   */
  public function addTypesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'types_fieldset_add')) {
      $form_state->setRebuild();
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('flat_permissions_collection');
      $indexes = $form_state->get('types_fieldsets_indexes');
      $next_index = max($indexes) + 1;
      $indexes[] = $next_index;
      $store->set('types_fieldsets_indexes', $indexes);
    }
  }

  /**
   * Adds a new value to the "indexes", thereby creating a new fieldset in the form and stores the new indexes in the temporary store.
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   *
   * @return void
   */
  public function addFilesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'files_fieldset_add')) {
      $form_state->setRebuild();
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('flat_permissions_collection');
      $indexes = $form_state->get('files_fieldsets_indexes');
      $next_index = max($indexes) + 1;
      $indexes[] = $next_index;
      $store->set('files_fieldsets_indexes', $indexes);
    }
  }

  /**
   * Removes a fieldset from the form based on the triggering element.
   *
   * @param array &$form The form array.
   * @param FormStateInterface $form_state The current state of the form.
   * @return void
   */
  public function removeTypesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'types_fieldset_remove')) {
      // getting the index of the fieldset to remove from the triggering element
      $pattern = "/mimes_fieldset_remove_(\d+)/";
      $match = preg_match($pattern, $triggeringElementName, $matches);
      if ($match) {
        $index = $matches[1];
      } else {
        return;
      }
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('flat_permissions_collection');
      $indexes = $form_state->get('types_fieldsets_indexes');
      if (($key = array_search($index, $indexes)) !== false) {
        unset($indexes[$key]);
      }
      $store->set('types_fieldsets_indexes', $indexes);
      $form_state->setRebuild();
    }
  }

  /**
   * Removes a fieldset from the form based on the triggering element.
   *
   * @param array &$form The form array.
   * @param FormStateInterface $form_state The current state of the form.
   * @return void
   */
  public function removeFilesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'files_fieldset_remove')) {
      // getting the index of the fieldset to remove from the triggering element
      $pattern = "/files_fieldset_remove_(\d+)/";
      $match = preg_match($pattern, $triggeringElementName, $matches);
      if ($match) {
        $index = $matches[1];
      } else {
        return;
      }
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('flat_permissions_collection');
      $indexes = $form_state->get('files_fieldsets_indexes');
      if (($key = array_search($index, $indexes)) !== false) {
        unset($indexes[$key]);
      }
      $store->set('files_fieldsets_indexes', $indexes);
      $form_state->setRebuild();
    }
  }

  /**
   * Builds a fieldset for adding file and/or mime type rules.
   *
   * @param int $i The index of the fieldset.
   * @param bool $remove Whether to include a remove button.
   * @param string $manager The manager class.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The fieldset array.
   */
  public function build_types_fieldset($i, $remove, $manager, FormStateInterface $form_state)
  {

    $levels = $manager::LEVELS;

    $user_input = $form_state->getUserInput();
    if (isset($user_input['types_level_' . $i])) {
      $default_value = $user_input['types_level_' . $i];
    } else {
      $default_value = '';
    }

    $fieldset = [

      '#tree'        => true,
      '#type'        => 'fieldset',
      //'level'        => $this->build_levels($i, 'mimes', $levels, $form_state),
      'level'        =>  [
        '#type' => 'select',
        '#title' => t('Access level'),
        '#options' => $levels,
        '#default_value' => $default_value,
        '#name' => 'types_level_' . $i,
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'types'],
          ],
        ],
      ],
      'users'        => [
        '#type' => 'textfield',
        '#title' => t('Specific users'),
        '#states' => [
          'visible' => [
            [':input[name="types_level_' . $i . '"]' => ['value' => 'none']],
            [':input[name="types_level_' . $i . '"]' => ['value' => 'academic']],
          ],
        ],
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/users',
          'data-hidden-input-name' => 'hidden_users_field_' . $i,
        ],
      ],
      'filetypes'        => [
        '#type' => 'checkboxes',
        '#title' => t('File type(s)'),
        '#prefix' => '<span class="label">' . t('File type(s)') . '</span>',
        '#options' => $manager::TYPES,
      ],
      'mimetypes'        => [
        '#type' => 'textfield',
        '#title' => t('Mime type(s)'),
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/mime_type',
          'data-hidden-input-name' => 'hidden_mimes_field_' . $i,
        ],
      ],
      'hidden-mimes'       => $this->build_hidden_field($i, 'mimes', $form_state),
      'hidden-users'       => $this->build_hidden_field($i, 'users', $form_state),
      '#attributes' => [
        'class' => 'rule-wrapper',
      ],
    ];

    if ($remove) {
      $fieldset['remove'] = $this->build_types_remove($i);
    }

    return $fieldset;
  }

  public function build_files_fieldset($i, $remove, $options, $manager, FormStateInterface $form_state)
  {

    $levels = $manager::LEVELS;

    $fieldset = [

      '#tree'        => true,
      '#type'        => 'fieldset',
      'level'        => $this->build_levels($i, 'files', $levels, $form_state),
      'users'        => [
        '#type' => 'textfield',
        '#title' => t('Specific users'),
        '#states' => [
          'visible' => [
            [':input[name="files_level_' . $i . '"]' => ['value' => 'none']],
            [':input[name="files_level_' . $i . '"]' => ['value' => 'academic']],
          ],
        ],
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/users',
          'data-hidden-input-name' => 'hidden_users_field_' . $i,
        ],
      ],

      'files' => [
        '#type' => 'tableselect',
        '#header' => ['filename' => t('Filename')],
        '#title' => t('Files'),
        '#id' => 'select_files_' . $i,
        '#options' => $options,
        '#name' => 'files_level',
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'files'],
          ],
        ],
      ],

      'hidden-users'       => $this->build_hidden_field($i, 'users', $form_state),
      '#attributes' => [
        'class' => 'rule-wrapper',
      ]
    ];

    if ($remove) {
      $fieldset['remove'] = $this->build_files_remove($i, 'files');
    }

    return $fieldset;
  }

  /**
   * Builds a hidden field that will contain the selected mime type values.
   *
   * @param int $i The index of the field.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The hidden field array.
   */
  public function build_hidden_field($index, $rule_type, FormStateInterface $form_state)
  {
    $user_input = $form_state->getUserInput();
    if (isset($user_input['hidden_' . $rule_type . '_field_' . $index])) {
      $default_value = $user_input['hidden_' . $rule_type . '_field_' . $index];
    } else {
      $default_value = '';
    }
    $field = [
      '#type' => 'hidden',
      '#default_value' => $default_value,
      '#attributes' => [
        'class' => ['hidden-multi-autocomplete'],
        'name' => 'hidden_' . $rule_type . '_field_' . $index,
      ],
    ];

    return $field;
  }

  /**
   * Builds a select element for the access level
   *
   * @param array $levels The array of levels to be used as options.
   * @return array The select element for the access level.
   */
  public function build_levels($index, $rule_type, $levels, FormStateInterface $form_state)
  {
    $user_input = $form_state->getUserInput();
    if (isset($user_input[$rule_type . '_level_' . $index])) {
      $default_value = $user_input[$rule_type . '_level_' . $index];
    } else {
      $default_value = '';
    }
    $level_element = [
      '#type' => 'select',
      '#title' => t('Access level'),
      '#options' => $levels,
      '#default_value' => $default_value,
      '#name' => $rule_type . '_level_' . $index,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => $rule_type],
        ],
      ],
    ];
    return $level_element;
  }

  /**
   * Builds a textfield element for specific users.
   *
   * This function creates a textfield element with the title "Specific users".
   * The element is conditionally visible based on the value of the "mimes_level" input field.
   * If the value is either "none" or "academic", the element will be visible.
   *
   * @return array The textfield element for specific users.
   */
  public function build_users()
  {
    $users_element = [
      '#type' => 'textfield',
      '#title' => t('Specific users'),
      '#states' => [
        'visible' => [
          [':input[name="mimes_level"]' => ['value' => 'none']],
          [':input[name="mimes_level"]' => ['value' => 'academic']],
        ],
      ],
    ];
    return $users_element;
  }

  /**
   * Builds a remove element for the mimes fieldset.
   *
   * @param int $index The index of the rule to be removed.
   * @return array The remove element with the specified index.
   */
  public function build_types_remove($index)
  {

    $remove_element = [
      '#type' => 'submit',
      '#value' => t('Remove rule'),
      '#name' => 'types_fieldset_remove_' . $index,
      '#ajax' => [
        'callback' => '::typesCallback',
        'wrapper' => 'types-fieldsets-wrapper',
      ],
      '#attributes' => [
        'class' => ['remove-button btn-danger'],
      ],
      '#submit' => ['::removeTypesFieldset'],
    ];
    return $remove_element;
  }

  /**
   * Builds a remove element for the files fieldset.
   *
   * @param int $index The index of the rule to be removed.
   * @return array The remove element with the specified index.
   */
  public function build_files_remove($index)
  {

    $remove_element = [
      '#type' => 'submit',
      '#value' => t('Remove rule'),
      '#name' => 'files_fieldset_remove_' . $index,
      '#ajax' => [
        'callback' => '::filesCallback',
        'wrapper' => 'files-fieldsets-wrapper',
      ],
      '#attributes' => [
        'class' => ['remove-button btn-danger'],
      ],
      '#submit' => ['::removeFilesFieldset'],
    ];
    return $remove_element;
  }

  /**
   * Read group fieldset
   *
   * @param string $currentGroup
   *
   * @return array
   */
  public function build_read_group_fieldset($currentGroup)
  {

    return [

      '#type'      => 'fieldset',
      '#title'     => 'Read groups',
      'read_group' => [

        '#type'          => 'radios',
        '#default_value' => $currentGroup,
        '#attributes'    => [
          'data-role' => 'select-group',
        ],
        '#options'       => [

          PermissionsManager::ROLE_ANONYMOUS     => 'Public',
          PermissionsManager::ROLE_AUTHENTICATED => 'Registered Users',
          PermissionsManager::ROLE_ACADEMIC      => 'Academic Users',
          PermissionsManager::ROLE_SPECIFIC      => 'Specific Users',
        ],
      ],
    ];
  }

  /**
   * Visibility checkbox fieldset
   *
   * @return array
   */
  public function build_visibility_fieldset($visibility)
  {

    return [

      '#type'      => 'fieldset',
      '#title'     => 'Visibility',
      'visibility' => [

        '#type'          => 'checkbox',
        '#title'         => 'Invisible',
        '#default_value' => $visibility,
        '#attributes'    => [
          'data-role' => 'visibility',
        ],
      ],
    ];
  }

  /**
   * Abstract add user fieldset (for read and management)
   *
   * @param array  $form
   * @param array  $form_state
   * @param string $type (usually read/management)
   * @param array  $users
   *
   * @return array
   */
  public function build_users_fieldset(&$form_state, $type, $users)
  {

    if (count($form_state['new_users'][$type]) > 0) {
      $users = array_merge($users, $form_state['new_users'][$type]);
    }

    $fieldset = [

      '#tree'        => true,
      '#type'        => 'container',
      'delete'       => build_delete_users_fieldset($type, $users),
      'hidden'       => build_hidden_users_fieldset($type, $users),
      'autocomplete' => build_autocomplete_fieldset(

        'user/autocomplete',
        'Add user',
        'add_' . $type . '_user',
        'flat_permissions_form_add_' . $type . '_user_submit',
        'flat_permissions_form_add_user_validate',
        'flat_permissions_form_add_' . $type . '_user_js'
      ),
    ];

    return $fieldset;
  }

  /**
   * Deleted users fieldset
   *
   * @param string $type
   * @param array  $users
   *
   * @return array
   */
  public function build_delete_users_fieldset($type, $users)
  {

    $fieldset = [];

    $i = 1;

    foreach ($users as $user) {

      $fieldset[$i] = [

        '#type'         => 'checkbox',
        '#title'        => $user,
        '#return_value' => $user,
      ];

      $i += 1;
    }

    return $fieldset;
  }

  /**
   * @param string $type
   * @param array  $users
   *
   * @return array
   */
  public function build_hidden_users_fieldset($type, $users)
  {

    $fieldset = [

      '#tree'       => true,
      '#type'       => 'container',
      '#attributes' => [
        'data-role' => 'hidden-' . $type . '-users',
      ],
    ];

    foreach ($users as $key => $user) {

      $fieldset[$key + 1] = [

        '#type'       => 'hidden',
        '#value'      => $user,
        '#attributes' => [

          'data-type' => $type,
          'data-user' => $user,
        ],
      ];
    }

    return $fieldset;
  }

  /**
   * Abstract add user submit handler, adding new user
   * to the list using AJAX if javascript enabled
   *
   * @param array  $form
   * @param FormStateInterface  $form_state
   * @param string $type (usually read/management)
   */
  public function flat_permissions_form_add_user_submit(array &$form, FormStateInterface $form_state, $type)
  {

    if (isset($form_state['values']['add_' . $type . '_user'])) {

      // if checkbox is checked, it has a string, otherwise value is 0,
      // so let's filter by is_string
      $users        = [];
      $removedUsers = array_filter($form_state['values'][$type . '_users']['delete'], 'is_string');

      foreach ($form[$type . '_users']['delete'] as $field) {

        if ($field['#type'] === 'checkbox' && !in_array($field['#return_value'], $removedUsers)) {
          $users[] = $field['#return_value'];
        }
      }

      foreach ($form_state['new_users'][$type] as $user) {

        if (!in_array($user, $removedUsers)) {
          $users[] = $user;
        }
      }

      if (!in_array($form_state['values'][$type . '_users']['autocomplete']['field'], $users)) {

        $form_state['rebuild']            = true;
        $form_state['new_users'][$type][] = $form_state['values'][$type . '_users']['autocomplete']['field'];
        $form_state['new_users'][$type]   = array_unique($form_state['new_users'][$type]);
      }
    }
  }

  /**
   * Concrete implementation of read users submit handler
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_read_user_submit(array &$form, FormStateInterface $form_state)
  {
    $this->flat_permissions_form_add_user_submit($form, $form_state, 'read');
  }

  /**
   * Concrete implementation of management users submit handler
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_management_user_submit(array &$form, FormStateInterface $form_state)
  {
    return $this->flat_permissions_form_add_user_submit($form, $form_state, 'management');
  }

  /**
   * Abstract response handler ajax for adidng user
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param string $type (usually read/management)
   *
   * @return array
   */
  public function flat_permissions_form_add_user_js(array &$form, FormStateInterface $form_state)
  {

    $count    = 0;
    $username = '';

    foreach ($form[$type . '_users']['delete'] as $field) {

      if ($field['#type'] === 'checkbox') {

        $count   += 1;
        $username = $field['#return_value'];
      }
    }

    unset($form[$type . '_users']['delete'][$count]['#title']);

    return [

      '#type'     => 'ajax',
      '#commands' => [

        ajax_command_invoke(null, 'onAddUser', [

          $type,
          $username,
          drupal_render($form[$type . '_users']['delete'][$count]),
          drupal_render($form[$type . '_users']['hidden'][$count]),
          $form_state['rebuild']
        ]),
      ],
    ];
  }

  /**
   * Concrete implementation of read users ajax response
   * it calls the jQuery.fn.onAddUser method to add new line
   * in the users table
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_read_user_js(array &$form, FormStateInterface $form_state)
  {
    return $this->flat_permissions_form_add_user_js($form, $form_state, 'read');
  }

  /**
   * Concrete implementation of management users ajax response
   * it calls the jQuery.fn.onAddUser method to add new line
   * in the users table
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_management_user_js(array &$form, FormStateInterface $form_state)
  {
    return $this->flat_permissions_form_add_user_js($form, $form_state, 'management');
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @param array $mimes
   */
  public function flat_permissions_form_add_mime_submit(array &$form, FormStateInterface $form_state)
  {

    if ($form_state->hasValue('add_mime')) {

      // if checkbox is checked, it has a string, otherwise value is 0,
      // so let's filter by is_string
      $mimes        = [];
      $removedMimes = $form_state->getValue(['mimes', 'delete']);

      foreach ($form['rules']['mimes']['delete'] as $field) {

        if (is_array($field)) {

          if (array_key_exists('#type', $field)) {
            if ($field['#type'] === 'checkbox' && !in_array($field['#return_value'], $removedMimes)) {
              $mimes[] = $field['#return_value'];
            }
          }
        }
      }

      foreach ($form_state->getValue(['mimes', 'delete']) as $mime) {

        if (!in_array($mime, $removedMimes)) {
          $mimes[] = $mime;
        }
      }

      if (!in_array($form_state->getValue(['rules', 'mimes', 'autocomplete', 'field', 'input']), $mimes)) {

        $form_state->set('rebuild', true);
        $form_state->set('rules', 'mimes', 'mimes', $form_state->getValue(['rules', 'mimes', 'autocomplete', 'field', 'input']));
        $form_state->set('rules', 'mimes', 'mimes', array_unique($form_state->getValue(['rules', 'mimes', 'mimes'])));
      }
    }
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_mime_validate(array &$form, FormStateInterface $form_state)
  {
  }

  private function findDuplicateValuesInMultipleArrays($arrays)
  {
    $valueCounts = [];
    foreach ($arrays as $array) {
      $uniqueValues = array_unique($array);
      foreach ($uniqueValues as $value) {
        if ($value !== '' and $value !== 0) {
          if (!isset($valueCounts[$value])) {
            $valueCounts[$value] = 0;
          }
          $valueCounts[$value]++;
        }
      }
    }
    $duplicateValuesInMultipleArrays = [];
    foreach ($valueCounts as $value => $count) {
      if ($count > 1) {
        $duplicateValuesInMultipleArrays[] = $value;
      }
    }

    return $duplicateValuesInMultipleArrays;
  }

  private function findValuesOccurringMoreThanOnce($array)
  {

    $valueCounts = array_count_values($array);
    $multipleValues = array_filter($valueCounts, function ($count) {
      return $count > 1;
    });
    $valuesWithMultipleOccurrences = array_keys($multipleValues);

    return $valuesWithMultipleOccurrences;
  }

  private function checkboxesAreChecked(array $checkboxes_values)
  {
    foreach ($checkboxes_values as $value) {
      if (!empty($value)) {
        return TRUE;
      }
    }
    return FALSE;
  }


  /**
   * Instead of using validateForm, which is called every time either ajax or form requests
   * are processed, we hook up this custom validation handler to the submit button so that this
   * handler will only be called if user presses the submit button.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $errors = [];
    $level_options = $manager::LEVELS;
    $rules = $form_state->getValue(['rules']);
    // for some reason, form_state values are not present for some of the dynamically added fields,
    // whereas the user input array does have them, so we'll get them from there.
    $user_input = $form_state->getUserInput();
    if (array_key_exists('radio', $user_input)) {
      $rule_type = $user_input['radio'];
      if ($rule_type === 'types') {
        // check for empty rules and duplicate filetype and mimetype values
        $types_rules = $rules['types']['types_fieldset']['fieldsets'];
        foreach ($types_rules as $key => $type_rule) {
          $types = $type_rule['filetypes'];
          $empty_checkboxes = !$this->checkboxesAreChecked($types);
          $type_options = $form['rules']['types']['types_fieldset']['fieldsets'][$key]['filetypes']['#options'];
          $types_rules_types[] = $types;
          $hidden_mimes = $user_input['hidden_mimes_field_' . $key];
          if ($empty_checkboxes && empty($hidden_mimes)) {
            $errors[] = 'You have created a rule with no file or mime types selected.';
          }
          $types_rules_mimes[] = explode(',', $hidden_mimes);
          $rule_levels[] = $user_input['types_level_' . $key];
          $rule_users[] = $user_input['hidden_users_field_' . $key];
        }
        $type_duplicates = $this->findDuplicateValuesInMultipleArrays($types_rules_types);
        foreach ($type_duplicates as $key => $value) {
          $type_duplicate_names[] = $type_options[$value];
        }
        $mime_duplicates = $this->findDuplicateValuesInMultipleArrays($types_rules_mimes);
        if (!empty($type_duplicates)) {
          $errors[] = 'You have used the same file type(s) in more than one rule: ' . implode(', ', $type_duplicate_names);
        }
        if (!empty($mime_duplicates)) {
          $errors[] = 'You have used the same mime type(s) in more than one rule: ' . implode(', ', $mime_duplicates);
        }
      }


      if ($rule_type === 'files') {
        // check for empty rules andduplicate file values
        $files_rules = $rules['files']['files_fieldset']['fieldsets'];
        foreach ($files_rules as $key => $file_rule) {
          $files = $file_rule['files'];
          $file_options = $form['rules']['files']['files_fieldset']['fieldsets'][$key]['files']['#options'];
          $files_rules_files[] = $files;
          $rule_levels[] = $user_input['files_level_' . $key];
          $rule_users[] = $user_input['hidden_users_field_' . $key];
        }
        $file_duplicates = $this->findDuplicateValuesInMultipleArrays($files_rules_files);
        foreach ($file_duplicates as $key => $value) {
          $file_duplicate_names[] = $file_options[$value]['filename'];
        }
        if (!empty($file_duplicate_names)) {
          $errors[] = 'You have used the same file(s) in more than one rule: ' . implode(', ', $file_duplicate_names);
        }
      }

      if ($rule_type !== 'all') {
        // check whether there are multiple rules with the same access level.
        // in case of academic or restricted (none) access, that is OK as long as the users are distinct
        $level_duplicates = $this->findValuesOccurringMoreThanOnce($rule_levels);
        if (!empty($level_duplicates)) {
          $no_users_duplicate_levels = array_intersect(array_values($level_duplicates), ['anonymous', 'authenticated']);
          if (!empty($no_users_duplicate_levels)) {
            foreach ($no_users_duplicate_levels as $key => $value) {
              $no_users_duplicate_level_names[] = $level_options[$value];
            }
            $errors[] = 'You have used these access level(s) for more than one rule: ' . implode(', ', $no_users_duplicate_level_names) . '.
            Please create a single rule for them.';
          }
          $users_duplicate_levels = array_intersect(array_values($level_duplicates), ['academic', 'none']);
          if (!empty($users_duplicate_levels)) {
            $unique_users = array_unique($rule_users);
            if (sizeof($unique_users) === 1) {
              foreach ($users_duplicate_levels as $key => $value) {
                $users_duplicate_level_names[] = $level_options[$value];
              }
              $errors[] = 'You have used the the same access level(s) in more than one rule with (some of) the same users: ' . implode(', ', $users_duplicate_level_names) . '.
              Please create a single rule for these levels or make sure that theusers are distinct for each rule.';
            }
          }
        }
      }
    } else {
      $write_users = $user_input['hidden_users_field_write'];
      if (empty($write_users)) {
        // no write users defined and no read rules defined
        $errors[] = 'No Read or Write access has been defined.';
      }
    }
    if (!empty($errors)) {
      if (count($errors) > 1) {
        $errors = [
          '#theme' => 'item_list',
          '#type' => 'ul',
          '#attributes' => ['class' => 'mylist'],
          '#items' => $errors,
          '#prefix' => '<h4>There are some conflicts in your access rules:</h4>',
        ];
      } else {
        $errors = $errors[0];
      }
      $form_state->setErrorByName('', $errors);
      return $form;
    }
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $read_rule_output = [];
    $write_rule_output = [];
    $rules = $form_state->getValue(['rules']);
    // for some reason, form_state values are not present for some of the dynamically added fields,
    // whereas the user input array does have them, so we'll get them from there.
    $user_input = $form_state->getUserInput();
    if (array_key_exists('radio', $user_input)) {
      $rule_type = $user_input['radio'];
      if ($rule_type === 'all') {
        $read_rule_output['all'] = $manager->fieldsetToRule($rules['all']['all_fieldset']);
      }
      if ($rule_type === 'types') {
        $mime_rules = $rules['types']['types_fieldset']['fieldsets'];
        foreach ($mime_rules as $key => $mime_rule) {
          $level = $user_input['types_level_' . $key];
          $mime_rule['level'] = $level;
          $hidden_mimes = $user_input['hidden_mimes_field_' . $key];
          $mime_rule['hidden-mimes'] = explode(',', $hidden_mimes);
          $hidden_users = $user_input['hidden_users_field_' . $key];
          $mime_rule['hidden-users'] = explode(',', $hidden_users);
          $read_rule = $manager->fieldsetToRule($mime_rule);
          $read_rule_output['types'][] = $read_rule;
        }
      }
      if ($rule_type === 'files') {
        $file_rules = $rules['files']['files_fieldset']['fieldsets'];
        foreach ($file_rules as $key => $file_rule) {
          $level = $user_input['files_level_' . $key];
          $file_rule['level'] = $level;
          $hidden_users = $user_input['hidden_users_field_' . $key];
          $mime_rule['hidden-users'] = explode(',', $hidden_users);
          $read_rule = $manager->fieldsetToRule($file_rule);
          $read_rule_output['files'][] = $read_rule;
        }
      }
    }
    if (array_key_exists('hidden_users_field_write', $user_input)) {
      $write_users = $user_input['hidden_users_field_write'];
      if (!empty($write_users)) {
        $write_rule_output['users'] = explode(',', $write_users);
      }
    }
    if (!empty($read_rule_output)) {
      $output['read'] = $read_rule_output;
    }
    if (!empty($write_rule_output)) {
      $output['write'] = $write_rule_output;
    }
    if (!empty($output)) {
      $output_json = json_encode($output);
      ddm($output_json);
    }

    \Drupal::messenger()->addMessage('Your access rules have been saved.');


    //ddm($form_state->getValues());
    //ddm($form_state->getValue(['rules', 'all', 'radio']));
    //ddm($form_state);


    //$node = \Drupal::routeMatch()->getParameter('node');
    //$nid = $node->id();
  }
}
