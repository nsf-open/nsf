<?php

namespace Drupal\brightcove;

use Drupal\brightcove\Entity\BrightcoveAPIClient;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Brightcove Subscription entities.
 */
interface BrightcoveSubscriptionInterface extends ConfigEntityInterface {
  /**
   * The status of the Subscription.
   *
   * @return bool
   *   It always returns TRUE except for default, which can be FALSE.
   */
  public function isActive();

  /**
   * Whether the Subscription is default or not.
   *
   * @return bool
   *   If the current Subscription is default return TRUE, otherwise FALSE.
   */
  public function isDefault();

  /**
   * Returns the API Client ID.
   *
   * @return \Drupal\brightcove\Entity\BrightcoveAPIClient
   *   The API Client for this Subscription.
   */
  public function getAPIClient();

  /**
   * Returns the Subscription endpoint.
   *
   * @return string
   *   The endpoint for the Subscription.
   */
  public function getEndpoint();

  /**
   * Returns subscribed events.
   *
   * @return string[]
   *   Array of events subscribed to.
   */
  public function getEvents();

  /**
   * Sets the API Client ID.
   *
   * @param \Drupal\brightcove\Entity\BrightcoveAPIClient $api_client
   *   The API Client.
   *
   * @return $this
   */
  public function setAPIClient(BrightcoveAPIClient $api_client) ;

  /**
   * Set the endpoint for the subscription.
   *
   * @param string $endpoint
   *   The Subscription's endpoint.
   *
   * @return $this
   */
  public function setEndpoint($endpoint);

  /**
   * Sets the events for which we want to subscribe.
   *
   * @param string[] $events
   *   Array of events to subscribe to.
   *
   * @return $this
   */
  public function setEvents(array $events);
}