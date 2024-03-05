<?php

namespace Drupal\flat_deposit\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * MyModule theme negotiator for node editing.
 */
class ThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * Constructs a new ThemeNegotiator object.
   *
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Check if the route is for node editing ("node/{node}/edit").
    $route_name = $route_match->getRouteName();
    return $route_name === 'entity.node.edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return 'tla_bootstrap_sass';
  }
}