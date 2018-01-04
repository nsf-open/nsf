<?php

namespace Drupal\brightcove\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Database;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;

class CSRFCallbackAccessCheck implements AccessInterface {
  /**
   * Custom access callback.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   RouterMatch object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access allowed only if the token is exists and did not expired.
   */
  public function access(RouteMatchInterface $route_match) {
    $token = $route_match->getParameter('token');
    return AccessResult::allowedIf(\Drupal::keyValueExpirable('brightcove_callback')->has($token));
  }
}
