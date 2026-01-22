<?php

namespace Drupal\Tests\html_title\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the HTML Title module.
 *
 * @group html_title
 */
class HtmlTitleTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'html_title_test',
    'node',
    'block',
    'search',
    'content_translation',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * User with administer HTML Title settings rights.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * A node entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node1;

  /**
   * A node entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node2;

  /**
   * A node entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node3;

  /**
   * A node entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node4;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->config('html_title.settings')
      ->set('allow_html_tags', '<br> <sub> <sup>')
      ->save();
    $this->adminUser = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($this->adminUser);

    $this->drupalCreateContentType(['type' => 'test']);

    // Add FR language.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Add path_prefix based language negotiation.
    $this->config('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes', ['en' => 'en', 'fr' => 'fr'])
      ->save();

    // Turn on content translation for test pages.
    $config = ContentLanguageSettings::loadByEntityTypeBundle('node', 'test');
    $config->setDefaultLangcode('en')
      ->setLanguageAlterable(TRUE)
      ->save();

    $this->node1 = $this->drupalCreateNode([
      'title' => 'Test <sup>sup</sup>-tag',
      'type' => 'test',
      'path' => '/parent',
    ]);
    $this->node2 = $this->drupalCreateNode([
      'title' => 'Test <sup>sup</sup>-tag, <sub>sub</sub>-tag and <br>br-tag',
      'type' => 'test',
      'path' => '/parent/child',
    ]);
    $this->node3 = $this->drupalCreateNode([
      'title' => 'Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and <p>p</p>-tag',
      'type' => 'test',
    ]);
    $translation = $this->node3->addTranslation('fr', ['title' => 'Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and <p>p</p>-tag FR']);
    $translation->save();
    $this->node4 = $this->drupalCreateNode([
      'title' => 'Test <p>p</p>-tag',
      'type' => 'test',
    ]);

    // Run a cron job so the nodes are indexed and shown on the search page.
    $this->container->get('cron')->run();

    $this->state = $this->container->get('state');
  }

  /**
   * Tests the page title block in combination with HTML Title.
   */
  public function testPageTitleBlock() {
    $assert_session = $this->assertSession();
    $this->drupalGet($this->node1->toUrl());
    $assert_session->responseContains('<h1>Test <sup>sup</sup>-tag</h1>');

    $this->drupalGet($this->node2->toUrl());
    $assert_session->responseContains('<h1>Test <sup>sup</sup>-tag, <sub>sub</sub>-tag and <br>br-tag</h1>');

    $this->drupalGet($this->node3->toUrl());
    $assert_session->responseContains('<h1>Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag</h1>');

    // Check that translations still work.
    $this->drupalGet('fr/node/' . $this->node3->id());
    $assert_session->responseContains('<h1>Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag FR</h1>');

    $this->drupalGet($this->node4->toUrl());
    $assert_session->responseContains('<h1>Test p-tag</h1>');
  }

  /**
   * Tests the edit node page title.
   */
  public function testEditNodePageTitle() {
    $assert_session = $this->assertSession();
    $this->drupalGet(Url::fromRoute('entity.node.edit_form', [
      'node' => $this->node1->id(),
    ]));
    $assert_session->responseContains('<h1><em>Edit test</em> Test <sup>sup</sup>-tag</h1>');

    $this->drupalGet(Url::fromRoute('entity.node.edit_form', [
      'node' => $this->node2->id(),
    ]));
    $assert_session->responseContains('<h1><em>Edit test</em> Test <sup>sup</sup>-tag, <sub>sub</sub>-tag and <br>br-tag</h1>');

    $this->drupalGet(Url::fromRoute('entity.node.edit_form', [
      'node' => $this->node3->id(),
    ]));
    $assert_session->responseContains('<h1><em>Edit test</em> Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag</h1>');

    $this->drupalGet('fr/node/' . $this->node3->id() . '/edit');
    $assert_session->responseContains('<h1><em>Edit test</em> Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag FR</h1>');

    $this->drupalGet(Url::fromRoute('entity.node.edit_form', [
      'node' => $this->node4->id(),
    ]));
    $assert_session->responseContains('<h1><em>Edit test</em> Test p-tag</h1>');
  }

  /**
   * Tests the breadcrumb block in combination with HTML Title.
   */
  public function testBreadcrumbBlock() {
    $assert_session = $this->assertSession();
    $this->drupalGet($this->node1->toUrl());
    $element = $assert_session->elementExists('css', 'nav[role="navigation"] ol li:last-child');
    $this->assertEquals('Test <sup>sup</sup>-tag', trim($element->getHtml()));

    $this->drupalGet($this->node2->toUrl());
    $element = $assert_session->elementExists('css', 'nav[role="navigation"] ol li:nth-child(2) a');
    $this->assertEquals('Test <sup>sup</sup>-tag', trim($element->getHtml()));
    $element = $assert_session->elementExists('css', 'nav[role="navigation"] ol li:last-child');
    $this->assertEquals('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag and <br>br-tag', trim($element->getHtml()));

    $this->drupalGet($this->node3->toUrl());
    $element = $assert_session->elementExists('css', 'nav[role="navigation"] ol li:last-child');
    $this->assertEquals('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag', trim($element->getHtml()));

    // Check that translations still work.
    $this->drupalGet('fr/node/' . $this->node3->id());
    $element = $assert_session->elementExists('css', 'nav[role="navigation"] ol li:last-child');
    $this->assertEquals('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag FR', trim($element->getHtml()));

    $this->drupalGet($this->node4->toUrl());
    $element = $assert_session->elementExists('css', 'nav[role="navigation"] ol li:last-child');
    $this->assertEquals('Test p-tag', trim($element->getHtml()));
  }

  /**
   * Tests the search module in combination with HTML title.
   */
  public function testSearchPage() {
    $assert_session = $this->assertSession();
    $this->drupalGet('search/node');
    $this->submitForm(['keys' => 'test br-tag p-tag', 'language[en]' => 'en'],
      'Search'
    );

    $element = $assert_session->elementExists('css', 'ol li:first-child h3 a');
    $this->assertEquals('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag', trim($element->getHtml()));

    $this->drupalGet('fr/search/node');
    $this->submitForm(['keys' => 'test br-tag p-tag', 'language[fr]' => 'fr'],
      'Search'
    );

    $element = $assert_session->elementExists('css', 'ol li:first-child h3 a');
    $this->assertEquals('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag FR', trim($element->getHtml()));
  }

  /**
   * Tests the revision pages in combination with HTML Title.
   */
  public function testRevisionsPage() {

    $assert_session = $this->assertSession();
    $node_1_revision = clone $this->node1;
    $node_1_revision->set('body', ['value' => $this->getRandomGenerator()->paragraphs(12)]);
    $node_1_revision->setNewRevision();
    $node_1_revision->save();

    $this->drupalGet(Url::fromRoute('node.revision_revert_confirm', [
      'node' => $this->node1->id(),
      'node_revision' => $this->node1->getRevisionId(),
    ]));
    $assert_session->responseContains('<h1>Test <sup>sup</sup>-tag</h1>');

    $this->drupalGet(Url::fromRoute('entity.node.revision', [
      'node' => $this->node1->id(),
      'node_revision' => $this->node1->getRevisionId(),
    ]));
    $assert_session->responseContains('<h1>Test <sup>sup</sup>-tag</h1>');

    $this->drupalGet(Url::fromRoute('node.revision_delete_confirm', [
      'node' => $this->node1->id(),
      'node_revision' => $this->node1->getRevisionId(),
    ]));
    $assert_session->responseContains('<h1>Test <sup>sup</sup>-tag</h1>');
  }

  /**
   * Test the node confirmation message.
   */
  public function testNodeConfirmationMessage() {
    // Test that node confirmation messages renders the allowed HTML-tags.
    $this->drupalGet('node/add');
    $this->submitForm(['title[0][value]' => 'Test <sup>sup</sup>-tag'], 'Save');
    $this->assertSession()->elementContains('css', 'div[data-drupal-messages]', 'Test <sup>sup</sup>-tag');

    // Nodes without HTML should still work.
    $this->drupalGet('node/add');
    $this->submitForm(['title[0][value]' => 'Test node'], 'Save');
    $this->assertSession()->elementTextContains('css', 'div[data-drupal-messages]', 'Test node');

    // HTML-tags that are not allowed should be filtered out of the message.
    $this->drupalGet('node/add');
    $this->submitForm(['title[0][value]' => 'Test <p>p</p>-tag'], 'Save');
    $this->assertSession()->elementTextContains('css', 'div[data-drupal-messages]', 'Test p-tag');

    // When this setting is TRUE, this will add extra messages on the node
    // confirmation. This can be used to check that only the node confirmation
    // message is altered.
    $this->state->set('html_title_test.set_node_confirmation_messages', TRUE);

    // Test that only the node confirmation message is replaced and other
    // messages still work.
    $this->drupalGet('node/add');
    $this->submitForm(['title[0][value]' => 'Test <sub>sub</sub>-tag'], 'Save');
    $this->assertSession()->elementContains('css', 'div[data-drupal-messages]', 'Test <sub>sub</sub>-tag');
    $this->assertSession()->elementContains('css', 'div[data-drupal-messages]', 'Test status message');
    $this->assertSession()->elementTextContains('css', 'div[data-drupal-messages]', 'Test status message with <p>HTML</p>');
    $this->assertSession()->elementContains('css', 'div[data-drupal-messages]', 'Test error message');
  }

}
