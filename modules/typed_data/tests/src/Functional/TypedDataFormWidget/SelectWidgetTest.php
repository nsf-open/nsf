<?php

namespace Drupal\Tests\typed_data\Functional\TypedDataFormWidget;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\typed_data\Traits\BrowserTestHelpersTrait;
use Drupal\typed_data\Util\StateTrait;
use Drupal\typed_data\Widget\FormWidgetManagerTrait;

/**
 * Class SelectWidgetTest.
 *
 * @group typed_data
 *
 * @coversDefaultClass \Drupal\typed_data\Plugin\TypedDataFormWidget\SelectWidget
 */
class SelectWidgetTest extends BrowserTestBase {

  use BrowserTestHelpersTrait;
  use FormWidgetManagerTrait;
  use StateTrait;
  use TypedDataTrait;

  /**
   * The tested form widget.
   *
   * @var \Drupal\typed_data\Widget\FormWidgetInterface
   */
  protected $widget;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'typed_data',
    'typed_data_widget_test',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->widget = $this->getFormWidgetManager()->createInstance('select');
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable() {
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('any')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('binary')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('boolean')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('datetime_iso8601')));;
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('duration_iso8601')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('email')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('float')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('integer')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('string')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('timespan')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('timestamp')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('uri')));
    $this->assertFalse($this->widget->isApplicable(ListDataDefinition::create('string')));
    $this->assertFalse($this->widget->isApplicable(MapDataDefinition::create()));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('filter_format')));
  }

  /**
   * @covers ::form
   * @covers ::extractFormValues
   */
  public function testFormEditing() {
    $context_definition = ContextDefinition::create('filter_format')
      ->setLabel('Filter format')
      ->setDescription('Some example selection.');
    $this->getState()->set('typed_data_widgets.definition', $context_definition);

    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    $this->assertSession()->elementTextContains('css', 'label[for=edit-data-value]', $context_definition->getLabel());
    $this->assertSession()->elementTextContains('css', 'div[id=edit-data-value--description]', $context_definition->getDescription());
    $this->assertSession()->fieldValueEquals('data[value]', $context_definition->getDefaultValue());

    $this->getSession()->getPage()->selectFieldOption('data[value]', 'plain_text');
    $this->pressButton('Submit');

    $this->drupalGet($path);
    $this->assertSession()->fieldValueEquals('data[value]', 'plain_text');
  }

  /**
   * @covers ::form
   * @covers ::flagViolations
   */
  public function testValidation() {
    $context_definition = ContextDefinition::create('filter_format')
      ->setLabel('Filter format')
      ->setDescription('Some example selection.')
      ->setRequired(TRUE);
    $this->getState()->set('typed_data_widgets.definition', $context_definition);

    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    // Set the empty option and make sure it results in a violation.
    $this->fillField('data[value]', '');
    $this->pressButton('Submit');
    $this->assertSession()
      ->fieldExists('data[value]')
      ->hasClass('error');

    // Make sure the changes have not been saved also.
    $this->drupalGet($path);
    $this->assertSession()->fieldValueEquals('data[value]', $context_definition->getDefaultValue());
  }

}
