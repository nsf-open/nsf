<?php

namespace Drupal\media_entity_brightcove\Plugin\MediaEntity\Type;

use Drupal\brightcove\BrightcoveUtil;
use Drupal\media_entity\MediaBundleInterface;
use Drupal\media_entity\MediaInterface;
use Drupal\media_entity\MediaTypeBase;

/**
 * @MediaType(
 *   id = "media_entity_brightcove",
 *   label = @Translation("Brightcove Video"),
 *   description = @Translation("Provides business logic and metadata for videos.")
 * )
 */
class BrightcoveVideo extends MediaTypeBase {

  /**
   * The used field name.
   */
  const FIELD_NAME = 'field_video';

  /**
   * {@inheritdoc}
   */
  public function providedFields() {
    return [
      'name' => $this->t('Name'),
      'complete' => $this->t('Complete'),
      'description' => $this->t('Description'),
      'long_description' => $this->t('Long description'),
      'reference_id' => $this->t('Reference ID'),
      'state' => $this->t('State'),
      'tags' => $this->t('Tags'),
      'custom_fields' => $this->t('Custom fields'),
      'geo' => $this->t('Geo information'),
      'geo.countries' => $this->t('Geo countries'),
      'geo.exclude_countries' => $this->t('Exclude countries'),
      'geo.restricted' => $this->t('Geo Restricted'),
      'schedule' => $this->t('Schedule'),
      'starts_at' => $this->t('Starts at'),
      'ends_at' => $this->t('Ends at'),
      'picture_thumbnail' => $this->t('Thumbnail picture'),
      'picture_poster' => $this->t('Picture poster'),
      'video_source' => $this->t('Vidoe source'),
      'economics' => $this->t('Economics'),
      'partner_channel' => $this->t('Partner channel'),
    ];
  }

  /**
   * Returns the data stored on this video media as object.
   *
   * @return \Brightcove\Object\Video\Video|null
   *
   * @todo Decide whether we want to have our own custom domain value object.
   */
  public function getVideo(MediaInterface $media) {
    /** @var \Drupal\brightcove\Entity\BrightcoveVideo $video */
    if ($video = $media->{static::FIELD_NAME}->entity) {
      $cms = BrightcoveUtil::getCMSAPI($video->getAPIClient());
      $brightcove_video = $cms->getVideo($video->getVideoId());
      return $brightcove_video;
    }
  }

  /**
   * Returns the brightcove video entity.
   *
   * @param \Drupal\media_entity\MediaInterface $media
   *   The media
   *
   * @return \Drupal\brightcove\Entity\BrightcoveVideo
   */
  public function getVideoEntity(MediaInterface $media) {
    return $media->{static::FIELD_NAME}->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getField(MediaInterface $media, $name) {
    switch ($name) {
      case 'thumbnail':
        return $this->thumbnail($media);

      case 'name':
        return $this->getVideoEntity($media)->getName();

      case 'complete':
        break;

      case 'description':
        return $this->getVideoEntity($media)->getDescription();

      case 'long_description':
        return $this->getVideoEntity($media)->getLongDescription();

      case 'reference_id':
        return $this->getVideoEntity($media)->getReferenceID();

      case 'state':
        break;

      case 'tags':
        return $this->getVideoEntity($media)->getTags();

      case 'custom_fields':
        return $this->getVideoEntity($media)->getCustomFieldValues();

      case 'geo':
        break;

      case 'geo.countries':
        return $this->getVideoEntity($media)->geo_countries->value;

      case 'geo.exclude_countries':
        return $this->getVideoEntity($media)->geo_exclude_countries->value;

      case 'geo.restricted':
        return $this->getVideoEntity($media)->geo_restricted->value;

      case 'schedule':
        break;

      case 'starts_at':
        return $this->getVideoEntity($media)->getScheduleStartsAt();

      case 'ends_at':
        return $this->getVideoEntity($media)->getScheduleEndsAt();

      case 'picture_thumbnail':
        return $this->thumbnail($media);

      case 'picture_poster':
        return $this->getVideoEntity($media)->getPoster();

      case 'video_source':
        return $this->getVideoEntity($media)->getVideoUrl();

      case 'economics':
        return $this->getVideoEntity($media)->getEconomics();

      case 'partner_channel':
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function thumbnail(MediaInterface $media) {
    if ($thumbnail_info = $this->getVideoEntity($media)->getThumbnail()) {
      /** @var \Drupal\file\FileInterface $file */
      if ($file = $this->entityTypeManager->getStorage('file')->load($thumbnail_info['target_id'])) {
        return $file->getFileUri();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultName(MediaInterface $media) {
    return $this->getField($media, 'name');
  }

  /**
   * {@inheritdoc}
   */
  protected function getSourceFieldName() {
    // Must be implemented to return something that fits into the 32 characters
    // limit for a field name.
    // @TODO: Check if this needs to be unique.
    // @see \Drupal\media_entity\MediaTypeBase::getSourceFieldName()
    return 'brightcove_video';
  }

  /**
   * {@inheritdoc}
   */
  function createSourceFieldStorage() {
    return $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create([
        'entity_type' => 'media',
        'field_name' => $this->getSourceFieldName(),
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'brightcove_video',
        ],
      ]);
  }

  /**
   * {@inheritdoc}
   */
  function createSourceField(MediaBundleInterface $bundle) {
    return $this->entityTypeManager
      ->getStorage('field_config')
      ->create([
        'field_storage' => $this->getSourceFieldStorage(),
        'bundle' => $bundle->id(),
        'required' => TRUE,
        'label' => 'Brightcove Video',
        'settings' => [
          'handler' => 'default:brightcove_video',
        ],
      ]);
  }

}
