<?php

namespace Drupal\brightcove;

use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Class EntityChangedFieldsTrait
 *
 * @package Drupal\brightcove
 */
trait EntityChangedFieldsTrait {
  /**
   * Changed fields.
   *
   * @var bool[]
   */
  protected $changedFields;

  /**
   * Has changed field or not.
   *
   * @var bool
   */
  protected $hasChangedField = FALSE;

  /**
   * Returns whether the field is changed or not.
   *
   * @param $name
   *   The name of the field on the entity.
   *
   * @return bool
   *   The changed status of the field, TRUE if changed, FALSE otherwise.
   */
  public function isFieldChanged($name) {
    // Indicate that there is at least one changed field.
    if (!$this->changedFields) {
      $this->changedFields = TRUE;
    }

    return !empty($this->changedFields[$name]);
  }

  /**
   * Checked if the Entity has a changed field or not.
   *
   * @return bool
   */
  public function hasChangedField() {
    return $this->hasChangedField;
  }

  /**
   * Check for updated fields, ideally it should be called from the entity's
   * preSave() method before the parent's preSave() call.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   */
  public function checkUpdatedFields(EntityStorageInterface $storage) {
    // Collect object getters.
    $methods = [];
    foreach (get_class_methods($this) as $key => $method) {
      // Create a matchable key for the get methods.
      if (preg_match('/^(?:get|is)[\w\d_]+$/i', $method)) {
        $methods[strtolower($method)] = $method;
      }
    }

    // Check fields if they were updated and mark them if changed.
    if (!empty($this->id())) {
      /** @var \Drupal\brightcove\Entity\BrightcoveVideo $original_entity */
      $original_entity = $storage->loadUnchanged($this->id());

      if ($original_entity->getChangedTime() != $this->getChangedTime()) {
        /**
         * @var string $name
         * @var \Drupal\Core\Field\FieldItemList $field
         */
        foreach ($this->getFields() as $name => $field) {
          $getter = $this->getGetterName($name, $methods);

          // If the getter is available for the field then compare the two
          // field and if changed mark it.
          if (!is_null($getter) && $this->$getter() != $original_entity->$getter()) {
            $this->changedFields[$name] = TRUE;
          }
        }
      }
    }
    // If there is no original entity, mark all fields modified, because in
    // this case the entity is being created.
    else {
      foreach ($this->getFields() as $name => $field) {
        if (!is_null($this->getGetterName($name, $methods))) {
          $this->changedFields[$name] = TRUE;
        }
      }
    }
  }

  /**
   * Get getter method from the name of the field.
   *
   * @param string $name
   *   The name of the field.
   * @param array $methods
   *   The available methods.
   *
   * @return string
   *   The name of the getter function.
   */
  public function getGetterName($name, array $methods) {
    $function_part_name = $name;

    // Get entity key's status field alias.
    $status = self::getEntityType()->getKey('status');

    // Use the correct function for the status field.
    if ($name == $status) {
      $function_part_name = 'published';
    }

    // Acquire getter method name.
    $getter_name = 'get' . str_replace('_', '', $function_part_name);
    $is_getter_name = 'is' . str_replace('_', '', $function_part_name);

    $getter = NULL;
    if (isset($methods[$getter_name])) {
      $getter = $methods[$getter_name];
    }
    elseif (isset($methods[$is_getter_name])) {
      $getter = $methods[$is_getter_name];
    }

    return $getter;
  }
}
