<?php

namespace Drupal\brightcove;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Brightcove Text Track entities.
 *
 * @ingroup brightcove
 */
interface BrightcoveTextTrackInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {
  /**
   * Returns the name of Text Track.
   *
   * @return string
   */
  public function getName();

  /**
   * Sets the name of the Text Track.
   *
   * @param string $name
   *   The name of the Text Track.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setName($name);

  /**
   * Returns the WebVTT file entity.
   *
   * @return array
   *   WebVTT file entity.
   */
  public function getWebVTTFile();

  /**
   * Sets the WebVTT file.
   *
   * @param array $file
   *   The WebVTT file entity.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setWebVTTFile($file);

  /**
   * Returns the Brightcove ID of the Text Track.
   *
   * @return string
   *   The Brightcove ID of the Text Track.
   */
  public function getTextTrackId();

  /**
   * Sets the Brightcove ID of the Text Track.
   *
   * @param string $text_track_id
   *   The Brightcove ID of the Text Track.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setTextTrackId($text_track_id);

  /**
   * Returns the source link.
   *
   * @return array
   *   The related link.
   */
  public function getSource();

  /**
   * Sets the source link.
   *
   * @param $source
   *   The related link.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setSource($source);

  /**
   * Returns the source language.
   *
   * @return string
   *   The 2-letter source language.
   */
  public function getSourceLanguage();

  /**
   * Sets the source language.
   *
   * @param string $source_language
   *   2-letter source language, eg.: hu, en.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setSourceLanguage($source_language);

  /**
   * Gets the Brightcove Text Track name.
   *
   * @return string
   *   Name of the Brightcove Text Track.
   */
  public function getLabel();

  /**
   * Sets the Brightcove Text Track name.
   *
   * @param string $label
   *   The Brightcove Text Track name.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called Brightcove Text Track entity.
   */
  public function setLabel($label);

  /**
   * Returns the Text Tracks's kind.
   *
   * @return string
   *   The kind of the Text Track.
   */
  public function getKind();

  /**
   * Sets the Text Tracks's kind.
   *
   * @param string $kind
   *   The Text Tracks's kind.
   *   Possible values are:
   *     - BrightcoveTextTrack::KIND_CAPTIONS
   *     - BrightcoveTextTrack::KIND_SUBTITLES
   *     - BrightcoveTextTrack::KIND_DESCRIPTION
   *     - BrightcoveTextTrack::KIND_CHAPTERS
   *     - BrightcoveTextTrack::KIND_METADATA
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setKind($kind);

  /**
   * Returns the mime type.
   *
   * @return string
   *   The mime type.
   */
  public function getMimeType();

  /**
   * Sets the mime type.
   *
   * @param string $mime_type
   *   The mime type.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setMimeType($mime_type);

  /**
   * Returns the asset ID.
   *
   * Only for managed text tracks.
   *
   * @return string
   *   Asset ID.
   */
  public function getAssetId();

  /**
   * Sets the Asset ID.
   *
   * @param string $asset_id
   *   The asset ID.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setAssetId($asset_id);

  /**
   * Returns a list of text track sources.
   *
   * @return array
   *   List of text track sources.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function getSources();

  /**
   * Set text track sources.
   *
   * @param array $sources
   *   Text track sources.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setSources($sources);

  /**
   * Whether or not the text track is default.
   *
   * @return bool
   *   TRUE if the text track is the default one, FALSE otherwise.
   */
  public function isDefault();

  /**
   * Set Text Track as default.
   *
   * @param bool $default
   *   TRUE or FALSE whether is the Text Track is the default one or not.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called BrightcoveTextTrack entity.
   */
  public function setDefault($default);

  /**
   * Gets the Brightcove Text Track creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Brightcove Text Track.
   */
  public function getCreatedTime();

  /**
   * Sets the Brightcove Text Track creation timestamp.
   *
   * @param int $timestamp
   *   The Brightcove Text Track creation timestamp.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called Brightcove Text Track entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Brightcove Text Track published status indicator.
   *
   * Unpublished Brightcove Text Track are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Brightcove Text Track is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Brightcove Text Track.
   *
   * @param bool $published
   *   TRUE to set this Brightcove Text Track to published, FALSE to set it to
   *   unpublished.
   *
   * @return \Drupal\brightcove\BrightcoveTextTrackInterface
   *   The called Brightcove Text Track entity.
   */
  public function setPublished($published);

}
