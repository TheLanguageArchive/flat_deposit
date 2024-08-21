<?php

namespace Drupal\flat_permissions\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Index\Token;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Plugin\search_api\data_type\value\TextToken;

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
class AccessLevelProcessor extends ProcessorPluginBase
{

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index)
  {

    return TRUE;
  }


  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items)
  {

    ddm('alterIndexedItems');
    foreach ($items as $item) {
      $field = $item->getField('field_read_access_policy');
      if ($field) {

        $this->processReadAccessPolicyField($field);

        $item->setField('field_read_access_policy', $field);
      }
    }
  }

  /**
   * Process the Read Access Policy field before it is indexed. The field needs to be configured 
   * in the search api coniguration as a "Fulltext Tokens" field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field to process.
   */
  protected function processReadAccessPolicyField(FieldInterface &$field)
  {

    foreach ($field->getValues() as &$value) {

      if (is_object($value)) {

        $modified_value = $this->get_access_levels($value);

        $value->setTokens($modified_value);

        $modified_values[] = $value;
      } else {

        $modified_values[] = $value;
      }

      $field->setValues($modified_values);
    }
  }

  /**
   * Function to retrieve the access levels from the access policy
   */
  protected function get_access_levels($policy)
  {

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
          $levels[] = 'Restricted';
        }
      }

      if (property_exists($policy, "types")) {
        $type_rules = $policy->types;
        foreach ($type_rules as $type_rule) {
          if (property_exists($type_rule, "roles")) {
            $allowed_roles = $type_rule->roles;
            $levels[] = $manager->rolesToLevel($allowed_roles);
          } else {
            $levels[] = 'Restricted';
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
            $levels[] = 'Restricted';
          }
        }
      }
    }

    $levels = array_unique($levels);

    foreach ($levels as $level) {
      // Create a new TextToken object for each level.
      $level_tokens[] = new TextToken($level, 1);
    }

    return $level_tokens;
  }
}
