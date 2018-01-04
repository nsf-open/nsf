<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\brightcove\Entity\BrightcoveTextTrack;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brightcove\Entity\BrightcoveVideo;

/**
 * Processes Entity Update Tasks for Video.
 *
 * @QueueWorker(
 *   id = "brightcove_video_queue_worker",
 *   title = @Translation("Brightcove video queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveVideoQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_video storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Entity query factory.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The playlist page queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $text_track_queue;

  /**
   * The playlist page queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $text_track_delete_queue;

  /**
   * Constructs a new BrightcoveVideoQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The storage object.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Queue\QueueInterface $text_track_queue
   *   Text track queue object.
   * @param \Drupal\Core\Queue\QueueInterface $text_track_delete_queue
   *   Text track delete queue object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $storage, Connection $connection, QueueInterface $text_track_queue, QueueInterface $text_track_delete_queue) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storage = $storage;
    $this->connection = $connection;
    $this->text_track_queue = $text_track_queue;
    $this->text_track_delete_queue = $text_track_delete_queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('brightcove_video'),
      $container->get('database'),
      $container->get('queue')->get('brightcove_text_track_queue_worker'),
      $container->get('queue')->get('brightcove_text_track_delete_queue_worker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Brightcove\Object\Video\Video $video */
    $video = $data['video'];

    /** @var \Drupal\brightcove\Entity\BrightcoveVideo $video_entity */
    $video_entity = BrightcoveVideo::createOrUpdate($video, $this->storage, $data['api_client_id']);

    if (!empty($video_entity)) {
      // Get existing text tracks.
      $existing_text_tracks = [];
      foreach ($video_entity->getTextTracks() as $text_track) {
        /** @var \Drupal\brightcove\Entity\BrightcoveTextTrack $text_track_entity */
        $text_track_entity = BrightcoveTextTrack::load($text_track['target_id']);

        if (!is_null($text_track_entity)) {
          $existing_text_tracks[$text_track_entity->getTextTrackId()] = TRUE;
        }
      }

      // Save Video text tracks.
      $text_tracks = $video->getTextTracks();
      foreach ($text_tracks as $text_track) {
        // Remove existing text tracks from the list which are still existing on
        // Brightcove.
        if (isset($existing_text_tracks[$text_track->getId()])) {
          unset($existing_text_tracks[$text_track->getId()]);
        }

        // Create new queue item for text track.
        $this->text_track_queue->createItem([
          'text_track' => $text_track,
          'video_entity_id' => $video_entity->id(),
        ]);
      }

      // Remove existing text tracks which are no longer available on Brightcove.
      foreach (array_keys($existing_text_tracks) as $text_track_id) {
        // Create new delete queue item for text track.
        $this->text_track_delete_queue->createItem($text_track_id);
      }
    }
  }
}
