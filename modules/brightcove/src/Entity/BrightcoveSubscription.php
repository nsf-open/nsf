<?php
namespace Drupal\brightcove\Entity;

use Brightcove\API\Request\SubscriptionRequest;
use Brightcove\Object\Subscription;
use Drupal\brightcove\BrightcoveSubscriptionInterface;
use Drupal\brightcove\BrightcoveUtil;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Url;

/**
 * Defines the Brightcove Subscription entity.
 *
 * @ingroup brightcove
 *
 * @ConfigEntityType(
 *   id = "brightcove_subscription",
 *   label = @Translation("Brightcove Subscription"),
 *   handlers = {
 *     "list_builder" = "Drupal\brightcove\BrightcoveSubscriptionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\brightcove\Form\BrightcoveSubscriptionForm",
 *       "delete" = "Drupal\brightcove\Form\BrightcoveSubscriptionDeleteForm"
 *     },
 *   },
 *   config_prefix = "brightcove_subscription",
 *   admin_permission = "administer brightcove configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/system/brightcove_subscription/{brightcove_subscription}",
 *     "add-form" = "/admin/config/system/brightcove_subscription/add",
 *     "delete-form" = "/admin/config/system/brightcove_subscription/{brightcove_subscription}/delete",
 *     "collection" = "/admin/config/system/brightcove_subscription",
 *     "enable": "/admin/config/system/brightcove_subscription/{brightcove_subscription}/enable",
 *     "disable": "/admin/config/system/brightcove_subscription/{brightcove_subscription}/disable",
 *   }
 * )
 */
class BrightcoveSubscription extends ConfigEntityBase implements BrightcoveSubscriptionInterface {
  /**
   * Indicates default subscription for the client.
   *
   * @var bool
   */
  protected $default;

  /**
   * The Brightcove API Client ID.
   *
   * @var string
   */
  protected $api_client_id;

