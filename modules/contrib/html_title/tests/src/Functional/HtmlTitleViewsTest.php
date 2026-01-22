<?php

namespace Drupal\Tests\html_title\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the HTML Title integration with Views.
 *
 * @group html_title
 */
class HtmlTitleViewsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'html_title',
    'html_title_test',
    'views',
    'views_ui',
    'node',
    'content_translation',
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

    $this->config('html_title.settings')
      ->set('allow_html_tags', '<br> <sub> <sup>')
      ->save();
    $this->drupalLogin($this->rootUser);

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
    ]);
    $this->node2 = $this->drupalCreateNode([
      'title' => 'Test <sup>sup</sup>-tag, <sub>sub</sub>-tag and <br>br-tag',
      'type' => 'test',
    ]);
    $this->node3 = $this->drupalCreateNode([
      'title' => 'Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and <p>p</p>-tag',
      'type' => 'test',
    ]);
    $translation = $this->node3->addTranslation('fr', [
      'title' => 'Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and <p>p</p>-tag FR',
    ]);
    $translation->save();
    $this->node4 = $this->drupalCreateNode([
      'title' => 'Test <p>p</p>-tag',
      'type' => 'test',
    ]);
  }

  /**
   * Tests the HTML Title views integration.
   */
  public function testViewsIntegration() {
    $this->drupalGet(Url::fromRoute('system.admin_content'));

    $assert_session = $this->assertSession();
    $assert_session->responseContains('Test <sup>sup</sup>-tag');
    $assert_session->responseContains('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag and <br>br-tag');
    $assert_session->responseContains('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag');
    $assert_session->responseNotContains('Test <p>p</p>-tag');
    $assert_session->responseContains('Test p-tag');
    // Check that the node title is a link.
    $assert_session->linkExists('Test p-tag');

    // Check that disabling the node title as a link still works.
    $this->drupalGet(Url::fromRoute('entity.view.edit_form', ['view' => 'content']));
    $this->clickLink('Content: Title (Title)');
    $this->submitForm(['options[settings][link_to_entity]' => FALSE], 'Apply');
    $this->submitForm([], 'Save');

    $this->drupalGet(Url::fromRoute('system.admin_content'));
    $assert_session->responseContains('Test <sup>sup</sup>-tag');
    $assert_session->responseContains('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag and <br>br-tag');
    $assert_session->responseContains('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag');
    $assert_session->responseNotContains('Test <p>p</p>-tag');
    $assert_session->linkNotExists('Test p-tag');
  }

  /**
   * Test views integration with interface translation rendering language.
   */
  public function testViewsInterfaceTranslationRenderingLanguage() {
    $this->drupalGet('/test-interface-language');
    $this->assertSession()->responseContains('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag');

    $this->drupalGet('fr/test-interface-language');
    $this->assertSession()->responseContains('Test <sup>sup</sup>-tag, <sub>sub</sub>-tag, <br>br-tag and p-tag FR');
  }

  /**
   * Test that the the content view renders the node titles to row language.
   */
  public function testAdminContentOverviewUrlsRenderedInRowLanguage() {
    $this->drupalGet(Url::fromRoute('system.admin_content'));
    // The nodes are order on last updated date. So the EN version is shown on
    // the third place and the translated FR version on place 4.
    $this->assertSession()->elementAttributeContains('css', 'table tr:nth-of-type(3) .views-field-title a', 'href', '/en/node/3');
    $this->assertSession()->elementAttributeContains('css', 'table tr:nth-of-type(4) .views-field-title a', 'href', '/fr/node/3');
  }

  /**
   * Tests the output when the views style RSS is used.
   */
  public function testViewsRssStyle() {
    $this->drupalGet('rss.xml');

    $assert_session = $this->assertSession();
    $assert_session->responseContains('<title>Test sup-tag</title>');
    $assert_session->responseContains('<title>Test sup-tag, sub-tag and br-tag</title>');
    $assert_session->responseContains('<title>Test sup-tag, sub-tag, br-tag and p-tag</title>');
    $assert_session->responseContains('<title>Test p-tag</title>');
  }

  /**
   * Test that views using 'node_html_title' plugin still work after uninstall.
   */
  public function testAdminContentViewAfterModuleUninstall() {
    $this->drupalGet('/test-interface-language');
    $this->assertSession()->statusCodeEquals(200);

    // Uninstall html_title_test and html_title.
    $this->drupalGet(Url::fromRoute('system.modules_uninstall'));
    $this->submitForm(['uninstall[html_title_test]' => TRUE], 'Uninstall');
    $this->submitForm([], 'Uninstall');
    $this->submitForm(['uninstall[html_title]' => TRUE], 'Uninstall');
    $this->submitForm([], 'Uninstall');

    $this->drupalGet('/test-interface-language');
    $this->assertSession()->statusCodeEquals(200);
  }

}
