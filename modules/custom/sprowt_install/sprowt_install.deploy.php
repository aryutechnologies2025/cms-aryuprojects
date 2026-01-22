<?php

/**
 * See: https://github.com/drush-ops/drush/blob/7b847f659c64d1831377f07cbc64b51831c7183f/tests/fixtures/modules/woot/woot.deploy.php
 *
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and hook_post_update_NAME
 * functions. See https://www.drush.org/latest/deploycommand/#authoring-update-functions
 * for a detailed comparison.
 */

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\webform\Plugin\WebformHandlerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/***
 * For Sprowt deploy hooks are run AFTER config imports
 *
 *
 */

function _sprowt_install_check_schema_version($module) {
    /** @var \Drupal\Core\Update\UpdateHookRegistry $service */
    $service = \Drupal::service('update.update_hook_registry');
    return $service->getInstalledVersion((string) $module);
}

/**
 * Fixes pathauto configs
 */
function sprowt_install_deploy_9001()
{
//    require_once \Drupal::service('extension.path.resolver')->getPath('module', 'pathauto') . '/pathauto.install';
//    $current_version = _sprowt_install_check_schema_version('pathauto');
//    if($current_version < 8108) {
//        pathauto_update_8108();
//    }
}

/**
 * Solution fiunder fixes
 */
function sprowt_install_deploy_9002() {
//    require_once \Drupal::service('extension.path.resolver')->getPath('module', 'solution_finder') . '/solution_finder.install';
//    $current_version = _sprowt_install_check_schema_version('solution_finder');
//    if($current_version < 9001) {
//        solution_finder_update_9001();
//    }
}

/**
 * Admin toolbar updates
 */
function sprowt_install_deploy_9003() {
//    require_once \Drupal::service('extension.path.resolver')->getPath('module', 'admin_toolbar') . '/admin_toolbar.install';
//    require_once \Drupal::service('extension.path.resolver')->getPath('module', 'admin_toolbar_tools') . '/admin_toolbar_tools.install';
//    $at_current_version = _sprowt_install_check_schema_version('admin_toolbar');
//    $att_current_version = _sprowt_install_check_schema_version('admin_toolbar_tools');
//
//    if($at_current_version < 8002) {
//        admin_toolbar_update_8002();
//    }
//    if($att_current_version < 8201) {
//        admin_toolbar_tools_update_8201();
//        admin_toolbar_tools_update_8202();
//    }
}

/**
 * Convert images to webp
 */
function sprowt_install_deploy_9004() {

    //don't need to run this anymore
    return null;

    $originalFiles = \Drupal::state()->get('sprowt_install.files_before_webp', []);
    $fileStorage = \Drupal::entityTypeManager()->getStorage('file');
    $files = $fileStorage->loadByProperties([
        'filemime' => ['image/png', 'image/jpeg', 'image/tiff']
    ]);
    /** @var \Drupal\file\Entity\File $file */
    foreach($files as $file) {
        $fileUri = $file->getFileUri();
        $fileMime = $file->getMimeType();
        try {
            $newFileUri = sprowt_install_convert_to_webp($fileUri, $fileMime);
        }
        catch (\Exception $e) {
            \Drupal::logger('sprowt_install')->error('Failed creating webp file from ' . $fileUri . ': ' . $e->getMessage());
            $newFileUri = null;
        }

        if(!empty($newFileUri)) {
            $originalFiles[$file->id()] = [
                'id' => $file->id(),
                'uri' => $fileUri,
                'filename' => $file->getFilename(),
                'filemime' => $file->getMimeType(),
            ];
            $newFileName = basename($newFileUri);
            image_path_flush($fileUri);
            $file->setFileUri($newFileUri);
            $file->setFilename($newFileName);
            $file->setMimeType('image/webp');
            $file->save();
        }
    }
    \Drupal::state()->set('sprowt_install.files_before_webp', $originalFiles);

    /** @var \Drupal\Core\File\FileSystem $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    /** @var \Drupal\sprowt_settings\SprowtSettings $sprowtSettings */
    $sprowtSettings = \Drupal::service('sprowt_settings.manager');
    $activeTheme = \Drupal::theme()->getActiveTheme();
    $themeFiles = ['logo', 'logo_reverse'];
    $originalThemeFiles = \Drupal::state()->get('sprowt_install.theme_files_before_webp', []);
    foreach ($themeFiles as $themeFileKey) {
        $originalThemeSetting = [
            $themeFileKey . '.use_default' =>  theme_get_setting($themeFileKey . '.use_default') ?? 1,
            $themeFileKey . '.path' => theme_get_setting($themeFileKey . '.path')
        ];
        $logoUseDefault = theme_get_setting($themeFileKey . '.use_default') ?? 1;

        if ($logoUseDefault) {
            $logoPath = 'internal:/' . $sprowtSettings->getThemeSetting($themeFileKey);
        }
        else {
            $logoPath = theme_get_setting($themeFileKey . '.path');
        }

        if(!empty($logoPath)) {
            try {
                $newLogoPath = sprowt_install_convert_to_webp($logoPath);
            }
            catch (\Exception $e) {
                \Drupal::logger('sprowt_install')->error('Failed creating webp file from ' . $fileUri . ': ' . $e->getMessage());
                $newLogoPath = null;
            }
            if (!empty($newLogoPath)) {
                $originalThemeFiles[$themeFileKey] = $originalThemeSetting;
                $config = \Drupal::configFactory()->getEditable($activeTheme->getName() . '.settings');
                $config->set($themeFileKey . '.use_default', 0);
                $config->set($themeFileKey . '.path', $newLogoPath);
                $config->save();
            }
        }
    }

    \Drupal::state()->set('sprowt_install.theme_files_before_webp', $originalThemeFiles);
}

