<?php

namespace Drupal\brightcove;

interface BrightcoveCustomFieldInterface {
  /**
   * Returns the Brightcove ID of the Custom Field.
   *
   * @return string
   *   Brightcove's Custom Field ID.
   */
  public function getCustomFieldId();

  /**
   * Sets the Brightcove ID of the Custom Field.
   *
   * @param string $custom_field_id
   *   The ID of the Custom Field on Brightcove.
   *
   * @return \Drupal\brightcove\BrightcoveCustomFieldInterface
   *   The called Brightcove Custom Field.
   */
  public function setCustomFieldId($custom_field_id);

  /**
   * Returns enum values.
   *
   * @return array
   *   The enum values in array.
   */
  public function getEnumValues();

  /**
   * Sets the enum values.
   *
   * @param array $enum_values
   *   The enum values array.
   *
   * @return \Drupal\brightcove\BrightcoveCustomFieldInterface
   *   The called Brightcove Custom Field.
   */
  public function setEnumValues($enum_values);

  /**
   * Returns whether the field is set to required or not.
   *
   * @return bool
   *   Whether the field is required or not.
   */
  public function isRequired();

  /**
   * Set the field's required value.
   *
   * @param bool $required
   *   TRUE if the field needs to be set, FALSE otherwise.
   *
   * @return \Drupal\brightcove\BrightcoveCustomFieldInterface
   *   The called Brightcove Custom Field.
   */
  public function setRequired($required);

  /**
   * Returns the type of the field.
   *
   * @return string
   *   The type of the field, which can be either 'enum' or 'string'.
   */
  public function getType();

  /**
   * Sets the type of the field.
   *
   * @param string $type
   *   The type of the field, it can be either 'enum' or 'string'.
   *
   * @return \Drupal\brightcove\BrightcoveCustomFieldInterface
   *   The called Brightcove Custom Field.
   */
  public function setType($type);
}