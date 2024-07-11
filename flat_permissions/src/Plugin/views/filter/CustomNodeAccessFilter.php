<?php

namespace Drupal\flat_permissions\Plugin\views\filter;

use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom filter to restrict access to nodes based on custom logic.
 *
 * @ViewsFilter("custom_node_access_filter")
 *
 * @ingroup views_filter_handlers
 *
 */
class CustomNodeAccessFilter extends FilterPluginBase
{

  /**
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * @var \Drupal\flat_permissions\NodeAccessService
   */
  protected $nodeAccessService;

  public function __construct(array $configuration, $plugin_id, $plugin_definition)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = \Drupal::entityTypeManager();
    $this->nodeAccessService = \Drupal::service('flat_permissions.node_access');
  }

  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL)
  {
    parent::init($view, $display, $options);
    $this->definition['title'] = t('FLAT Node Access Filter');
  }

  /**
   * {@inheritdoc}
   */
  public function query()
  {
    // Add a placeholder condition.
    $this->query->addWhereExpression(0, "1 = 1");
  }

  /**
   * Remove nodes that the user isn't allowed to view
   *
   * @param array $values
   *
   */
  public function postExecute(&$values)
  {

    $current_user = \Drupal::currentUser();

    foreach ($values as $index => $row) {
      $node = Node::load($row->nid);
      if ($node && !$this->nodeAccessService->userHasNodeAccess($node, 'view', $current_user)) {
        unset($values[$index]);
      }
    }
  }
}
