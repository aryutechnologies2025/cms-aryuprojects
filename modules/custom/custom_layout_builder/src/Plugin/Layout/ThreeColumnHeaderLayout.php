<?php

namespace Drupal\custom_layout_builder\Plugin\Layout;

/**
 * Configurable three column layout plugin class.
 *
 * @internal
 *   Plugin classes are internal.
 */
class ThreeColumnHeaderLayout extends MultiWidthLayoutBase {

  /**
   * {@inheritdoc}
   */
  protected function getWidthOptions() {
    return [
      '25-50-25' => '25%/50%/25%',
      '33-34-33' => '33%/34%/33%',
      '25-25-50' => '25%/25%/50%',
      '50-25-25' => '50%/25%/25%',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultWidth() {
    return '33-34-33';
  }

}
