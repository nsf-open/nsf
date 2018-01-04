<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\brightcove\Entity\BrightcoveCustomField;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Delete Tasks for Custom Fields.
 *
 * @QueueWorker(
 *   id = "brightcove_custom_field_delete_queue_worker",
 *   title = @Translation("Brightcove custom field delete queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveCustomFieldDeleteQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Delete custom field.
    if ($data instanceof BrightcoveCustomField) {
      $data->delete();
    }
    else {
      /** @var \Drupal\brightcove\Entity\BrightcoveCustomField $custom_field_entity */
      $custom_field_entity = BrightcoveCustomField::load($data);

      if (!is_null($custom_field_entity)) {
        $custom_field_entity->delete();
      }
    }
  }
}