function _sprowt_install_undo_webp_convert() {
    $originalFiles = \Drupal::state()->get('sprowt_install.files_before_webp', []);
    if(!empty($originalFiles)) {
        $fileStorage = \Drupal::entityTypeManager()->getStorage('file');
        $files = $fileStorage->loadMultiple(array_keys($originalFiles));
    }
    else {
        $files = [];
    }
    /** @var \Drupal\file\Entity\File $file */
    foreach($files as $file) {
        $originalInfo = $originalFiles[$file->id()];
        image_path_flush($file->getFileUri());
        $file->setFileUri($originalInfo['uri']);
        $file->setFilename($originalInfo['filename']);
        $file->setMimeType($originalInfo['filemime']);
        $file->save();
    }
    $originalThemeFiles = \Drupal::state()->get('sprowt_install.theme_files_before_webp', []);
    $activeTheme = \Drupal::theme()->getActiveTheme();
    foreach ($originalThemeFiles as $originalThemeFile) {
        $config = \Drupal::configFactory()->getEditable($activeTheme->getName() . '.settings');
        foreach($originalThemeFile as $themeKey => $value) {
            $config->set($themeKey, $value);
        }
        $config->save();
    }
}


/**
 * Update services view
 */
function sprowt_install_deploy_9005() {
    return null;
    $config = \Drupal::configFactory()->getEditable('views.view.services');
    $dataYaml = file_get_contents(DRUPAL_ROOT . '/config/sync/views.view.services.yml');
    $data = \Symfony\Component\Yaml\Yaml::parse($dataYaml);
    $config->setData($data);
    $config->save();
}

/**
 * Update colorbox settings
 */
function sprowt_install_deploy_9006() {
    $config = \Drupal::configFactory()->getEditable('colorbox.settings');
    $uniqueToken = $config->get('advanced.unique_token', true);
    if($uniqueToken) {
        $config->set('advanced.unique_token', 0);
        $config->save();
    }
}

/**
 * Populate new usage table
 */
function sprowt_install_deploy_9007() {
    _sprowt_install_populate_inline_block_usage_table();
}

/**
 * import global blog breadcrumbs block
 */
function sprowt_install_deploy_9008() {
    $uuid = '8f58d2da-75c8-4c68-a8f0-7e8e2e27cf61';
    $blocks = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties([
        'uuid' => $uuid
    ]);
    if(empty($blocks)) {
        /** @var \Drupal\sprowt_content\SprowtContentService $service */
        $service = \Drupal::service('sprowt_content.service');
        /** @var \Drupal\content_import_export\Importer $importer */
        $importer = \Drupal::service('content_import_export.importer');
        $data = $service->getEntitiesByUuid('block_content', [$uuid]);
        $importer->import($data);
    }
}

