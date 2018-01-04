<?php

namespace Drupal\brightcove\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brightcove\BrightcoveUtil;

/**
 * Processes Entity Update Tasks for Video.
 *
 * @QueueWorker(
 *   id = "brightcove_video_page_queue_worker",
 *   title = @Translation("Brightcove client queue worker."),
 *   cron = { "time" = 30 }
 * )
 */
class BrightcoveVideoPageQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The video queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $video_queue;

  /**
   * Constructs a new BrightcoveVideoPageQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Queue\QueueInterface $video_queue
   *   The queue object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, QueueInterface $video_queue) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->video_queue = $video_queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('queue')->get('brightcove_video_queue_worker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $cms = BrightcoveUtil::getCMSAPI($data['api_client_id']);

    // Get videos.
    $videos = $cms->listVideos(NULL, 'created_at', $data['items_per_page'], $data['page'] * $data['items_per_page']);
    foreach ($videos as $video) {
      $this->video_queue->createItem(array(
        'api_client_id' => $data['api_client_id'],
        'video' => $video,
      ));
    }
  }
}
