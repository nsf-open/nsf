<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Local Delete Tasks for Playlist.
 *
 * @QueueWorker(
 *   id = "brightcove_playlist_local_delete_queue_worker",
 *   title = @Translation("Brightcove playlist local delete queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcovePlaylistLocalDeleteQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_playlist storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a new BrightcovePlaylistLocalDeleteQueueWorker object.
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
    /** @var \Drupal\brightcove\Entity\BrightcovePlaylist $playlist */
    $playlist = $this->storage->load($data);

    if (!is_null($playlist)) {
      $playlist->delete(TRUE);
    }
  }
}
