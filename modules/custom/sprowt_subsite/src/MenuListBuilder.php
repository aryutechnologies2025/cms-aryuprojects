<?php

namespace Drupal\sprowt_subsite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\menu_ui\MenuListBuilder as BaseListBuilder;
use Drupal\node\Entity\Node;
use Drupal\sprowt_subsite\Form\MenuListFilterForm;

class MenuListBuilder extends BaseListBuilder
{
    protected $subSiteMap;


    /**
     * {@inheritdoc}
     */
    protected function getAllEntityIds() {
        $query = $this
            ->getStorage()
            ->getQuery()
            ->sort('label', 'ASC');

        return $query->execute();
    }

    protected function getSubsiteMap() {
        if(isset($this->subSiteMap)) {
            return $this->subSiteMap;
        }
        $map = [];
        $field_type = 'entity_reference';
        /** @var \Drupal\Core\Entity\EntityFieldManager $entityFieldManager */
        $entityFieldManager = \Drupal::service('entity_field.manager');
        $field_map = $entityFieldManager->getFieldMapByFieldType($field_type);
        $nodeFields = $field_map['node'] ?? [];
        $definitions = $entityFieldManager->getFieldStorageDefinitions('node');
        $fields = [];
        foreach(array_keys($nodeFields) as $fieldName) {
            /** @var \Drupal\field\Entity\FieldStorageConfig $definition */
            $definition = $definitions[$fieldName] ?? null;
            if(empty($definition)) {
                continue;
            }
            $entityType = $definition->getSetting('target_type') ?? null;
            if(empty($entityType) || $entityType != 'menu') {
                continue;
            }

            $fields[] = $fieldName;
        }

        $selectSql = [];
        foreach($fields as $fieldName) {
            $valueColumn = $fieldName . '_target_id';
            $selectSql[] = "SELECT entity_id, {$valueColumn} as 'menu' FROM node__{$fieldName}";
        }
        $sql = implode("\nUNION\n", $selectSql);
        $rows = \Drupal::database()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $nids = [];
        foreach($rows as $row) {
            $nids[] = $row['entity_id'];
        }
        if(empty($nids)) {
            $nodes = [];
        }
        else {
            $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
                'nid' => $nids,
                'type' => 'subsite'
            ]);
        }
        foreach($rows as $row) {
            if(empty($map[$row['menu']])
                && !empty($nodes[$row['entity_id']])
            ) {
                if(empty($map[$row['menu']])) {
                    $map[$row['menu']] = [];
                }
                $map[$row['menu']][] = $nodes[$row['entity_id']];
            }
        }
        $this->subSiteMap = $map;
        return $this->subSiteMap;
    }

    public function getFilteredMenuIds($criteria) {
        $adminMenus = [
            'admin',
            'tools',
            'account',
            'devel'
        ];
        $ids = [];
        if(!empty($criteria['type'])) {
            if($criteria['type'] == 'admin') {
                $ids = $adminMenus;
            }
            elseif($criteria['type'] == 'all') {
                $ids = $this->getAllEntityIds();
            }
            else {
                $allMenuIds = $this->getAllEntityIds();
                $ids = array_merge($ids, array_filter($allMenuIds, function($id) use ($adminMenus) {
                    return !in_array($id, $adminMenus);
                }));
            }
        }

        if(!empty($criteria['subsite'])) {
            if(!is_array($criteria['subsite'])) {
                $criteria['subsite'] = [$criteria['subsite']];
            }
            $map = $this->getSubsiteMap();
            $filteredMap = array_filter($map, function($nodes) use ($criteria) {
                $return = false;
                foreach($nodes as $node) {
                    $return |= in_array($node->id(), $criteria['subsite']);
                }
                return $return;
            });
            $filteredIds = array_keys($filteredMap);
            $ids = array_intersect($ids, $filteredIds);
        }
        else {
            $map = $this->getSubsiteMap();
            $subsiteIds = array_keys($map);
            $ids = array_diff($ids, $subsiteIds);
        }
        return $ids;
    }

    public function getEntityIds()
    {
        $request = \Drupal::request();
        $query = $request->query->all() ?? [];
        $type = $query['type'] ?? 'client';
        $subsite = $query['subsite'] ?? [];
        return $this->getFilteredMenuIds([
            'type' => $type,
            'subsite' => $subsite
        ]);
    }

    public function buildHeader()
    {
        $header = parent::buildHeader();
        $header['subsite'] = [
            'data' => 'Subsite',
            'weight' => 0
        ];
        $operations = $header['operations'];
        unset($header['operations']);

        $header['operations'] = [
            'data' => $operations,
            'weight' => 10
        ];

        return $header;
    }

    public function buildRow(EntityInterface $entity)
    {
        $subsiteMap = $this->getSubsiteMap();
        $subsites = $subsiteMap[$entity->id()] ?? null;
        $row = parent::buildRow($entity);
        if(!empty($subsites)) {
            $labels = [];
            foreach($subsites as $subsite) {
                $labels[] = $subsite->label();
            }
        }
        $row['subsite'] = !empty($labels) ? implode(', ', $labels) : '--';
        $operations = $row['operations'];
        unset($row['operations']);
        $row['operations'] = $operations;

        return $row;
    }

    public function load()
    {
        $entity_ids = $this->getEntityIds();
        if(empty($entity_ids)) {
            return [];
        }
        return parent::load(); // TODO: Change the autogenerated stub
    }

    public function render()
    {
        $form = \Drupal::formBuilder()->getForm(MenuListFilterForm::class);
        $build['form'] = $form;
        return $build + parent::render(); // TODO: Change the autogenerated stub
    }
}
