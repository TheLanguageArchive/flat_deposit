<?php

namespace Drupal\flat_permissions;

use stdClass;

class PermissionsManager
{
    /** @var string */
    const ROLE_ANONYMOUS     = 'anonymous user';

    /** @var string */
    const ROLE_AUTHENTICATED = 'authenticated user';

    /** @var stirng */
    const ROLE_ACADEMIC      = 'academic user';

    /** @var string */
    const ROLE_SPECIFIC      = 'specific';

    /** @var string */
    const ROLE_MANAGER       = 'manager';

    /** @var string */
    const ROLE_ADMINISTRATOR = 'administrator';

    /** @var array */
    const DEFAULT_USERS      = ['fedoraAdmin', 'admin'];

    /** @var array */
    const DEFAULT_GROUPS     = ['administrator'];

    /** @var array */
    const LEVELS      = ['anonymous' => 'Open', 'authenticated' => 'Registered Users', 'academic' => 'Academic Users', 'none' => 'Restricted'];

    /** @var array */
    const TYPES     = ['audio' => 'Audio', 'video' => 'Video', 'image' => 'Images', 'text'  => 'Written/Annotations', 'other' => 'Other'];

    /** @var array */
    const WRITTEN_MIMETYPES = ['text/plain', 'application/pdf', 'text/html', 'application/xml'];

    /** Fetch the access policy for the given node, or go up the hierarchy if it doesn't have one
     *
     * @param string $nid
     * @return object | null
     *      access policy object
     */
    public function fetchEffectiveAccessPolicy($nid, $class): ?array
    {
        if ($class === 'read') {
            $field = 'field_read_access_policy';
        } elseif ($class === 'write') {
            $field = 'field_write_access_policy';
        }

        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);

        if ($node && $node->hasField($field)) {
            $accessPolicyField = $node->get($field);
            if ($accessPolicyField->value) {
                $policy_json = $accessPolicyField->value;
                return ['nid' => $nid,  'policy' => json_decode($policy_json)];
            }
        }
        // If node doesn't have an access policy, go up the hierarchy
        if ($node && $node->hasField('field_member_of')) {
            $parentNodes = $node->get('field_member_of')->referencedEntities();
            if ($parentNodes) {
                $policy = $this->fetchEffectiveAccessPolicy($parentNodes[0]->id(), $class);
                if ($policy) {
                    return $policy;
                }
            }
        }

