<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\brightcove\Entity\BrightcoveTextTrack;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Update Tasks for Text Track.
 *
 * @QueueWorker(
 *   id = "brightcove_text_track_queue_worker",
 *   title = @Translation("Brightcove text track queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveTextTrackQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_text_track storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a new BrightcoveTextTrackQueueWorker object.
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
      $container->get('entity_type.manager')->getStorage('brightcove_text_track')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Brightcove\Object\Video\TextTrack $text_track */
    $text_track = $data['text_track'];

    BrightcoveTextTrack::createOrUpdate($text_track, $this->storage, $data['video_entity_id']);
  }
}
