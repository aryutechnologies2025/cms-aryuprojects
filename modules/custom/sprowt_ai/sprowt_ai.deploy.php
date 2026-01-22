<?php

use Drupal\Core\Serialization\Yaml;


/**
 * Update field configs based on core
 *
 */
function sprowt_ai_deploy_100001()
{
    $entityfields = [
        'node' => [
            'blog' => [
                'body'
            ]
        ],
        'block_content' => [
            'areas_serviced' => [
                'field_heading',
                'field_subheading_tag',
                'field_text'
            ],
            'basic' => [
                'field_heading',
                'field_subheading_tag',
                'field_text'
            ],
            'benefits' => [
                'field_heading',
                'field_subheading_tag',
                'field_text'
            ],
            'city_page_service_blurb' => [
                'body',
                'field_heading',
                'field_subheading_tag',
            ],
            'faqs' => [
                'field_heading',
                'field_subheading_tag',
                'field_text'
            ],
            'hero' => [
                'field_heading',
                'field_subheading_tag',
                'field_text'
            ]
        ]
    ];

    foreach ($entityfields as $entityTypeId => $bundles) {
        foreach ($bundles as $bundle => $fields) {
            foreach ($fields as $fieldName) {
                $configName = "field.field.{$entityTypeId}.{$bundle}.{$fieldName}";
                $config = \Drupal::configFactory()->getEditable($configName);
                $yamlFile = DRUPAL_ROOT . '/config/sync/' . $configName . '.yml';
                $yamlData = file_get_contents($yamlFile);
                $yamlData = Yaml::decode($yamlData);
                $current = $config->get('third_party_settings.sprowt_ai') ?? [];
                if(empty($current)
                    && empty($current['enabled'])
                    && !empty($yamlData['third_party_settings']['sprowt_ai'])
                    && !empty($yamlData['third_party_settings']['sprowt_ai']['enabled'])
                ) {
                    $config->set('third_party_settings.sprowt_ai', $yamlData['third_party_settings']['sprowt_ai']);
                    $config->save();
                }
            }
        }
    }
}
/**
 * Update source to remove local tokens
 *
 */
function sprowt_ai_deploy_100002()
{
    if($_SERVER['SPROWTHQ_SITE_NAME'] != 'sprowt3-source') {
        return;
    }

    /** @var \Drupal\sprowt_ai_prompt_library\AiPromptLibraryService $service */
    $service = \Drupal::service('sprowt_ai_prompt_library.service');
    $db = \Drupal::database();
    $rows = $db->query('SELECT * FROM {sprowt_ai_widget_prompts}')->fetchAll(\PDO::FETCH_ASSOC);
    $updates = [];
    foreach($rows as $row) {
        $prompt = $row['prompt'];
        $newPrompt = $service->deLocalizePrompt($prompt);
        if($prompt == $newPrompt) {
            continue;
        }
        $updates[] = $db->update('sprowt_ai_widget_prompts')
            ->fields(['prompt' => $newPrompt])
            ->condition('id', $row['id']);
    }
    foreach($updates as $update) {
        $update->execute();
    }
}
