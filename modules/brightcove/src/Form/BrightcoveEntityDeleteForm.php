<?php

namespace Drupal\brightcove\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

class BrightcoveEntityDeleteForm extends ContentEntityDeleteForm {
  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();

    if (!$entity->isDefaultTranslation()) {
      return $this->t('The @entity-type %label @language translation has been deleted.', [
        '@entity-type' => $entity->getEntityType()->getLabel(),
        '%label'       => $entity->label(),
        '@language'    => $entity->language()->getName(),
      ]);
    }

    return $this->t('The @entity-type %label has been deleted.', array(
      '@entity-type' => $entity->getEntityType()->getLabel(),
      '%label' => $entity->label(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();

    if (!$entity->isDefaultTranslation()) {
      return $this->t('Are you sure you want to delete the @language translation of the @entity-type %label?', array(
        '@language' => $entity->language()->getName(),
        '@entity-type' => $this->getEntity()->getEntityType()->getLabel(),
        '%label' => $this->getEntity()->label(),
      ));
    }

    return $this->t('Are you sure you want to delete the @entity-type %label?', array(
      '@entity-type' => $this->getEntity()->getEntityType()->getLabel(),
      '%label' => $this->getEntity()->label(),
    ));
  }
}
