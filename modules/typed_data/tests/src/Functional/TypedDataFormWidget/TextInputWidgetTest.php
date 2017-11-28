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
 * Class TextInputWidgetTest.
 *
 * @group typed_data
 *
 * @coversDefaultClass \Drupal\typed_data\Plugin\TypedDataFormWidget\TextInputWidget
 */
class TextInputWidgetTest extends BrowserTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->widget = $this->getFormWidgetManager()->createInstance('text_input');
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable() {
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('any')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('binary')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('boolean')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('datetime_iso8601')));;
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('duration_iso8601')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('email')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('float')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('integer')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('string')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('timespan')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('timestamp')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('uri')));
    $this->assertFalse($this->widget->isApplicable(ListDataDefinition::create('string')));
    $this->assertFalse($this->widget->isApplicable(MapDataDefinition::create()));
  }

  /**
   * @covers ::form
   * @covers ::extractFormValues
   */
  public function testFormEditing() {
    $context_definition = ContextDefinition::create('string')
      ->setLabel('Example string')
      ->setDescription('Some example string')
      ->setDefaultValue('default1');
    $this->getState()->set('typed_data_widgets.definition', $context_definition);

    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    $this->assertSession()->elementTextContains('css', 'label[for=edit-data-value]', $context_definition->getLabel());
    $this->assertSession()->elementTextContains('css', 'div[id=edit-data-value--description]', $context_definition->getDescription());
    $this->assertSession()->fieldValueEquals('data[value]', $context_definition->getDefaultValue());

    $this->fillField('data[value]', 'jump');
    $this->pressButton('Submit');

    $this->drupalGet($path);
    $this->assertSession()->fieldValueEquals('data[value]', 'jump');
  }

  /**
   * @covers ::form
   * @covers ::flagViolations
   */
  public function testValidation() {
    $context_definition = ContextDefinition::create('string')
      ->setLabel('Example string')
      ->setDescription('Some example string')
      ->setDefaultValue('default1')
      ->addConstraint('Length', ['max' => 3]);
    $this->getState()->set('typed_data_widgets.definition', $context_definition);

    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    $this->fillField('data[value]', 'too-long');
    $this->pressButton('Submit');
    $this->assertSession()
      ->fieldExists('data[value]')
      ->hasClass('error');

    // Make sure the changes have not been saved also.
    $this->drupalGet($path);
    $this->assertSession()->fieldValueEquals('data[value]', $context_definition->getDefaultValue());
  }

}
