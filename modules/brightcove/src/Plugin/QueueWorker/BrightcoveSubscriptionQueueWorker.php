<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\brightcove\Entity\BrightcoveSubscription;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Sync Tasks for Subscription.
 *
 * @QueueWorker(
 *   id = "brightcove_subscription_queue_worker",
 *   title = @Translation("Brightcove subscription queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveSubscriptionQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_subscription storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $api_client_storage;

  /**
   * Constructs a new BrightcoveSubscriptionQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $api_client_storage
   *   The brightcove_api_client storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $api_client_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->api_client_storage = $api_client_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('brightcove_api_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    BrightcoveSubscription::createOrUpdate($data['subscription'], $this->api_client_storage->load($data['api_client_id']));
  }
}
