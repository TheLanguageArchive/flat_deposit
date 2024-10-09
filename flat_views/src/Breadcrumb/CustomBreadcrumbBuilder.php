<?php

namespace Drupal\flat_views\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Link;
use Drupal\facets\FacetInterface;
use Drupal\facets\Entity\Facet;


class CustomBreadcrumbBuilder implements BreadcrumbBuilderInterface
{

    /**
     * The current route match.
     *
     * @var \Drupal\Core\Routing\RouteMatchInterface
     */
    protected $routeMatch;

    /**
     * The entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * The current path.
     *
     * @var \Drupal\Core\Path\CurrentPathStack
     */
    protected $currentPath;

    /**
     * Constructs a CustomBreadcrumbBuilder object.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The current route match.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     * @param \Drupal\Core\Path\CurrentPathStack $current_path
     *   The current path stack.
     */
    public function __construct(RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, CurrentPathStack $current_path)
    {
        $this->routeMatch = $route_match;
        $this->entityTypeManager = $entity_type_manager;
        $this->currentPath = $current_path;
    }

    /**
     * {@inheritdoc}
     */
    public function applies(RouteMatchInterface $route_match)
    {
        $route_name = $route_match->getRouteName();

        if ($route_name === 'view.solr_search_content.page_1') {
            return TRUE;
        }

        return FALSE;
    }


    /**
     * Builds the breadcrumb based on selected facet values. Based on facets_system_breadcrumb_alter from
     * the facets module
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The current route match.
     *
     * @return \Drupal\Core\Breadcrumb\Breadcrumb
     *   The breadcrumb, with the links and cacheability metadata set.
     */
    public function build(RouteMatchInterface $route_match)
    {

        $breadcrumb = new Breadcrumb();
        $breadcrumb->addCacheContexts(['url.path', 'route']);
        $breadcrumb->addCacheTags(['node_list']);

        // Start with the Home link.
        $breadcrumb->addLink(\Drupal\Core\Link::createFromRoute(t('Home'), '<front>'));

        /** @var \Drupal\facets\FacetSource\FacetSourcePluginManager $facet_source_manager */
        $facet_source_manager = \Drupal::service('plugin.manager.facets.facet_source');

        /** @var \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager */
        $facet_manager = \Drupal::service('facets.manager');

        /** @var \Drupal\Core\Entity\EntityTypeManager $entity_type_manager */
        $entity_type_manager = \Drupal::service('entity_type.manager');

        /** @var \Drupal\Core\Entity\EntityStorageInterface $facet_source_storage */
        $facet_source_storage = $entity_type_manager->getStorage('facets_facet_source');

        $facet_sources_definitions = $facet_source_manager->getDefinitions();

        $facets_url_generator = \Drupal::service('facets.utility.url_generator');

        // No facet sources found, so don't do anything.
        if (empty($facet_sources_definitions)) {
            return $breadcrumb;
        }

        $facet_source_id = 'search_api:views_page__solr_search_content__page_1';

        /** @var \Drupal\facets\FacetSource\FacetSourcePluginBase $facet_source_plugin */

        $facet_source_plugin = $facet_source_manager->createInstance($facet_source_id);

        $source_id = str_replace(':', '__', $facet_source_id);
        /** @var \Drupal\facets\FacetSourceInterface $facet_source */
        $facet_source = $facet_source_storage->load($source_id);

        // Add the required cacheability metadata.
        $breadcrumb->addCacheContexts(['url']);
        $breadcrumb->addCacheableDependency($facet_source);


        // Process the facets if they are not already processed.
        $facet_manager->processFacets($facet_source_id);
        $facets = $facet_manager->getFacetsByFacetSourceId($facet_source_id);

        // Sort facets by weight.
        uasort($facets, function (FacetInterface $a, FacetInterface $b) {
            return (int) $a->getWeight() - $b->getWeight();
        });

        /** @var \Drupal\facets\UrlProcessor\UrlProcessorPluginManager $url_processor_manager */
        $url_processor_manager = \Drupal::service('plugin.manager.facets.url_processor');

        // Get active facets and results to use them at building the crumbs.
        $active_results = [];
        $active_facets = [];
        foreach ($facets as $facet) {
            if (count($facet->getActiveItems()) > 0) {
                // Add the facet as a cacheable dependency.
                $breadcrumb->addCacheableDependency($facet);

                /** @var \Drupal\facets\UrlProcessor\UrlProcessorInterface $url_processor */
                $url_processor = $url_processor_manager->createInstance($facet_source->getUrlProcessorName(), ['facet' => $facet]);
                $facet_manager->build($facet);

                //dpm($facet->getResults());

                $results = $facet->getResults();

                foreach ($results as $result) {

                    $cloned_result = clone $result;

                    if (in_array($cloned_result->getRawValue(), $cloned_result->getFacet()->getActiveItems())) {
                        $cloned_result->setActiveState(TRUE);
                        $active_results[$facet->id()][] = $cloned_result;

                        $active_facets[$facet->id()] = $facet;
                    }
                }
            }
        }

        //dpm($active_results);

        /** @var \Drupal\facets\Result\ResultInterface[] $facet_results */
        foreach ($active_results as $facet_id => $facet_results) {
            $facet_used_result[$facet_id] = [];
            $facet_crumb_items = [];

            foreach ($facet_results as $res) {
                //dpm($res->getRawValue());
                $facet_used_result[$facet_id][] = $res->getRawValue();
                $facet_crumb_items = $res->getFacet()->getActiveItems();
            }

            sort($facet_crumb_items);

            foreach ($facet_crumb_items as $facet_crumb_item) {

                $facet_used_result[$facet_id] = [$facet_crumb_item];

                $facet_url = $facets_url_generator->getUrl($facet_used_result, FALSE);

                $options = $facet_url->getOptions();

                if (str_contains($facet_id, '_include')) {
                    $options['attributes']['class'][] = 'breadcrumb-include';
                } elseif (str_contains($facet_id, '_exclude')) {
                    $options['attributes']['class'][] = 'breadcrumb-exclude';
                }

                $facet_url->setOptions($options);

                if (!empty($facet_source->getBreadcrumbSettings()['before'])) {
                    $crumb_text = $active_facets[$facet_id]->label() . ': ' . $facet_crumb_item;
                } else {
                    $crumb_text = $facet_crumb_item;
                };
                $link = Link::fromTextAndUrl($crumb_text, $facet_url);

                $breadcrumb->addLink($link);
            }
        }

        return $breadcrumb;
    }
}
