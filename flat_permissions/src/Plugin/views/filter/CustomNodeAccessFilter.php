<?php

namespace Drupal\flat_permissions\Plugin\views\filter;

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
   * @var \flat_permissions\NodeAccessService
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
    $this->definition['title'] = t('FLAT Access Filter');
  }

  private function getFilterId()
  {
    return $this->options['expose']['identifier'];
  }

  public function postExecute(&$values)
  {
    $current_user = \Drupal::currentUser();
    ddm($current_user);
    foreach ($values as &$value) {
      $node = $this->entityManager->getStorage('node')->load($value->_entity_id);
      if (!$this->nodeAccessService->userHasAccess($node, 'view', $current_user)) {
        // If the user doesn't have access, remove the value from the result
        $value->_views_skip_result = TRUE;
      }
    }
  }
}
