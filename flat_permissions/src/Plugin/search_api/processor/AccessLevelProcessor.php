<?php

namespace Drupal\flat_permissions\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;

/**
 * Provides a 'Access Level' processor.
 *
 * @SearchApiProcessor(
 *   id = "access_level_processor",
 *   label = @Translation("Access Level Processor"),
 *   description = @Translation("Transforms the read access policy into access levels for indexing."),
 *   stages = {
 *     "alter_items" = 0,
 *   },
 * )
 */
class AccessLevelProcessor extends ProcessorPluginBase {

    /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {

    return TRUE;

}


  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    foreach ($items as $item) {
              $field = $item->getField('field_read_access_policy');
            if ($field) {

              $this->processReadAccessPolicyField($field);

              $item->setField('field_read_access_policy', $field);

            }
          }

    }

  /**
   * Process the Read Access Policy field before it is indexed.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field to process.
   */
  protected function processReadAccessPolicyField(FieldInterface &$field) {
    foreach ($field->getValues() as &$value) {

    if (is_string($value)) {

        $modified_value = $this->get_access_levels($value);

        $modified_values[] = $modified_value;

      } else {

        $modified_values[] = $value;

      }

     $field->setValues($modified_values);
    }
  }

  /**
   * Function to retrieve the access levels from the access policy
   */
  protected function get_access_levels($policy) {

    $levels = [];

    $manager = \Drupal::service('flat_permissions.permissions_manager');

    $policy = json_decode($policy);

    if ($policy) {
        if (property_exists($policy, "all")) {
            $rule = $policy->all;
            if (property_exists($rule, "roles")) {
                $allowed_roles = $rule->roles;
                $levels[] = $manager->rolesToLevel($allowed_roles);
            } else {
                $levels[] = 'restricted';
            }
        }

        if (property_exists($policy, "types")) {
            $type_rules = $policy->types;
            foreach ($type_rules as $type_rule) {
                if (property_exists($type_rule, "roles")) {
                    $allowed_roles = $type_rule->roles;
                    $levels[] = $manager->rolesToLevel($allowed_roles);
                } else {
                    $levels[] = 'restricted';
                }
            }
        }

        if (property_exists($policy, "files")) {
            $files_rules = $policy->files;
            foreach ($files_rules as $file_rule) {
                if (property_exists($file_rule, "roles")) {
                    $allowed_roles = $file_rule->roles;
                    $levels[] = $manager->rolesToLevel($allowed_roles);
                } else {
                    $levels[] = 'restricted';
                }
            }
        }
    }

    $levels = array_unique($levels);

    $levels = implode(',', $levels);

    return $levels;

}
}