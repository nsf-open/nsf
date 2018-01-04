<?php

namespace Drupal\brightcove\Entity;

use Drupal\brightcove\BrightcoveCMSEntityInterface;
use Drupal\brightcove\EntityChangedFieldsTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\UserInterface;

/**
 * Common base class for CMS entities like Video and Playlist.
 */
abstract class BrightcoveCMSEntity extends ContentEntityBase implements BrightcoveCMSEntityInterface {
  use EntityChangedTrait;
  use EntityChangedFieldsTrait;

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
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAPIClient() {
    return $this->get('api_client')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setAPIClient($api_client) {
    $this->set('api_client', $api_client);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->set('description', $description);
    return $this;
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
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
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
    $this->set('uid', $uid);
    return $this;
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
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $this->checkUpdatedFields($storage);
    return parent::preSave($storage);
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
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * Load multiple CMS Entity for the given api client.
   *
   * @param string $api_client
   *   The ID of the BrightcoveAPIClient entity.
   * @param bool $status
   *   The status of the entity, if TRUE then published entities will be
   *   returned, otherwise the unpublished entities.
   *
   * @return \Drupal\brightcove\Entity\BrightcoveCMSEntity[]
   *   An array of BrightcoveCMSEntity objects.
   */
  public static function loadMultipleByAPIClient($api_client, $status = TRUE) {
    $repository = \Drupal::getContainer()->get('entity_type.repository');
    $entity_ids = \Drupal::entityQuery($repository->getEntityTypeFromClass(get_called_class()))
      ->condition('api_client', $api_client)
      ->condition('status', $status ? 1 : 0)
      ->execute();
    return self::loadMultiple($entity_ids);
  }
}