        return null;
    }


    /** Fetch the access policy for the given node
     *
     * @param string $nid
     * @return object | null
     *      access policy object
     */
    public function fetchAccessPolicy($nid, $class): ?stdClass
    {
        if ($class === 'read') {
            $field = 'field_read_access_policy';
        } elseif ($class === 'write') {
            $field = 'field_write_access_policy';
        }

        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);

        if ($node && $node->hasField($field)) {
            $accessPolicyField = $node->get($field);
            if ($accessPolicyField->value) {
                $policy_json = $accessPolicyField->value;
                return json_decode($policy_json);
            }
        }

        return null;
    }

    public function hasChildrenWithPolicies($nid, $class)
    {

        if ($class === 'read') {
            $field = 'field_read_access_policy';
        } elseif ($class === 'write') {
            $field = 'field_write_access_policy';
        }

        // TODO, implement check whether any of the children have an access policy, to display a warning.
        // Best to do this once indexing of the collection hierarchy is implemented, otherwise
        // this is a very expensive operation for large collections

    }

    /**
     * Interpret roles and add access level to the policy (for display purposes only)
     *
     * @param object $policy
     * @return object
     */
    public function addLevels($policy)
    {
        $rules = [];
        if (property_exists($policy, 'all')) {
            $rules[] = $policy->all;
        };
        if (property_exists($policy, 'types')) {
            $rules = $policy->types;
        };
        if (property_exists($policy, 'files')) {
            $rules = $policy->files;
        };
        foreach ($rules as $rule) {
            if (property_exists($rule, 'roles')) {
                if (in_array('anonymous', $rule->roles)) {
                    $rule->effective_role = 'anonymous';
                    $rule->level = $this::LEVELS['anonymous'];
                } elseif (in_array('authenticated', $rule->roles)) {
                    $rule->effective_role = 'authenticated';
                    $rule->level = $this::LEVELS['authenticated'];
                } elseif (in_array('academic', $rule->roles)) {
                    $rule->effective_role = 'academic';
                    $rule->level = $this::LEVELS['academic'];
                } else {
                    $rule->effective_role = 'none';
                    $rule->level = $this::LEVELS['none'];
                };
            } else {
                $rule->effective_role = 'none';
                $rule->level = 'Restricted';
            }
        }
        return $policy;
    }

    public function sortByEffectiveRole($policy)
    {
        if (property_exists($policy, 'all')) {
            return ($policy);
        }
        $key = array_keys((array)$policy)[0];
        if (!array_key_exists('effective_role', (array)$policy->{$key}[0])) {
            return ($policy);
        } else {
            usort($policy->{$key}, array($this, 'compareEffectiveRoles'));
            return ($policy);
        }
    }

    public function compareEffectiveRoles($a, $b)
    {
        $roleOrder = array_keys($this::LEVELS);

        $orderA = array_search($a->effective_role, $roleOrder);
        $orderB = array_search($b->effective_role, $roleOrder);

        $orderA = ($orderA !== false) ? $orderA : PHP_INT_MAX;
        $orderB = ($orderB !== false) ? $orderB : PHP_INT_MAX;

        if ($orderA == $orderB) {
            return 0;
        }
        return ($orderA < $orderB) ? -1 : 1;
    }

    /**
     * Gets all "original file" media entities linked to a given node ID.
     *
     * @param int $nid
     *   The node ID.
     *
     * @return \Drupal\media\MediaInterface[]
     *   An array of media entities.
     */
    public function getMediaEntitiesByNodeId($nid)
    {
        $entity_type_manager = \Drupal::entityTypeManager();
        $entity_query = \Drupal::entityQuery('media');

        $query = $entity_query->condition('field_media_of', $nid)
            ->accessCheck(TRUE);

        $mids = $query->execute();

        $media_entities = $entity_type_manager->getStorage('media')->loadMultiple($mids);

        return $media_entities;
    }

    public function fieldsetToRule($fieldset)
    {
        $rule = [];
        $level = $fieldset['level'];
        $roles = $this->levelToRoles($level);
        $rule['roles'] = $roles;
        if ($level === 'academic' || $level === 'none') {
            if (array_key_exists('hidden-users', $fieldset)) {
                $rule['users'] = $fieldset['hidden-users'];
            }
        }
        if (array_key_exists('filetypes', $fieldset)) {
            if (!empty($fieldset['filetypes'])) {
                $rule['filetypes'] = array_keys(array_filter($fieldset['filetypes']));
            }
        }
        if (array_key_exists('hidden-mimetypes', $fieldset)) {
            $rule['mimetypes'] = $fieldset['hidden-mimetypes'];
        }
        if (array_key_exists('files', $fieldset)) {
            $rule['files'] = $fieldset['files'];
        }
        if (array_key_exists('visible', $fieldset)) {
            $rule['visible'] = $fieldset['visible'];
        }
        return $rule;
    }

    public function levelToRoles($level)
    {
        if ($level === 'anonymous') {
            return ['anonymous', 'authenticated'];
        } elseif ($level === 'authenticated') {
            return ['authenticated'];
        } elseif ($level === 'academic') {
            return ['academic'];
        } elseif ($level === 'none') {
            return ['none'];
        }
    }

    /**
     * Finds duplicate values in multiple arrays.
     *
     * This function takes an array of arrays as input and returns an array of values
     * that occur more than once in any of the input arrays. It iterates over each
     * array, removes duplicate values, and keeps track of the count of each value.
     * Finally, it filters out values that occur only once and returns the remaining
     * values.
     *
     * @param array[] $arrays The array of arrays to search for duplicate values.
     * @return array The array of duplicate values.
     */
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

    private function findMimeAndTypeOverlaps($mimes_array, $type_arrays)
    {
        $matches = [];
        foreach ($mimes_array as $mime) {
            $type = $this->mimeToType($mime);
            foreach ($type_arrays as $type_array) {
                if (in_array($type, $type_array)) {
                    $matches[$type][] = $mime;
                    break;
                }
            }
        }

        return $matches;
    }

    private function mimeToType(string $mimetype)
    {
        $manager = \Drupal::service('flat_permissions.permissions_manager');

        $type = explode('/', $mimetype)[0];
        if (in_array($type, ['audio', 'video', 'image'])) {
            return $type;
        } elseif (in_array($mimetype, $manager::WRITTEN_MIMETYPES)) {
            return 'text';
        } else {
            return 'other';
        }
    }


    /**
     * Finds values occurring more than once in the given array.
     *
     * @param array $array The input array to search for duplicate values.
     * @return array The array of values that occur more than once.
     */
    private function findValuesOccurringMoreThanOnce($array)
    {

        $valueCounts = array_count_values($array);
        $multipleValues = array_filter($valueCounts, function ($count) {
            return $count > 1;
        });
        $valuesWithMultipleOccurrences = array_keys($multipleValues);

        return $valuesWithMultipleOccurrences;
    }

    /**
     * Checks if any of the checkboxes in the given array have a non-empty value.
     *
     * @param array $checkboxes_values An array of checkbox values.
     * @return bool Returns TRUE if any of the checkboxes have a non-empty value, FALSE otherwise.
     */
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
     * Helper function that checks if the given object and its nested properties exist.
     *
     * @param mixed $object The object to check.
     * @param string $propertyPath The property path to check, separated by '->'.
     * @return bool Returns true if the object and all its nested properties exist, false otherwise.
     */
    public function objectAndPropertiesExist($object, $propertyPath)
    {
        // Check if the initial object itself is null
        if ($object === null) {
            return false;
        }

        // Split the property path into an array of properties
        $properties = explode('->', $propertyPath);

        foreach ($properties as $property) {
            // If the object is null or the property does not exist, return false
            if ($object === null || !is_object($object) || !property_exists($object, $property)) {
                return false;
            }
            // Move to the next nested property
            $object = $object->$property;
        }

        return true;
    }

    public function storeAccessPolicy($nid, $policy, $class)
    {
        if ($class === 'read') {
            $field = 'field_read_access_policy';
        } elseif ($class === 'write') {
            $field = 'field_write_access_policy';
        }
        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);
        $node->set($field, $policy);
        $node->save();
    }

    public function deleteAccessPolicy($nid, $class)
    {
        if ($class === 'read') {
            $field = 'field_read_access_policy';
        } elseif ($class === 'write') {
            $field = 'field_write_access_policy';
        }
        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);
        if ($node && $node->hasField($field)) {
            $node->set($field, NULL);
        }
        $node->save();
    }
}
