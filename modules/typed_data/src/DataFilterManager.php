<?php

namespace Drupal\typed_data;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\typed_data\Annotation\DataFilter;

/**
 * Manager for data filter plugins.
 *
 * @see \Drupal\typed_data\DataFilterInterface
 */
class DataFilterManager extends DefaultPluginManager implements DataFilterManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, ModuleHandlerInterface $module_handler, $plugin_definition_annotation_name = DataFilter::class) {
    $this->alterInfo('typed_data_filter');
    parent::__construct('Plugin/TypedDataFilter', $namespaces, $module_handler, DataFilterInterface::class, $plugin_definition_annotation_name);
  }

}
