<?php

namespace Drupal\we_megamenu_fork\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Provides blocks which belong to Drupal 8 Mega Menu.
 */
class WeMegaMenuBlock extends DeriverBase {
  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $menus = _we_megamenu_fork_get_menus();
    foreach ($menus as $menu => $title) {
      $this->derivatives[$menu] = $base_plugin_definition;
      $this->derivatives[$menu]['admin_label'] = $title;
    }
    return $this->derivatives;
  }
}