/**
 * Set page type on special offer form page
 */
function sprowt_install_deploy_9009() {
    $uuid = '84275946-3f55-4072-8dad-5f861acaa16e';
    $node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'uuid' => $uuid
    ]);
    if(empty($node)) {
        return;
    }
    /** @var \Drupal\node\Entity\Node $node */
    $node = array_shift($node);
    $node->set('field_page_type', [
        [
            'value' => 'special_offer_form'
        ]
    ]);
    $node->save();
}

/**
 * Fix careers webform
 */
function sprowt_install_deploy_9010() {

    $configYaml = file_get_contents(DRUPAL_ROOT . '/config/sync/webform.webform.careers.yml');
    $config = Symfony\Component\Yaml\Yaml::parse($configYaml);

    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = \Drupal::entityTypeManager()->getStorage('webform')->load('careers');
    if(!empty($webform)) {
        $handlers = $webform->getHandlers('email');
        foreach ($handlers as $handler) {
            $handlerConfig = $handler->getConfiguration();
            $handlerId = $handler->getHandlerId();
            if ($handlerId == 'email_to_company' && $handlerConfig['label'] == 'Email to Triad') {
                $handlerConfig = $config["handlers"]["email_to_company"];
                $handler->setConfiguration($handlerConfig);
                $webform->updateWebformHandler($handler);
            }
            if ($handlerId == 'email_to_lake_norman'
                || $handlerId == 'email_to_fort_mill'
            ) {
                $webform->deleteWebformHandler($handler);
            }
            $webform->save();
        }
    }
}

/**
 * Fix privacy policy
 */
function sprowt_install_deploy_9011() {
    $node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'uuid' => '303295ff-0b1f-47f0-89d8-0e4cfb028f45'
    ]);
    if(empty($node)) {
       return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    $node = array_shift($node);

    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $layout */
    $layout = $node->get('layout_builder__layout');
    $sections = $layout->getSections();
    /** @var \Drupal\layout_builder\Section $section */
    foreach($sections as $section) {
        $components = $section->getComponents();
        /** @var \Drupal\layout_builder\SectionComponent $component */
        foreach($components as $component) {
            $config = $component->get('configuration');
            if($config['id'] == 'inline_block:basic'
                && trim(strtolower($config['label'])) == 'policy'
            ) {
                $block = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($config['block_revision_id']);
                break;
            }
        }
        if(!empty($block)) {
            break;
        }
    }

    /** @var \Drupal\block_content\Entity\BlockContent $block */
    if(!empty($block)) {
        $textField = $block->get('field_text')->first()->getValue();
        $text = $textField['value'];
        $newText = str_replace('&nbsp;', ' ', $text);
        $newText = preg_replace('#<a href="[^"]*:?void[^>]+>([^<]+)</a>#', '$1', $newText);
        $newTextField = $textField;
        $newTextField['value'] = $newText;
        $block->set('field_text', $newTextField);
        $block->save();
    }
}


/**
 * Create install config
 */
function sprowt_install_deploy_9012() {
    $installed = \Drupal::state()
        ->get('sprowt_site_installed', false);
    if($installed) {
        $config = \Drupal::configFactory()->getEditable('sprowt.site_installed');
        $config->set('installed', true);
        $config->save();
    }
}

/**
 * Import new blocks
 */
function sprowt_install_deploy_9013() {
    $uuids = [
        '6c516ecc-fe32-4e1e-8bcc-d756fd98ff44',
        'c5135cd6-02af-42b4-b4a5-03f89600dad3'
    ];
    /** @var \Drupal\content_import_export\Importer $importer */
    $importer = \Drupal::service('content_import_export.importer');
    foreach ($uuids as $uuid) {
        $blocks = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties([
            'uuid' => $uuid
        ]);
        if(empty($blocks)) {
            $jsonFile = __DIR__ . '/content/block_content--'.$uuid.'.json';
            if(file_exists($jsonFile)) {
                $importer->importFromFile($jsonFile);
            }
        }
    }
}

