<?php

namespace Drupal\brightcove;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

interface BrightcoveCMSEntityInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {
  /**
   * Gets the Brightcove CMS entity name.
   *
   * @return string
   *   Name of the Brightcove CMS entity.
   */
  public function getName();

  /**
   * Sets the Brightcove CMS entity name.
   *
   * @param string $name
   *   The Brightcove CMS entity name.
   *
   * @return \Drupal\brightcove\BrightcoveCMSEntityInterface
   *   The called Brightcove CMS entity.
   */
  public function setName($name);

  /**
   * Returns the Brightcove Client API target ID.
   *
   * return int
   *   Target ID of the Brightcove Client API.
   */
  public function getAPIClient();

  /**
   * Sets the Brightcove Client API target ID.
   *
   * @param int $api_client
   *   Target ID of the Brightcove Client API.
   *
   * @return \Drupal\brightcove\BrightcoveCMSEntityInterface
   *   The called Brightcove CMS entity.
   */
  public function setAPIClient($api_client);

  /**
   * Returns the description.
   *
   * @return string
   *   The description of the CMS entity.
   */
  public function getDescription();

  /**
   * Sets the CMS entity's description.
   *
   * @param string $description
   *   The description of the CMS entity.
   *
   * @return \Drupal\brightcove\BrightcoveCMSEntityInterface
   *   The called Brightcove CMS entity.
   */
  public function setDescription($description);

  /**
   * Gets the Brightcove CMS entity creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Brightcove CMS entity.
   */
  public function getCreatedTime();

  /**
   * Sets the Brightcove CMS entity creation timestamp.
   *
   * @param int $timestamp
   *   The Brightcove CMS entity creation timestamp.
   *
   * @return \Drupal\brightcove\BrightcoveCMSEntityInterface
   *   The called Brightcove CMS entity.
   */
  public function setCreatedTime($timestamp);
}