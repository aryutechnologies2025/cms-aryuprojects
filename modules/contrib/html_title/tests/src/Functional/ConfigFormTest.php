<?php

namespace Drupal\Tests\html_title\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the HTML Title config form.
 *
 * @group html_title
 */
class ConfigFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['html_title'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User with administer HTML Title settings rights.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * A regular user.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->webUser = $this->createUser();
    $this->adminUser = $this->createUser(['administer html title settings']);
  }

  /**
   * Tests the HTML Title config form.
   */
  public function testHtmlTitleConfigForm() {
    // Check default config.
    $config = $this->config('html_title.settings');
    $this->assertEquals('<br> <sub> <sup>', $config->get('allow_html_tags'));

    // Unauthorized user should not have access.
    $this->drupalGet(Url::fromRoute('html_title.settings'));
    $this->assertSession()->statusCodeEquals(403);

    // Login as a regular user.
    $this->drupalLogin($this->webUser);

    // Unauthorized user should not have access.
    $this->drupalGet(Url::fromRoute('html_title.settings'));
    $this->assertSession()->statusCodeEquals(403);

    // Login as an admin user.
    $this->drupalLogin($this->adminUser);

    // Update HTML Title config.
    $this->drupalGet(Url::fromRoute('html_title.settings'));
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm(['allow_html_tags' => '<br>'], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Check if config is updated.
    $config = $this->config('html_title.settings');
    $this->assertEquals('<br>', $config->get('allow_html_tags'));
  }

}
