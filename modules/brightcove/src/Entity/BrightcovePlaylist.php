<?php

namespace Drupal\brightcove\Entity;

use Brightcove\Object\Playlist;
use Drupal\brightcove\BrightcoveUtil;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\brightcove\BrightcovePlaylistInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Defines the Brightcove Playlist.
 *
 * @ingroup brightcove
 *
 * @ContentEntityType(
 *   id = "brightcove_playlist",
 *   label = @Translation("Brightcove Playlist"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\brightcove\BrightcovePlaylistListBuilder",
 *     "views_data" = "Drupal\brightcove\Entity\BrightcovePlaylistViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\brightcove\Form\BrightcovePlaylistForm",
 *       "add" = "Drupal\brightcove\Form\BrightcovePlaylistForm",
 *       "edit" = "Drupal\brightcove\Form\BrightcovePlaylistForm",
 *       "delete" = "Drupal\brightcove\Form\BrightcoveEntityDeleteForm",
 *     },
 *     "access" = "Drupal\brightcove\Access\BrightcovePlaylistAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\brightcove\BrightcovePlaylistHtmlRouteProvider",
 *     },
 *     "inline_form" = "Drupal\brightcove\Form\BrightcoveInlineForm",
 *   },
 *   base_table = "brightcove_playlist",
 *   admin_permission = "administer brightcove playlists",
 *   entity_keys = {
 *     "id" = "bcplid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/brightcove_playlist/{brightcove_playlist}",
 *     "add-form" = "/brightcove_playlist/add",
 *     "edit-form" = "/brightcove_playlist/{brightcove_playlist}/edit",
 *     "delete-form" = "/brightcove_playlist/{brightcove_playlist}/delete",
 *     "collection" = "/admin/content/brightcove_playlist",
 *   },
 *   field_ui_base_route = "brightcove_playlist.settings"
 * )
 */
class BrightcovePlaylist extends BrightcoveVideoPlaylistCMSEntity implements BrightcovePlaylistInterface {
  /**
   * Manual playlist type.
   */
  const TYPE_MANUAL = 0;

  /**
   * Smart playlist type.
   */
  const TYPE_SMART = 1;

  /**
   * Tag search condition "one_or_more".
   */
  const TAG_SEARCH_CONTAINS_ONE_OR_MORE = 'contains_one_or_more';

  /**
   * Tag search condition "all".
   */
  const TAG_SEARCH_CONTAINS_ALL = 'contains_all';

