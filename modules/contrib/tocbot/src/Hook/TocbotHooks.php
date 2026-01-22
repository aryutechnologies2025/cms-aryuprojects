<?php

namespace Drupal\tocbot\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provide Tocbot Hooks.
 */
class TocbotHooks {

  /**
   * Constructs a TocbotHooks object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(protected ModuleHandlerInterface $moduleHandler) {}

  /**
   * Provides the hook_help.
   *
   * @param string $route_name
   *   The route name.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   *
   * @return string|null
   *   Return the help string if otherwise NULL.
   *
   * @throws \Drupal\Core\Extension\Exception\UnknownExtensionException
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.tocbot':
        $module_path = $this->moduleHandler->getModule('tocbot')->getPath();
        $filepath = $module_path . '/README.md';
        if (file_exists($filepath)) {
          $readme = file_get_contents($filepath);
        }
        return '<pre>' . $readme . '</pre>';
    }

    return NULL;
  }

}
