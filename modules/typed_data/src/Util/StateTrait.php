<?php

namespace Drupal\typed_data\Util;

use Drupal\Core\State\StateInterface;

/**
 * Helper for classes that need the state storage.
 */
trait StateTrait {

  /**
   * The state storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Sets the state storage.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state storage.
   *
   * @return $this
   */
  public function setState(StateInterface $state) {
    $this->state = $state;
    return $this;
  }

  /**
   * Gets the state storage.
   *
   * @return \Drupal\Core\State\StateInterface
   *   The state storage.
   */
  public function getState() {
    if (empty($this->state)) {
      $this->state = \Drupal::state();
    }

    return $this->state;
  }

}
