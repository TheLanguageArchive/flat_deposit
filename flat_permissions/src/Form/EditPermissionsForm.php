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

    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $policy = $manager->fetchAccessPolicy($nid);

    $policy = $policy ? $manager->addLevels($policy) : null;

    // Using Drupal tempstore to store the number of fieldsets
    $tempstore = \Drupal::service('tempstore.private');

    $store = $tempstore->get('flat_permissions_collection');

    $mime_fieldset_indexes = $store->get('mime_fieldset_indexes');

    // If tempstore value is not yet set or if the form is reloaded other than through an AJAX call, we set it to 0
    $request = \Drupal::request();
    $is_ajax = $request->isXmlHttpRequest();

    if (!$mime_fieldset_indexes || !$is_ajax) {
      $store->set('mime_fieldset_indexes', [1]);
      $mime_fieldset_indexes = [1];
    }

    $form_state->set('mime_fieldset_indexes', $mime_fieldset_indexes);

    $form['#tree'] = true;

    $form['info'] = [
      '#type' => 'html_tag',
      '#title' => 'Effective Access Rules',
      '#prefix' => '<h3>Effective Access Rules</h3>
      <p>The nearest access rules that are defined in the collection hierarchy and that are applicable to files below this item.</p>',
      '#tag' => 'div',
      '#theme' => 'flat_permissions_policy',
      '#data' => $policy,
    ];

    $form['hidden'] = [
      '#type' => 'container',
      '#prefix' => '<div id="hidden-fields-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['rules'] = [
      '#type' => 'container',
      '#prefix' => '<h3>Define Access Rules</h3>
      <p>Define access rules that will apply to files below this item. Defining a rule here will override any rule in the parent collection(s).</p>',
    ];

    $form['rules']['all'] = [
      '#type' => 'fieldset',
    ];

    $form['rules']['all']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule for all files',
      '#return_value' => 'all',
      '#name' => 'radio',
    ];

    $form['rules']['all']['level'] = [
      '#type' => 'select',
      '#title' => t('Access level'),
      '#options' => $manager::LEVELS,
      '#name' => 'all_level',
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'all'],
        ],
      ],
    ];

    $form['rules']['all']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Specific users'),
      '#states' => [
        'visible' => [
          [':input[name="all_level"]' => ['value' => 'none']],
          [':input[name="all_level"]' => ['value' => 'academic']],
        ],
      ],
    ];

    $form['rules']['mimes'] = [
      '#type' => 'fieldset',
    ];

    $form['rules']['mimes']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule(s) for specific file types',
      '#return_value' => 'mimes',
      '#name' => 'radio',
    ];

    $form['rules']['mimes']['mime_fieldset'] = [
      '#type' => 'container',
      '#tree' => true,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'mimes'],
        ],
      ],
    ];

    $form['rules']['mimes']['mime_fieldset']['fieldsets'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<div id="mimes-fieldsets-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'mimes'],
        ],
      ],
    ];

    foreach ($mime_fieldset_indexes as $i) {
      // we only need a "remove" button if there's more than one fieldset
      if (sizeof($mime_fieldset_indexes) == 1) {
        $remove = false;
      } else {
        $remove = true;
      }
      $form['rules']['mimes']['mime_fieldset']['fieldsets'][$i] = $this->build_mimes_fieldset($i, $remove, $manager, $form_state);
    }

    $form['rules']['mimes']['mime_fieldset']['fieldsets']['add_more'] = [
      '#type' => 'submit',
      '#title' => t('Add rule'),
      '#value' => t('Add rule'),
      '#ajax' => [
        'callback' => '::mimesCallback',
        'wrapper' => 'mimes-fieldsets-wrapper',
      ],
      '#submit' => ['::addMimesFieldset'],
      '#limit_validation_errors' => [],
    ];

    $form['rules']['files'] = [
      '#type' => 'fieldset',
    ];

    $form['rules']['files']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule(s) for specific files',
      '#return_value' => 'files',
      '#name' => 'radio',
    ];

    $form['rules']['files']['level'] = [
      '#type' => 'select',
      '#title' => t('Access level'),
      '#options' => $manager::LEVELS,
      '#name' => 'files_level',
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'files'],
        ],
      ],
    ];

    $form['rules']['files']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Specific users'),
      '#states' => [
        'visible' => [
          [':input[name="files_level"]' => ['value' => 'none']],
          [':input[name="files_level"]' => ['value' => 'academic']],
        ],
      ],
    ];

    $form['#attached']['library'][] = 'flat_permissions/multivalue_autocomplete';

    return $form;
  }


  /**
   * Ajax callback for updating the mime fieldsets after adding or removing a fieldset
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The mime fieldsets element from the given form array.
   */
  public function mimesCallback(array &$form, FormStateInterface $form_state)
  {
    return $form['rules']['mimes']['mime_fieldset']['fieldsets'];
  }

  /**
   * Adds a new value to the "indexes", thereby creating a new fieldset in the form and stores the new indexes in the temporary store.
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   *
   * @return void
   */
  public function addMimesFieldset(array &$form, FormStateInterface $form_state)
  {
    $form_state->setRebuild();
    $tempstore = \Drupal::service('tempstore.private');
    $store = $tempstore->get('flat_permissions_collection');
    $indexes = $form_state->get('mime_fieldset_indexes');
    $next_index = max($indexes) + 1;
    $indexes[] = $next_index;
    $store->set('mime_fieldset_indexes', $indexes);
  }

  /**
   * Removes a fieldset from the form based on the triggering element.
   *
   * @param array &$form The form array.
   * @param FormStateInterface $form_state The current state of the form.
   * @return void
   */
  public function removeMimesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementId = $triggeringElement['#id'];
    // getting the index of the fieldset to remove from the triggering element
    $pattern = "/fieldsets-(\d+)/";
    $match = preg_match($pattern, $triggeringElementId, $matches);
    if ($match) {
      $index = $matches[1];
    } else {
      return;
    }
    $tempstore = \Drupal::service('tempstore.private');
    $store = $tempstore->get('flat_permissions_collection');
    $indexes = $form_state->get('mime_fieldset_indexes');
    if (($key = array_search($index, $indexes)) !== false) {
      unset($indexes[$key]);
    }
    $store->set('mime_fieldset_indexes', $indexes);
    $form_state->setRebuild();
  }

  /**
   * Builds a fieldset for adding mime types.
   *
   * @param int $i The index of the fieldset.
   * @param bool $remove Whether to include a remove button.
   * @param string $manager The manager class.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The fieldset array.
   */
  public function build_mimes_fieldset($i, $remove, $manager, FormStateInterface $form_state)
  {

    $mimes = [];

    $levels = $manager::LEVELS;

    $fieldset = [

      '#tree'        => true,
      '#type'        => 'fieldset',
      'mimes'        => [
        '#type' => 'textfield',
        '#title' => t('Add mime type'),
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/mime_type',
          'data-hidden-input-name' => 'hidden_mimes_field_' . $i,
        ],
      ],
      'level'        => $this->build_levels($i, 'mimes', $levels, $form_state),
      'users'        => [
        '#type' => 'textfield',
        '#title' => t('Add users'),
        '#states' => [
          'visible' => [
            [':input[name="mimes_level_' . $i . '"]' => ['value' => 'none']],
            [':input[name="mimes_level_' . $i . '"]' => ['value' => 'academic']],
          ],
        ],
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/users',
          'data-hidden-input-name' => 'hidden_users_field_' . $i,
        ],
      ],
      'hidden-mimes'       => $this->build_hidden_field($i, 'mimes', $form_state),
      'hidden-users'       => $this->build_hidden_field($i, 'users', $form_state),
    ];

    if ($remove) {
      $fieldset['remove'] = $this->build_remove($i, 'mimes');
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
   * Builds a remove element for the mime fieldset.
   *
   * @param int $index The index of the rule to be removed.
   * @return array The remove element with the specified index.
   */
  public function build_remove($index, $rule_type)
  {

    $remove_element = [
      '#type' => 'submit',
      '#value' => t('Remove rule'),
      '#name' => 'remove_' . $index,
      '#ajax' => [
        'callback' => '::mimesCallback',
        'wrapper' => $rule_type . '-fieldsets-wrapper',
      ],
      '#submit' => ['::removeMimesFieldset'],
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

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function flat_permissions_form_add_mime_js(array &$form, FormStateInterface $form_state)
  {

    $count = 0;
    $mime  = '';

    foreach ($form['rules']['mimes']['mimes']['delete'] as $field) {

      if ($field['#type'] === 'checkbox') {

        $count += 1;
        $mime   = $field['#return_value'];
      }
    }

    unset($form['rules']['mimes']['mimes']['delete'][$count]['#title']);

    return [

      '#type'     => 'ajax',
      '#commands' => [

        ajax_command_invoke(null, 'onAddMime', [

          $mime,
          drupal_render($form['rules']['mimes']['mimes']['delete'][$count]),
          drupal_render($form['rules']['mimes']['mimes']['hidden'][$count]),
          $form_state['rebuild']
        ]),
      ],
    ];
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
    //$owner = $form_state->getValue(['owner']);
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->id();
  }
}
