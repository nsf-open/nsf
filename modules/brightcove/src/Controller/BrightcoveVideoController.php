<?php

namespace Drupal\brightcove\Controller;

use Brightcove\API\Exception\APIException;
use Drupal\brightcove\BrightcoveUtil;
use Drupal\brightcove\Entity\BrightcoveTextTrack;
use Drupal\brightcove\Entity\BrightcoveVideo;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BrightcoveVideoController extends ControllerBase {
  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The brightcove_video storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $video_storage;

  /**
   * The brightcove_text_track storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $text_track_storage;

  /**
   * The video queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $video_queue;

  /**
   * Controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $video_storage
   *   Brightcove Video entity storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $text_track_storage
   *   Brightcove Text Track entity storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Queue\QueueInterface $video_queue
   *   The Video queue object.
   */
  public function __construct(Connection $connection, EntityStorageInterface $video_storage, EntityStorageInterface $text_track_storage, QueueInterface $video_queue) {
    $this->connection = $connection;
    $this->video_storage = $video_storage;
    $this->text_track_storage = $text_track_storage;
    $this->video_queue = $video_queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager')->getStorage('brightcove_video'),
      $container->get('entity_type.manager')->getStorage('brightcove_text_track'),
      $container->get('queue')->get('brightcove_video_queue_worker')
    );
  }

   /**
    * Menu callback to update the existing Video with the latest version.
    *
    * @param int $entity_id
    *   The ID of the video in Drupal.
    *
    * @return \Symfony\Component\HttpFoundation\RedirectResponse
    *   Redirection response.
    */
  public function update($entity_id) {
    /** @var \Drupal\brightcove\Entity\BrightcoveVideo $video_entity */
    $video_entity = BrightcoveVideo::load($entity_id);

    /** @var \Brightcove\API\CMS $cms */
    $cms = BrightcoveUtil::getCMSAPI($video_entity->getAPIClient());

    // Update video.
    $video = $cms->getVideo($video_entity->getVideoId());
    $this->video_queue->createItem(array(
      'api_client_id' => $video_entity->getAPIClient(),
      'video' => $video,
    ));

    // Run batch.
    batch_set([
      'operations' => [
        [[BrightcoveUtil::class, 'runQueue'], ['brightcove_video_queue_worker']],
        [[BrightcoveUtil::class, 'runQueue'], ['brightcove_text_track_queue_worker']],
        [[BrightcoveUtil::class, 'runQueue'], ['brightcove_text_track_delete_queue_worker']],
      ],
    ]);

    // Run batch and redirect back to the video edit form.
    return batch_process(Url::fromRoute('entity.brightcove_video.edit_form', ['brightcove_video' => $entity_id]));
  }

  /**
   * Ingestion callback for Brightcove.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @param int $token
   *   The token which matches the video ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty Response object.
   */
  public function ingestionCallback(Request $request, $token) {
    $content = Json::decode($request->getContent());

    if (is_array($content) && $content['status'] == 'SUCCESS' && $content['version'] == 1 && $content['action'] == 'CREATE') {
      $video_id = \Drupal::keyValueExpirable('brightcove_callback')
        ->get($token);

      if (!empty($video_id)) {
        /** @var \Drupal\brightcove\Entity\BrightcoveVideo $video_entity */
        $video_entity = BrightcoveVideo::load($video_id);

        if (!is_null($video_entity)) {
          try {
            // Basic semaphore to prevent race conditions, this is needed because
            // Brightcove may call this callback again before the previous one
            // would finish.
            //
            // To make sure that the waiting doesn't run indefinitely limit the
            // maximum iterations to 600 cycles, which in worst case scenario
            // it means 5 minutes maximum wait time.
            $limit = 600;
            for ($i = 0; $i < $limit; $i++) {
              // Try to acquire semaphore.
              for (; $i < $limit && $this->state()->get('brightcove_video_semaphore', FALSE) == TRUE; $i++) {
                // Wait random time between 100 and 500 milliseconds on each try.
                usleep(mt_rand(100000, 500000));
              }

              // Make sure that other processes have not acquired the semaphore
              // while we waited.
              if ($this->state()->get('brightcove_video_semaphore', FALSE) == FALSE) {
                // Acquire semaphore as soon as we can.
                $this->state()->set('brightcove_video_semaphore', TRUE);
                break;
              }
            }

            // If we couldn't acquire the semaphore in the given time, release
            // the semaphore (finally block will do this), and return with an
            // empty response.
            if (600 <= $i) {
              return new Response();
            }

            $cms = BrightcoveUtil::getCMSAPI($video_entity->getAPIClient());

            switch ($content['entityType']) {
              // Video.
              case 'TITLE':
                // Delete video file from the entity.
                $video_entity->setVideoFile(NULL);
                $video_entity->save();
                break;

              // Assets.
              case 'ASSET':
                // Check for Text Track ID format. There is no other way to
                // determine whether the asset is a text track or something
                // else...
                if (preg_match('/^\w{8}-\w{4}-\w{4}-\w{4}-\w{12}$/i', $content['entity'])) {
                  $text_tracks = $video_entity->getTextTracks();
                  foreach ($text_tracks as $key => $text_track) {
                    if (!empty($text_track['target_id'])) {
                      /** @var \Drupal\brightcove\Entity\BrightcoveTextTrack $text_track_entity */
                      $text_track_entity = BrightcoveTextTrack::load($text_track['target_id']);

                      if (!is_null($text_track_entity) && empty($text_track_entity->getTextTrackId())) {
                        // Remove text track without Brightcove ID.
                        $text_track_entity->delete();

                        // Try to find the ingested text track on the video
                        // object and recreate it.
                        $cms = BrightcoveUtil::getCMSAPI($video_entity->getAPIClient());;
                        $video = $cms->getVideo($video_entity->getVideoId());
                        $api_text_tracks = $video->getTextTracks();
                        $found_api_text_track = NULL;
                        foreach ($api_text_tracks as $api_text_track) {
                          if ($api_text_track->getId() == $content['entity']) {
                            $found_api_text_track = $api_text_track;
                            break;
                          }
                        }

                        // Create new text track.
                        if (!is_null($found_api_text_track)) {
                          BrightcoveTextTrack::createOrUpdate($found_api_text_track,  $this->text_track_storage, $video_entity->id());
                        }

                        // We need to process only one per request.
                        break;
                      }
                    }
                  }
                }
                // Try to figure out other assets.
                else {
                  $client = BrightcoveUtil::getClient($video_entity->getAPIClient());
                  $api_client = BrightcoveUtil::getAPIClient($video_entity->getAPIClient());

                  // Check for each asset type by try-and-error, currently there is
                  // no other way to determine which asset is what...
                  $asset_types = [
                    BrightcoveVideo::IMAGE_TYPE_THUMBNAIL,
                    BrightcoveVideo::IMAGE_TYPE_POSTER,
                  ];
                  foreach ($asset_types as $type) {
                    try {
                      $client->request('GET', 'cms', $api_client->getAccountID(), '/videos/' . $video_entity->getVideoId() . '/assets/' . $type . '/' . $content['entity'], NULL);

                      switch ($type) {
                        case BrightcoveVideo::IMAGE_TYPE_THUMBNAIL:
                        case BrightcoveVideo::IMAGE_TYPE_POSTER:
                          $images = $cms->getVideoImages($video_entity->getVideoId());
                          $function = ucfirst($type);

                          // @TODO: Download the image only if truly different.
                          // But this may not be possible without downloading the
                          // image.
                          $video_entity->saveImage($type, $images->{"get{$function}"}());
                          break 2;
                      }
                    }
                    catch (APIException $e) {
                      // Do nothing here, just catch the exception to not generate
                      // an error.
                    }
                  }
                }

                // @TODO: Do we have to do anything with the rest of the assets?
                break;

              // Don't do anything if we got something else.
              default:
                return new Response();
            }

            // Update video entity with the latest update date.
            $video = $cms->getVideo($video_entity->getVideoId());
            $video_entity->setChangedTime(strtotime($video->getUpdatedAt()));
            $video_entity->save();
          }
          catch (\Exception $e) {
            watchdog_exception('brightcove', $e, 'An error happened while processing the ingestion callback.');
          }
          finally {
            // Release semaphore.
            $this->state()->set('brightcove_video_semaphore', FALSE);
          }
        }
      }
    }

    return new Response();
  }

  /**
   * Destructor.
   */
  public function __destruct() {
    // Make sure that the semaphore gets released.
    if ($this->state()->get('brightcove_video_semaphore', FALSE) == TRUE) {
      $this->state()->set('brightcove_video_semaphore', FALSE);
    }
  }
}
