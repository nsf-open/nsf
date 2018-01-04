<?php

namespace Drupal\brightcove\Entity;

use Brightcove\Object\Video\TextTrack;
use Drupal\brightcove\EntityChangedFieldsTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\brightcove\BrightcoveTextTrackInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\link\LinkItemInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Brightcove Text Track entity.
 *
 * @ingroup brightcove
 *
 * @ContentEntityType(
 *   id = "brightcove_text_track",
 *   label = @Translation("Brightcove Text Track"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\brightcove\Entity\BrightcoveTextTrackViewsData",
 *
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\brightcove\Access\BrightcoveTextTrackAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "brightcove_text_track",
 *   entity_keys = {
 *     "id" = "bcttid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/brightcove_text_track/{brightcove_text_track}",
 *     "delete-form" = "/brightcove_text_track/{brightcove_text_track}/delete",
 *   },
 * )
 */
class BrightcoveTextTrack extends ContentEntityBase implements BrightcoveTextTrackInterface {
  use EntityChangedTrait;
  use EntityChangedFieldsTrait;

  const KIND_CAPTIONS = 'captions';
  const KIND_SUBTITLES = 'subtitles';
  const KIND_DESCRIPTION = 'descriptions';
  const KIND_CHAPTERS = 'chapters';
  const KIND_METADATA = 'metadata';

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    return $this->set('name', $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getWebVTTFile() {
    return $this->get('webvtt_file')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setWebVTTFile($file) {
    return $this->set('webvtt_file', $file);
  }

  /**
   * {@inheritdoc}
   */
  public function getTextTrackId() {
    return $this->get('text_track_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTextTrackId($text_track_id) {
    return $this->set('text_track_id', $text_track_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->get('source')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSource($source) {
    return $this->set('source', $source);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLanguage() {
    return $this->get('source_language')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceLanguage($source_language) {
    return $this->set('source_language', $source_language);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->get('label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    return $this->set('label', $label);
  }

  /**
   * {@inheritdoc}
   */
  public function getKind() {
    return $this->get('kind')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setKind($kind) {
    return $this->set('kind', $kind);
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType() {
    return $this->get('mime_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMimeType($mime_type) {
    return $this->set('mime_type', $mime_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getAssetId() {
    return $this->get('asset_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAssetId($asset_id) {
    return $this->set('asset_id', $asset_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getSources() {
    return $this->get('sources')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setSources($sources) {
    return $this->set('sources', $sources);
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault() {
    return (bool) $this->get('default_text_track')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefault($default) {
    // @TODO: Do some magic here to ensure only one default text track per
    //        video.
    return $this->set('default_text_track', $default);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    return $this->set('created', $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    return $this->set('uid', $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    return $this->set('uid', $account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    return $this->set('status', $published ? NODE_PUBLISHED : NODE_NOT_PUBLISHED);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $this->checkUpdatedFields($storage);

    // Generate name for the text track if the label is missing.
    if (empty($this->getLabel())) {
      $this->setName($this->getSourceLanguage());
    }
    // Otherwise set name as the label.
    else {
      $this->setName($this->getLabel());
    }

    return parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $weight = -30;

    $fields['bcttid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Brightcove Text Track entity.'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('Generated name for the Text Track.'))
      //->setRevisionable(TRUE)
      ->setRequired(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Brightcove Text Track entity.'))
      ->setReadOnly(TRUE);

    $fields['text_track_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Text Track ID'))
      ->setDescription(t('Unique Text Track ID assigned by Brightcove.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => ++$weight,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['webvtt_file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('WebVTT file'))
      ->setRequired(TRUE)
      //->setRevisionable(TRUE)
      ->setSettings([
        'file_extensions' => 'vtt',
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

    $fields['source'] = BaseFieldDefinition::create('link')
      ->setLabel(t('Source'))
      ->setDescription(t('Source text track.'))
      //->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 500,
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('view', [
        'type' => 'link',
        'label' => 'above',
        'weight' => $weight,
        'settings' => [
          'rel' => TRUE,
          'target' => '_blank',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['source_language'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Language'))
      //->setRevisionable(TRUE)
      ->setDescription(t('ISO-639-1 language code with optional ISO-3166 country name (en, en-US, de, de-DE).'))
      ->setRequired(TRUE)
      ->setSettings(array(
        'max_length' => 10,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'type' => 'string',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => $weight,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('Title to be displayed in the player menu.'))
      ->setSettings(array(
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setDefaultValue('')
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => ++$weight,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => $weight,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['kind'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Kind'))
      ->setDescription(t('How the vtt file is meant to be used.'))
      //->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setDefaultValue(self::KIND_CAPTIONS)
      ->setSetting('allowed_values', [
        self::KIND_CAPTIONS => 'captions',
        self::KIND_SUBTITLES => 'subtitles',
        self::KIND_DESCRIPTION => 'descriptions',
        self::KIND_CHAPTERS => 'chapters',
        self::KIND_METADATA => 'metadata',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => ++$weight,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['mime_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('MIME type'))
      ->setDescription(t('MIME type of the source text track.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => ++$weight,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['asset_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Asset ID'))
      ->setDescription(t('Asset ID assigned by Brightcove.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => ++$weight,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['sources'] = BaseFieldDefinition::create('link')
      ->setLabel(t('Sources'))
      ->setDescription(t('Address of the track file(s).'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      //->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 500,
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('view', [
        'type' => 'link',
        'label' => 'above',
        'weight' => $weight,
        'settings' => [
          'rel' => TRUE,
          'target' => '_blank',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['default_text_track'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Default'))
      ->setDescription(t('Setting this to true makes this the default captions file in the player menu.'))
//      ->setRevisionable(TRUE)
      ->setDefaultValue(FALSE)
      ->setSettings([
        'on_label' => t('Yes'),
        'off_label' => t('No'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => ++$weight,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => $weight,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Brightcove Text Track is published.'))
      ->setDefaultValue(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The username of the Brightcove Playlist author.'))
      //->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getCurrentUserId')
      ->setTranslatable(TRUE)
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

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the Brightcove Text Track entity.'))
      ->setDisplayOptions('form', array(
        'type' => 'language_select',
        'weight' => ++$weight,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * Create or update an existing text track from a Brightcove object.
   *
   * @param \Brightcove\Object\Video\TextTrack $text_track
   *   Brightcove Video object.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   EntityStorage.
   * @param int $video_entity_id
   *   The ID of the BrightcoveVideo entity.
   *
   * @return \Drupal\brightcove\Entity\BrightcoveTextTrack
   *   Created or update text track entity.
   *
   * @throws \Exception
   *   If BrightcoveVideo ID is missing when a new entity is being created or
   *   if the BrightcoveVideo cannot be found or loaded.
   */
  public static function createOrUpdate(TextTrack $text_track, EntityStorageInterface $storage, $video_entity_id) {
    /** @var \Drupal\brightcove\Entity\BrightcoveTextTrack $text_track_entity */
    $text_track_entity = BrightcoveTextTrack::loadByTextTrackId($text_track->getId());
    $text_track_needs_save = FALSE;

    if (!empty($video_entity_id)) {
      /** @var \Drupal\brightcove\Entity\BrightcoveVideo $video */
      $video = BrightcoveVideo::load($video_entity_id);
    }
    else {
      throw new \Exception('The $video_entity_id argument cannot be empty.');
    }

    // Get sources.
    $sources = $text_track->getSources();
    $field_sources = [];
    foreach ($sources as $source) {
      $field_sources[] = $source->getSrc();
    }

    // Update existing text track.
    if (!is_null($text_track_entity)) {
      // Update source field if needed.
      if ($text_track_entity->getSource() != ($source = $text_track->getSrc())) {
        $text_track_entity->setSource($source);
        $text_track_needs_save = TRUE;
      }

      // Update source language field if needed.
      if ($text_track_entity->getSourceLanguage() != ($source_language = $text_track->getSrclang())) {
        $text_track_entity->setSourceLanguage($source_language);
        $text_track_needs_save = TRUE;
      }

      // Update label field if needed.
      if ($text_track_entity->getLabel() != ($label = $text_track->getLabel())) {
        $text_track_entity->setLabel($label);
        $text_track_needs_save = TRUE;
      }

      // Update kind field if needed.
      if ($text_track_entity->getKind() != ($kind = $text_track->getKind())) {
        $text_track_entity->setKind($kind);
        $text_track_needs_save = TRUE;
      }

      // Update mime type field if needed.
      if ($text_track_entity->getMimeType() != ($mime_type = $text_track->getMimeType())) {
        $text_track_entity->setMimeType($mime_type);
        $text_track_needs_save = TRUE;
      }

      // Update sources if needed.
      $entity_sources = $text_track_entity->getSources();
      $entity_field_sources = [];
      foreach ($entity_sources as $entity_source) {
        $entity_field_sources[] = $entity_source['uri'];
      }
      if ($entity_field_sources != $field_sources) {
        $text_track_entity->setSources($field_sources);
        $text_track_needs_save = TRUE;
      }

      // Update default if needed.
      if ($text_track_entity->isDefault() != ($default = $text_track->isDefault())) {
        $text_track_entity->setDefault($default);
        $text_track_needs_save = TRUE;
      }
    }
    // Create new Text Track.
    else {
      // Build the new text track entity.
      $values = [
        'text_track_id' => $text_track->getId(),
        'source' => $text_track->getSrc(),
        'source_language' => $text_track->getSrclang(),
        'label' => $text_track->getLabel(),
        'kind' => $text_track->getKind(),
        'mime_type' => $text_track->getMimeType(),
        'asset_id' => $text_track->getAssetId(),
        'sources' => $field_sources,
        'default_text_track' => $text_track->isDefault(),
        'created' => $video->getCreatedTime(),
      ];
      $text_track_entity = BrightcoveTextTrack::create($values);
      $text_track_needs_save = TRUE;
    }

    // Save text track entity.
    if ($text_track_needs_save) {
      // Set the same changed time for the text track as the video.
      $text_track_entity->setChangedTime($video->getChangedTime());

      $text_track_entity->save();
    }

    // Add the current text track for the video if needed.
    $text_tracks = $video->getTextTracks();
    $exists = FALSE;
    foreach ($text_tracks as $text_track) {
      if ($text_track['target_id'] == $text_track_entity->id()) {
        $exists = TRUE;
        break;
      }
    }
    if (!$exists) {
      $text_tracks[] = [
        'target_id' => $text_track_entity->id(),
      ];
      $video->setTextTracks($text_tracks);
      $video->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += array(
      'uid' => \Drupal::currentUser()->id(),
      'name' => t('New text track'),
    );
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [
      \Drupal::currentUser()->id(),
    ];
  }

  /**
   * Load entity by the Text Track ID.
   *
   * @param string $id
   *   The ID of the Text Track provided by Brightcove.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface|NULL
   *   The loaded Text Track entity.
   */
  public static function loadByTextTrackId($id) {
    $entity_ids = \Drupal::entityQuery('brightcove_text_track')
      ->condition('text_track_id', $id)
      ->execute();

    if (empty($entity_ids)) {
      return NULL;
    }

    $entity_id = reset($entity_ids);

    return self::load($entity_id);
  }
}