function sprowt_install_deploy_9014() {
    /** @var \Drupal\Core\Entity\EntityFieldManager $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');
    /** @var \Drupal\Core\Entity\EntityTypeManager $entityTypeManager */
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $database = \Drupal::database();
    $map = $fieldManager->getFieldMapByFieldType('link');
    $entities = [];
    $revisionEntities = [];
    foreach($map as $entityTypeId => $fieldInfo) {
        $entityDefinition = $entityTypeManager->getDefinition($entityTypeId);
        if(!$entityDefinition instanceof \Drupal\Core\Entity\ContentEntityType) {
            continue;
        }
        $storage = $entityTypeManager->getStorage($entityTypeId);
        foreach($fieldInfo as $fieldName => $fieldDetails) {
            $column = $fieldName . '_uri';
            $table = $entityTypeId . '__' . $fieldName;
            if($database->schema()->tableExists($table)) {
                $rows = $database->query("
                    SELECT entity_id, delta, $column
                    FROM $table
                    WHERE $column LIKE 'token:%'
                ")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    if (strpos($row[$column], 'token:[') !== 0) {
                        if (isset($entities[$row['entity_id']])) {
                            $entity = $entities[$row['entity_id']];
                        } else {
                            $entity = $storage->load($row['entity_id']);
                        }
                        /** @var \Drupal\Core\Field\FieldItemList $list */
                        $list = $entity->get($fieldName);
                        /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $item */
                        $item = $list->get($row['delta']);
                        $item->uri = str_replace('token:', 'internal:', $row[$column]);
                        $list->set($row['delta'], $item);
                        $entity->set($fieldName, $list->getValue());
                        $entities[$row['entity_id']] = $entity;
                    }
                }
            }
            $revisionTable = $entityTypeId . '_revision__' . $fieldName;
            if($database->schema()->tableExists($revisionTable)) {
                $rows = $database->query("
                            SELECT entity_id, revision_id, delta, $column
                            FROM $revisionTable
                            WHERE $column LIKE 'token:%'
                        ")->fetchAll(\PDO::FETCH_ASSOC);
                foreach($rows as $row) {
                    if(strpos($row[$column], 'token:[') !== 0) {
                        if(isset($revisionEntities[$row['revision_id']])) {
                            $entity = $revisionEntities[$row['revision_id']];
                        }
                        else {
                            $entity = $storage->loadRevision($row['revision_id']);
                        }
                        /** @var \Drupal\Core\Field\FieldItemList $list */
                        $list = $entity->get($fieldName);
                        /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $item */
                        $item = $list->get($row['delta']);
                        $item->uri = str_replace('token:', 'internal:', $row[$column]);
                        $list->set($row['delta'], $item);
                        $entity->set($fieldName, $list->getValue());
                        $revisionEntities[$row['revision_id']] = $entity;
                    }
                }
            }
        }
    }
    /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
    foreach($entities as $entity) {
        if($entity instanceof \Drupal\Core\Entity\RevisionableContentEntityBase) {
            $entity->setNewRevision(false);
        }
        $entity->save();
    }

    /** @var \Drupal\Core\Entity\RevisionableContentEntityBase $entity */
    foreach($revisionEntities as $entity) {
        $entity->save();
    }
}

/**
 * Force-clear the config_filter plugin cache.
 */
function sprowt_install_deploy_9015() {
    \Drupal::cache('discovery')->delete('config_filter_plugins');
}

/**
 * Set landing pages pages
 */
function sprowt_install_deploy_9016() {
    $uuids = [
        '303295ff-0b1f-47f0-89d8-0e4cfb028f45',
        '49b5ea7c-a5d2-4f68-ba28-d31e49eca6d6',
        '64857db3-ee3f-44ec-915b-6a2a42f33bbb',
        '0973534e-42cc-4214-9861-bcdee5fb36f9',
        '35b69fb6-8142-408a-8a86-9ec2d5d1f4d7',
        'b55c29d7-33a9-4d8b-bedb-bd91e364b2c7',
        'e8eb6fd0-f1e2-40c3-9448-9ce712495641',
        'e29d50fc-485e-4a2a-913b-3c046e9a787b'
    ];

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'uuid' => $uuids
    ]);

    /** @var Node $node */
    foreach($nodes as $node) {
        $currentPackages = sprowt_get_field_value($node, 'field_package_level');
        if(!empty($currentPackages) && !in_array('landing_pages', $currentPackages)) {
            $value = [
                [
                    'value' => 'landing_pages'
                ]
            ];
            foreach($currentPackages as $currentPackage) {
                $value[] = [
                    'value' => $currentPackage
                ];
            }
            $node->set('field_package_level', $value);
            $node->save();
        }
    }
}

