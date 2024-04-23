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
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\Request;

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

    $form['#markup'] = [
      '#theme' => 'flat_permissions_policy',
      '#data' => $policy,
    ];

    $form['rules'] = array(
      '#type' => 'container',
    );

      $form['rules']['all'] = [
        '#type' => 'fieldset',
      ];

      $form['rules']['all']['radio'] = [
        '#type' => 'radio',
        '#title' => 'Apply to all files',
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
        '#title' => 'Apply to specific file types',
        '#return_value' => 'mimes',
        '#name' => 'radio',
      ];

      $form['rules']['mimes']['mimes'] = $this->build_mimes_fieldset($form_state);


      $form['rules']['mimes']['level'] = [
        '#type' => 'select',
        '#title' => t('Access level'),
        '#options' => $manager::LEVELS,
        '#name' => 'mimes_level',
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'mimes'],
          ],
        ],
      ];

      $form['rules']['mimes']['users'] = [
        '#type' => 'textfield',
        '#title' => t('Specific users'),
        '#states' => [
          'visible' => [
            [':input[name="mimes_level"]' => ['value' => 'none']],
            [':input[name="mimes_level"]' => ['value' => 'academic']],
          ],
        ],
      ];

      $form['rules']['files'] = [
        '#type' => 'fieldset',
      ];

      $form['rules']['files']['radio'] = [
        '#type' => 'radio',
        '#title' => 'Apply to specific files',
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


/*     $manager = \Drupal::service('flat_permissions.permissions_manager');

    $form['#validate']              = ['flat_permissions_form_validate'];
    $form['#group']                 = $manager->determineReadGroup();
    $form['#is_management_allowed'] = $isManagementAllowed;

    $form['visibility']             = build_visibility_fieldset($manager->getVisibility());
    $form['mimes']                  = build_mimes_fieldset($form_state, $object->id, $availableMimes, $manager->datastreamEnabled());
    $form['read_group']             = build_read_group_fieldset($form['#group']);
    $form['read_users']             = build_users_fieldset($form_state, 'read', $manager->getReadUsers());

    if ($isManagementAllowed) {
        $form['management_users']   = build_users_fieldset($form_state, 'management', $manager->getManagementUsers());
    } */

    return $form;
  }

  /**
 * Mimes fieldset
 *
 * @param array  $form_state
 * @param string $pid
 * @param array  $mimes
 * @param array  $availableMimes
 * @param bool   $enabled
 *
 * @return array
 */
private function build_mimes_fieldset(&$form_state) {

  $mimes = [];
/*   if (count($form_state->get('mimes')->value()) > 0) {
      $mimes = array_merge($mimes, $form_state['mimes']);
  } */

/*   $availableMimes = array_map(function($mime) {
      return ['field' => $mime, 'label' => $mime];
  }, $availableMimes); */

  $database = \Drupal::database();
  $query = $database->select('media__field_mime_type', 'mfmt');
  $query->addField('mfmt', 'field_mime_type_value');
  $query->distinct(TRUE);
  $allmimes = $query->execute()->fetchAllKeyed(0, 0);

  $field = [

      '#tree'       => true,
      '#type'       => 'select',
      '#options' => $allmimes,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'mimes'],
        ],
      ],
  ];

/*   $fieldset = [

      '#tree'        => true,
      '#type'        => 'container',
      'delete'       => build_delete_mimes_fieldset($mimes),
      'hidden'       => build_hidden_mimes_fieldset($mimes),
      'enabled'      => build_enabled_mimes_fieldset($mimes, $enabled),
      'autocomplete' => build_static_autocomplete_fieldset(

          $availableMimes,
          'Add mime type',
          'add_mime',
          'flat_permissions_form_add_mime_submit',
          'flat_permissions_form_add_mime_validate',
          'flat_permissions_form_add_mime_js'
      ),
  ]; */

  return $field;
}

