<?php

namespace Drupal\custom_facets\Plugin\Search;

use Drupal\facets\Plugin\facets\query\QueryProcessorPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a custom query processor to handle include/exclude facets.
 *
 * @SearchQueryProcessor(
 *   id = "custom_facets_query_processor",
 *   label = @Translation("Custom Facets Query Processor"),
 *   description = @Translation("Handles custom facet queries with include/exclude functionality."),
 *   stages = {
 *     "facets" = 100,
 *   }
 * )
 */
class CustomFacetsQueryProcessor extends QueryProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function processQuery(array &$query, array $facet_values) {
    // Process inclusion and exclusion values.
    $included = [];
    $excluded = [];

    // The key is expected to be the facet name that corresponds to a field.
    foreach ($facet_values as $facet_name => $values) {
      foreach ($values as $value => $options) {
        if (!empty($options['include'])) {
          $included[] = $value;
        }
        if (!empty($options['exclude'])) {
          $excluded[] = $value;
        }
      }
    }

    // Modify the query based on included and excluded values.
    if (!empty($included)) {
      // This adds an IN condition for the included values.
      $query->addCondition($facet_name, $included, 'IN');
    }
    if (!empty($excluded)) {
      // This adds a NOT IN condition for the excluded values.
      $query->addCondition($facet_name, $excluded, 'NOT IN');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Here you can provide configuration options for your query processor if needed.
    return $form;
  }
}
