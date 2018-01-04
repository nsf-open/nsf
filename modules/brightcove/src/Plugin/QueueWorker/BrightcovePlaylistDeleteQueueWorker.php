<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Brightcove\API\Exception\APIException;
use Drupal\brightcove\BrightcoveUtil;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Delete Tasks for Playlist.
 *
 * @QueueWorker(
 *   id = "brightcove_playlist_delete_queue_worker",
 *   title = @Translation("Brightcove playlist delete queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcovePlaylistDeleteQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_playlist storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a new BrightcovePlaylistDeleteQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Brightcove Playlist Entity storage.
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
      $container->get('entity_type.manager')->getStorage('brightcove_playlist')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Check the playlist if it is available on Brightcove or not.
    try {
      $cms = BrightcoveUtil::getCMSAPI($data->api_client);
      $cms->getPlaylist($data->playlist_id);
    }
    catch (APIException $e) {
      // If we got a not found response, delete the local version of the
      // playlist.
      if ($e->getCode() == 404) {
        /** @var \Drupal\brightcove\Entity\BrightcovePlaylist $playlist */
        $playlist = $this->storage->load($data->bcplid);
        $playlist->delete(TRUE);
      }
      // Otherwise throw again the same exception.
      else {
        throw new APIException($e->getMessage(), $e->getCode(), $e, $e->getResponseBody());
      }
    }
  }
}
