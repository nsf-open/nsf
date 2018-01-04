<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Brightcove\API\Exception\APIException;
use Drupal\brightcove\BrightcoveUtil;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Entity Delete Tasks for Video.
 *
 * @QueueWorker(
 *   id = "brightcove_video_delete_queue_worker",
 *   title = @Translation("Brightcove video delete queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveVideoDeleteQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * The brightcove_video storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

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
   *   Brightcove Video Entity storage.
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
      $container->get('entity_type.manager')->getStorage('brightcove_video')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Check the video if it is available on Brightcove or not.
    try {
      $cms = BrightcoveUtil::getCMSAPI($data->api_client);
      $cms->getVideo($data->video_id);
    }
    catch (APIException $e) {
      // If we got a not found response, delete the local version of the video.
      if ($e->getCode() == 404) {
        /** @var \Drupal\brightcove\Entity\BrightcoveVideo $video */
        $video = $this->storage->load($data->bcvid);
        $video->delete(TRUE);
      }
      // Otherwise throw again the same exception.
      else {
        throw new APIException($e->getMessage(), $e->getCode(), $e, $e->getResponseBody());
      }
    }
  }
}
