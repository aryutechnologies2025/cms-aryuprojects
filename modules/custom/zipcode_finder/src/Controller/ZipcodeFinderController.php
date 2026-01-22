<?php

namespace Drupal\zipcode_finder\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Zipcode Finder routes.
 */
class ZipcodeFinderController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
