<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\brightcove\Entity\BrightcovePlayer;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Delete Tasks for Player.
 *
 * @QueueWorker(
 *   id = "brightcove_player_delete_queue_worker",
 *   title = @Translation("Brightcove player delete queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcovePlayerDeleteQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_player storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a new BrightcovePlayerDeleteQueueWorker object.
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
      $container->get('entity_type.manager')->getStorage('brightcove_player')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (isset($data['player_id'])) {
      /** @var \Drupal\brightcove\Entity\BrightcovePlayer $player */
      $player = BrightcovePlayer::loadByPlayerId($data['player_id']);

      if (!is_null($player)) {
        $player->delete();
      }
    }
    elseif (isset($data['player_entity_id'])) {
      /** @var \Drupal\brightcove\Entity\BrightcovePlayer $player */
      $player = BrightcovePlayer::load($data['player_entity_id']);

      if (!is_null($player)) {
        $player->delete();
      }
    }
  }
}
