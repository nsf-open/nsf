<?php

namespace Drupal\brightcove;

use Brightcove\API\CMS;
use Brightcove\API\DI;
use Brightcove\API\Exception\APIException;
use Brightcove\API\PM;
use Drupal\brightcove\Entity\BrightcoveAPIClient;
use Drupal\brightcove\Entity\BrightcovePlayer;
use Drupal\brightcove\Entity\BrightcovePlaylist;
use Drupal\brightcove\Entity\BrightcoveVideo;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;

/**
 * Utility class for Brightcove.
 */
class BrightcoveUtil {
  /**
   * Array of BrightcoveAPIClient objects.
   *
   * @var \Drupal\brightcove\Entity\BrightcoveAPIClient[]
   */
  protected static $api_clients = [];

  /**
   * Array of CMS objects.
   *
   * @var \Brightcove\API\CMS[]
   */
  protected static $cms_apis = [];

  /**
   * Array of DI objects.
   *
   * @var \Brightcove\API\DI[]
   */
  protected static $di_apis = [];

  /**
   * Array of PM objects.
   *
   * @var \Brightcove\API\PM[]
   */
  protected static $pm_apis = [];

  /**
   * Convert Brightcove date make it digestible by Drupal.
   *
   * @param string $brightcove_date
   *   Brightcove date format.
   *
   * @return string|NULL
   *   Drupal date format.
   */
  public static function convertDate($brightcove_date) {
    if (empty($brightcove_date)) {
      return NULL;
    }

    return preg_replace('/\.\d{3}Z$/i', '', $brightcove_date);
  }

  /**
   * Gets BrightcoveAPIClient entity.
   *
   * @param string $entity_id
   *   The entity ID of the BrightcoveAPIClient.
   *
   * @return \Drupal\brightcove\Entity\BrightcoveAPIClient
   *   Loaded BrightcoveAPIClient object.
   */
  public static function getAPIClient($entity_id) {
    // Load BrightcoveAPIClient if it wasn't already.
    if (!isset(self::$api_clients[$entity_id])) {
      self::$api_clients[$entity_id] = BrightcoveAPIClient::load($entity_id);
    }

    return self::$api_clients[$entity_id];
  }

  /**
   * Gets Brightcove client.
   *
   * @param string $entity_id
   *   BrightcoveAPIClient entity ID.
   *
   * @return \Brightcove\API\Client
   *   Loaded Brightcove client.
   */
  public static function getClient($entity_id) {
    $api_client = self::getAPIClient($entity_id);
    return $api_client->getClient();
  }

  /**
   * Gets Brightcove CMS API.
   *
   * @param string $entity_id
   *   BrightcoveAPIClient entity ID.
   *
   * @return \Brightcove\API\CMS
   *   Initialized Brightcove CMS API.
   */
  public static function getCMSAPI($entity_id) {
    // Create new \Brightcove\API\CMS object if it is not exists yet.
    if (!isset(self::$cms_apis[$entity_id])) {
      $client = self::getClient($entity_id);
      self::$cms_apis[$entity_id] = new CMS($client, self::$api_clients[$entity_id]->getAccountID());
    }

    return self::$cms_apis[$entity_id];
  }

  /**
   * Gets Brightcove DI API.
   *
   * @param string $entity_id
   *   BrightcoveAPIClient entity ID.
   *
   * @return \Brightcove\API\DI
   *   Initialized Brightcove CMS API.
   */
  public static function getDIAPI($entity_id) {
    // Create new \Brightcove\API\DI object if it is not exists yet.
    if (!isset(self::$di_apis[$entity_id])) {
      $client = self::getClient($entity_id);
      self::$di_apis[$entity_id] = new DI($client, self::$api_clients[$entity_id]->getAccountID());
    }

    return self::$di_apis[$entity_id];
  }

  /**
   * Gets Brightcove PM API.
   *
   * @param string $entity_id
   *   BrightcoveAPIClient entity ID.
   *
   * @return \Brightcove\API\PM
   *   Initialized Brightcove PM API.
   */
  public static function getPMAPI($entity_id) {
    // Create new \Brightcove\API\PM object if it is not exists yet.
    if (!isset(self::$pm_apis[$entity_id])) {
      $client = self::getClient($entity_id);
      self::$pm_apis[$entity_id] = new PM($client, self::$api_clients[$entity_id]->getAccountID());
    }

    return self::$pm_apis[$entity_id];
  }

  /**
   * Check updated version of the CMS entity.
   *
   * If the checked CMS entity has a newer version of it on Brightcove then
   * show a message about it with a link to be able to update the local
   * version.
   *
   * @param \Drupal\brightcove\BrightcoveCMSEntityInterface $entity
   *   Brightcove CMS Entity, can be BrightcoveVideo or BrightcovePlaylist.
   *   Player is currently not supported.
   *
   * @throws \Exception
   *   If the version for the given entity is cannot be checked.
   */
  public static function checkUpdatedVersion(BrightcoveCMSEntityInterface $entity) {
    $client = self::getClient($entity->getAPIClient());

    if (!is_null($client)) {
      $cms = self::getCMSAPI($entity->getAPIClient());

      $entity_type = '';
      try {
        if ($entity instanceof BrightcoveVideo) {
          $entity_type = 'video';
          $cms_entity = $cms->getVideo($entity->getVideoId());
        }
        else if ($entity instanceof BrightcovePlaylist) {
          $entity_type = 'playlist';
          $cms_entity = $cms->getPlaylist($entity->getPlaylistId());
        }
        else {
          throw new \Exception(t("Can't check version for :entity_type entity.", [
            ':entity_type' => get_class($entity),
          ]));
        }

        if (isset($cms_entity)) {
          if ($entity->getChangedTime() < strtotime($cms_entity->getUpdatedAt())) {
            $url = Url::fromRoute("brightcove_manual_update_{$entity_type}", ['entity_id' => $entity->id()], ['query' => ['token' => \Drupal::getContainer()->get('csrf_token')->get("brightcove_{$entity_type}/{$entity->id()}/update")]]);

            drupal_set_message(t("There is a newer version of this :type on Brightcove, you may want to <a href=':url'>update the local version</a> before editing it.", [
              ':type' => $entity_type,
              ':url' => $url->toString(),
            ]), 'warning');
          }
        }
      }
      catch (APIException $e) {
        if (!empty($entity_type)) {
          $url = Url::fromRoute("entity.brightcove_{$entity_type}.delete_form", ["brightcove_{$entity_type}" => $entity->id()]);
          drupal_set_message(t("This :type no longer exists on Brightcove. You may want to <a href=':url'>delete the local version</a> too.", [
            ':type' => $entity_type,
            ':url' => $url->toString(),
          ]), 'error');
        }
        else {
          drupal_set_message($e->getMessage(), 'error');
        }
      }
    }
    else {
      drupal_set_message(t('Brightcove API connection error: :error', [
        ':error' => self::getAPIClient($entity->getAPIClient())->getClientStatusMessage()
      ]), 'error');
    }
  }