/**
* @param array $mimes
*
* @return array
*/
private function build_hidden_mimes_fieldset($mimes) {

  $fieldset = [

      '#tree'       => true,
      '#type'       => 'container',
      '#attributes' => [
          'data-role' => 'hidden-mimes',
      ],
  ];

  foreach ($mimes as $key => $mime) {

      $fieldset[$key + 1] = [

          '#type'       => 'hidden',
          '#value'      => $mime,
          '#attributes' => [
              'data-mime' => $mime,
          ],
      ];
  }

  return $fieldset;
}

/**
* Deleted mimes fieldset
*
* @param string $mimes
*
* @return array
*/
private function build_delete_mimes_fieldset($mimes) {

  $fieldset = [];

  $i = 1;

  foreach ($mimes as $mime) {

      $fieldset[$i] = [

          '#type'         => 'checkbox',
          '#title'        => $mime,
          '#return_value' => $mime,
      ];

      $i += 1;
  }

  return $fieldset;
}

/**
* @param array   $mimes
* @param boolean $enabled
*
* @return boolean
*/
private function build_enabled_mimes_fieldset(array $mimes, $enabled) {

  return [

      '#type'          => 'checkbox',
      '#default_value' => (count($mimes) > 0 ? $enabled : false),
      '#title'         => t('Only apply read permissions to certain file types'),
  ];
}