/**
 * Update header blocks
 */
function sprowt_install_deploy_9017() {
    $blocks = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties([
        'type' => 'header'
    ]);
    $nonMain = [
        '530a8bc4-a0bc-4089-839b-4e11a1ed8eb6',
        'f8869f56-9d5c-4330-ae9f-0ade36f31167',
        '207a568e-05ee-44bb-ba10-e26b22a3fc3d'
    ];
    $save = [];
    /** @var \Drupal\block_content\Entity\BlockContent $block */
    foreach($blocks as $block) {
        $uuid = $block->uuid();
        if(!in_array($uuid, $nonMain)) {
            $isInMain = sprowt_get_field_value($block, 'field_is_main_header');
            if(empty($isInMain)) {
                $block->field_is_main_header->value = true;
                $save[] = $block;
            }
        }
    }
    if(!empty($save)) {
        foreach($save as $saveBlock) {
            $saveBlock->save();
        }
    }
}

/**
 * Fills all field_url_title fields
 */
function sprowt_install_deploy_100001(&$sandbox)
{

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'page']);
    foreach($nodes as $node) {
        $node->set('field_url_title', [
            'value' => '[node:title]'
        ]);
        $node->save();
    }
}


/**
 * Run the following updates again
 * google_tag: Add hostname to the default container settings and all containers
 * views: Adds a default pager heading.
 * views: Removes entity display cache metadata from views with rendered entity fields.
 * views: Post update configured views for entity reference argument plugin IDs.
 */
function sprowt_install_deploy_100002(&$sandbox)
{
    /** @var \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver */
    $extensionPathResolver = \Drupal::service('extension.path.resolver');

    $tagModulePath = $extensionPathResolver->getPath('module', 'google_tag');
    require_once $tagModulePath . '/google_tag.install';
    google_tag_update_8104($sandbox);

    $viewsPath = $extensionPathResolver->getPath('module', 'views');
    require_once $viewsPath . '/views.post_update.php';
    views_post_update_pager_heading($sandbox);
    views_post_update_rendered_entity_field_cache_metadata($sandbox);
    views_post_update_views_data_argument_plugin_id($sandbox);
}

function sprowt_install_deploy_100003(&$sandbox)
{
    $configFile = DRUPAL_ROOT . '/config/sync/views.view.areas_serviced_subsite.yml';
    $config = \Drupal::service('config.factory')->getEditable('views.view.areas_serviced_subsite');
    $data = Yaml::parse(file_get_contents($configFile));
    $config->setData($data);
    $config->save();
}

/** Turn off advagg js minification */
function sprowt_install_deploy_100004(&$sandbox)
{
    $config = \Drupal::service('config.factory')->getEditable('advagg_js_minify.settings');
    $config->set('minifier', 0);
    $config->save();
}