  /**
   * Runs a queue.
   *
   * @param $queue
   *   The queue name to clear.
   * @param &$context
   *   The Batch API context.
   */
  public static function runQueue($queue, &$context) {
    // This is a static function called by Batch API, so it's not possible to
    // use dependency injection here.
    /** @var QueueWorkerInterface $queue_worker */
    $queue_worker = \Drupal::getContainer()->get('plugin.manager.queue_worker')->createInstance($queue);
    $queue = \Drupal::queue($queue);

    // Let's process ALL the items in the queue, 5 by 5, to avoid PHP timeouts.
    // If there's any problem with processing any of those 5 items, stop sooner.
    $limit = 5;
    $handled_all = TRUE;
    while (($limit > 0) && ($item = $queue->claimItem(5))) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        $handled_all = FALSE;
        break;
      }
      catch(APIException $e) {
        if ($e->getCode() == 401) {
          $queue->deleteItem($item);
          $handled_all = TRUE;
        }
        else {
          watchdog_exception('brightcove', $e);
          \Drupal::logger('brightcove')->error($e->getMessage());
          $handled_all = FALSE;
        }
      }
      catch (\Exception $e) {
        watchdog_exception('brightcove', $e);
        \Drupal::logger('brightcove')->error($e->getMessage());
        $handled_all = FALSE;
      }
      $limit--;
    }

    // As this batch may be run synchronously with the queue's cron processor,
    // we can't be sure about the number of items left for the batch as long as
    // there is any. So let's just inform the user about the number of remaining
    // items, as we don't really care if they are processed by this batch
    // processor or the cron one.
    $remaining = $queue->numberOfItems();
    $context['message'] = t('@count item(s) left in current queue', ['@count' => $remaining]);
    $context['finished'] = $handled_all && ($remaining == 0);
  }

  /**
   * Helper function to get default player for the given entity.
   *
   * @param \Drupal\brightcove\BrightcoveVideoPlaylistCMSEntityInterface $entity
   *   Video or playlist entity.
   *
   * @return string
   *   The ID of the player.
   */
  public static function getDefaultPlayer(BrightcoveVideoPlaylistCMSEntityInterface $entity) {
    if ($player = $entity->getPlayer()) {
      return BrightcovePlayer::load($player)->getPlayerId();
    }

    $api_client = self::getAPIClient($entity->getAPIClient());
    return $api_client->getDefaultPlayer();
  }

  /**
   * Helper function to save or update tags.
   *
   * @param \Drupal\brightcove\BrightcoveVideoPlaylistCMSEntityInterface $entity
   *   Video or playlist entity.
   * @param string $api_client_id
   *   API Client ID.
   * @param array $tags
   *   The list of tags from brightcove.
   */
  public static function saveOrUpdateTags(BrightcoveVideoPlaylistCMSEntityInterface $entity, $api_client_id, array $tags = array()) {
    $entity_tags = [];
    $video_entity_tags = $entity->getTags();
    foreach ($video_entity_tags as $index => $tag) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $term = Term::load($tag['target_id']);
      if (!is_null($term)) {
        $entity_tags[$term->id()] = $term->getName();
      }
      // Remove non-existing tag references from the video, if there would
      // be any.
      else {
        unset($video_entity_tags[$index]);
        $entity->setTags($video_entity_tags);
      }
    }
    if (array_values($entity_tags) != $tags) {
      // Remove deleted tags from the video.
      if (!empty($entity->id())) {
        $tags_to_remove = array_diff($entity_tags, $tags);
        foreach (array_keys($tags_to_remove) as $entity_id) {
          unset($entity_tags[$entity_id]);
        }
      }

      // Add new tags.
      $new_tags = array_diff($tags, $entity_tags);
      $entity_tags = array_keys($entity_tags);
      foreach ($new_tags as $tag) {
        $existing_tags = \Drupal::entityQuery('taxonomy_term')
          ->condition('name', $tag)
          ->execute();

        // Create new Taxonomy term item.
        if (empty($existing_tags)) {
          $values = [
            'name' => $tag,
            'vid' => 'brightcove_video_tags',
            'brightcove_api_client' => [
              'target_id' => $api_client_id,
            ],
          ];
          $taxonomy_term = Term::create($values);
          $taxonomy_term->save();
        }
        $entity_tags[] = isset($taxonomy_term) ? $taxonomy_term->id() : reset($existing_tags);
      }
      $entity->setTags($entity_tags);
    }
  }
}
