<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\brightcove\Entity\BrightcoveTextTrack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Delete Tasks for Text Track.
 *
 * @QueueWorker(
 *   id = "brightcove_text_track_delete_queue_worker",
 *   title = @Translation("Brightcove text track delete queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveTextTrackDeleteQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
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
    $text_track_entity = BrightcoveTextTrack::loadByTextTrackId($data);

    if (!is_null($text_track_entity)) {
      $text_track_entity->delete();
    }
  }
}
