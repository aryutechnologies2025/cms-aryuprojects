<?php

namespace Drupal\Tests\html_title\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the HTML Title in combination with layout_builder.
 *
 * @group html_title
 */
class HtmlTitleLayoutBuilderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'html_title',
    'field_ui',
    'node',
    'layout_builder',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A node entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);

    $this->drupalCreateContentType(['type' => 'test']);
    $this->node = $this->drupalCreateNode([
      'title' => 'Test <sup>sup</sup>-tag',
      'type' => 'test',
    ]);
  }

  /**
   * Test that this module works in combination with layout_builder.
   */
  public function testLayoutBuilder() {
    // Enable layout_builder and add the title field.
    $this->drupalGet('admin/structure/types/manage/test/display');
    $this->submitForm([
      'layout[enabled]' => TRUE,
    ], 'Save');
    $this->clickLink('Manage layout');
    $this->clickLink('Add block');
    $this->clickLink('Title');
    $this->submitForm([], 'Add block');
    $this->submitForm([], 'Save layout');

    $this->drupalGet('node/' . $this->node->id());

    // Check that the title field correctly filters the HTML.
    $this->assertSession()->responseContains('Test <sup>sup</sup>-tag');
  }

}
