<?php

namespace Drupal\flat_permissions\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flat_permissions\NodeAccessService;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\QueryPluginBase;

/**
 * Custom filter to restrict access to nodes based on custom logic.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("flat_node_access_filter",
 *   id = "flat_node_access_filter",
 *   value = "FLAT Node Access Filter"
 * )
 */
class CustomNodeAccessFilter extends FilterPluginBase {
  protected $nodeAccessService;



  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->nodeAccessService = \Drupal::service('flat_permissions.node_access');

  }

  /**
   * {@inheritdoc}
   */
  protected function canBuildGroup() {
    return FALSE;
  }

  public function query() {
    // Ensure the query respects the access control.
    $configuration = [
      'table' => 'node_field_data',
      'field' => 'nid',
      'operator' => 'IN',
      'subquery' => $this->subquery(),
    ];

    $this->query->addWhere('AND', $configuration);
  }

  protected function subquery() {
    // Create a subquery that includes only nodes the user has access to.
    $current_user = \Drupal::currentUser();
    $nids = [];
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple();
    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface && $this->nodeAccessService->userHasAccess($node, 'view')) {
        $nids[] = $node->id();
      }
    }
    $subquery = $this->query->subquery('node_field_data', 'nfd')
      ->fields('nfd', ['nid'])
      ->condition('nfd.nid', $nids, 'IN');

    return $subquery;
  }

}
