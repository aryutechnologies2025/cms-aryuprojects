<?php

/**
 * @file
 * Post update functions for HTML Title.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\user\Entity\Role;
use Drupal\views\ViewEntityInterface;

/**
 * Use a dedicated HTML Title permission.
 *
 * Grant users with the 'administer actions' permission also the
 * 'administer html title settings' permission.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function html_title_post_update_provide_dedicated_html_title_settings_permission() {
  /** @var \Drupal\user\Entity\Role[] $roles */
  $roles = Role::loadMultiple();
  foreach ($roles as $role) {
    if ($role->hasPermission('administer actions')) {
      $role->grantPermission('administer html title settings');
      $role->save();
    }
  }
}

/**
 * Remove the link_to_node option and replace it with link_to_entity.
 *
 * Replace the option to link_with_entity since that is the option used by
 * the views entity field.
 */
function html_title_post_update_remove_link_to_node_from_title_fields_in_views(&$sandbox = NULL) {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return t('The views module is not enabled, so there is nothing to update.');
  }

  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $config_entity_updater->update($sandbox, 'view', function (ViewEntityInterface $view) {
    if (in_array('html_title', $view->getDependencies()['module'], TRUE)) {
      $displays = $view->get('display');
      foreach ($displays as &$display) {
        if (isset($display['display_options']['fields'])) {
          foreach ($display['display_options']['fields'] as $field_name => $field) {
            if ($field['table'] === 'node_field_data' && $field['field'] === 'title' && isset($field['link_to_node'])) {
              $display['display_options']['fields'][$field_name]['settings']['link_to_entity'] = (bool) $field['link_to_node'];
              unset($display['display_options']['fields'][$field_name]['link_to_node']);
              $view->set('display', $displays);
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
  });
}

/**
 * Remove dependency on html_title for all views.
 *
 * Resave all views that use the title field so the config dependencies are
 * recalculated and the dependency on html_title is removed. When this module
 * is uninstalled, the views will not be removed and keep working as before.
 */
function html_title_post_update_views_remove_html_title_dependency(&$sandbox = NULL) {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return t('The views module is not enabled, so there is nothing to update.');
  }

  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $config_entity_updater->update($sandbox, 'view', function (ViewEntityInterface $view) {
    if (in_array('html_title', $view->getDependencies()['module'], TRUE)) {
      $displays = $view->get('display');
      foreach ($displays as $display) {
        if (isset($display['display_options']['fields'])) {
          foreach ($display['display_options']['fields'] as $field) {
            if ($field['table'] === 'node_field_data' && $field['field'] === 'title') {
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
  });
}
