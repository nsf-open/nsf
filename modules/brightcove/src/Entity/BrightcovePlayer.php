<?php

namespace Drupal\brightcove\Entity;

use Brightcove\Object\Player\Player;
use Drupal\brightcove\BrightcovePlayerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Brightcove Player.
 *
 * @ingroup brightcove
 *
 * @ContentEntityType(
 *   id = "brightcove_player",
 *   label = @Translation("Brightcove Player"),
 *   base_table = "brightcove_player",
 *   entity_keys = {
 *     "id" = "bcpid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   }
 * )
 */
class BrightcovePlayer extends BrightcoveCMSEntity implements BrightcovePlayerInterface {
  /**
   * {@inheritdoc}
   */
  public function getPlayerId() {
    return $this->get('player_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPlayerId($player_id) {
    $this->set('player_id', $player_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isAdjusted() {
    return $this->get('adjusted')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAdjusted($adjusted) {
    return $this->set('adjusted', $adjusted);
  }

  /**
   * {@inheritdoc}
   */
  public function getHeight() {
    return $this->get('height')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeight($height) {
    return $this->set('height', $height);
  }

  /**
   * {@inheritdoc}
   */
  public function getWidth() {
    return $this->get('width')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setWidth($width) {
    return $this->set('width', $width);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['bcpid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The Drupal entity ID of the Brightcove Player.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The Brightcove Player UUID.'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Player name'))
      ->setDescription(t('The name of the Brightcove Player.'));
      //->setRevisionable(TRUE)

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the Brightcove Player.'));
      //->setRevisionable(TRUE)

    $fields['api_client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('API Client'))
      ->setDescription(t('API Client to use for the Player.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'brightcove_api_client');

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The username of the Brightcove Playlist author.'))
      //->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\brightcove\Entity\BrightcovePlayer::getCurrentUserId')
      ->setTranslatable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the Brightcove Player was created.'))
      //->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the Brightcove Player was last edited.'))
      //->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['player_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Player ID'))
      ->setDescription(t('Unique Player ID assigned by Brightcove.'))
      ->setReadOnly(TRUE);

    $fields['adjusted'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Adjusted'))
      ->setDescription(t('Indicates if player dimensions should be adjusted for playlist.'));

    $fields['height'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Adjusted'))
      ->setDescription(t('The height of the player.'));

    $fields['width'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Adjusted'))
      ->setDescription(t('The width of the player.'));

    return $fields;
  }

  /**
   * Create or update an existing player from a Brightcove Player object.
   *
   * @param \Brightcove\Object\Player\Player $player
   *   Brightcove Player object.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Player Entity storage.
   * @param int|NULL $api_client_id
   *   The ID of the BrightcoveAPIClient entity.
   *
   * @throws \Exception
   *   If BrightcoveAPIClient ID is missing when a new entity is being created.
   */
  public static function createOrUpdate(Player $player, EntityStorageInterface $storage, $api_client_id = NULL) {
    // Try to get an existing player.
    $existing_player = $storage->getQuery()
      ->condition('player_id', $player->getId())
      ->execute();

    $needs_save = FALSE;

    $branches = $player->getBranches();
    $master = $branches->getMaster();
    $configuration = $master->getConfiguration();
    $studio_configuration = $configuration->getStudioConfiguration();

    // Update existing player.
    if (!empty($existing_player)) {
      // Load Brightcove Player.
      /** @var BrightcovePlayer $player_entity */
      $player_entity = self::load(reset($existing_player));

      // Update player if it is changed on Brightcove.
      if ($player_entity->getChangedTime() < strtotime($master->getUpdatedAt())) {
        $needs_save = TRUE;
      }

      // Set player configs if they are set.
      if (!empty($studio_configuration)) {
        $player_config = $studio_configuration->getPlayer();

        // Save or update adjusted if needed.
        if ($player_entity->isAdjusted() != ($adjusted = $player_config->isAdjusted())) {
          $player_entity->setAdjusted($adjusted);
        }

        // Save or update height if needed.
        if ($player_entity->getHeight() != ($height = $player_config->getHeight())) {
          $player_entity->setHeight($height);
        }

        // Save or update width if needed.
        if ($player_entity->getWidth() != ($width = $player_config->getWidth())) {
          $player_entity->setWidth($width);
        }
      }
      else {
        // Remove configs if there are none set.
        $player_entity->setAdjusted(NULL);
        $player_entity->setHeight(NULL);
        $player_entity->setWidth(NULL);
      }
    }
    // Create player if it does not exist.
    else {
      // Make sure we got an api client id when a new player is being created.
      if (is_null($api_client_id)) {
        throw new \Exception(t('To create a new BrightcovePlayer entity, the api_client_id must be given.'));
      }

      // Create new Brightcove player entity.
      $values = [
        'player_id' => $player->getId(),
        'api_client' => [
          'target_id' => $api_client_id,
        ],
        'created' => strtotime($player->getCreatedAt()),
      ];

      // Set player settings.
      if (!empty($studio_configuration)) {
        $player_config = $studio_configuration->getPlayer();
        $values['adjusted'] = $player_config->isAdjusted();
        $values['height'] = $player_config->getHeight();
        $values['width'] = $player_config->getWidth();
      }

      $player_entity = self::create($values);
      $needs_save = TRUE;
    }

    // Save entity only if it is being created or updated.
    if ($needs_save) {
      // Save or update changed time.
      $player_entity->setChangedTime(strtotime($master->getUpdatedAt()));

      // Save or update Name field if needed.
      if ($player_entity->getName() != ($name = $player->getName())) {
        $player_entity->setName($name);
      }

      $player_entity->save();
    }
  }

  /**
   * Helper function to load entity by the Brightcove player ID.
   *
   * @param $player_id
   *   The Brightcove ID of the player.
   *
   * @return \Drupal\brightcove\Entity\BrightcovePlayer
   *   The loaded BrightcovePlayer.
   */
  public static function loadByPlayerId($player_id) {
    $eq = \Drupal::entityQuery('brightcove_player');
    $player = $eq->condition('player_id', $player_id)
      ->execute();
    return self::load(reset($player));
  }

  /**
   * Returns a list of players.
   *
   * @param NULL|array $api_client
   *   The API Client for which the players should be returned. If it's NULL,
   *   then only the default player will be returned.
   * @param bool $use_entity_id
   *   Whether to use the Entity's ID or Brightcove's ID for the player's key.
   *
   * @return array
   *   A list of player names keyed by their Brightcove ID or by the Entity ID
   *   if $use_entity_id is set.
   */
  public static function getList($api_client, $use_entity_id = FALSE) {
    // If use entity IDs set to true then don't add the default player
    // to the list, because it could be a non-existing Entity.
    if ($use_entity_id) {
      $players = [];
    }
    // Otherwise add the default player.
    else {
      $players = [
        BrightcoveAPIClient::DEFAULT_PLAYER => t('Brightcove Default Player'),
      ];
    }
    if (!$api_client) {
      return $players;
    }

    // Collect players referencing a given api client.
    $eq = \Drupal::entityQuery('brightcove_player');
    $eq->condition('api_client', $api_client);
    $player_ids = $eq->execute();
    /** @var \Drupal\brightcove\Entity\BrightcovePlayer $player */
    foreach (self::loadMultiple($player_ids) as $player) {
      $players[$use_entity_id ? $player->id() : $player->getPlayerId()] = $player->getName();
    }
    return $players;
  }
}
