<?php

/**
 * @file
 * Contains \Drupal\time_formatter\Plugin\Field\FieldFormatter\TimeFieldFormatter.
 */

namespace Drupal\time_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'time_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "number_time",
 *   label = @Translation("Time"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class TimeFieldFormatter extends FormatterBase {

  /**
   * Denotes that the field value should be treated as number of seconds.
   */
  const STORAGE_SECONDS = 0;

  /**
   * Denotes that the field value should be treated as number of milliseconds.
   */
  const STORAGE_MILLISECONDS = 1;

  /**
   * Denotes that the field should be displayed as "123h 59m 59s 999ms".
   */
  const DISPLAY_HMSMS = 0;

  /**
   * Denotes that the field should be displayed as "123h 59m 59s".
   */
  const DISPLAY_HMS = 1;

  /**
   * Denotes that the field should be displayed as "123:59:59.999".
   */
  const DISPLAY_NUMBERSMS = 2;

  /**
   * Denotes that the field should be displayed as "123:59:59".
   */
  const DISPLAY_NUMBERS = 3;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'storage' => self::STORAGE_MILLISECONDS,
      'display' => self::DISPLAY_NUMBERSMS,
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return array(
      'storage' => [
        '#type' => 'select',
        '#title' => $this->t('Storage'),
        '#options' => [
          self::STORAGE_SECONDS => $this->t('Seconds'),
          self::STORAGE_MILLISECONDS => $this->t('Milliseconds'),
        ],
        '#default_value' => $this->getSetting('storage'),
      ],
      'display' => [
        '#type' => 'select',
        '#title' => $this->t('Storage'),
        '#options' => [
          self::DISPLAY_HMSMS => $this->t('123h 59m 59s 999ms'),
          self::DISPLAY_HMS => $this->t('123h 59m 59s'),
          self::DISPLAY_NUMBERSMS => $this->t('123:59:59.999'),
          self::DISPLAY_NUMBERS => $this->t('123:59:59'),
        ],
        '#default_value' => $this->getSetting('display'),
      ],
    ) + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    switch ($this->getSetting('storage')) {
      case self::STORAGE_SECONDS:
        $summary['storage'] = $this->t('Storage: Seconds');
        break;

      case self::STORAGE_MILLISECONDS:
        $summary['storage'] = $this->t('Storage: Milliseconds');
        break;
    }

    switch ($this->getSetting('display')) {
      case self::DISPLAY_HMSMS:
        $summary['display'] = $this->t('Display: 123h 59m 59s 999ms');
        break;

      case self::DISPLAY_HMS:
        $summary['display'] = $this->t('Display: 123h 59m 59s');
        break;

      case self::DISPLAY_NUMBERSMS:
        $summary['display'] = $this->t('Display: 123:59:59.999');
        break;

      case self::DISPLAY_NUMBERS:
        $summary['display'] = $this->t('Display: 123:59:59');
        break;
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = ['#markup' => $this->viewValue($item)];
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    $value = $item->value;
    if ($this->getSetting('storage') == self::STORAGE_SECONDS) {
      $value *= 1000;
    }

    $milliseconds = $value % 1000;
    $value = ($value - $milliseconds) / 1000;
    $seconds = $value % 60;
    $value = ($value - $seconds) / 60;
    $minutes = $value % 60;
    $value = ($value - $minutes) / 60;

    $return = 'N/A';
    switch ($this->getSetting('display')) {
      case self::DISPLAY_HMSMS:
        $return = "{$value}h {$minutes}m {$seconds}s {$milliseconds}ms";
        break;

      case self::DISPLAY_HMS:
        $return = "{$value}h {$minutes}m {$seconds}s";
        break;

      case self::DISPLAY_NUMBERSMS:
        $return = "{$value}:{$minutes}:{$seconds}.{$milliseconds}";
        break;

      case self::DISPLAY_NUMBERS:
        $return = "{$value}:{$minutes}:{$seconds}";
        break;
    }

    return $return;
  }

}
