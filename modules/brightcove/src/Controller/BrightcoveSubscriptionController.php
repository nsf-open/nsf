<?php

namespace Drupal\brightcove\Controller;

use Drupal\brightcove\BrightcoveUtil;
use Drupal\brightcove\Entity\BrightcoveSubscription;
use Drupal\brightcove\Entity\BrightcoveVideo;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\Query\Query;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BrightcoveSubscriptionController extends ControllerBase {
  /**
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  private $container;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $video_storage;

  /**
   * @var \Drupal\Core\Config\Entity\Query\Query
   */
  private $subscription_query;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container,
      $container->get('entity_type.manager')->getStorage('brightcove_video'),
      $container->get('entity.query')->get('brightcove_subscription')
    );
  }

  /**
   * BrightcoveSubscriptionController constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param \Drupal\Core\Entity\EntityStorageInterface $video_storage
   */
  public function __construct(ContainerInterface $container, EntityStorageInterface $video_storage, Query $subscription_query) {
    $this->container = $container;
    $this->video_storage = $video_storage;
    $this->subscription_query = $subscription_query;
  }

  /**
   * Menu callback to handle the Brightcove notification callback.
   *
   * @param Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Redirection response.
   */
  public function notificationCallback(Request $request) {
    $content = Json::decode($request->getContent());

    switch ($content['event']) {
      case 'video-change':
        /** @var \Drupal\brightcove\Entity\BrightcoveVideo $video_entity */
        $video_entity = BrightcoveVideo::loadByBrightcoveVideoId($content['account_id'], $content['video']);

        // Get CMS API.
        $cms = BrightcoveUtil::getCMSAPI($video_entity->getAPIClient());

        // Update video.
        $video = $cms->getVideo($video_entity->getVideoId());
        BrightcoveVideo::createOrUpdate($video, $this->video_storage, $video_entity->getAPIClient());
        break;
    }

    return new Response();
  }

  /**
   * Enables and creates the default Subscription from Brightcove.
   *
   * @param string $brightcove_subscription
   *   The ID of the Brightcove Subscription.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function enable($brightcove_subscription) {
    try {
      $subscription = BrightcoveSubscription::load($brightcove_subscription);
      $subscription->saveDefaultToBrightcove();
      drupal_set_message($this->t('Default subscription for the "@api_client" API client has been successfully enabled.', ['@api_client' => $subscription->getAPIClient()->label()]));
    }
    catch(\Exception $e) {
      drupal_set_message($this->t('Failed to enable the default subscription: @error', ['@error' => $e->getMessage()]), 'error');
    }
    return $this->redirect('entity.brightcove_subscription.collection');
  }

  /**
   * Disabled and removed the default Subscription from Brightcove.
   *
   * @param string $brightcove_subscription
   *   The ID of the Brightcove Subscription.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function disable($brightcove_subscription) {
    try {
      $subscription = BrightcoveSubscription::load($brightcove_subscription);
      $subscription->deleteDefaultFromBrightcove();
      drupal_set_message($this->t('Default subscription for the "@api_client" API client has been successfully disabled.', ['@api_client' => $subscription->getAPIClient()->label()]));
    }
    catch (\Exception $e) {
      drupal_set_message($this->t('Failed to disable the default subscription: @error', ['@error' => $e->getMessage()]), 'error');
    }
    return $this->redirect('entity.brightcove_subscription.collection');
  }
}