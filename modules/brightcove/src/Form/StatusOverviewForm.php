<?php

namespace Drupal\brightcove\Form;

use Drupal\brightcove\BrightcoveUtil;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Class StatusOverviewForm.
 *
 * @package Drupal\brightcove\Form
 */
class StatusOverviewForm extends FormBase {

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a StatusOverviewForm object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(QueueFactory $queueFactory, Connection $connection, EntityTypeManager $entityTypeManager) {
    $this->queueFactory = $queueFactory;
    $this->connection = $connection;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * @inheritdoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue'),
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $video_num = $this->entityTypeManager->getStorage('brightcove_video')->getQuery()->count()->execute();
    $playlist_num = $this->entityTypeManager->getStorage('brightcove_playlist')->getQuery()->count()->execute();
    $subscription_num = $this->entityTypeManager->getStorage('brightcove_subscription')->getQuery()->count()->execute();

    $counts = [
      'client' => $this->entityTypeManager->getStorage('brightcove_api_client')->getQuery()->count()->execute(),
      'subscription' => $subscription_num,
      'subscription_delete' => $subscription_num,
      'video' => $video_num,
      'video_delete' => $video_num,
      'text_track' => $this->entityTypeManager->getStorage('brightcove_text_track')->getQuery()->count()->execute(),
      'playlist' => $playlist_num,
      'playlist_delete' => $playlist_num,
      'player' => $this->entityTypeManager->getStorage('brightcove_player')->getQuery()->count()->execute(),
      'custom_field' => $this->entityTypeManager->getStorage('brightcove_custom_field')->getQuery()->count()->execute(),
    ];

    $queues = [
      'client' => $this->t('Client'),
      'subscription' => $this->t('Subscription'),
      'player' => $this->t('Player'),
      'custom_field' => $this->t('Custom field'),
      'video' => $this->t('Video'),
      'text_track' => $this->t('Text Track'),
      'playlist' => $this->t('Playlist'),
      'video_delete' => $this->t('Check deleted videos *'),
      'playlist_delete' => $this->t('Check deleted playlists *'),
      'subscription_delete' => $this->t('Check deleted subscriptions'),
    ];

    // There is no form element (ie. widget) in the table, so it's safe to
    // return a render array for a table as a part of the form build array.
    $form['queues'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Number of entities'),
        $this->t('Item(s) in queue'),
      ],
      '#rows' => [],
    ];
    foreach ($queues as $queue => $title) {
      $form['queues']['#rows'][$queue] = [
        $title,
        $counts[$queue],
        $this->queueFactory->get("brightcove_{$queue}_queue_worker")->numberOfItems(),
      ];
    }

    $form['notice'] = [
      '#type' => 'item',
      '#markup' => '<em>* ' . $this->t('May run slowly with lots of items.') . '</em>',
    ];

    $form['sync'] = array(
      '#name' => 'sync',
      '#type' => 'submit',
      '#value' => $this->t('Sync all'),
    );
    $form['run'] = array(
      '#name' => 'run',
      '#type' => 'submit',
      '#value' => $this->t('Run all queues'),
    );
    $form['clear'] = array(
      '#name' => 'clear',
      '#type' => 'submit',
      '#value' => $this->t('Clear all queues'),
      '#description' => $this->t('Remove all items from all queues'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($triggering_element = $form_state->getTriggeringElement()) {
      $batch_operations = [];
      $util_class = BrightcoveUtil::class;
      switch ($triggering_element['#name']) {
        case 'sync':
          $batch_operations[] = ['_brightcove_initiate_sync', []];
          // There is intentionally no break here.
        case 'run':
          // These queues are responsible for synchronizing from Brightcove to
          // Drupal (IOW pulling). The order is important.
          // - The client queue must be run first, that's out of question: this
          //   worker populates most of the other queues.
          // - Players should be pulled before videos and playlists.
          // - Custom fields (which means custom field definitions, not values)
          //   should be pulled before videos.
          // - Text tracks can only be pulled after videos.
          // - Playlists can only be pulled after videos.
          // - Custom fields (again: their definitions) have to be deleted
          //   before pulling videos.
          // - Text tracks have to be deleted before videos are pulled or
          //   deleted.
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_client_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_player_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_player_delete_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_custom_field_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_custom_field_delete_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_video_page_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_video_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_text_track_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_text_track_delete_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_playlist_page_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_playlist_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_video_delete_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_playlist_delete_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_subscriptions_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_subscription_queue_worker']];
          $batch_operations[] = [[$util_class, 'runQueue'], ['brightcove_subscription_delete_queue_worker']];
          break;

        case 'clear':
          // The order shouldn't really matter for clearing the queues, but we
          // are repeating the order from above for the sake of consistency.
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_client_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_player_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_player_delete_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_custom_field_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_custom_field_delete_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_video_page_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_video_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_text_track_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_text_track_delete_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_playlist_page_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_playlist_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_video_delete_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_playlist_delete_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_subscriptions_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_subscription_queue_worker']];
          $batch_operations[] = [[self::class, 'clearQueue'], ['brightcove_subscription_delete_queue_worker']];
          break;
      }

      if ($batch_operations) {
        // Reset expired items in the default queue implementation table. If
        // that's not used, this will simply be a no-op.
        // @see system_cron()
        $this->connection->update('queue')
          ->fields(array(
            'expire' => 0,
          ))
          ->condition('expire', 0, '<>')
          ->condition('expire', REQUEST_TIME, '<')
          ->condition('name', 'brightcove_%', 'LIKE')
          ->execute();

        batch_set([
          'operations' => $batch_operations,
        ]);
      }
    }
  }

  /**
   * Clears a queue.
   *
   * @param $queue
   *   The queue name to clear.
   */
  public static function clearQueue($queue) {
    // This is a static function called by Batch API, so it's not possible to
    // use dependency injection here.
    \Drupal::queue($queue)->deleteQueue();
  }
}
