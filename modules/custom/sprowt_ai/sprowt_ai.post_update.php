<?php

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\node\Entity\Node;


/**
 * Install the bulk regenerate action for nodes
 */
function sprowt_ai_post_update_install_node_bulk_action(&$sandbox)
{
    $yaml = file_get_contents(__DIR__ . '/config/install/system.action.node_regenerate_content.yml');
    $data = Symfony\Component\Yaml\Yaml::parse($yaml);
    $config = \Drupal::configFactory()->getEditable('system.action.node_regenerate_content');
    $config->setData($data);
    $config->save();
}

/**
 * Add link field type to supported types
 */
function sprowt_ai_post_update_add_link_to_supported_types(&$sandbox)
{
    $config = \Drupal::configFactory()->getEditable('sprowt_ai.settings');
    $supportedTypes = $config->get('supported_field_types');
    $supportedTypes[] = 'link';
    $config->set('supported_field_types', $supportedTypes);
    $config->save();
}

/**
 * Add field properties to table
 */
function sprowt_ai_post_update_add_field_properties_to_table(&$sandbox)
{
    $table = 'sprowt_ai_widget_prompts';
    $db = \Drupal::database();
    $rows = $db->query("SELECT * FROM $table")->fetchAll(\PDO::FETCH_ASSOC);
    /** @var EntityFieldManager $manager */
    $manager = \Drupal::service('entity_field.manager');
    $fieldStorageDefinitions = [];
    foreach ($rows as $row) {
        $entityType = $row['entity_type'];
        if(!isset($fieldStorageDefinitions[$entityType])) {
            $fieldStorageDefinitions[$entityType] = $manager->getFieldStorageDefinitions($entityType);
        }
        /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storageDefinition */
        $storageDefinition = $fieldStorageDefinitions[$entityType][$row['field_name']];
        $mainProperty =$storageDefinition->getMainPropertyName();
        $conditions = $row;
        unset($conditions['prompt']);
        unset($conditions['system']);
        $update = $db->update($table)
            ->fields([
                'field_property' => $mainProperty,
            ])
        ->condition('id', $row['id']);
        $update->execute();
    }

}
