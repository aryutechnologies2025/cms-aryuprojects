<?php

namespace Drupal\lb_copy_section;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

/**
 * Implements preRender for layout builder.
 */
class CopySectionRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Alters layout builder to add copy/paste functionality to sections.
   */
  public static function preRender($element) {

    // Check if the user has permission to use the copy/paste functionality.
    if (!\Drupal::currentUser()->hasPermission('copy paste sections')) {
      return $element;
    }

    $layout = &$element['layout_builder'];
    $tempstore = \Drupal::service('tempstore.private')->get('lb_copy_section');
    $section_storage = $element['#section_storage'];
    $copied_section = $tempstore->get('copied_section');

    foreach (Element::children($layout) as $id) {

      // Check what kind of element we are looping over.
      $is_add_link = isset($layout[$id]['link']) && isset($layout[$id]['link']['#url']);
      $is_section = isset($layout[$id]['configure']) && isset($layout[$id]['configure']['#url']);

      if ($is_add_link && !empty($copied_section)) {

        // Collect parameters to generate the link.
        $parameters = $layout[$id]['link']['#url']->getRouteParameters();
        $section_label = !empty($tempstore->get('copied_section_label')) ? $tempstore->get('copied_section_label') : t('Section');

        $paste_section_url = Url::fromRoute(
          'lb_copy_section.paste',
          [
            'section_storage_type' => $parameters['section_storage_type'],
            'section_storage' => $parameters['section_storage'],
            'delta' => $parameters['delta'],
          ],
          [
            'attributes' => [
              'class' => [
                'use-ajax',
                'layout-builder__link',
                'layout-builder__link--add',
                'layout-builder__link--paste-section',
              ],
            ],
          ],
        );
        $layout[$id]['paste'] = [
          '#type' => 'link',
          '#title' => t('Paste @section', ['@section' => $section_label]),
          '#url' => $paste_section_url,
          '#access' => $paste_section_url->access(),
        ];
      }
      elseif ($is_section) {

        // Collect parameters to generate the link.
        $parameters = $layout[$id]['configure']['#url']->getRouteParameters();
        $active_section = $section_storage->getSections()[$parameters['delta']];
        $layout_settings = $active_section->getLayoutSettings();
        $section_label = !empty($layout_settings['label']) ? $layout_settings['label'] : t('Section @section', ['@section' => $parameters['delta'] + 1]);

        $copy_section_url = Url::fromRoute(
          'lb_copy_section.copy',
          [
            'section_storage_type' => $parameters['section_storage_type'],
            'section_storage' => $parameters['section_storage'],
            'delta' => $parameters['delta'],
          ],
          [
            'attributes' => [
              'class' => [
                'use-ajax',
                'layout-builder__link',
                'layout-builder__link--copy-section',
              ],
            ],
          ],
        );
        $layout[$id]['copy'] = [
          '#type' => 'link',
          '#title' => t('Copy @section', ['@section' => $section_label]),
          '#url' => $copy_section_url,
          '#access' => $copy_section_url->access(),
          '#weight' => 4,
        ];
        $layout[$id]['layout-builder__section']['#weight'] = 5;
      }
    }

    // Attach library.
    $element['#attached']['library'][] = 'lb_copy_section/admin';

    return $element;
  }

}