/** Update webforms to use new current customer fields */
function sprowt_install_deploy_100005(&$sandbox)
{

    $sql = "SELECT count(*) FROM {speed_lead_webform}";
    $sql .= " WHERE form_reactor_enabled = 1";

    $results = \Drupal::database()->query($sql)->fetchField();
    if(!empty($results)) {
        //skip update for speedlead clients
        return;
    }

    $webforms = \Drupal::entityTypeManager()->getStorage('webform')->loadMultiple();

    /** @var \Drupal\webform\Entity\Webform $webform */
    foreach ($webforms as $webform) {
        $components = $webform->getElementsDecodedAndFlattened();
        $saveWebform = false;
        // change current customer text
        foreach ($components as $componentKey => $component) {
            if($componentKey == 'new_customer_select') {
                if($component['#title'] != 'Are You a Current Customer?') {
                    $component['#title'] = 'Are You a Current Customer?';
                    $webform->setElementProperties($componentKey, $component);
                    $saveWebform = true;
                }
            }
        }


        $hasConfirmationUrlHandler = false;

        $addHandlers = [];
        $deleteHandlers = [];
        $handlers = $webform->getHandlers();
        /** @var WebformHandlerInterface $handler */
        foreach ($handlers as $handler) {
            $pluginId = $handler->getPluginId();
            $handlerId = $handler->getHandlerId();
            if($pluginId == 'settings') {
                /** @var \Drupal\webform\Plugin\WebformHandler\SettingsWebformHandler $handler */
                $configuration = $handler->getConfiguration();
                if(!empty($configuration['settings']["confirmation_url"]) && $handler->isEnabled()){
                    $hasConfirmationUrlHandler = true;
                }
                if(!empty($configuration["conditions"]["enabled"][":input[name=\"new_customer_select\"]"])) {
                    if($configuration["conditions"]["enabled"][":input[name=\"new_customer_select\"]"]['value'] == 'Yes') {
                        if($handlerId == 'confirmation_new_customer') {
                            $configuration["conditions"]["enabled"][":input[name=\"new_customer_select\"]"]['value'] = 'No';
                            $handler->setConfiguration($configuration);
                            $saveWebform = true;
                            $webform->updateWebformHandler($handler);
                            continue;
                        }
                        $label = $handler->getLabel();
                        if($label != 'Confirmation URL - Current Customer') {
                            $handler->setLabel('Confirmation URL - Current Customer');
                            $configuration = $handler->getConfiguration();
                            $handlerUrl = $configuration["settings"]["confirmation_url"];
                            $startsWithToken = false;
                            if(strpos($handlerUrl, '/') !== 0) {
                                if(strpos($handlerUrl, '[') === 0) {
                                    $startsWithToken = true;
                                }
                                $parseUrl = 'http://example.com/' . $handlerUrl;
                            }
                            else {
                                $parseUrl = 'http://example.com' . $handlerUrl;
                            }
                            $parts = parse_url($parseUrl);
                            $query = [];
                            if(isset($parts['query'])) {
                                parse_str($parts['query'], $query);
                            }
                            $query['t'] = 'current-customer';
                            $newQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
                            $handlerUrl = $parts['path'] . '?' . $newQuery;
                            if($startsWithToken) {
                                $handlerUrl = ltrim($handlerUrl, '/');
                            }
                            $configuration["settings"]["confirmation_url"] = $handlerUrl;
                            $handler->setConfiguration($configuration);

                            $webform->updateWebformHandler($handler);
                            $saveWebform = true;
                        }
                    }
                    if($configuration["conditions"]["enabled"][":input[name=\"new_customer_select\"]"]['value'] == 'No') {
                        if($handlerId == 'confirmation_current_customer') {
                            $configuration["conditions"]["enabled"][":input[name=\"new_customer_select\"]"]['value'] = 'Yes';
                            $handler->setConfiguration($configuration);
                            $saveWebform = true;
                            $webform->updateWebformHandler($handler);
                            continue;
                        }
                        $label = $handler->getLabel();
                        if($label != 'Confirmation URL - New Customer') {
                            $handler->setLabel('Confirmation URL - New Customer');
                            $configuration = $handler->getConfiguration();
                            $handlerUrl = $configuration["settings"]["confirmation_url"];
                            $startsWithToken = false;
                            if(strpos($handlerUrl, '/') !== 0) {
                                if(strpos($handlerUrl, '[') === 0) {
                                    $startsWithToken = true;
                                }
                                $parseUrl = 'http://example.com/' . $handlerUrl;
                            }
                            else {
                                $parseUrl = 'http://example.com' . $handlerUrl;
                            }
                            $parts = parse_url($parseUrl);
                            $query = [];
                            if(isset($parts['query'])) {
                                parse_str($parts['query'], $query);
                            }
                            $query['t'] = 'new-customer';
                            $newQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
                            $handlerUrl = $parts['path'] . '?' . $newQuery;
                            if($startsWithToken) {
                                $handlerUrl = ltrim($handlerUrl, '/');
                            }
                            $configuration["settings"]["confirmation_url"] = $handlerUrl;
                            $handler->setConfiguration($configuration);

                            $webform->updateWebformHandler($handler);
                            $saveWebform = true;
                        }
                    }
                }
            }
        }

        if(!empty($deleteHandlers)) {
            foreach ($deleteHandlers as $deleteHandler) {
                $webform->deleteWebformHandler($deleteHandler);
            }
        }

        if(!!empty($addHandlers)) {
            foreach ($addHandlers as $addHandler) {
                $webform->addWebformHandler($addHandler);
            }
        }

        if($hasConfirmationUrlHandler) {
            $current = $webform->getSetting('confirmation_url');
            if($current != 'DO NOT USE. UPDATE VIA SETTINGS HANDLERS.') {
                $webform->setSetting('confirmation_url', 'DO NOT USE. UPDATE VIA SETTINGS HANDLERS.');
                $saveWebform = true;
            }
        }

        if($saveWebform) {
            $webform->save();
        }
    }
}

