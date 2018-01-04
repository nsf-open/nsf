<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Brightcove\API\Exception\APIException;
use Drupal\brightcove\BrightcoveUtil;
use Drupal\brightcove\Entity\BrightcoveSubscription;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Sync Tasks for Subscription.
 *
 * @QueueWorker(
 *   id = "brightcove_subscription_delete_queue_worker",
 *   title = @Translation("Brightcove subscription queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveSubscriptionDeleteQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_subscription storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $subscription_storage;

  /**
   * Constructs a new BrightcoveSubscriptionQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $subscription_storage
   *   The brightcove_subscription storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $subscription_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->subscription_storage = $subscription_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('brightcove_subscription')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var array $data */
    if (!empty($data['local_only'])) {
      /** @var \Drupal\brightcove\Entity\BrightcoveSubscription $subscription */
      $subscription = BrightcoveSubscription::load($data['subscription_id']);
      if (!empty($subscription)) {
        $subscription->delete(TRUE);
      }
    }
    else {
      // Check the Subscription if it is available on Brightcove or not.
      try {
        $cms = BrightcoveUtil::getCMSAPI($data['api_client_id']);
        $cms->getSubscription($data['subscription_id']);
      }
      catch (APIException $e) {
        // If we got a not found response, delete the local version of the
        // subscription.
        if ($e->getCode() == 404) {
          /** @var \Drupal\brightcove\Entity\BrightcoveSubscription $subscription */
          $subscription = $this->subscription_storage->load($data['subscription_id']);
          if ($subscription->isDefault()) {
            $subscription->set('status', 0);
            $subscription->set('id', "default_{$data['api_client_id']}");
            $subscription->save(FALSE);
          }
          else {
            $subscription->delete(TRUE);
          }
        }
        elseif ($e->getCode() == 401) {
          watchdog_exception('brightcove', $e, 'Access denied for Notification.', [], RfcLogLevel::WARNING);
        }
        // Otherwise throw again the same exception.
        else {
          throw $e;
        }
      }
    }
  }
}
