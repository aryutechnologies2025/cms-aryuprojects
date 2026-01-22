<?php

use Drupal\node\Entity\Node;
use Drupal\webform\Plugin\WebformHandler\SettingsWebformHandler;
use Drupal\webform\Plugin\WebformHandlerPluginCollection;


/**
 * Prepopulate all existing subsite fields
 */
function sprowt_subsite_deploy_9001() {
    $field_type = 'sprowt_subsite_reference';
    $field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType($field_type);
    $fieldBundleMap = $field_map['node'];
    $manager   = \Drupal::entityDefinitionUpdateManager();
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    foreach($fieldBundleMap as $fieldName => $fieldInfo) {
        $field_storage_definition = $manager->getFieldStorageDefinition($fieldName, 'node');
        $cardinality = $field_storage_definition->getCardinality();
        $multiple = $cardinality !== 1;
        $bundles = $fieldInfo['bundles'] ?? [];
        $nodes = $nodeStorage->loadByProperties([
            'type' => $bundles
        ]);
        /** @var Node $node */
        foreach($nodes as $node) {
            /** @var \Drupal\sprowt_subsite\Plugin\Field\FieldType\SubsiteReferenceItemList $itemList */
            $itemList = $node->get($fieldName);
            $values = $itemList->getValue();
            if($itemList->isEmpty()) {
                if($multiple) {
                    $value = [['target' => '_main']];
                }
                else {
                    $value = ['target' => '_main'];
                }
                $node->set($fieldName, $value);
                $node->save();
            }
        }
    }
}

/**
 * Update webforms
 */
function sprowt_subsite_deploy_9002() {
    $webforms = \Drupal::entityTypeManager()->getStorage('webform')->loadMultiple();
    /** @var \Drupal\webform\Entity\Webform $webform */
    foreach($webforms as $webform) {
        $settings = $webform->getSettings();
        $confirmationUrl = $settings['confirmation_url'] ?? '';
        if(!empty($confirmationUrl) && strpos($confirmationUrl, '[sprowt:subsite_home_url]') !== 0) {
            $webform->setSetting('confirmation_url', '[sprowt:subsite_home_url]' . $confirmationUrl);
            $webform->save();
        }
        $confirmationUrl = null;
        /** @var WebformHandlerPluginCollection $handlers */
        $handlers = $webform->getHandlers('settings');
        if($handlers->count() > 1) {
            /** @var SettingsWebformHandler $handler */
            foreach($handlers as $handler) {
                $handlerSettings = $handler->getSettings();
                $confirmationUrl = $handlerSettings['confirmation_url'] ?? '';
                if(!empty($confirmationUrl) && strpos($confirmationUrl, '[sprowt:subsite_home_url]') !== 0) {
                    $handler->setSetting('confirmation_url', '[sprowt:subsite_home_url]' . $confirmationUrl);
                    $webform->getHandlers()->setInstanceConfiguration($handler->getHandlerId(), $handler->getConfiguration());
                    $webform->save();
                }
            }
        }
    }

}

/**
 * Convert block visibility condition to new format
 */
function sprowt_subsite_deploy_9003() {
    $blocks = \Drupal::entityTypeManager()->getStorage('block')->loadMultiple();
    /** @var \Drupal\block\Entity\Block $block */
    foreach($blocks as $block) {
        try {
            $conditionConfig = $block->getVisibilityCondition('sprowt_subsite_condition')->getConfiguration() ?? [];
        }
        catch (\Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
            $conditionConfig = [];
        }
        if(!empty($conditionConfig) && !empty($conditionConfig['id']) && !empty($conditionConfig['fields']['field_subsite'])) {
            $newConfig = [
                'id' => $conditionConfig['id'],
                'context_mapping' => $conditionConfig['context_mapping'],
                'negate' => false,
                'use' => false,
                'value' => [],
                'hasNoFields' => $conditionConfig['hasNoFields'] ?? 'hide'
            ];
            $fieldConfig = $conditionConfig['fields']['field_subsite'] ?? [];
            if(!empty($fieldConfig)) {
                $newConfig['value'] = $fieldConfig['value'];
                $newConfig['use'] = $fieldConfig['use'];
                $newConfig['negate'] = $fieldConfig['negate'];
            }
            $block->setVisibilityConfig($newConfig['id'], $newConfig);
            $block->save();

            $stop = true;
        }
    }
}

/**
 * import menu link for zipcode finder
 */
function sprowt_subsite_deploy_9004() {
    $exportFile = __DIR__ . '/content/zip-code-finder-link.json';
    $uuid = '6fd84e1d-91b4-4125-8ebe-e662b05c76a4';
    $menuLink = \Drupal::entityTypeManager()->getStorage('menu_link_content')
        ->loadByProperties([
            'uuid' => $uuid
        ]);
    if(empty($menuLink)) {
        /** @var \Drupal\content_import_export\Importer $importer */
        $importer = \Drupal::service('content_import_export.importer');
        $importer->importFromFile($exportFile);
    }
}

/**
 * Import subsite full
 */
function sprowt_subsite_deploy_10001() {
    if($_SERVER['SPROWTHQ_SITE_NAME'] != 'sprowt3-source') {
        return;
    }
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'uuid' => '002be109-057a-451a-b2a2-431f4cc29b5e'
    ]);
    if(!empty($nodes)) {
        return;
    }
    $menu = \Drupal::entityTypeManager()->getStorage('menu')->load('main-navigation-subsite-full');
    if(empty($menu)) {
        $yamlFile = DRUPAL_ROOT . '/config/sync/system.menu.main-navigation-subsite-full.yml';
        $yaml = file_get_contents($yamlFile);
        $data = Symfony\Component\Yaml\Yaml::parse($yaml);
        $config = \Drupal::configFactory()->getEditable('system.menu.main-navigation-subsite-full');
        $config->setData($data);
        $config->save();
    }

    $file = __DIR__ . '/content/subsite-full-export.json';
    /** @var \Drupal\content_import_export\Importer $importer */
    $importer = \Drupal::service('content_import_export.importer');
    $importer->importFromFile($file);
}
