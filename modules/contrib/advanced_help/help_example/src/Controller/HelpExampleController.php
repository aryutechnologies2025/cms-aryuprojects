<?php

namespace Drupal\help_example\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Help example routes.
 */
final class HelpExampleController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $output = '<p>' . t('Read the source code of the module <strong>Help Example</strong> to learn how to create themed and plain links to help topics, and how to render help in the adminstrative theme.') . '</p>';
    $output .= '<p>' . t('Two popup examples:') . '<br />';

    // Create the question mark icon for the topic.
    /*
    theme() is deprecated see issue https://www.drupal.org/project/advanced_help/issues/3299760
    $toc_qm = theme('advanced_help_topic', array(
      'module' => 'help_example',
      'topic' => 'toc',
      'type' => 'icon',
    ));
    */
    $toc_qm = '[?]';
    // Append some explanatory text.
    $output .= $toc_qm . '&nbsp;' . t('Click the help icon on the left to view a popup of the example module index page.');

    /*
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];
    */

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $output,
    ];

    return $build;
  }

}
