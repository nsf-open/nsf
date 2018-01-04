<?php

namespace Drupal\brightcove\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Extends the inline form to pass along TRUE to the video/playlist save method.
 */
class BrightcoveInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  public function save(EntityInterface $entity) {
    $entity->save(TRUE);
  }

}