/** Update webforms to remove recaptcha */
function sprowt_install_deploy_100006(&$sandbox)
{
    $webforms = \Drupal::entityTypeManager()->getStorage('webform')->loadMultiple();
    $hasCaptcha = false;
    foreach($webforms as $webform) {
        $components = $webform->getElementsDecodedAndFlattened();
        $saveWebform = false;
        foreach ($components as $componentKey => $component) {
            if($component['#type'] == 'captcha') {
                $webform->deleteElement($componentKey);
                $saveWebform = true;
            }
        }
        if($saveWebform) {
            $hasCaptcha = true;
            $webform->save();
        }
    }

    if($hasCaptcha) {
        $onSprowtHq = _sprowt_install_on_sprowthq();
        $hqBinary = _sprowt_install_hq_binary();
        $siteEnv = _sprowt_install_site_env();
        if($onSprowtHq && !empty($hqBinary) && !empty($siteEnv)) {
            $cmd = [$hqBinary, 'remove-recaptcha', $siteEnv];
            $process = new Process($cmd);
            $process->setWorkingDirectory(DRUPAL_ROOT);
            $process->run();
            if(!$process->isSuccessful()) {
                $error = new ProcessFailedException($process);
                \Drupal\Core\Utility\Error::logException(\Drupal::logger('sprowt_install'), $error);
            }
        }

        $config = \Drupal::service('config.factory')->getEditable('recaptcha.settings');
        $config->set('site_key', null);
        $config->set('secret_key', null);
        $config->save();
    }

}

/**
 * transfer values from field_subsite to field_subsite_multiple
 */
function sprowt_install_deploy_100007(&$sandbox)
{
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'profile']);
    /** @var \Drupal\node\Entity\Node $node */
    foreach($nodes as $node) {
        if($node->hasField('field_subsite')
            && $node->hasField('field_subsite_multiple')
            && $node->get('field_subsite_multiple')->isEmpty()
        ) {
            $subsite = $node->get('field_subsite')->getValue();
            if(!empty($subsite) && !empty($subsite[0]['target'])) {
                $node->set('field_subsite_multiple', [
                    [
                        'target' => $subsite[0]['target']
                    ]
                ]);
                $node->save();
            }
            else {
                $node->set('field_subsite_multiple', [
                    [
                        'target' => '_main'
                    ]
                ]);
                $node->save();
            }
        }
    }
}

/**
 * Update content view to match source
 */
function sprowt_install_deploy_100008(&$sandbox) {
    $sourceConfigKey = 'config_split.config_split.source';
    $sourceConfig = \Drupal::configFactory()->getEditable($sourceConfigKey);
    $siteEnv = _sprowt_install_site_env();
    $parts = explode('.', $siteEnv);
    $site = $parts[0];
    if($site != 'sprowt3-source' && !$sourceConfig->isNew()) {
        $sourceConfig->delete();
    }

    $viewConfigKey = 'views.view.content';
    $yamlFile = DRUPAL_ROOT . '/config/sync/views.view.content.yml';
    $data = Yaml::parse(file_get_contents($yamlFile));
    $config = \Drupal::service('config.factory')->getEditable($viewConfigKey);
    $config->setData($data);
    $config->save();
}

/**
 * Update content view to match source
 */
function sprowt_install_deploy_100009(&$sandbox) {
    $configKey = 'mailgun.settings';
    $config = \Drupal::configFactory()->getEditable($configKey);
    $domain = $config->get('working_domain');
    if($domain == 'dms.workwave.com') {
        $config->set('working_domain', '_sender');
        $config->save();
    }
}

/**
 * Update link fields on locations
 */