  /**
   * Get Playlist types.
   *
   * @see http://docs.brightcove.com/en/video-cloud/cms-api/references/playlist-fields-reference.html
   *
   * @param int|NULL $type Get specific type of playlist types.
   *   Possible values are TYPE_MANUAL, TYPE_SMART.
   *
   * @return array
   *   Manual and smart playlist types.
   *
   * @throws \Exception
   *   If an invalid type was given.
   */
  public static function getTypes($type = NULL) {
    $manual = [
      'EXPLICIT' => t('Manual: Add videos manually'),
    ];

    $smart_label = t('Smart: Add videos automatically based on tags')->render();
    $smart = [
      $smart_label => [
        'ACTIVATED_OLDEST_TO_NEWEST' => t('Smart: Activated Date (Oldest First)'),
        'ACTIVATED_NEWEST_TO_OLDEST' => t('Smart: Activated Date (Newest First)'),
        'ALPHABETICAL' => t('Smart: Video Name (A-Z)'),
        'PLAYS_TOTAL' => t('Smart: Total Plays'),
        'PLAYS_TRAILING_WEEK' => t('Smart: Trailing Week Plays'),
        'START_DATE_OLDEST_TO_NEWEST' => t('Smart: Start Date (Oldest First)'),
        'START_DATE_NEWEST_TO_OLDEST' => t('Smart: Start Date (Newest First)'),
      ],
    ];

    // Get specific type of playlist if set.
    if (!is_null($type)) {
      switch ($type) {
        case self::TYPE_MANUAL:
          return $manual;

        case self::TYPE_SMART:
          return reset($smart);

        default:
          throw new \Exception('The type must be either TYPE_MANUAL or TYPE_SMART');
      }
    }

    return $manual + $smart;
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
  public static function typeAllowedValues(FieldStorageDefinitionInterface $definition, FieldableEntityInterface $entity = NULL, &$cacheable = TRUE) {
    return self::getTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->get('type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setType($type) {
    return $this->set('type', $type);
  }

  /**
   * {@inheritdoc}
   */
  public function isFavorite() {
    return (bool) $this->get('favorite')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlaylistId() {
    return $this->get('playlist_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPlaylistId($playlist_id) {
    $this->set('playlist_id', $playlist_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTagsSearchCondition() {
    return $this->get('tags_search_condition')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTagsSearchCondition($condition) {
    return $this->set('tags_search_condition', $condition);
  }

  /**
   * {@inheritdoc}
   */
  public function getVideos() {
    return $this->get('videos')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setVideos($videos) {
    $this->set('videos', $videos);
    return $this;
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

    if ($upload) {
      $cms = BrightcoveUtil::getCMSAPI($this->getAPIClient());

      // Setup playlist object and set minimum required values.
      $playlist = new Playlist();
      $playlist->setName($this->getName());

      // Save or update type if needed.
      if ($this->isFieldChanged('type')) {
        $playlist->setType($this->getType());

        // Unset search if the playlist is manual.
        if ($playlist->getType() == 'EXPLICIT') {
          $playlist->setSearch('');
          $this->setTags([]);
        }
        // Otherwise if the playlist is smart, unset video references.
        else {
          $playlist->setVideoIds([]);
          $this->setVideos([]);
        }
      }

      // Save or update description if needed.
      if ($this->isFieldChanged('description')) {
        $playlist->setDescription($this->getDescription());
      }

      // Save or update reference ID if needed.
      if ($this->isFieldChanged('reference_id')) {
        $playlist->setReferenceId($this->getReferenceID());
      }

      // Save or update search if needed.
      if ($this->isFieldChanged('tags') || $this->isFieldChanged('tags_search_condition')) {
        $condition = '';
        if ($this->getTagsSearchCondition() == self::TAG_SEARCH_CONTAINS_ALL) {
          $condition = '+';
        }

        $tags = '';
        if (!empty($tag_items = $this->getTags())) {
          if (count($tag_items) == 1) {
            $this->setTagsSearchCondition(self::TAG_SEARCH_CONTAINS_ALL);
          }

          $tags .= $condition . 'tags:"';
          $first = TRUE;
          foreach ($tag_items as $tag) {
            $tag_term = Term::load($tag['target_id']);
            $tags .= ($first ? '' : '","') . $tag_term->getName();
            if ($first) {
              $first = FALSE;
            }
          }
          $tags .= '"';
        }

        $playlist->setSearch($tags);
      }

      // Save or update videos list if needed.
      if ($this->isFieldChanged('videos')) {
        $video_entities = $this->getVideos();
        $videos = [];
        foreach ($video_entities as $video) {
          $videos[] = BrightcoveVideo::load($video['target_id'])->getVideoId();
        }

        $playlist->setVideoIds($videos);
      }

      // Create or update a playlist.
      switch ($status) {
        case SAVED_NEW:
          // Create new playlist on Brightcove.
          $saved_playlist = $cms->createPlaylist($playlist);

          // Set the rest of the fields on BrightcoveVideo entity.
          $this->setPlaylistId($saved_playlist->getId());
          $this->setCreatedTime(strtotime($saved_playlist->getCreatedAt()));
          break;

        case SAVED_UPDATED:
          // Set playlist ID.
          $playlist->setId($this->getPlaylistId());

          // Update playlist.
          $saved_playlist = $cms->updatePlaylist($playlist);
          break;
      }

      // Update changed time and playlist entity with the video ID.
      if (isset($saved_playlist)) {
        $this->setChangedTime(strtotime($saved_playlist->getUpdatedAt()));

        // Save the entity again to save some new values which are only
        // available after creating/updating the playlist on Brightcove.
        // Also don't change the save state to show the correct message when
        // the entity is created or updated.
        parent::save();
      }
    }

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
    // Delete playlist from Brightcove.
    if (!$this->isNew() && !$local_only) {
      $cms = BrightcoveUtil::getCMSAPI($this->getAPIClient());
      $cms->deletePlaylist($this->getPlaylistId());
    }

    parent::delete();
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
     * bcplid - Brightcove Playlist ID (Drupal-internal).
     * uuid - UUID.
     * - "Playlist type" comes here, but that's a Brightcove-specific field.
     * - Title comes here, but that's the "Name" field from Brightcove.
     * langcode - Language.
     * api_client - Entityreference to BrightcoveAPIClient.
     * - Brightcove fields come here.
     * uid - Author.
     * created - Posted.
     * changed - Last modified.
     */
    $fields['bcplid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The Drupal entity ID of the Brightcove Playlist.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The Brightcove Playlist UUID.'))
      ->setReadOnly(TRUE);

    $fields['api_client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('API Client'))
      ->setDescription(t('Brightcove API credentials (account) to use.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'brightcove_api_client')
      ->setDisplayOptions('form', array(
        'type' => 'options_select',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('view', array(
        'type' => 'hidden',
        'label' => 'inline',
        'weight' => $weight,
      ))
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

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Playlist Type'))
//      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setDefaultValue('EXPLICIT')
      ->setSetting('allowed_values_function', [self::class, 'typeAllowedValues'])
      ->setDisplayOptions('form', array(
        'type' => 'options_select',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('view', array(
        'type' => 'list_default',
        'label' => 'inline',
        'weight' => $weight,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Playlist name'))
      ->setDescription(t('Title of the playlist.'))
//      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setSettings(array(
        'max_length' => 250,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('view', array(
        'type' => 'string',
        'label' => 'hidden',
        'weight' => $weight,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the Brightcove Video.'))
//      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'language_select',
        'weight' => ++$weight,
      ))
      ->setDisplayConfigurable('form', TRUE);

    /**
     * Additional Brightcove fields, based on
     * @see http://docs.brightcove.com/en/video-cloud/cms-api/references/cms-api/versions/v1/index.html#api-playlistGroup-Get_Playlists
     *
     * description - string - Playlist description
     * favorite - boolean - Whether playlist is in favorites list
     * playlist_id - string - The playlist id
     * name - string - The playlist name
     * reference_id - string - The playlist reference id
     * type - string - The playlist type: EXPLICIT or smart playlist type
     * videos - entityref/string array of video ids (EXPLICIT playlists only)
     * search - string - Search string to retrieve the videos (smart playlists
     *   only)
     */
    $fields['favorite'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Show Playlist in Sidebar'))
      ->setDescription(t('Whether playlist is in favorites list'))
//      ->setRevisionable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', array(
        'type' => 'string',
        'label' => 'inline',
        'weight' => ++$weight,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['playlist_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Playlist ID'))
      ->setDescription(t('Unique Playlist ID assigned by Brightcove.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', array(
        'type' => 'string',
        'label' => 'inline',
        'weight' => ++$weight,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['reference_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference ID'))
      ->addConstraint('UniqueField')
      ->setDescription(t('Value specified must be unique'))
//      ->setRevisionable(TRUE)
      ->setSettings(array(
        'max_length' => 150,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('view', array(
        'type' => 'string',
        'label' => 'inline',
        'weight' => $weight,
      ))
      ->setDefaultValueCallback(static::class . '::getDefaultReferenceId')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Short description'))
//      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'string_textarea',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('view', array(
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => $weight,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->addPropertyConstraints('value', [
        'Length' => [
          'max' => 250,
        ],
      ]);

    $fields['tags_search_condition'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Tags search condition'))
      ->setRequired(TRUE)
      //->setRevisionable(TRUE)
      ->setDefaultValue(self::TAG_SEARCH_CONTAINS_ONE_OR_MORE)
      ->setSetting('allowed_values', [
        self::TAG_SEARCH_CONTAINS_ONE_OR_MORE => t('contains one or more'),
        self::TAG_SEARCH_CONTAINS_ALL => t('contains all'),
      ])
      ->setDisplayOptions('form', array(
        'type' => 'options_select',
        'weight' => ++$weight,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tags'))
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
      ->setDisplayOptions('view', array(
        'type' => 'entity_reference_label',
        'label' => 'above',
        'weight' => $weight,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['videos'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Videos'))
      ->setDescription(t('Videos in the playlist.'))
      //->setDefaultValue('')
//      ->setRevisionable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSettings([
        'target_type' => 'brightcove_video',
        'handler' => 'views',
        'handler_settings' => [
          'view' => [
            'view_name' => 'brightcove_videos_by_api_client',
            'display_name' => 'videos_entity_reference',
            'arguments' => [],
          ],
        ],
      ])
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('view', array(
        'type' => 'string',
        'label' => 'above',
        'weight' => $weight,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The username of the Brightcove Playlist author.'))
//      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\brightcove\Entity\BrightcovePlaylist::getCurrentUserId')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'author',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'weight' => $weight,
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ),
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Brightcove Playlist is published.'))
//      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the Brightcove Playlist was created.'))
//      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => ++$weight,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the Brightcove Playlist was last edited.'))
//      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * Converts videos from \Brightcove\Object\Playlist to Drupal Field API array.
   *
   * @param \Brightcove\Object\Playlist $playlist
   *   The playlist whose videos should be extracted.
   * @param \Drupal\Core\Entity\EntityStorageInterface $video_storage
   *   Video entity storage.
   *
   * @return array|null
   *   The Drupal Field API array that can be saved into a multivalue
   *   entity_reference field (like brightcove_playlist.videos), or NULL if the
   *   playlist does not have any videos.
   *
   * @throws \Exception
   *   Thrown when any of the videos is unavailable on the Drupal side.
   */
  protected static function extractVideosArray(Playlist $playlist, EntityStorageInterface $video_storage) {
    $videos = $playlist->getVideoIds();
    if (!$videos) {
      return NULL;
    }
    $return = [];
    foreach ($playlist->getVideoIds() as $video_id) {
      // Try to retrieve the video from Drupal's brightcove_video storage.
      $bcvid = $video_storage->getQuery()
        ->condition('video_id', $video_id)
        ->execute();
      if ($bcvid) {
        $bcvid = reset($bcvid);
        $return[] = ['target_id' => $bcvid];
      }
      else {
        // If the video is not found, then throw this exception, which will
        // effectively stop the queue worker, so the playlist with any "unknown"
        // video will remain in the queue, so it could be picked up the next
        // time hoping the video will be available by then.
        throw new \Exception(t('Playlist contains a video that is not (yet) available on the Drupal site'));
      }
    }
    return $return;
  }

  /**
   * Create or update an existing playlist from a Brightcove Playlist object.
   *
   * @param \Brightcove\Object\Playlist $playlist
   *   Brightcove Playlist object.
   * @param \Drupal\Core\Entity\EntityStorageInterface $playlist_storage
   *   Playlist EntityStorage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $video_storage
   *   Video EntityStorage.
   * @param int|NULL $api_client_id
   *   The ID of the BrightcoveAPIClient entity.
   *
   * @throws \Exception
   *   If BrightcoveAPIClient ID is missing when a new entity is being created.
   */
  public static function createOrUpdate(Playlist $playlist, EntityStorageInterface $playlist_storage, EntityStorageInterface $video_storage, $api_client_id = NULL) {
    // May throw an \Exception if any of the videos is not found.
    $videos = self::extractVideosArray($playlist, $video_storage);

    // Only save the brightcove_playlist entity if it's really updated or
    // created now.
    $needs_save = FALSE;

    // Try to get an existing playlist.
    $existing_playlist = $playlist_storage->getQuery()
      ->condition('playlist_id', $playlist->getId())
      ->execute();

    // Update existing playlist if needed.
    if (!empty($existing_playlist)) {
      // Load Brightcove Playlist.
      $playlist_entity_id = reset($existing_playlist);
      /** @var BrightcovePlaylist $playlist_entity */
      $playlist_entity = BrightcovePlaylist::load($playlist_entity_id);

      // Update playlist if it is changed on Brightcove.
      if ($playlist_entity->getChangedTime() < ($updated_at = strtotime($playlist->getUpdatedAt()))) {
        $needs_save = TRUE;
        // Update changed time.
        $playlist_entity->setChangedTime($updated_at);
      }
    }
    // Create playlist if it does not exist.
    else {
      $needs_save = TRUE;
      // Create new Brightcove Playlist entity.
      /** @var BrightcovePlaylist $playlist_entity */
      $playlist_entity = BrightcovePlaylist::create([
        'api_client' => [
          'target_id' => $api_client_id,
        ],
        'playlist_id' => $playlist->getId(),
        'created' => strtotime($playlist->getCreatedAt()),
        'changed' => strtotime($playlist->getUpdatedAt()),
      ]);
    }

    if ($needs_save) {
      // Update type field if needed.
      if ($playlist_entity->getType() != ($type = $playlist->getType())) {
        $playlist_entity->setType($type);
      }

      // Update name field if needed.
      if ($playlist_entity->getName() != ($name = $playlist->getName())) {
        $playlist_entity->setName($name);
      }

      // Update favorite field if needed.
      if ($playlist_entity->isFavorite() != ($favorite = $playlist->isFavorite())) {
        // This is a non-modifiable field so it does not have a specific
        // setter.
        $playlist_entity->set('favorite', $favorite);
      }

      // Update reference ID field if needed.
      if ($playlist_entity->getReferenceID() != ($reference_id = $playlist->getReferenceId())) {
        $playlist_entity->setReferenceID($reference_id);
      }

      // Update description field if needed.
      if ($playlist_entity->getDescription() != ($description = $playlist->getDescription())) {
        $playlist_entity->setDescription($description);
      }

      // Update tags field if needed.
      $playlist_search = $playlist->getSearch();
      preg_match('/^(\+?)([^\+].*):(?:,?"(.*?[^"])")$/i', $playlist_search, $matches);
      if (count($matches) == 4 && $matches[2] == 'tags') {
        $playlist_tags = explode('","', $matches[3]);

        // Save or update tag search condition if needed.
        $playlist_search_condition = $matches[1] == '+' ? self::TAG_SEARCH_CONTAINS_ALL : self::TAG_SEARCH_CONTAINS_ONE_OR_MORE;
        if ($playlist_entity->getTagsSearchCondition() != $playlist_search_condition) {
          $playlist_entity->setTagsSearchCondition($playlist_search_condition);
        }

        BrightcoveUtil::saveOrUpdateTags($playlist_entity, $api_client_id, $playlist_tags);
      }

      // Update videos field if needed.
      if ($playlist_entity->getVideos() != $videos) {
        $playlist_entity->setVideos($videos);
      }
      // @TODO: State/published.

      $playlist_entity->save();
    }
  }
}
