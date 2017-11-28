<?php

/**
 * @file
 * Contains \Drupal\tour_ui\Tests\TourUITest.
 */

namespace Drupal\tour_ui\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the Tour UI.
 */
class TourUITest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('tour_ui', 'tour_test');

  public static function getInfo() {
    return array(
      'name' => 'Tour UI',
      'description' => 'Tests the Tour UI.',
      'group' => 'Tour',
    );
  }

  /**
   * Tests the listing and editing of a tour.
   */
  public function testUI() {
    $this->drupalLogin($this->root_user);
    $this->listTest();
    $this->editTest();
    $this->tipTest();
  }

  /**
   * Tests the listing of a tour.
   */
  protected function listTest() {
    // Assert that two test tours are shown.
    $this->drupalGet('admin/config/user-interface/tour');
    $elements = $this->xpath('//table/tbody/tr');
    $this->assertEqual(count($elements), 2);

    // The first column contains the id.
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[1]', array(':class' => 'tour-test'));
    $this->assertIdentical((string) $elements[0], 'tour-test-2');
    // The second column contains the title.
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[2]', array(':class' => 'tour-test'));
    $this->assertIdentical((string) $elements[0], t('Tour test english'));
    // The third column contains the paths.
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td/a[contains(@href, :href)]', array(':class' => 'tour-test', ':href' => 'tour-test-1'));
    $this->assertIdentical((string) $elements[0], '/tour-test-1');
    // The fourth column contains the number of tips.
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[4]', array(':class' => 'tour-test'));
    $this->assertIdentical((string) $elements[0], '1', 'Core tour_test/config/tour-test-2 has 1 tip');
    $this->assertIdentical((string) $elements[1], '3', 'Core tour_test/config/tour-test-1 has 3 tips');
  }

  /**
   * Tests the editing of a tour.
   */
  protected function editTest() {
    // Create a new tour. Ensure that it comes before the test tours.
    $edit = array(
      'label' => 'a' . $this->randomString(),
      'id' => strtolower($this->randomName()),
      'module' => strtolower($this->randomName()),
    );
    $this->drupalPost('admin/config/user-interface/tour/add', $edit, t('Save'));
    $this->assertRaw(t('The %tour tour has been created.', array('%tour' => $edit['label'])));
    $elements = $this->xpath('//table/tbody/tr');
    $this->assertEqual(count($elements), 1);

    // Edit and re-save an existing tour.
    $this->assertTitle(t('Edit tour | @site-name', array('@site-name' => config('system.site')->get('name'))));
    $this->drupalPost(NULL, array(), t('Save'));
    $this->assertRaw(t('Updated the %tour tour', array('%tour' => $edit['label'])));

    // Reorder the tour tips.
    $this->drupalGet('admin/config/user-interface/tour/manage/tour-test');
    $weights = array(
      'tips[tour-test-1][weight]' => '2',
      'tips[tour-test-3][weight]' => '1',
    );
    $this->drupalPost(NULL, $weights, t('Save'));
    $this->drupalGet('admin/config/user-interface/tour/manage/tour-test');
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', array(
      ':class' => 'draggable odd',
      ':text' => 'The awesome image',
    ));
    $this->assertEqual(count($elements), 1, 'Found odd tip "The awesome image".');
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', array(
      ':class' => 'draggable even',
      ':text' => 'The first tip',
    ));
    $this->assertEqual(count($elements), 1, 'Found even tip "The first tip".');
    $weights = array(
      'tips[tour-test-1][weight]' => '1',
      'tips[tour-test-3][weight]' => '2',
    );
    $this->drupalPost(NULL, $weights, t('Save'));
    $this->drupalGet('admin/config/user-interface/tour/manage/tour-test');
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', array(
      ':class' => 'draggable odd',
      ':text' => 'The first tip',
    ));
    $this->assertEqual(count($elements), 1, 'Found odd tip "The first tip".');
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', array(
      ':class' => 'draggable even',
      ':text' => 'The awesome image',
    ));
    $this->assertEqual(count($elements), 1, 'Found even tip "The awesome image".');

    // Attempt to create a duplicate tour.
    $this->drupalPost('admin/config/user-interface/tour/add', $edit, t('Save'));
    $this->assertRaw(t('The machine-readable name is already in use. It must be unique.'));

    // Delete a tour.
    $this->drupalGet('admin/config/user-interface/tour/manage/' . $edit['id']);
    $this->drupalPost(NULL, NULL, t('Delete'));
    $this->assertRaw(t('Are you sure you want to delete the %tour tour?', array('%tour' => $edit['label'])));
    $this->clickLink(t('Cancel'));
    $this->clickLink(t('Delete'));
    $this->drupalPost(NULL, NULL, t('Delete'));
    $elements = $this->xpath('//table/tbody/tr');
    $this->assertEqual(count($elements), 2);
    $this->assertRaw(t('Deleted the %tour tour.', array('%tour' => $edit['label'])));
  }

  /**
   * Tests the add/edit/delete of a tour tip.
   */
  protected function tipTest() {
    // Create a new tour for tips to be added to.
    $edit = array(
      'label' => 'a' . $this->randomString(),
      'id' => strtolower($this->randomName()),
      'module' => $this->randomString(),
      'paths' => '',
    );
    $this->drupalPost('admin/config/user-interface/tour/add', $edit, t('Save'));
    $this->assertRaw(t('The %tour tour has been created.', array('%tour' => $edit['label'])));

    // Add a new tip.
    $tip = array(
      'new' => 'image',
    );
    $this->drupalPost('admin/config/user-interface/tour/manage/' . $edit['id'], $tip, t('Add'));
    $tip = array(
      'label' => 'a' . $this->randomString(),
      'id' => 'tour-ui-test-image-tip',
      'url' => 'http://testimage.png',
      'alt' => 'Testing a new image tip through Tour UI.',
    );
    $this->drupalPost(NULL, $tip, t('Save'));
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', array(
      ':class' => 'draggable odd',
      ':text' => $tip['label'],
    ));
    $this->assertEqual(count($elements), 1, 'Found tip "' . $tip['label']. '".');

    // Edit the tip.
    $tip_id = $tip['id'];
    unset($tip['id']);
    $tip['label'] = 'a' . $this->randomString();
    $this->drupalPost('admin/config/user-interface/tour/manage/' . $edit['id'] . '/tip/edit/' . $tip_id, $tip, t('Save'));
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', array(
      ':class' => 'draggable odd',
      ':text' => $tip['label'],
    ));
    $this->assertEqual(count($elements), 1, 'Found tip "' . $tip['label']. '".');

    // Delete the tip.
    $this->drupalPost('admin/config/user-interface/tour/manage/' . $edit['id'] .'/tip/delete/' . $tip_id, array(), t('Delete'));
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', array(
      ':class' => 'draggable odd',
      ':text' => $tip['label'],
    ));
    $this->assertNotEqual(count($elements), 1, 'Did not find tip "' . $tip['label']. '".');
  }

}
