<?php

namespace Drupal\Tests\html_title\Functional;

use Drupal\Core\Url;
use Drupal\Tests\content_moderation\Functional\ModerationStateTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests the HTML Title module's handling of draft revisions.
 *
 * @group html_title
 */
class HtmlTitleDraftTest extends ModerationStateTestBase {

  use ContentModerationTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'html_title_test',
    'content_moderation',
    'block',
    'block_content',
    'node',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Sets the test up.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUi('Moderated content', 'moderated_content', TRUE);
    $this->config('html_title.settings')
      ->set('allow_html_tags', '<b>')
      ->save();
  }

  /**
   * Test the title on draft revisions.
   */
  public function testDraftRevisionTitle() {
    $node = $this->drupalCreateNode([
      'title' => '<b>Original Title</b>',
      'type' => 'moderated_content',
      'moderation_state' => 'published',
    ]);

    $node->setNewRevision();
    $node->save();

    $node->set('title', '<b>Draft Title</b>');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision();
    $node->save();

    // Check the canonical node for the original title.
    $this->drupalGet(Url::fromRoute('entity.node.canonical', [
      'node' => $node->id(),
    ]));
    $this->assertSession()->responseContains('<b>Original Title</b>');

    // Check the latest version for the draft title.
    $this->drupalGet(Url::fromRoute('entity.node.latest_version', [
      'node' => $node->id(),
      'node_revision' => $node->getRevisionId(),
    ]));
    $this->assertSession()->responseContains('<b>Draft Title</b>');
  }

}
