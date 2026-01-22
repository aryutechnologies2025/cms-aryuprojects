<?php

namespace Drupal\layout_builder_ids\Service;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class UWService.
 *
 * UW Service that holds common functionality used by uw blocks.
 *
 * @package Drupal\uw_cfg_common\Service
 */
class LayoutBuilderIdsService implements LayoutBuilderIdsServiceInterface {

  /**
   * {@inheritDoc}
   */
  public static function layoutBuilderIdsCheckIds(string $layout_builder_id, FormStateInterface $form_state, string $type):bool {

    // Return the found id, which will tell us if we have a
    // duplicate id.
    return LayoutBuilderIdsService::layoutBuilderIdsCheckSectionIds($layout_builder_id, $form_state, $type) ||
      LayoutBuilderIdsService::layoutBuilderIdsCheckBlockIds($layout_builder_id, $form_state, $type);
  }

  /**
   * {@inheritDoc}
   */
  public static function layoutBuilderIdsCheckSectionIds(string $layout_builder_id, FormStateInterface $form_state, string $type):bool {

    // Get the sections from the formObject.
    $sections = $form_state->getFormObject()->getSectionStorage()->getSections();

    // Set the delta to null to start.
    $delta = NULL;

    // If we are on a section check, then get the delta
    // form the form state.
    if ($type == 'section') {

      // Get the delta from the form state.
      $delta = $form_state->getBuildInfo()['args'][1];
    }

    // Step through each section and check for duplicate id.
    foreach ($sections as $index => $section) {

      // If we are on a section check and the delta is the same
      // as the index we are on, just skip over the check, so
      // that we are not checking the current section.
      if ($type == 'section' && $delta == $index) {
        continue;
      }

      // Get the layout settings for the section.
      $layout_settings = $section->getLayoutSettings();

      // If there is a layout_builder_id setting and it is the same as the
      // specified id, then return TRUE, as we found a duplicate id.
      if (
        isset($layout_settings['layout_builder_id']) &&
        $layout_settings['layout_builder_id'] == $layout_builder_id
      ) {

        return TRUE;
      }
    }

    // Return FALSE as we will return TRUE if we find a duplicate.
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public static function layoutBuilderIdsCheckBlockIds(string $layout_builder_id, FormStateInterface $form_state, string $type):bool {

    // Get the sections from the formObject.
    $sections = $form_state->getFormObject()->getSectionStorage()->getSections();

    // If we are on a block check, get the current component uuid.
    if ($type == 'block') {

      // Get the current component uuid.
      $current_component_uuid = $form_state->getFormObject()->getCurrentComponent()->get('uuid');
    }

    // Step through each section and get the blocks to
    // check for duplicate ids.
    foreach ($sections as $section) {

      // Get the components of the section.
      $components = $section->getComponents();

      // Step through each of the components and check for duplicate ids.
      foreach ($components as $uuid => $component) {

        // If we are on a block check and we are looking at the current
        // component, skip the check for ID.
        if ($type == 'block' && $uuid == $current_component_uuid) {
          continue;
        }

        // Get the additional setting from the component.
        $additional = $component->get('additional');

        // Ensure that the layout_builder_id is set in additional settings.
        if (isset($additional['layout_builder_id'])) {

          // If there is already an id with the one specified return TRUE.
          if ($additional['layout_builder_id'] == $layout_builder_id) {
            return TRUE;
          }
        }
      }
    }

    // Return FALSE as we will return TRUE if we find a duplicate.
    return FALSE;
  }

}
