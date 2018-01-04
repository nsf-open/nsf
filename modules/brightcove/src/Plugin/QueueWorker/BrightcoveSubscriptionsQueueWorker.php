<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Brightcove\API\Exception\APIException;
use Brightcove\Object\Subscription;
use Drupal\brightcove\BrightcoveUtil;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gathers the subscriptions for creation and delete check.
 *
 * @QueueWorker(
 *   id = "brightcove_subscriptions_queue_worker",
 *   title = @Translation("Brightcove subscriptions queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveSubscriptionsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_subscription create queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $subscription_queue;

  /**
   * The brightcove_delete_subscription queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $subscription_delete_queue;

  /**
   * Constructs a new BrightcoveSubscriptionQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Queue\QueueInterface $subscription_queue
   *   The brightcove_subscription queue.
   * @param \Drupal\Core\Queue\QueueInterface $subscription_delete_queue
   *   The brightcove_delete_subscription queue.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, QueueInterface $subscription_queue, QueueInterface $subscription_delete_queue) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->subscription_queue = $subscription_queue;
    $this->subscription_delete_queue = $subscription_delete_queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('queue')->get('brightcove_subscription_queue_worker'),
      $container->get('queue')->get('brightcove_subscription_delete_queue_worker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $cms = BrightcoveUtil::getCMSAPI($data);
    try {
      $subscriptions = $cms->getSubscriptions();

      foreach (!empty($subscriptions) ? $subscriptions : [] as $subscription) {
        if ($subscription instanceof Subscription) {
          $this->subscription_queue->createItem([
            'api_client_id' => $data,
            'subscription' => $subscription,
          ]);
        }
      }
    }
    catch (APIException $e) {
      if ($e->getCode() == 401) {
        watchdog_exception('brightcove', $e, 'Access denied for Notifications.', [], RfcLogLevel::WARNING);
      }
      else {
        throw $e;
      }
    }
  }
}
