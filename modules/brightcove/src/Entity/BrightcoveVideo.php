<?php

namespace Drupal\brightcove\Entity;

use Brightcove\API\Exception\APIException;
use Brightcove\API\Request\IngestImage;
use Brightcove\API\Request\IngestRequest;
use Brightcove\API\Request\IngestRequestMaster;
use Brightcove\API\Request\IngestTextTrack;
use Brightcove\Object\Video\Link;
use Brightcove\Object\Video\Schedule;
use Brightcove\Object\Video\TextTrack;
use Brightcove\Object\Video\TextTrackSource;
use Brightcove\Object\Video\Video;
use Drupal\brightcove\BrightcoveUtil;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\brightcove\BrightcoveVideoInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\link\LinkItemInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\time_formatter\Plugin\Field\FieldFormatter\TimeFieldFormatter;

/**
 * Defines the Brightcove Video entity.
 *
 * @ingroup brightcove
 *
 * @ContentEntityType(
 *   id = "brightcove_video",
 *   label = @Translation("Brightcove Video"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\brightcove\BrightcoveVideoListBuilder",
 *     "views_data" = "Drupal\brightcove\Entity\BrightcoveVideoViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\brightcove\Form\BrightcoveVideoForm",
 *       "add" = "Drupal\brightcove\Form\BrightcoveVideoForm",
 *       "edit" = "Drupal\brightcove\Form\BrightcoveVideoForm",
 *       "delete" = "Drupal\brightcove\Form\BrightcoveEntityDeleteForm",
 *     },
 *     "access" = "Drupal\brightcove\Access\BrightcoveVideoAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\brightcove\BrightcoveVideoHtmlRouteProvider",
 *     },
 *     "inline_form" = "Drupal\brightcove\Form\BrightcoveInlineForm",
 *   },
 *   base_table = "brightcove_video",
 *   admin_permission = "administer brightcove videos",
 *   entity_keys = {
 *     "id" = "bcvid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/brightcove_video/{brightcove_video}",
 *     "add-form" = "/brightcove_video/add",
 *     "edit-form" = "/brightcove_video/{brightcove_video}/edit",
 *     "delete-form" = "/brightcove_video/{brightcove_video}/delete",
 *     "collection" = "/admin/content/brightcove_video",
 *   },
 *   field_ui_base_route = "brightcove_video.settings"
 * )
 */
class BrightcoveVideo extends BrightcoveVideoPlaylistCMSEntity implements BrightcoveVideoInterface {
  /**
   * Ingestion request object.
   *
   * @var \Brightcove\API\Request\IngestRequest;
   */
  protected $ingestRequest = NULL;

  /**
   * Create or get an existing ingestion request object.
   *
   * @return \Brightcove\API\Request\IngestRequest
   */
  protected function getIngestRequest() {
    if (is_null($this->ingestRequest)) {
      $this->ingestRequest = new IngestRequest();
    }
    return $this->ingestRequest;
  }

