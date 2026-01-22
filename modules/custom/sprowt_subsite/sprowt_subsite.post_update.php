<?php

/**
 * Fill values for tables
 */
function sprowt_subsite_post_update_9001()
{

    $db = \Drupal::database();
    $uuidMap = $db->query("
        SELECT uuid, nid
        FROM node
    ")->fetchAllKeyed();

    $updates = [];

    $field_type = 'sprowt_subsite_reference';
    $new_property = 'target_id';
    $manager   = \Drupal::entityDefinitionUpdateManager();
    $field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType($field_type);
    foreach ($field_map as $entity_type_id => $fields) {
        foreach (array_keys($fields) as $field_name) {
            $field_storage_definition = $manager->getFieldStorageDefinition($field_name, $entity_type_id);
            $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
            if ($storage instanceof \Drupal\Core\Entity\Sql\SqlContentEntityStorage) {
                $table_mapping = $storage->getTableMapping([
                    $field_name => $field_storage_definition,
                ]);
                $table_names = $table_mapping->getDedicatedTableNames();
                $columns = $table_mapping->getColumnNames($field_name);

                foreach ($table_names as $table_name) {
                    $idColumn = $columns[$new_property];
                    $uuidColumn = $columns['target'];

                    $tableRows = $db->query("
                        SELECT * FROM {$table_name}
                    ")->fetchAll(\PDO::FETCH_ASSOC);
                    $updated = [];
                    foreach($tableRows as $tableRow) {
                        $uuid = $tableRow[$uuidColumn] ?? null;
                        if(!empty($uuid)
                            && $uuid != '_main'
                            && !in_array($uuid, $updated)
                            && !empty($uuidMap[$uuid])
                            && empty($tableRow[$idColumn])
                        ) {
                            $updates[] = $db->update($table_name)
                                ->fields([
                                    $idColumn => $uuidMap[$uuid]
                                ])
                                ->condition($uuidColumn, $uuid);
                            $updated[] = $uuid;
                        }
                    }

                }
            }
        }
    }

    $transaction = $db->startTransaction();
    foreach($updates as $update) {
        $update->execute();
    }

    // Commit the transaction by unsetting the $transaction variable.
    unset($transaction);
}
