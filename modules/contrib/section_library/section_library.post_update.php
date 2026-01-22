<?php

/**
 * @file
 * Post update functions for Section Library.
 */

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Update section_library_template entity to be fieldable.
 */
function section_library_post_update_make_templates_fieldable(&$sandbox) {
  $entityUpdateManager = \Drupal::entityDefinitionUpdateManager();
  $entity_type = $entityUpdateManager->getEntityType('section_library_template');
  if (!$entity_type) {
    return;
  }
  // Make sure we need to update the entity type.
  if ($entity_type->getStorageClass() instanceof SqlContentEntityStorage) {
    return;
  }
  // Update the template entity settings.
  $entity_type->setStorageClass(SqlContentEntityStorage::class);
  $entity_type->set('field_ui_base_route', 'section_library.admin_index');
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('section_library_template');
  $entityUpdateManager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);
  return t('Section library templates have been converted to be fieldable.');
}

/**
 * Update section library views to use image field instead of file.
 */
function section_library_post_update_image_fields_in_views(&$sandbox) {
  $config_factory = \Drupal::configFactory();

  // Update the existing section template views.
  foreach ($config_factory->listAll('views.view.') as $view_config_name) {
    $view = $config_factory->getEditable($view_config_name);
    $base_table = $view->get('base_table');

    // Find section library template views.
    if ($base_table === 'section_library_template') {
      // Go through each display on the section library template view.
      $displays = $view->get('display');
      foreach ($displays as $display_name => $display) {
        // Look for instances of the image field.
        if (!empty($display['display_options']['fields'])) {
          $base = '';
          foreach ($display['display_options']['fields'] as $field_name => $field) {
            if ($field_name === 'image__target_id' && isset($field['entity_type']) &&
              $field['entity_type'] === 'section_library_template') {
              // Update the field type to image.
              $base = "display.$display_name.display_options.fields.$field_name";
              $view->set($base . '.alter.alter_text', FALSE);
              $view->set($base . '.alter.text', '');
              $view->set($base . '.type', 'image');
              $view->set($base . '.settings.image_link', '');
              $view->set($base . '.settings.image_style', '');
              $view->set($base . '.settings.image_loading.attribute', 'lazy');
            }
          }
          if (!empty($base)) {
            $view->set('dependencies.module', ['image', 'options', 'section_library', 'user']);
          }
        }
      }
    }

    $view->save(TRUE);
  }

  return t('Updated section library template views to use image instead of file field.');
}

/**
 * Add core hash and UUID to section_library view if needed.
 *
 * Note: Only for dev branch updates.
 */
function section_library_post_update_image_fields_in_views_uuid(&$sandbox) {
  $config_factory = \Drupal::configFactory();
  $configName = 'views.view.section_library';
  $config = $config_factory->getEditable($configName);
  if ($config->get('base_table') === 'section_library_template') {
    $data = [];

    // Set default _core if needed.
    if ($config->get('_core') == NULL) {
      $data['_core'] = [
        'default_config_hash' => Crypt::hashBase64(\serialize($configName)),
      ];
    }
    elseif ($config->get('_core.default_config_hash') == NULL) {
      $data['_core'] = $config->get('_core') + [
        'default_config_hash' => Crypt::hashBase64(\serialize($configName)),
      ];
    }
    $changed = FALSE;
    if (!empty($data)) {
      foreach ($data as $key => $value) {
        $config->set($key, $value);
      }
      $changed = TRUE;
    }

    // Set default UUID if needed.
    if ($config->get('uuid') == NULL) {
      $uuid = \Drupal::service('uuid')->generate();
      $config->set('uuid', $uuid);
      $changed = TRUE;
    }
    if ($changed) {
      $config->save(TRUE);
      return t('Add UUID and/or _core hash to section library view.');
    }
  }

  return t('No changes needed to section library view.');
}

/**
 * Update field_ui_base_route to the new settings form if needed.
 *
 * Note: Only for dev branch updates.
 */
function section_library_post_update_make_templates_fieldable_field_ui(&$sandbox) {
  $entityUpdateManager = \Drupal::entityDefinitionUpdateManager();
  $entity_type = $entityUpdateManager->getEntityType('section_library_template');
  if (!$entity_type) {
    return;
  }
  if ($entity_type->get('field_ui_base_route') === 'section_library.admin_index') {
    return t('No changes needed to section template entity.');
  }
  $entity_type->set('field_ui_base_route', 'section_library.admin_index');
  $entity_type->set('data_table', NULL);
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('section_library_template');
  $entityUpdateManager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);
  return t('Updated field_ui_base_route for section_library_template entity.');
}

/**
 * Clear cache to reflect new preview route.
 */
function section_library_post_update_preview_route_clear_cache(&$sandbox) {
  // Empty update to cause a cache rebuild so that the new route appears.
}
