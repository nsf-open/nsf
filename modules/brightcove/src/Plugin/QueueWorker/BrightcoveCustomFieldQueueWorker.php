<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\brightcove\Entity\BrightcoveCustomField;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Update Tasks for Custom Fields.
 *
 * @QueueWorker(
 *   id = "brightcove_custom_field_queue_worker",
 *   title = @Translation("Brightcove custom field queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveCustomFieldQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_custom_field storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a new BrightcoveCustomFieldQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The storage object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('brightcove_custom_field')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Brightcove\Object\CustomField $custom_field */
    $custom_field = $data['custom_field'];

    BrightcoveCustomField::createOrUpdate($custom_field, $this->storage, $data['api_client_id']);
  }
}
