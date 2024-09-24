<?php

namespace Drupal\flat_views\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;

/**
 * Provides a 'FlatFacetsBlock' block.
 *
 * @Block(
 *   id = "flat_facets_block",
 *   admin_label = @Translation("FLAT Facets Block"),
 * )
 */
class FlatFacetsBlock extends BlockBase implements ContainerFactoryPluginInterface
{

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
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
  public function build()
  {

    dpm('build called');

    $build = [];

    // Get the current path.
    $current_path = \Drupal::service('path.current')->getPath();

    // Load the facet entity by its machine name.
    $facet_storage = \Drupal::entityTypeManager()->getStorage('facets_facet');

    // List of facet machine names.
    $facet_machine_names = ['descendant_of'];

    foreach ($facet_machine_names as $facet_name) {
      // Load the facet entity.
      $facet = $facet_storage->load($facet_name);

      //dpm($facet);

      if ($facet instanceof FacetInterface) {
        // Get the facet source.
        $facet_source = $facet->getFacetSource();

        //dpm($facet_source);

        // Ensure facet source context is available.
        if ($facet_source && ($current_path === '/search' || $this->isNodeOfType('islandora_object'))) {
          // Render the facet block.
          $facet_block_id = 'facet_block:' . $facet_name;
          $block_manager = \Drupal::service('plugin.manager.block');
          //dpm($block_manager);

          $block_definitions = \Drupal::service('plugin.manager.block')->getDefinitions();

          //dpm($block_definitions);
          // Loop through block definitions and log only facet-related ones.
          foreach ($block_definitions as $block_id => $definition) {
            if (strpos($block_id, 'facets_block:') !== FALSE) {
              \Drupal::logger('flat_views')->debug('Facet Block ID: @id', ['@id' => $block_id]);
            }
          }
          // Ensure that the block exists and can be instantiated.
          if ($block_manager->hasDefinition($facet_block_id)) {
            dpm("has defnition");
            $plugin_block = $block_manager->createInstance($facet_block_id);
            $block_content = $plugin_block->build();

            // Add the block content to the build array.
            $build[] = $block_content;
          } else {
            // Log an error if the block definition is not found.
            \Drupal::logger('flat_views')->error('The facet block "@id" was not found.', ['@id' => $facet_block_id]);
          }
        } else {
          // Log an error if the facet was not loaded correctly.
          \Drupal::logger('flat_views')->error('Facet with machine name "@name" was not found.', ['@name' => 'read_access_policy_search']);
        }
      }
    }

    return $build;
  }

  /**
   * Helper function to check if the current route is a node of a specific content type.
   *
   * @param string $content_type
   *   The content type machine name.
   *
   * @return bool
   *   TRUE if the current page is a node of the given content type.
   */
  protected function isNodeOfType($content_type)
  {
    $node = $this->routeMatch->getParameter('node');
    return $node instanceof \Drupal\node\NodeInterface && $node->bundle() === $content_type;
  }
}