  /**
   * The notifications endpoint.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * Array of events subscribed to.
   *
   * @var array[string]
   */
  protected $events;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values) {
    parent::__construct($values, 'brightcove_subscription');
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    if ($this->default) {
      return $this->status;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault() {
    return $this->default;
  }

  /**
   * @inheritdoc
   */
  public function getAPIClient() {
    return !empty($this->api_client_id) ? BrightcoveAPIClient::load($this->api_client_id) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint() {
    return $this->endpoint;
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents() {
    return $this->events;
  }

  /**
   * @inheritdoc
   */
  public function setAPIClient(BrightcoveAPIClient $api_client) {
    $this->api_client_id = $api_client->id();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEndpoint($endpoint) {
    $this->endpoint = $endpoint;
    return $this->endpoint;
  }

  /**
   * {@inheritdoc}
   */
  public function setEvents(array $events) {
    $this->events = $events;
    return $this->events;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    if ($this->default) {
      $this->status = $status;
      return $this;
    }
    throw new \Exception('Not possible to set status of a non-default Subscription.');
  }

  /**
   * Loads the default subscription by API Client ID.
   *
   * @param $api_client_id
   *   API Client ID.
   * @return \Drupal\brightcove\Entity\BrightcoveSubscription|null
   *   The default Brightcove Subscription for the given api client or NULL if
   *   not found.
   */
  public static function loadDefault($api_client_id) {
    $entity_ids = \Drupal::entityQuery('brightcove_subscription')
      ->condition('api_client_id', $api_client_id)
      ->execute();


    /** @var static $subscriptions */
    $subscriptions = self::loadMultiple($entity_ids);
    foreach (!empty($subscriptions) ? $subscriptions : [] as $subscription) {
      if ($subscription->isDefault()) {
        return $subscription;
      }
    }

    return NULL;
  }

  /**
   * Load a Subscription by its endpoint.
   *
   * @param $endpoint
   *   The endpoint string.
   *
   * @return \Drupal\brightcove\Entity\BrightcoveSubscription|null
   *   The Subscription with the given endpoint or NULL if not found.
   *
   */
  public static function loadByEndpoint($endpoint) {
    $entity_ids = \Drupal::entityQuery('brightcove_subscription')
      ->condition('endpoint', $endpoint)
      ->execute();

    if (!empty($entity_ids)) {
      $entity_id = reset($entity_ids);
      return self::load($entity_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @param bool $upload
   *   Whether to create the new subscription on Brightcove or not.
   */
  public function save($upload = TRUE) {
    // Create subscription on Brightcove only if the entity is new, as for now
    // it is not possible to edit existing subscriptions.
    if ($this->isNew() && $upload) {
      $this->saveToBrightcove();
    }

    return parent::save();
  }

  /**
   * Saves the subscription entity to Brightcove.
   */
  public function saveToBrightcove() {
    try {
      // Get CMS API.
      $cms = BrightcoveUtil::getCMSAPI($this->api_client_id);

      // Create subscription.
      $subscription = new SubscriptionRequest();
      $subscription->setEndpoint($this->getEndpoint());
      $subscription->setEvents($this->getEvents());
      $new_subscription = $cms->createSubscription($subscription);
      $this->set('id', $new_subscription->getId());
    }
    catch (\Exception $e) {
      watchdog_exception('brightcove', $e, 'Failed to save Subscription with @endpoint endpoint (ID: @id).', [
        '@endpoint' => $this->getEndpoint(),
        '@id' => $this->id(),
      ]);
      throw $e;
    }
  }

  /**
   * Saves the default subscription entity to Brightcove.
   */
  public function saveDefaultToBrightcove() {
    if ($this->isDefault()) {
      // Make sure that when the default is enabled, always use the correct URL.
      $default_endpoint = Url::fromRoute('brightcove_notification_callback', [], ['absolute' => TRUE])->toString();
      if ($this->endpoint != $default_endpoint) {
        $this->setEndpoint($default_endpoint);
      }

      $this->saveToBrightcove();

      $this->set('status', 1);
      $this->save(FALSE);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param bool $local_only
   *   If TRUE delete the local Subscription entity only, otherwise delete the
   *   subscription from Brightcove as well.
   */
  public function delete($local_only = FALSE) {
    if (!$local_only) {
      $this->deleteFromBrightcove();
    }
    parent::delete();
  }

  /**
   * Delete the Subscription from Brightcove only.
   */
  public function deleteFromBrightcove() {
    try {
      $cms = BrightcoveUtil::getCMSAPI($this->api_client_id);
      $cms->deleteSubscription($this->id());
    }
    catch (\Exception $e) {
      watchdog_exception('brightcove', $e, 'Failed to delete Subscription with @endpoint endpoint (ID: @id).', [
        '@endpoint' => $this->getEndpoint(),
        '@id' => $this->id(),
      ]);
      throw $e;
    }
  }

  /**
   * Deletes the default Subscription from Brightcove.
   */
  public function deleteDefaultFromBrightcove() {
    // This would make sense only in case of the default subscription, if it's
    // not a default one, do nothing.
    if ($this->isDefault()) {
      $this->deleteFromBrightcove();
      $this->set('id', "default_{$this->api_client_id}");
      $this->set('status', 0);
      $this->save(FALSE);
    }
  }

  /**
   * Create or update a Subscription entity.
   *
   * @param \Brightcove\Object\Subscription $brightcove_subscription
   *   Subscription object from Brightcove.
   * @param \Drupal\brightcove\Entity\BrightcoveAPIClient|null $api_client
   *   Loaded API client entity, or null.
   */
  public static function createOrUpdate(Subscription $brightcove_subscription, BrightcoveAPIClient $api_client = NULL) {
    /** @var \Drupal\brightcove\Entity\BrightcoveSubscription $subscription */
    $subscription = self::loadByEndpoint($brightcove_subscription->getEndpoint());

    // If there is no Subscription by the endpoint, try to get one by its ID.
    if (empty($subscription)) {
      /** @var \Drupal\brightcove\Entity\BrightcoveSubscription $subscription */
      $subscription = self::load($brightcove_subscription->getId());
    }

    // Update existing subscription.
    if (!empty($subscription)) {
      self::saveSubscription($subscription, $brightcove_subscription);
    }
    // Otherwise create new subscription.
    else {
      $subscription = self::create([
        'id' => $brightcove_subscription->getId(),
      ]);

      /** @var \Drupal\brightcove\Entity\BrightcoveAPIClient $api_client */
      if (!empty($api_client)) {
        $subscription->setAPIClient($api_client);
        self::saveSubscription($subscription, $brightcove_subscription);
      }
    }
  }

  /**
   * Save subscription.
   *
   * @param \Drupal\brightcove\Entity\BrightcoveSubscription $subscription
   *   The loaded or pre-created Subscription entity.
   * @param \Brightcove\Object\Subscription $brightcove_subscription
   *   The Subscription from Brightcove.
   */
  protected static function saveSubscription(BrightcoveSubscription $subscription, Subscription $brightcove_subscription) {
    $needs_save = FALSE;

    // Update default Subscription's ID.
    if ($subscription->isDefault() && ($id = $brightcove_subscription->getId()) != $subscription->id()) {
      $subscription->set('id', $id);
      $subscription->setStatus(TRUE);
      $needs_save = TRUE;
    }

    // Update endpoint.
    if (($endpoint = $brightcove_subscription->getEndpoint()) != $subscription->getEndpoint()) {
      $subscription->setEndpoint($endpoint);
      $needs_save = TRUE;
    }

    // Update events.
    $events = $brightcove_subscription->getEvents();
    if (!is_array($events)) {
      $events = [$events];
    }
    if ($events != $subscription->getEvents()) {
      $subscription->setEvents($events);
      $needs_save = TRUE;
    }

    // Save the Subscription if needed.
    if ($needs_save) {
      $subscription->save(FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    // Sort endpoints by their endpoints.
    if ($a instanceof BrightcoveSubscriptionInterface && $b instanceof BrightcoveSubscriptionInterface) {
      // The default Subscription is always the first.
      if ($a->isDefault()) {
        return -1;
      }
      elseif ($b->isDefault()) {
        return 1;
      }

      $a_endpoint = $a->getEndpoint();
      $b_endpoint = $b->getEndpoint();
      return strnatcasecmp($a_endpoint, $b_endpoint);
    }

    // Use parent sort if it's not a BrightcoveSubscription, but it should never
    // happen.
    return parent::sort($a, $b);
  }
}