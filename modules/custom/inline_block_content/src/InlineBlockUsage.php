<?php

namespace Drupal\inline_block_content;

use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\InlineBlockUsage as InlineBlockUsageBase;
use Drupal\layout_builder\Section;

/**
 * Uses a different table to allow the same block to be used on multiple entities
 */
class InlineBlockUsage extends InlineBlockUsageBase
{

    protected $usageTable = 'inline_block_content_usage';


    protected function getBlockIdFromUuid($uuid) {
        $row = $this->database->query("
            SELECT id
            FROM block_content
            WHERE uuid = :uuid
        ", [
            ':uuid' => $uuid
        ])->fetchAssoc();
        if(empty($row) || empty($row['id'])) {
            return null;
        }
        return $row['id'];
    }

    public function addUsageByBlockUuid($uuid, EntityInterface $entity) {
        $id = $this->getBlockIdFromUuid($uuid);
        if(!empty($id)) {
            return $this->addUsage($id, $entity);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addUsage($block_content_id, EntityInterface $entity) {
        $this->database->merge($this->usageTable)
            ->keys([
                'block_content_id' => $block_content_id,
                'layout_entity_id' => $entity->id(),
                'layout_entity_type' => $entity->getEntityTypeId(),
            ])->execute();
    }

    public function doubleCheckNodeUsage($limit = 100)
    {
        $query = $this->database->select($this->usageTable, 't');
        $query->fields('t', ['block_content_id']);
        $query->isNull('layout_entity_id');
        $query->isNull('layout_entity_type');
        $blockIds = $query->range(0, $limit)->execute()->fetchCol();
        if(empty($blockIds)) {
            return $blockIds;
        }
        $blockUuidMap = $this->database->query("
            SELECT uuid, id
            FROM block_content
            WHERE id IN (:ids[])
        ", [
            ':ids[]' => $blockIds
        ])->fetchAllKeyed();

        $rows = $this->database->query("
            SELECT entity_id, layout_builder__layout_section
            FROM {node__layout_builder__layout}
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $update = [];
        foreach($rows as $row) {
            $nid = $row['entity_id'];
            $serialized = $row['layout_builder__layout_section'];
            /** @var Section $section */
            $section = unserialize($serialized);
            $components = $section->getComponents();
            foreach($components as $component) {
                $config = $component->get('configuration');
                if(!empty($config['uuid']) && isset($blockUuidMap[$config['uuid']])) {
                    $bid = $blockUuidMap[$config['uuid']];
                    $bidx = array_search($bid, $blockIds);
                    if ($bidx !== false) {
                        unset($blockIds[$bidx]);
                        $update[] = [
                            'block_content_id' => $bid,
                            'layout_entity_id' => $nid,
                            'layout_entity_type' => 'node',
                        ];
                    }
                }
            }
        }

        if(!empty($update)) {
            foreach($update as $merge) {
                $this->database->merge($this->usageTable)
                    ->key('block_content_id', $merge['block_content_id'])
                    ->fields($merge)
                    ->execute();
            }
        }

        return array_values($blockIds);
    }

    /**
     * {@inheritdoc}
     */
    public function getUnused($limit = 100) {
        return $this->doubleCheckNodeUsage($limit);
    }

    /**
     * {@inheritdoc}
     */
    public function removeByLayoutEntity(EntityInterface $entity) {
        $query = $this->database->update($this->usageTable)
            ->fields([
                'layout_entity_type' => NULL,
                'layout_entity_id' => NULL,
            ]);
        $query->condition('layout_entity_type', $entity->getEntityTypeId());
        $query->condition('layout_entity_id', $entity->id());
        $query->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteUsage(array $block_content_ids) {
        if (!empty($block_content_ids)) {
            $query = $this->database->delete($this->usageTable)->condition('block_content_id', $block_content_ids, 'IN');
            $query->execute();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUsage($block_content_id) {
        $query = $this->database->select($this->usageTable, 'u');
        $query->condition('block_content_id', $block_content_id);
        $query->fields('u', ['layout_entity_id', 'layout_entity_type']);
        $query->range(0, 1);
        return $query->execute()->fetchObject();
    }
}