function sprowt_install_deploy_100010(&$sandbox) {

    $locations = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'branch',
    ]);

    $industryTerms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'industry',
    ]);

    $industryTermsByLabel = [];
    foreach($industryTerms as $term) {
        $industryTermsByLabel[$term->label()] = $term;
    }

    $map = [
        'field_city_link_home_services' => 'Home Services',
        'field_city_link_lawn_care' => 'Lawn Care',
        'field_city_link_no_industry' => '',
        'field_city_link_pest_control' => 'Pest Control',
        'field_city_link_tree_shrub' => 'Tree & Shrub',
        'field_city_link_wildlife' => 'Wildlife'
    ];

    /** @var Node $location */
    foreach($locations as $location) {
        if(!$location->hasField('field_industry_page_links')) {
            continue;
        }
        $industryLinksValue = $location->get('field_industry_page_links')->getValue() ?? [];
        $change = false;
        foreach($map as $field => $industryTermName) {
            if($location->hasField($field)) {
                $list = $location->get($field);
                if(!$list->isEmpty()) {
                    $link = $list->first()->getValue();
                    $paragraph = Paragraph::create([
                        'type' => 'industry_page_links',
                        'field_page_link' => [$link],
                    ]);
                    if($field != 'field_city_link_no_industry') {
                        $term = $industryTermsByLabel[$industryTermName] ?? null;
                        if(!empty($term)) {
                            $paragraph->set('field_industry', [
                                [
                                    'target_id' => $term->id()
                                ]
                            ]);
                            $paragraph->save();
                        }
                        else {
                            $newIndustryTerm = Term::create([
                                'vid' => 'industry',
                                'name' => $industryTermName,
                            ]);
                            $newIndustryTerm->save();
                            $industryTermsByLabel[$industryTermName] = $newIndustryTerm;
                            $paragraph->set('field_industry', [
                                [
                                    'target_id' => $newIndustryTerm->id()
                                ]
                            ]);
                            $paragraph->save();
                        }
                    }
                    else {
                        $paragraph->save();
                    }
                    if(!$paragraph->isNew()) {
                        $industryLinksValue[] = [
                            'target_id' => $paragraph->id(),
                            'target_revision_id' => $paragraph->getRevisionId()
                        ];
                        $change = true;
                    }
                }
            }
        }
        if($change) {
            $location->set('field_industry_page_links', $industryLinksValue);
            $location->save();
        }
    }
}


/**
 * Migrate metatag description values
 */
function sprowt_install_deploy_100011(&$sandbox) {
    /** @var \Drupal\metatag\MetatagManager $metaTagManager */
    $metaTagManager = \Drupal::service('metatag.manager');

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple();

    /** @var Node $node */
    foreach ($nodes as $node) {
        if($node->hasField('field_meta_description')
            && $node->hasField('field_meta_tags')
        ) {
            $metaTags = $metaTagManager->tagsFromEntity($node);
            $description = $metaTags['description'] ?? null;
            if(!empty($description)) {
                unset($metaTags['description']);
                $node->set('field_meta_tags', metatag_data_encode($metaTags));
                $node->set('field_meta_description', [
                    'value' => $description,
                ]);
                $node->save();
            }
        }
    }

}

/**
 * Set issues and concerns to be noindexed in the sitemap
 */
function sprowt_install_deploy_100012(&$sandbox) {

    /** @var \Drupal\simple_sitemap\Manager\Generator $simpleSiteMap */
    $simpleSiteMap = \Drupal::service('simple_sitemap.generator');
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => ['concern', 'issue'],
    ]);

    $stop = true;

    /** @var \Drupal\node\Entity\Node $node */
    foreach($nodes as $node) {
        $sitemapSettings = $simpleSiteMap->setSitemaps()->entityManager()->getEntityInstanceSettings(
            'node',
            $node->id()
        ) ?? [];
        if(isset($sitemapSettings['default'])) {
            $sitemapSettings = $sitemapSettings['default'];
        }
        $sitemapSettings['index'] = false;
        $simpleSiteMap->setSitemaps()->entityManager()->setEntityInstanceSettings(
            'node',
            $node->id(),
            $sitemapSettings
        );
    }

    $simpleSiteMap->setSitemaps()
        ->rebuildQueue()
        ->generate();
}
