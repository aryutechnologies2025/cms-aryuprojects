<?php

namespace Drupal\layout_builder_ids\Service;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface that is collection of common functions used in layout builder ids.
 *
 * @package Drupal\layout_builder_ids\Service
 */
interface LayoutBuilderIdsServiceInterface {

  /**
   * Function to check for duplicate ids.
   *
   * @param string $layout_builder_id
   *   A string representing the if we are looking for.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $type
   *   A string representing the type of check, either section or block.
   *
   * @return bool
   *   A boolean value to whether or not there is a duplicate id.
   */
  public static function layoutBuilderIdsCheckIds(string $layout_builder_id, FormStateInterface $form_state, string $type):bool;

  /**
   * Function to check the sections for duplicate ids.
   *
   * @param string $layout_builder_id
   *   A string representing the if we are looking for.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $type
   *   A string representing the type of check, either section or block.
   *
   * @return bool
   *   A boolean value to whether or not there is a duplicate id.
   */
  public static function layoutBuilderIdsCheckSectionIds(string $layout_builder_id, FormStateInterface $form_state, string $type):bool;

  /**
   * A function to check the blocks for a duplicate id.
   *
   * @param string $layout_builder_id
   *   A string representing the if we are looking for.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $type
   *   A string representing the type of check, either section or block.
   *
   * @return bool
   *   A boolean value to whether or not there is a duplicate id.
   */
  public static function layoutBuilderIdsCheckBlockIds(string $layout_builder_id, FormStateInterface $form_state, string $type):bool;

}
