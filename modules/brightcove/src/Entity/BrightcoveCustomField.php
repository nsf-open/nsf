<?php

namespace Drupal\brightcove\Entity;

use Brightcove\Object\CustomField;
use Drupal\brightcove\BrightcoveCustomFieldInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Brightcove Custom Field.
 *
 * @ingroup brightcove
 *
 * @ContentEntityType(
 *   id = "brightcove_custom_field",
 *   label = @Translation("Brightcove Custom Field"),
 *   base_table = "brightcove_custom_field",
 *   entity_keys = {
 *     "id" = "bccfid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   }
 * )
 */
class BrightcoveCustomField extends BrightcoveCMSEntity implements BrightcoveCustomFieldInterface {
  /**
   * Field type string.
   */
  const TYPE_STRING = 'string';

  /**
   * Field type enum.
   */
  const TYPE_ENUM = 'enum';

  /**
   * {@inheritdoc}
   */
  public function getCustomFieldId() {
    return $this->get('custom_field_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomFieldId($custom_field_id) {
    return $this->set('custom_field_id', $custom_field_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getEnumValues() {
    return $this->get('enum_values')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setEnumValues($enum_values) {
    return $this->set('enum_values', $enum_values);
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired() {
    return $this->get('required')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequired($required) {
    return $this->set('required', $required);
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
    if (!in_array($type, [self::TYPE_STRING, self::TYPE_ENUM])) {
      throw new \Exception(t('Invalid field type.'));
    }

    return $this->set('type', $type);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['bccfid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The Drupal entity ID of the Brightcove Custom Field.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The Brightcove Custom Field UUID.'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Custom Field name'))
      ->setDescription(t('The name of the Brightcove Custom Field.'));
      //->setRevisionable(TRUE)

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the Brightcove Custom Field.'));
      //->setRevisionable(TRUE)

    $fields['api_client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('API Client'))
      ->setDescription(t('API Client where the Custom Field belongs.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'brightcove_api_client');

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The username of the Brightcove Custom Field author.'))
      //->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\brightcove\Entity\BrightcoveCustomField::getCurrentUserId')
      ->setTranslatable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the Brightcove Custom Field was created.'))
      //->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the Brightcove Custom Field was last edited.'))
      //->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      //->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setDefaultValue(TRUE)
      ->setSettings([
        'on_label' => t('Active'),
        'off_label' => t('Inactive'),
      ]);

    $fields['custom_field_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Custom Field ID'))
      ->setDescription(t('Unique Custom Field ID assigned by Brightcove.'))
      ->setReadOnly(TRUE);

    $fields['description'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Description'));
     //->setRevisionable(TRUE)

    $fields['enum_values'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Enum values'))
      ->setDescription(t('Max 150 enum value per custom field'))
      // We can't really say 150 here as it'd yield 150 textfields on the UI.
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
      //->setRevisionable(TRUE)

    $fields['required'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Required'))
      //->setRevisionable(TRUE)
      ->setDefaultValue(FALSE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'));
      //->setRevisionable(TRUE)

    return $fields;
  }

  /**
   * Create or update an existing custom field.
   *
   * @param \Brightcove\Object\CustomField $custom_field
   *   Brightcove Custom Field object.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Custom Field Entity storage.
   * @param int|NULL $api_client_id
   *   The ID of the BrightcoveAPIClient entity.
   *
   * @throws \Exception
   *   If BrightcoveAPIClient ID is missing when a new entity is being created.
   */
  public static function createOrUpdate(CustomField $custom_field, EntityStorageInterface $storage, $api_client_id = NULL) {
    // Try to get an existing custom field.
    $existing_custom_field = $storage->getQuery()
      ->condition('custom_field_id', $custom_field->getId())
      ->execute();

    // Update existing custom field.
    if (!empty($existing_custom_field)) {
      // Load Brightcove Custom Field.
      /** @var \Drupal\brightcove\Entity\BrightcoveCustomField $custom_field_entity */
      $custom_field_entity = self::load(reset($existing_custom_field));
    }
    // Create custom field if it does not exist.
    else {
      // Make sure we got an api client id when a new custom field is being
      // created.
      if (is_null($api_client_id)) {
        throw new \Exception(t('To create a new BrightcoveCustomField entity, the api_client_id must be given.'));
      }

      // Create new Brightcove custom field entity.
      $values = [
        'custom_field_id' => $custom_field->getId(),
        'api_client' => [
          'target_id' => $api_client_id,
        ],
      ];
      $custom_field_entity = self::create($values);
    }

    if ($custom_field_entity->getName() != ($enum_values = $custom_field->getDisplayName())) {
      $custom_field_entity->setName($enum_values);
    }

    if ($custom_field_entity->getDescription() != ($enum_values = $custom_field->getDescription())) {
      $custom_field_entity->setDescription($enum_values);
    }

    $enum_values = [];
    foreach ($custom_field_entity->getEnumValues() as $enum_value) {
      $enum_values[] = $enum_value['value'];
    }
    if (!empty(array_diff($enum_values, $custom_field_enum_values = ($custom_field->getEnumValues() ?: []))) || !empty(array_diff($custom_field_enum_values, $enum_values))) {
      $custom_field_entity->setEnumValues($custom_field_enum_values);
    }

    if ($custom_field_entity->isRequired() != ($required = $custom_field->isRequired())) {
      $custom_field_entity->setRequired($required);
    }

    if ($custom_field_entity->getType() != ($enum_values = $custom_field->getType())) {
      $custom_field_entity->setType($enum_values);
    }

    $custom_field_entity->save();
  }
}
