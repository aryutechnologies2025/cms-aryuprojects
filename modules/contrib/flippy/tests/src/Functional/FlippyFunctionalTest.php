<?php

namespace Drupal\Tests\flippy\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Flippy pagers are appearing.
 *
 * @group flippy
 */
class FlippyFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'test_page_test',
    'flippy',
  ];

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';


  /**
   * Disable schema validation.
   *
   * This is super dirty... @todo Should be removed ASAP, when 3.x is released!
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('page.front', '/test-page')->save();
    $this->user = $this->drupalCreateUser([]);
    $this->adminUser = $this->drupalCreateUser([]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);

    // Create an article content type that we will use for testing.
    $type = $this->container->get('entity_type.manager')->getStorage('node_type')
      ->create([
        'type' => 'article',
        'name' => 'Article',
      ]);
    $type->save();
    $this->container->get('router.builder')->rebuild();

    // Enable flippy for article content type.
    \Drupal::configFactory()
      ->getEditable('flippy.settings')
      ->set('flippy_article', 1)
      ->set('flippy_prev_label_article', 'Previous')
      ->set('flippy_next_label_article', 'Next')
      ->set('flippy_first_label_article', 'First')
      ->set('flippy_last_label_article', 'Last')
      ->set('flippy_random_label_article', 'Random')
      ->save();

    // Create sample nodes in article content type.
    for ($i = 0; $i <= 2; $i++) {
      $node = $this->container->get('entity_type.manager')->getStorage('node')
        ->create([
          'type' => 'article',
          'title' => $i,
        ]);
      $node->save();
    }
  }

  /**
   * Tests if installing the module, won't break the site.
   */
  public function testInstallation() {
    $session = $this->assertSession();
    $this->drupalGet('<front>');
    // Ensure the status code is success:
    $session->statusCodeEquals(200);
    // Ensure the correct test page is loaded as front page:
    $session->pageTextContains('Test page text.');
  }

  /**
   * Tests if uninstalling the module, won't break the site.
   */
  public function testUninstallation() {
    // Go to uninstallation page an uninstall flippy:
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet('/admin/modules/uninstall');
    $session->statusCodeEquals(200);
    $page->checkField('edit-uninstall-flippy');
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    // Confirm uninstall:
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    $session->pageTextContains('The selected modules have been uninstalled.');
    // Retest the frontpage:
    $this->drupalGet('<front>');
    // Ensure the status code is success:
    $session->statusCodeEquals(200);
    // Ensure the correct test page is loaded as front page:
    $session->pageTextContains('Test page text.');
  }

  /**
   * Make sure pages appear in article node pages.
   */
  public function testPagerOnPage() {
    $session = $this->assertSession();
    // Load the first page.
    $this->drupalGet('/node/1');

    // Confirm that the site didn't throw a server error or something else.
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the front page contains the standard text.
    $session->pageTextContains('Next');
    $session->elementTextEquals('css', 'article > div > ul > li > a', 'Next');
    $session->pageTextNotContains('Previous');

    // Load the second page.
    $this->drupalGet('/node/2');

    // Confirm that the site didn't throw a server error or something else.
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the front page contains the standard text.
    $session->pageTextContains('Next');
    $session->elementTextEquals('css', 'article > div > ul.flippy > li.flippy-next > a', 'Next');
    $session->pageTextContains('Previous');
    $session->elementTextEquals('css', 'article > div > ul.flippy > li.flippy-previous > a', 'Previous');

    // Load the last page.
    $this->drupalGet('/node/3');

    // Confirm that the site didn't throw a server error or something else.
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the front page contains the standard text.
    $session->pageTextNotContains('Next');
    $session->pageTextContains('Previous');
    $session->elementTextEquals('css', 'article > div > ul.flippy > li.flippy-previous > a', 'Previous');
  }

}
