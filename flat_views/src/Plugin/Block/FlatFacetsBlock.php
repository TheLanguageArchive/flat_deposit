<?php

namespace Drupal\flat_views\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a 'FlatFacetsBlock' block.
 *
 * @Block(
 *   id = "flat_facets_block",
 *   admin_label = @Translation("FLAT Facets Block"),
 * )
 */
class FlatFacetsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new MyFacetsBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Get the node from the current route.
    $node = $this->routeMatch->getParameter('node');

    // Get the current path.
    $current_path = \Drupal::service('path.current')->getPath();

    // Check if the current page is the search results page or a node of the desired content type.
    if ($current_path === '/search' || ($node instanceof \Drupal\node\NodeInterface && $node->bundle() === 'islandora_object')) {
      // Load and render multiple facet blocks programmatically.
      $block_manager = \Drupal::service('plugin.manager.block');

      // List of facet block IDs to render. Replace these with your actual facet block IDs.
      $facet_block_ids = [
        'facet_block:read_access_policy_search',
      ];

    // Loop through the facet block IDs and add their rendered output to the build array.
    foreach ($facet_block_ids as $facet_block_id) {
        $plugin_block = $block_manager->createInstance($facet_block_id);

        // Check if we are on a non-search page and force the facet source context if necessary.
        if ($current_path !== '/search') {
          // Simulate a search context for non-search pages.
          // The actual implementation depends on the facet source (like Solr or Views) and may require additional parameters.
          $facet_source = \Drupal::service('facets.manager')->getFacetSource('search_api:views_page__solr_search_content');

          // Attach the source to the plugin.
          $plugin_block->setContextValue('facet_source', $facet_source);
        }

        $block_content = $plugin_block->build();
        $build[] = $block_content;
      }

      return $build;
    }
  }
}
