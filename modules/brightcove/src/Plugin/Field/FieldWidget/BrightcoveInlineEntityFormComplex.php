<?php

namespace Drupal\brightcove\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;

/**
 * Complex inline widget.
 *
 * @FieldWidget(
 *   id = "brightcove_inline_entity_form_complex",
 *   label = @Translation("Brightcove Inline entity form - Complex"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = true
 * )
 */
class BrightcoveInlineEntityFormComplex extends InlineEntityFormComplex {
  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Wrap label in link.
    foreach (Element::children($element['entities']) as $key) {
      /** @var \Drupal\Core\Entity\Entity $entity */
      $entity = $element['entities'][$key]['#entity'];
      $child_element = &$element['entities'][$key];
      if (!empty($entity->id())) {
        $child_element['#label'] = Link::fromTextAndUrl($child_element['#label'], Url::fromRoute('entity.brightcove_text_track.canonical', [
          'brightcove_text_track' => $entity->id()
        ]));
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildEntityFormActions($element) {
    $parent_element = parent::buildEntityFormActions($element);

    // Override add/update button labels.
    if ($element['#op'] == 'add') {
      $save_label = t('Save Text Track');
    }
    else {
      $save_label = t('Update Text Track');
    }
    $parent_element['actions']['ief_' . $element['#op'] . '_save']['#value'] = $save_label;

    return $parent_element;
  }
}
