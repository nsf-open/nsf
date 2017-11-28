<?php

namespace Drupal\typed_data\Widget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for 'form widget' plugin implementations.
 */
abstract class FormWidgetBase extends PluginBase implements FormWidgetInterface, ContainerFactoryPluginInterface {

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('typed_data_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TypedDataManagerInterface $typedDataManager) {
    parent::__construct($configuration + $this->defaultConfiguration(), $plugin_id, $plugin_definition);
    $this->typedDataManager = $typedDataManager;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

}