/**
* Read group fieldset
*
* @param string $currentGroup
*
* @return array
*/
private function build_read_group_fieldset($currentGroup) {

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
private function build_visibility_fieldset($visibility) {

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
private function build_users_fieldset(&$form_state, $type, $users) {

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
* @param array  $results
* @param string $title
* @param string $name
* @param string $submit
* @param string $validation
* @param string $ajax
*
* @return array
*/
private function build_static_autocomplete_fieldset($results, $title, $name, $submit, $validation, $ajax) {

  return [

      '#type'  => 'fieldset',
      '#title' => $title,
      'field'  => [
          'input' => [

              '#type'       => 'textfield',
              '#prefix'     => '<div class="input-group form-autocomplete">',
              '#suffix'     => '<span class="input-group-addon"><span class="icon glyphicon glyphicon-refresh"></span></span></div>',
              '#attributes' => [

                  'data-role'    => 'static-autocomplete',
                  'data-results' => drupal_json_encode($results),
              ],
          ],
      ],
      'button' => [

          '#type'     => 'submit',
          '#prefix'   => '<div class="mt-1">',
          '#suffix'   => '</div>',
          '#validate' => [$validation],
          '#name'     => $name,
          '#value'    => t($title),
          '#submit'   => [$submit],
          '#ajax'     => [
              'callback' => $ajax,
          ],
      ],
  ];
}

/**
* Autocomplete fieldset
*
* @param string $autocomplete
* @param string $title
* @param string $name
* @param string $submit
* @param string $validation
* @param string $ajax
*
* @return array
*/
private function build_autocomplete_fieldset($autocomplete, $title, $name, $submit, $validation, $ajax) {

  return [

      '#type'  => 'fieldset',
      '#title' => t($title),
      'field'  => [

          '#type'              => 'textfield',
          '#autocomplete_path' => $autocomplete,
      ],
      'button' => [

          '#type'     => 'submit',
          '#validate' => [$validation],
          '#name'     => $name,
          '#value'    => t($title),
          '#submit'   => [$submit],
          '#ajax'     => [
              'callback' => $ajax,
          ],
      ],
  ];
}

/**
* Deleted users fieldset
*
* @param string $type
* @param array  $users
*
* @return array
*/
private function build_delete_users_fieldset($type, $users) {

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
private function build_hidden_users_fieldset($type, $users) {

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
* @param array  $form_state
* @param string $type (usually read/management)
*/
private function flat_permissions_form_add_user_submit($form, &$form_state, $type) {

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
* @param array $form_state
*/
private function flat_permissions_form_add_read_user_submit($form, &$form_state) {
  flat_permissions_form_add_user_submit($form, $form_state, 'read');
}

/**
* Concrete implementation of management users submit handler
*
* @param array $form
* @param array $form_state
*/
private function flat_permissions_form_add_management_user_submit($form, &$form_state) {
  return flat_permissions_form_add_user_submit($form, $form_state, 'management');
}

/**
* Abstract response handler ajax for adidng user
*
* @param array  $form
* @param array  $form_state
* @param string $type (usually read/management)
*
* @return array
*/
private function flat_permissions_form_add_user_js($form, &$form_state, $type) {

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
* @param array $form_state
*/
private function flat_permissions_form_add_read_user_js($form, &$form_state) {
  return flat_permissions_form_add_user_js($form, $form_state, 'read');
}

/**
* Concrete implementation of management users ajax response
* it calls the jQuery.fn.onAddUser method to add new line
* in the users table
*
* @param array $form
* @param array $form_state
*/
private function flat_permissions_form_add_management_user_js($form, &$form_state) {
  return flat_permissions_form_add_user_js($form, $form_state, 'management');
}

/**
* @param array $form
* @param array $form_state
* @param array $mimes
*/
private function flat_permissions_form_add_mime_submit(&$form, &$form_state) {

  if (isset($form_state['values']['add_mime'])) {

      // if checkbox is checked, it has a string, otherwise value is 0,
      // so let's filter by is_string
      $mimes        = [];
      $removedMimes = array_filter($form_state['values']['mimes']['delete'], 'is_string');

      foreach ($form['mimes']['delete'] as $field) {

          if ($field['#type'] === 'checkbox' && !in_array($field['#return_value'], $removedMimes)) {
              $mimes[] = $field['#return_value'];
          }
      }

      foreach ($form_state['mimes'] as $mime) {

          if (!in_array($mime, $removedMimes)) {
              $mimes[] = $mime;
          }
      }

      if (!in_array($form_state['values']['mimes']['autocomplete']['field']['input'], $mimes)) {

          $form_state['rebuild'] = true;
          $form_state['mimes'][] = $form_state['values']['mimes']['autocomplete']['field']['input'];
          $form_state['mimes']   = array_unique($form_state['mimes']);
      }
  }
}

/**
* @param array $form
* @param array $form_state
*/
private function flat_permissions_form_add_mime_validate(&$form, &$form_state) {}

/**
* @param array $form
* @param array $form_state
*
* @return array
*/
private function flat_permissions_form_add_mime_js(&$form, &$form_state) {

  $count = 0;
  $mime  = '';

  foreach ($form['mimes']['delete'] as $field) {

      if ($field['#type'] === 'checkbox') {

          $count += 1;
          $mime   = $field['#return_value'];
      }
  }

  unset($form['mimes']['delete'][$count]['#title']);

  return [

      '#type'     => 'ajax',
      '#commands' => [

          ajax_command_invoke(null, 'onAddMime', [

              $mime,
              drupal_render($form['mimes']['delete'][$count]),
              drupal_render($form['mimes']['hidden'][$count]),
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
    $owner = $form_state->getValue(['owner']);
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->id();
    $title = $node->get('title')->value;

    $new_file = File::create([
      'filename' => 'record.cmdi',
      'uri' => $form_state->getValue('cmdiFile'),
      'status' => 1,
      'filemime' => 'application/x-cmdi+xml',
      'display' => '1',
      'description' => '',
    ]);

    $new_file->setPermanent();
    $new_file->save();
    $new_file_id = $new_file->id();

    // for some unknown reason flat_location and flat_original_path are messed up by attaching the newly created cmdi file, so we need to restore it
    // TODO check if still needed
    //$flat_location_original = $node->flat_location->value();
    //$flat_original_path_original = $node->flat_original_path->value();

    $node->set('flat_cmdi_file', ['target_id' => $new_file_id]);
    $node->save();

    \Drupal::messenger()->addMessage(t('Metadata for bundle %title has been saved', [
      '%title' => $title
    ]));

  }
}