  /**
   * Helper function to provide default values for image fields.
   *
   * The original and the save entity's field arrays is a bit different from
   * each other, so provide the missing values.
   *
   * @param array &$values
   *   The field's values array.
   */
  protected function provideDefaultValuesForImageField(&$values) {
    /** @var \Drupal\file\Entity\File $file */
    foreach ($values as $delta => &$value) {
      $file = File::load($value['target_id']);

      if (!is_null($file)) {
        $value += [
          'display' => $file->status->value,
          'description' => '',
          'upload' => '',
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveImage($type, $image) {
    // Prepare function name and throw an exception if it doesn't match for an
    // existing function.
    $image_dir = 'public://';
    switch ($type) {
      case self::IMAGE_TYPE_THUMBNAIL:
        $image_dir .= self::VIDEOS_IMAGES_THUMBNAILS_DIR;
        break;

      case self::IMAGE_TYPE_POSTER:
        $image_dir .= self::VIDEOS_IMAGES_POSTERS_DIR;
        break;

      default:
        throw new \Exception(t("Invalid type given: @type, the type argument must be either '@thumbnail' or '@poster'.", [
          '@type' => $type,
          '@thumbnail' => self::IMAGE_TYPE_THUMBNAIL,
          '@poster' => self::IMAGE_TYPE_POSTER,
        ]));
    }

    // Make type's first character uppercase for correct function name.
    $function = ucfirst($type);

    $needs_save = TRUE;

    // Try to get the image's filename from Brightcove.
    // @TODO: Find a drupal friendly solution for file handling.
    preg_match('/\/(?!.*\/)([\w-\.]+)/i', $img_src = (is_array($image) ? $image['src'] : $image->getSrc()), $matches);
    if (isset($matches[1])) {
      // Get entity's image file.
      /** @var \Drupal\file\Entity\File $file */
      $entity_image = $this->{"get{$function}"}();
      // If the entity already has an image then load and delete it.
      if (!empty($entity_image['target_id'])) {
        $file = File::load($entity_image['target_id']);
        if (!is_null($file) && $file->getFileName() != $matches[1]) {
          $this->{"set{$function}"}(NULL);
        }
        else if (!is_null($file) && $file->getFilename() == $matches[1]) {
          $needs_save = FALSE;
        }
      }

      // Save the new image from Brightcove to the entity.
      if ($needs_save) {
        $image_content = file_get_contents($img_src);
        // Prepare directory and if it was a success try to save the image.
        if (file_prepare_directory($image_dir, FILE_MODIFY_PERMISSIONS | FILE_CREATE_DIRECTORY)) {
          $image_name = $matches[1];
          $file = file_save_data($image_content, "{$image_dir}/{$image_name}");

          // Set image if there was no error.
          if ($file !== FALSE) {
            $this->{"set{$function}"}($file->id());
          }
        }
      }
    }

    return $this;
  }

  /**
   * Helper function to delete a file from the entity.
   *
   * @param int $target_id
   *   The target ID of the saved file.
   *
   * @return \Drupal\brightcove\BrightcoveVideoInterface
   *   The called Brightcove Video.
   *
   * @throws \Exception
   *   If the $target_id is not a positive number or zero.
   */
  private function deleteFile($target_id) {
    if (!is_numeric($target_id) || $target_id <= 0) {
      throw new \Exception(t('Target ID must be non-zero number.'));
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = File::load($target_id);

    // Delete image.
    // @TODO: Check for multiple file references when it will be needed.
    // So far as I find it, it is not possible to create multiple file
    // references on the Drupal's UI, so it should be OK now.
    if (!is_null($file)) {
      $file->delete();
    }

    return $this;
  }

  /**
   * Create an ingestion request for image.
   *
   * @param $type
   *   The type of the image, possible values are:
   *     - IMAGE_TYPE_POSTER
   *     - IMAGE_TYPE_THUMBNAIL
   *
   * @throws \Exception
   *   If the $type is not matched with the possible types.
   */
  protected function createIngestImage($type) {
    if (!in_array($type, [self::IMAGE_TYPE_POSTER, self::IMAGE_TYPE_THUMBNAIL])) {
      throw new \Exception(t("Invalid type given: @type, the type argument must be either '@thumbnail' or '@poster'.", [
        '@type' => $type,
        '@thumbnail' => self::IMAGE_TYPE_THUMBNAIL,
        '@poster' => self::IMAGE_TYPE_POSTER,
      ]));
    }

    $function = ucfirst($type);

    // Set up image ingestion.
    if (!empty($this->{"get{$function}"}()['target_id'])) {
      $ingest_request = $this->getIngestRequest();

      /** @var \Drupal\file\Entity\File $file */
      $file = File::load($this->{"get{$function}"}()['target_id']);

      // Load the image object factory so we can access height and width.
      $image_factory = \Drupal::service('image.factory');
      $image = $image_factory->get($file->getFileUri());

      if (!is_null($image)) {
        $ingest_image = new IngestImage();
        $ingest_image->setUrl(file_create_url($file->getFileUri()));
        $ingest_image->setWidth($image->getWidth());
        $ingest_image->setHeight($image->getHeight());
        $ingest_request->{"set{$function}"}($ingest_image);
      }
    }
  }

  /**
   * Get a random hash token for ingestion request callback.
   *
   * @return string|NULL
   *   The generated random token or NULL if an error happened.
   */
  protected function getIngestionToken() {
    $token = NULL;

    try {
      // Generate unique token.
      do {
        $token = Crypt::hmacBase64($this->getVideoId(), Crypt::randomBytesBase64() . Settings::getHashSalt());
      }
      while (\Drupal::keyValueExpirable('brightcove_callback')->has($token));

      // Insert unique token into database.
      \Drupal::keyValueExpirable('brightcove_callback')
        ->setWithExpire($token, $this->id(), \Drupal::config('brightcove.settings')->get('notification.callbackExpirationTime'));
    }
    catch (\Exception $e) {
      watchdog_exception('brightcove', $e);
      // Reset token to NULL.
      $token = NULL;
    }

    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function getVideoId() {
    return $this->get('video_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setVideoId($video_id) {
    $this->set('video_id', $video_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDuration() {
    return $this->get('duration')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDuration($duration) {
    $this->set('duration', $duration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedLink() {
    $value = $this->get('related_link')->getValue();

    if (empty($value[0])) {
      return array();
    }

    // Original entity missing this array value, so to be consistent when
    // comparing the original entity with new entity values add this array
    // value if missing.
    if (!isset($value[0]['attributes'])) {
      $value[0]['attributes'] = [];
    }

    return $value[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setRelatedLink($related_link) {
    // If the protocol is missing from the link add default http protocol to
    // the link.
    if (!empty($related_link['uri']) && !preg_match('/[\w-]+:\/\//i', $related_link['uri'])) {
      $related_link['uri'] = "http://{$related_link['uri']}";
    }

    $this->set('related_link', $related_link);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLongDescription() {
    return $this->get('long_description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLongDescription($long_description) {
    $this->set('long_description', $long_description);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEconomics() {
    return $this->get('economics')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEconomics($economics) {
    $this->set('economics', $economics);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVideoFile() {
    $value = $this->get('video_file')->getValue();

    if (empty($value[0]['target_id'])) {
      return [];
    }

    if (!isset($value[0]['upload'])) {
      $value[0]['upload'] = '';
    }

    return $value[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setVideoFile($video_file) {
    $video_to_delete = $this->getVideoFile();
    if (is_null($video_file) && !empty($video_to_delete['target_id'])) {
      $this->deleteFile($video_to_delete['target_id']);
    }
    $this->set('video_file', $video_file);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVideoUrl() {
    return $this->get('video_url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setVideoUrl($video_url) {
    $this->set('video_url', $video_url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile() {
    return $this->get('profile')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setProfile($profile) {
    $this->set('profile', $profile);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPoster() {
    $values = $this->get('poster')->getValue();

    if (empty($values[0]['target_id'])) {
      return NULL;
    }

    $this->provideDefaultValuesForImageField($values);

    return $values[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setPoster($poster) {
    // Handle image deletion here as well.
    $poster_to_delete = $this->getPoster();
    if (is_null($poster) && !empty($poster_to_delete['target_id'])) {
      $this->deleteFile($poster_to_delete['target_id']);
    }

    $this->set('poster', $poster);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThumbnail() {
    $values = $this->get('thumbnail')->getValue();

    if (empty($values[0]['target_id'])) {
      return NULL;
    }

    $this->provideDefaultValuesForImageField($values);

    return $values[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setThumbnail($thumbnail) {
    // Handle image deletion here as well.
    $thumbnail_to_delete = $this->getThumbnail();
    if (is_null($thumbnail) && !empty($thumbnail_to_delete['target_id'])) {
      $this->deleteFile($thumbnail_to_delete['target_id']);
    }

    $this->set('thumbnail', $thumbnail);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomFieldValues() {
    $value = $this->get('custom_field_values')->getValue();

    if (!empty($value)) {
      return $value[0];
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomFieldValues(array $values) {
    return $this->set('custom_field_values', $values);
  }

  /**
   * {@inheritdoc}
   */
  public function getScheduleStartsAt() {
    $value = $this->get('schedule_starts_at')->getValue();
    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setScheduleStartsAt($schedule_starts_at) {
    $this->set('schedule_starts_at', $schedule_starts_at);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScheduleEndsAt() {
    $value = $this->get('schedule_ends_at')->getValue();
    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setScheduleEndsAt($schedule_ends_at) {
    $this->set('schedule_ends_at', $schedule_ends_at);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextTracks() {
    return $this->get('text_tracks')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setTextTracks($text_tracks) {
    return $this->set('text_tracks', $text_tracks);
  }

  /**
   * Loads a Video based on the Brightcove Video ID and Account ID.
   *
   * @param $account_id
   *   The ID of the account.
   * @param $brightcove_video_id
   *   The External ID of the Video.
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   *   The matching Brightcove Video.
   */
  public static function loadByBrightcoveVideoId($account_id, $brightcove_video_id) {
    // Get API Client by Account ID.
    $api_client_ids = \Drupal::entityQuery('brightcove_api_client')
      ->condition('account_id', $account_id)
      ->execute();

    $entity_ids = \Drupal::entityQuery('brightcove_video')
      ->condition('api_client', reset($api_client_ids))
      ->condition('video_id', $brightcove_video_id)
      ->condition('status', 1)
      ->execute();

    return self::load(reset($entity_ids));
  }

  /**
   * {@inheritdoc}
   *
   * @param bool $upload
   *   Whether to upload the video to Brightcove or not.
   */
  public function save($upload = FALSE) {
    // Check if it will be a new entity or an existing one being updated.
    $status = $this->id() ? SAVED_UPDATED : SAVED_NEW;

    // Make sure that preSave runs before any modification is made for the
    // entity.
    $saved = parent::save();

    // Upload data for Brightcove only if we saved the video from form.
    if ($upload) {
      $cms = BrightcoveUtil::getCMSAPI($this->getAPIClient());

      // Setup video object and set minimum required values.
      $video = new Video();
      $video->setName($this->getName());

      // Save or update description if needed.
      if ($this->isFieldChanged('description')) {
        $video->setDescription($this->getDescription());
      }

      // Save or update duration if needed.
      if ($this->isFieldChanged('duration')) {
        $video->setDuration($this->getDuration());
      }

      // Save or update economics if needed.
      if ($this->isFieldChanged('economics')) {
        $video->setEconomics($this->getEconomics());
      }

      // Save or update tags if needed.
      if ($this->isFieldChanged('tags')) {
        // Get term IDs.
        $term_ids = [];
        foreach ($this->getTags() as $tag) {
          $term_ids[] = $tag['target_id'];
        }

        // Load terms.
        /** @var \Drupal\taxonomy\Entity\Term[] $terms */
        $terms = Term::loadMultiple($term_ids);
        $tags = [];
        foreach ($terms as $term) {
          $tags[] = $term->getName();
        }

        $video->setTags($tags);
      }

      // Save or update link if needed.
      if ($this->isFieldChanged('related_link')) {
        $link = new Link();
        $related_link = $this->getRelatedLink();
        $is_link_set = FALSE;

        if (!empty($related_link['uri'])) {
          $link->setUrl(Url::fromUri($related_link['uri'], ['absolute' => TRUE])->toString());
          $is_link_set = TRUE;
        }

        if (!empty($related_link['title'])) {
          $link->setText($related_link['title']);
          $is_link_set = TRUE;
        }

        if ($is_link_set) {
          $video->setLink($link);
        }
        else {
          $video->setLink();
        }
      }

      // Save or update long description if needed.
      if ($this->isFieldChanged('long_description')) {
        $video->setLongDescription($this->getLongDescription());
      }

      // Save or update reference ID if needed.
      if ($this->isFieldChanged('reference_id')) {
        $video->setReferenceId($this->getReferenceID());
      }

      // Save or update custom field values.
      $custom_field_num = \Drupal::entityQuery('brightcove_custom_field')
        ->condition('api_client', $this->getAPIClient())
        ->count()
        ->execute();
      if ($this->isFieldChanged('custom_field_values') && $custom_field_num) {
        $video->setCustomFields($this->getCustomFieldValues());
      }

      // Save or update schedule start at date if needed.
      if ($this->isFieldChanged('schedule_starts_at') || $this->isFieldChanged('schedule_ends_at')) {
        $starts_at = $this->getScheduleStartsAt();
        $ends_at = $this->getScheduleEndsAt();
        $schedule = new Schedule();
        $is_schedule_set = FALSE;

        if (!is_null($starts_at)) {
          $schedule->setStartsAt($starts_at . '.000Z');
          $is_schedule_set = TRUE;
        }

        if (!is_null($ends_at)) {
          $schedule->setEndsAt($ends_at . '.000Z');
          $is_schedule_set = TRUE;
        }

        if ($is_schedule_set) {
          $video->setSchedule($schedule);
        }
        else {
          $video->setSchedule();
        }
      }

      // Upload text tracks.
      if ($this->isFieldChanged('text_tracks')) {
        $video_text_tracks = [];
        $ingest_text_tracks = [];
        foreach ($this->getTextTracks() as $text_track) {
          if (!empty($text_track['target_id'])) {
            /** @var \Drupal\brightcove\Entity\BrightcoveTextTrack $text_track_entity */
            $text_track_entity = BrightcoveTextTrack::load($text_track['target_id']);

            if (!is_null($text_track_entity)) {
              // Setup ingestion request if there was a text track uploaded.
              $webvtt_file = $text_track_entity->getWebVTTFile();
              if (!empty($webvtt_file[0]['target_id'])) {
                /** @var \Drupal\file\Entity\File $file */
                $file = File::load($webvtt_file[0]['target_id']);

                if (!is_null($file)) {
                  $ingest_text_tracks[] = (new IngestTextTrack())
                    ->setSrclang($text_track_entity->getSourceLanguage())
                    ->setUrl(file_create_url($file->getFileUri()))
                    ->setKind($text_track_entity->getKind())
                    ->setLabel($text_track_entity->getLabel())
                    ->setDefault($text_track_entity->isDefault());
                }
              }
              // Build the whole text track for Brightcove.
              else {
                $video_text_track = (new TextTrack())
                  ->setId($text_track_entity->getTextTrackId())
                  ->setSrclang($text_track_entity->getSourceLanguage())
                  ->setLabel($text_track_entity->getLabel())
                  ->setKind($text_track_entity->getKind())
                  ->setMimeType($text_track_entity->getMimeType())
                  ->setAssetId($text_track_entity->getAssetId());

                // If asset ID is set the src will be ignored, so in this case
                // we don't set the src.
                if (!empty($text_track_entity->getAssetId())) {
                  $video_text_track->setAssetId($text_track_entity->getAssetId());
                }
                // Otherwise set the src.
                else {
                  $video_text_track->setSrc($text_track_entity->getSource());
                }

                // Get text track sources.
                $video_text_track_sources = [];
                foreach ($text_track_entity->getSources() as $source) {
                  $text_track_source = new TextTrackSource();
                  $text_track_source->setSrc($source['uri']);
                  $video_text_track_sources[] = $text_track_source;
                }
                $video_text_track->setSources($video_text_track_sources);

                $video_text_tracks[] = $video_text_track;
              }
            }
          }
        }

        // Set existing text tracks.
        $video->setTextTracks($video_text_tracks);
      }

      // Update status field based on Brightcove's Video state.
      if ($this->isFieldChanged('status')) {
        $video->setState($this->isPublished() ? self::STATE_ACTIVE : self::STATE_INACTIVE);
      }

      // Create or update a video.
      switch ($status) {
        case SAVED_NEW:
          // Create new video on Brightcove.
          $saved_video = $cms->createVideo($video);

          // Set the rest of the fields on BrightcoveVideo entity.
          $this->setVideoId($saved_video->getId());
          $this->setCreatedTime(strtotime($saved_video->getCreatedAt()));
          break;

        case SAVED_UPDATED:
          // Set Video ID.
          $video->setId($this->getVideoId());

          // Update video.
          $saved_video = $cms->updateVideo($video);
          break;
      }

      // Set ingestion for thumbnail.
      if ($this->isFieldChanged(self::IMAGE_TYPE_THUMBNAIL)) {
        $this->createIngestImage(self::IMAGE_TYPE_THUMBNAIL);
      }

      // Set ingestion for poster.
      if ($this->isFieldChanged(self::IMAGE_TYPE_POSTER)) {
        $this->createIngestImage(self::IMAGE_TYPE_POSTER);
      }

      // Set ingestion for video.
      if ($this->isFieldChanged('video_file') && !empty($this->getVideoFile())) {
        /** @var \Drupal\file\Entity\File $file */
        $file = File::load($this->getVideoFile()['target_id']);

        $ingest_request = $this->getIngestRequest();

        $ingest_master = new IngestRequestMaster();
        $ingest_master->setUrl(file_create_url($file->getFileUri()));

        $ingest_request->setMaster($ingest_master);
        $profiles = self::getProfileAllowedValues($this->getAPIClient());
        $ingest_request->setProfile($profiles[$this->getProfile()]);
      }

      // Set ingestion for video url.
      if ($this->isFieldChanged('video_url') && !empty($this->getVideoUrl())) {
        $ingest_request = $this->getIngestRequest();

        $ingest_master = new IngestRequestMaster();
        $ingest_master->setUrl($this->getVideoUrl());
        $ingest_request->setMaster($ingest_master);
        $profiles = self::getProfileAllowedValues($this->getAPIClient());
        $ingest_request->setProfile($profiles[$this->getProfile()]);
      }

      // Set ingestion for text tracks.
      if (!empty($ingest_text_tracks)) {
        $ingest_request = $this->getIngestRequest();
        $ingest_request->setTextTracks($ingest_text_tracks);
      }

      // Send the ingest request if there was an ingestible asset.
      if (!is_null($this->ingestRequest)) {
        // Get token.
        $token = $this->getIngestionToken();

        // Make sure that the token is generated successfully. If there was an
        // error generating the token then be nice to Brightcove and don't set
        // the callback url, so it won't hit a wall by trying to notify us on a
        // non-valid URL.
        if (!is_null($token)) {
          $callback_url = Url::fromRoute('brightcove_ingestion_callback', ['token' => $token], ['absolute' => TRUE])->toString();
          $this->ingestRequest->setCallbacks([
            $callback_url,
          ]);
        }

        // Send request.
        $di = BrightcoveUtil::getDIAPI($this->getAPIClient());
        $di->createIngest($this->getVideoId(), $this->ingestRequest);
      }

      // Update changed time and video entity with the video ID.
      if (isset($saved_video)) {
        $this->setChangedTime(strtotime($saved_video->getUpdatedAt()));

        // Save the entity again to save some new values which are only
        // available after creating/updating the video on Brightcove.
        // Also don't change the save state to show the correct message when if
        // the entity is created or updated.
        parent::save();
      }
    }

    // Reset changed fields.
    $this->changedFields = [];

    return $saved;
  }

  /**
   * {@inheritdoc}
   *
   * @param bool $local_only
   *   Whether to delete the local version only or both local and Brightcove
   *   versions.
   */
  public function delete($local_only = FALSE) {
    // Delete video from Brightcove.
    if (!$this->isNew() && !$local_only) {
      $api_client = BrightcoveUtil::getAPIClient($this->getAPIClient());
      $client = $api_client->getClient();

      // Delete video references.
      try {
        $client->request('DELETE', 'cms', $api_client->getAccountID(), "/videos/{$this->getVideoId()}/references", NULL);

        // Delete video.
        $cms = BrightcoveUtil::getCMSAPI($this->getAPIClient());
        $cms->deleteVideo($this->getVideoId());

        parent::delete();
      }
      catch (APIException $e) {
        if ($e->getCode() == 404) {
          drupal_set_message(t('The video was not found on Brightcove, only the local version was deleted.'), 'warning');
          parent::delete();
        }
        else {
          drupal_set_message(t('There was an error while trying to delete the Video from Brightcove: @error', [
            '@error' => ($e->getMessage()),
          ]), 'error');
        }
      }
    }
    else {
      parent::delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // Set weights based on the real order of the fields.
    $weight = -30;

    /**
     * Drupal-specific fields first.
     *
     * bcvid - Brightcove Video ID (Drupal-internal).
     * uuid - UUID.
     * - Title comes here, but that's the "Name" field from Brightcove.
     * langcode - Language.
     * api_client - Entityreference to BrightcoveAPIClient.
     * - Brightcove fields come here.
     * uid - Author.
     * created - Posted.
     * changed - Last modified.
     */
    $fields['bcvid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The Drupal entity ID of the Brightcove Video.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The Brightcove Video UUID.'))
      ->setReadOnly(TRUE);

    $fields['api_client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('API Client'))
      ->setDescription(t('Brightcove API credentials (account) to use.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'brightcove_api_client')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['player'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Player'))
      ->setDescription(t('Brightcove Player to be used for playback.'))
      ->setSetting('target_type', 'brightcove_player')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Status field, tied together with the status of the entity.
    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enabled'))
      ->setDescription(t('Determines whether the video is playable.'))
      //->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setSettings([
        'on_label' => t('Active'),
        'off_label' => t('Inactive'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'label' => 'above',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('Title of the video.'))
      //->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setSettings([
        // Not applying the max_length setting any longer. Without an explicit
        // max_length setting Drupal will use a varchar(255) field, at least on
        // my MySQL backend. BC docs currently say the length of the 'name'
        // field is 1..255, but let's just not apply any explicit limit any
        // longer on the Drupal end.
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'hidden',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the Brightcove Video.'))
      //->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'language_select',
        'weight' => ++$weight,
      ])
      ->setDisplayConfigurable('form', TRUE);

    /**
     * Additional Brightcove fields, based on
     * @see https://videocloud.brightcove.com/admin/fields
     * superseded by
     * @see http://docs.brightcove.com/en/video-cloud/cms-api/references/cms-api/versions/v1/index.html#api-videoGroup-Get_Videos
     * superseded by
     * @see http://docs.brightcove.com/en/video-cloud/cms-api/references/cms-api/versions/v1/index.html#api-videoGroup-Create_Video
     *
     * Brightcove ID - string (Not editable. Unique Video ID assigned by
     *   Brightcove)
     * Economics - list (ECONOMICS_TYPE_FREE, ECONOMICS_TYPE_AD_SUPPORTED)
     * Force Ads - boolean
     * Geo-filtering Country List - list (ISO-3166 country code list)
     * Geo-filtering On - boolean
     * Geo-filtering Options - list (Include countries, Exclude Countries)
     * Logo Overlay Alignment - list (Top Left, Top Right, Bottom Right,
     *   Bottom Left)
     * Logo Overlay Image - image file (Transparent PNG or GIF)
     * Logo Overlay Tooltip - text(128)
     * Logo Overlay URL - URL(128)
     * Long Description - string(0..5000)
     * Bumper Video - video file (FLV or H264 video file to playback before the
     *   Video content)
     * Reference ID - string(..150) (Value specified must be unique)
     * Related Link Text - text(40)
     * Scheduling End Date - date (Day/Time for the video to be hidden in the
     *   player)
     * Scheduling Start Date - date (Day/Time for the video be displayed in the
     *   player)
     * Short Description - string(0..250)
     * Tags - text (Separate tags with a comma; no tag > 128 characters. Max
     *   1200 tags per video)
     * Thumbnail - image file (Suggested size: 120 x 90 pixels, JPG)
     * Video Duration - number (Not editable. Stores the length of the video
     *   file.)
     * Video Files - video file (One or more FLV or H264 video files)
     * Video Name - string(1..255)
     * Video Still - image file (Suggested size: 480 x 360 pixels, JPG)
     * Viral Distribution - boolean (Enables the get code and blogging menu
     *   options for the video)
     */
    $fields['video_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Video ID'))
      ->setDescription(t('Unique Video ID assigned by Brightcove.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => ++$weight,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['duration'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Video Duration'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'number_time',
        'label' => 'inline',
        'weight' => ++$weight,
        'settings' => [
          'storage' => TimeFieldFormatter::STORAGE_MILLISECONDS,
          'display' => TimeFieldFormatter::DISPLAY_NUMBERSMS,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Short description'))
      ->setDescription(t('Max 250 characters.'))
      //->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->addPropertyConstraints('value', [
        'Length' => [
          'max' => 250,
        ],
      ]);

    $fields['tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tags'))
      ->setDescription(t('Max 1200 tags per video'))
      // We can't really say 1200 here as it'd yield 1200 textfields on the UI.
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      //->setRevisionable(TRUE)
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler_settings' => [
          'target_bundles' => ['brightcove_video_tags' => 'brightcove_video_tags'],
          'auto_create' => TRUE,
        ],
      ])
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'weight' => ++$weight,
        'settings' => array(
          'autocomplete_type' => 'tags',
        ),
      ))
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'label' => 'above',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['related_link'] = BaseFieldDefinition::create('link')
      ->setLabel(t('Related Link'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 150,
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_OPTIONAL,
      ])
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'link',
        'label' => 'inline',
        'weight' => $weight,
        'settings' => [
          'trim_length' => 150,
          'target' => '_blank',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['reference_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference ID'))
      ->addConstraint('UniqueField')
      ->setDescription(t('Value specified must be unique'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 150,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDefaultValueCallback(static::class . '::getDefaultReferenceId')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['long_description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Long description'))
      ->setDescription(t('Max 5000 characters'))
      //->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->addPropertyConstraints('value', [
        'Length' => [
          'max' => 5000,
        ],
      ]);

    // Advertising field, but Brightcove calls it 'economics' in the API.
    $fields['economics'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Advertising'))
      //->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setDefaultValue(self::ECONOMICS_TYPE_FREE)
      ->setSetting('allowed_values', [
        self::ECONOMICS_TYPE_FREE => 'Free',
        self::ECONOMICS_TYPE_AD_SUPPORTED => 'Ad Supported',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // @TODO: Folder

    $fields['video_file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Video source'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'file_extensions' => '3gp 3g2 aac ac3 asf avchd avi avs bdav dv dxa ea eac3 f4v flac flv h261 h263 h264 m2p m2ts m4a m4v mjpeg mka mks mkv mov mp3 mp4 mpeg mpegts mpg mt2s mts ogg ps qt rtsp thd ts vc1 wav webm wma wmv',
        'file_directory' => '[random:hash:md5]',
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'file_url_plain',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Provide an external URL
    $fields['video_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Video source URL'))
      ->setDisplayOptions('form', [
        'type' => 'uri',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'uri_link',
        'label' => 'inline',
        'weight' => $weight,
        'settings' => [
          'trim_length' => 150,
          'target' => '_blank',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['profile'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Encoding profile'))
      //->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', [self::class, 'profileAllowedValues'])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => ++$weight,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['poster'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Video Still'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'file_extensions' => 'jpg jpeg png',
        'file_directory' => self::VIDEOS_IMAGES_POSTERS_DIR,
        'alt_field' => FALSE,
        'alt_field_required' => FALSE,
      ])
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'image',
        'label' => 'above',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['thumbnail'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Thumbnail'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'file_extensions' => 'jpg jpeg png',
        'file_directory' => self::VIDEOS_IMAGES_THUMBNAILS_DIR,
        'alt_field' => FALSE,
        'alt_field_required' => FALSE,
      ])
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'image',
        'label' => 'above',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['custom_field_values'] = BaseFieldDefinition::create('map');
      //->setRevisionable(TRUE)

    $fields['schedule_starts_at'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Scheduled Start Date'))
      ->setDescription(t('If not specified, the video will be Available Immediately.'))
      //->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'datetime_default',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['schedule_ends_at'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Scheduled End Date'))
      ->setDescription(t('If not specified, the video will have No End Date.'))
      //->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'datetime_default',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['text_tracks'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Text Tracks'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDescription(t('Referenced text tracks which belong to the video.'))
      ->setSetting('target_type', 'brightcove_text_track')
      ->setDisplayOptions('form', [
        'type' => 'brightcove_inline_entity_form_complex',
        'settings' => [
          'allow_new' => TRUE,
          'allow_existing' => FALSE,
        ],
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The username of the Brightcove Video author.'))
      //->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValueCallback('Drupal\brightcove\Entity\BrightcoveVideo::getCurrentUserId')
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => ++$weight,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'author',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the Brightcove Video was created.'))
      //->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => ++$weight,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the Brightcove Video was last edited.'))
      //->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['force_ads'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Force Ads'))
      //->setRevisionable(TRUE)
      ->setDefaultValue(FALSE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['geo_countries'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Geo-filtering Country List'))
      ->setDescription(t('ISO-3166 country code list.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      //->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 5,
        'text_processing' => 0,
      ])
      // FIXME: Use a dropdown to select the country code/country name instead.
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: string_textfield.
        'weight' => ++$weight,
      ])
      // FIXME: Display the country name instead.
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: string.
        'label' => 'above',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['geo_restricted'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Geo-filtering On'))
      //->setRevisionable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: boolean_checkbox.
        'weight' => ++$weight,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: string.
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['geo_exclude_countries'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Geo-filtering Options: Exclude countries'))
      ->setDescription(t('If enabled, country list is treated as a list of countries excluded from viewing.'))
      //->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: boolean_checkbox.
        'weight' => ++$weight,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: string.
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['logo_alignment'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Logo Overlay Alignment'))
      ->setCardinality(4)
      //->setRevisionable(TRUE)
      ->setSetting('allowed_values', [
        'top_left' => 'Top Left',
        'top_right' => 'Top Right',
        'bottom_left' => 'Bottom Left',
        'bottom_right' => 'Bottom Right',
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: options_select.
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: string.
        'label' => 'above',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['logo_image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Logo Overlay Image'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'file_extensions' => 'png gif',
        'file_directory' => '[random:hash:md5]',
        'alt_field' => FALSE,
        'alt_field_required' => FALSE,
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: image_image.
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: image.
        'label' => 'above',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['logo_tooltip'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Logo Overlay Tooltip'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: string_textfield.
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: string.
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['logo_url'] = BaseFieldDefinition::create('link')
      ->setLabel(t('Logo Overlay URL'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 128,
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: link_default.
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: link.
        'label' => 'inline',
        'weight' => $weight,
        'settings' => [
          'trim_length' => 128,
          'target' => '_blank',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['bumper_video'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Bumper Video'))
      ->setDescription(t('FLV or H264 video file to playback before the Video content'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'file_extensions' => 'flv',
        'file_directory' => '[random:hash:md5]',
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: file_generic.
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: file_url_plain.
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // FIXME: Couldn't find this on the Brightcove UI: https://studio.brightcove.com/products/videocloud/media/videos/4585854207001
    $fields['viral'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Viral Distribution'))
      ->setDescription(t('Enables the get code and blogging menu options for the video'))
      //->setRevisionable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'hidden', // Usable default: boolean_checkbox.
        'weight' => ++$weight,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden', // Usable default: string.
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // FIXME: Cue points?
    return $fields;
  }

  /**
   * Gets the allowed values for video profile.
   *
   * @param string $api_client
   *   The API Client ID.
   *
   * @return array
   *   The list of profiles.
   */
  public static function getProfileAllowedValues($api_client) {
    $profiles = [];

    if (!empty($api_client)) {
      $cid = 'brightcove:video:profiles:' . $api_client;

      // If we have a hit in the cache, return the results.
      if ($cache = \Drupal::cache()->get($cid)) {
        $profiles = $cache->data;
      }
      // Otherwise download the profiles from brightcove and cache the results.
      else {
        /** @var \Drupal\brightcove\Entity\BrightcoveAPIClient $api_client_entity */
        $api_client_entity = BrightcoveAPIClient::load($api_client);

        try {
          if (!is_null($api_client_entity)) {
            $client = $api_client_entity->getClient();
            $json = $client->request('GET', 'ingestion', $api_client_entity->getAccountID(), '/profiles', NULL);

            foreach ($json as $profile) {
              $profiles[$profile['id']] = $profile['name'];
            }

            // Order profiles by value.
            asort($profiles);

            // Save the results to cache.
            \Drupal::cache()->set($cid, $profiles);
          }
          else {
            $profiles[] = t('Error: unable to fetch the list');
          }
        }
        catch (APIException $exception) {
          $profiles[] = t('Error: unable to fetch the list');
          watchdog_exception('brightcove', $exception);
        }
      }
    }

    return $profiles;
  }

  /**
   * Implements callback_allowed_values_function().
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   *   The field storage definition.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   (optional) The entity context if known, or NULL if the allowed values
   *   are being collected without the context of a specific entity.
   * @param bool &$cacheable
   *   (optional) If an $entity is provided, the $cacheable parameter should be
   *   modified by reference and set to FALSE if the set of allowed values
   *   returned was specifically adjusted for that entity and cannot not be
   *   reused for other entities. Defaults to TRUE.
   *
   * @return array
   *   The array of allowed values. Keys of the array are the raw stored values
   *   (number or text), values of the array are the display labels. If $entity
   *   is NULL, you should return the list of all the possible allowed values
   *   in any context so that other code (e.g. Views filters) can support the
   *   allowed values for all possible entities and bundles.
   */
  public static function profileAllowedValues(FieldStorageDefinitionInterface $definition, FieldableEntityInterface $entity = NULL, &$cacheable = TRUE) {
    $profiles = [];

    if ($entity instanceof BrightcoveVideo) {
      // Collect profiles for all of the api clients if the ID is not set.
      if (empty($entity->id())) {
        $api_clients = \Drupal::entityQuery('brightcove_api_client')
          ->execute();

        foreach ($api_clients as $api_client_id) {
          $profiles[$api_client_id] = self::getProfileAllowedValues($api_client_id);
        }
      }
      // Otherwise just return the results for the given api client.
      else {
        $profiles = self::getProfileAllowedValues($entity->getAPIClient());
      }
    }

    return $profiles;
  }

  /**
   * Create or update an existing video from a Brightcove Video object.
   *
   * @param \Brightcove\Object\Video\Video $video
   *   Brightcove Video object.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   EntityStorage.
   * @param int|NULL $api_client_id
   *   The ID of the BrightcoveAPIClient entity.
   *
   * @return int
   *   The saved BrightcoveVideo entity ID.
   *
   * @throws \Exception
   *   If BrightcoveAPIClient ID is missing when a new entity is being created.
   */
  public static function createOrUpdate(Video $video, EntityStorageInterface $storage, $api_client_id = NULL) {
    // Try to get an existing video.
    $existing_video = $storage->getQuery()
      ->condition('video_id', $video->getId())
      ->execute();

    $needs_save = FALSE;

    // Update existing video.
    if (!empty($existing_video)) {
      // Load Brightcove Video.
      /** @var BrightcoveVideo $video_entity */
      $video_entity = self::load(reset($existing_video));

      // Update video if it is changed on Brightcove.
      if ($video_entity->getChangedTime() < strtotime($video->getUpdatedAt())) {
        $needs_save = TRUE;
      }
    }
    // Create video if it does not exist.
    else {
      // Make sure we got an api client id when a new video is being created.
      if (is_null($api_client_id)) {
        throw new \Exception(t('To create a new BrightcoveVideo entity, the api_client_id must be given.'));
      }

      // Create new Brightcove video entity.
      $values = [
        'video_id' => $video->getId(),
        'api_client' => [
          'target_id' => $api_client_id,
        ],
        'created' => strtotime($video->getCreatedAt()),
      ];
      $video_entity = self::create($values);
      $needs_save = TRUE;
    }

    // Save entity only if it is being created or updated.
    if ($needs_save) {
      // Save or update changed time.
      $video_entity->setChangedTime(strtotime($video->getUpdatedAt()));

      // Save or update Description field if needed.
      if ($video_entity->getDescription() != ($description = $video->getDescription())) {
        $video_entity->setDescription($description);
      }

      // Save or update duration field if needed.
      if ($video_entity->getDuration() != ($duration = $video->getDuration())) {
        $video_entity->setDuration($duration);
      }

      // Save or update economics field if needed.
      if ($video_entity->getEconomics() != ($economics = $video->getEconomics())) {
        $video_entity->setEconomics($economics);
      }

      // Save or update tags field if needed.
      BrightcoveUtil::saveOrUpdateTags($video_entity, $api_client_id, $video->getTags());

      // Get images.
      $images = $video->getImages();

      // Save or update thumbnail image if needed.
      if (!empty($images[self::IMAGE_TYPE_THUMBNAIL]) && !empty($images[self::IMAGE_TYPE_THUMBNAIL]->getSrc())) {
        $video_entity->saveImage(self::IMAGE_TYPE_THUMBNAIL, $images[self::IMAGE_TYPE_THUMBNAIL]);
      }
      // Otherwise leave empty or remove thumbnail from BrightcoveVideo entity.
      else {
        // Delete file.
        $video_entity->setThumbnail(NULL);
      }

      // Save or update poster image if needed.
      if (!empty($images[self::IMAGE_TYPE_POSTER]) && !empty($images[self::IMAGE_TYPE_POSTER]->getSrc())) {
        $video_entity->saveImage(self::IMAGE_TYPE_POSTER, $images[self::IMAGE_TYPE_POSTER]);
      }
      // Otherwise leave empty or remove poster from BrightcoveVideo entity.
      else {
        // Delete file.
        $video_entity->setPoster(NULL);
      }

      // Save or update link url field if needed.
      $link = $video->getLink();
      $related_link_field = $video_entity->getRelatedLink() ?: NULL;
      $related_link = [];
      if (empty($related_link_field) && !empty($link)) {
        $related_link['uri'] = $link->getUrl();
        $related_link['title'] = $link->getText();
      }
      else if (!empty($related_link_field) && !empty($link)) {
        if ($related_link_field['uri'] != ($url = $link->getUrl())) {
          $related_link['uri'] = $url;
        }

        if ($related_link_field['title'] != ($title = $link->getText())) {
          $related_link['title'] = $title;
        }
      }
      else {
        $video_entity->setRelatedLink(NULL);
      }
      if (!empty($related_link)) {
        $video_entity->setRelatedLink($related_link);
      }

      // Save or update long description if needed.
      if ($video_entity->getLongDescription() != ($long_description = $video->getLongDescription())) {
        $video_entity->setLongDescription($long_description);
      }

      // Save or update Name field if needed.
      if ($video_entity->getName() != ($name = $video->getName())) {
        $video_entity->setName($name);
      }

      // Save or update reference ID field if needed.
      if ($video_entity->getReferenceID() != ($reference_id = $video->getReferenceId())) {
        $video_entity->setReferenceID($reference_id);
      }

      // Save or update custom field values.
      if ($video_entity->getCustomFieldValues() != $custom_fields = $video->getCustomFields()) {
        $video_entity->setCustomFieldValues($custom_fields);
      }

      // Save or update schedule dates if needed.
      $schedule = $video->getSchedule();
      if (!is_null($schedule)) {
        if ($video_entity->getScheduleStartsAt() != ($starts_at = $schedule->getStartsAt())) {
          $video_entity->setScheduleStartsAt(BrightcoveUtil::convertDate($starts_at));
        }
        if ($video_entity->getScheduleEndsAt() != ($ends_at = $schedule->getEndsAt())) {
          $video_entity->setScheduleEndsAt(BrightcoveUtil::convertDate($ends_at));
        }
      }
      else {
        $video_entity->setScheduleStartsAt(NULL);
        $video_entity->setScheduleEndsAt(NULL);
      }

      // Save or update state.
      // We are settings the video as published only if the state is ACTIVE,
      // otherwise it is set as unpublished.
      $state = $video->getState() == self::STATE_ACTIVE ? TRUE : FALSE;
      if ($video_entity->isPublished() != $state) {
        $video_entity->setPublished($state);
      }

      // Save video entity.
      $video_entity->save();
    }

    return $video_entity;
  }
}
