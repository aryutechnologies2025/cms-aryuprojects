<?php

namespace Drupal\sprowt_subsite\Plugin\views\relationship;

use Drupal\views\Plugin\views\relationship\EntityReverse;
use Drupal\views\Views;

/**
 * A relationship handlers which reverse subsite references.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("subsite_reverse")
 */
class SubsiteFieldReverseRelationship extends EntityReverse
{
    public function query()
    {
        $this->ensureMyTable();
        // First, relate our base table to the current base table to the
        // field, using the base table's id field to the field's column.
        $views_data = Views::viewsData()->get($this->table);
        $left_field = $views_data['table']['base']['field'];

        $first = [
            'left_table' => $this->tableAlias,
            'left_field' => $left_field,
            'table' => 'node',
            'field' => 'nid',
            'adjusted' => true,
        ];

        $first_join = $this->joinManager->createInstance('standard', $first);
        $first_alias = $this->tableAlias . '__node';
        $this->first_alias = $this->query->addTable('node', $this->relationship, $first_join, $first_alias);
        $second = [
            'left_table' => $this->first_alias,
            'left_field' => 'uuid',
            'table' => $this->definition['field table'],
            'field' => $this->definition['field field'],
            'adjusted' => TRUE,
        ];

        if (!empty($this->options['required'])) {
            $second['type'] = 'INNER';
        }

        $second_alias  = $this->first_alias . '__' . $this->definition['field table'];

        $second_join = $this->joinManager->createInstance('standard', $second);
        $this->second_alias = $this->query->addTable($this->definition['field table'], $this->relationship, $second_join, $second_alias);


        $third = [
            'left_table' => $this->second_alias,
            'left_field' => 'entity_id',
            'table' => $this->definition['base'],
            'field' => $this->definition['base field'],
            'adjusted' => TRUE,
        ];
        $third_join = $this->joinManager->createInstance('standard', $third);
        $third_join->adjusted = TRUE;

        // use a short alias for this:
        $alias = $this->second_alias . '__' . $this->definition['base'];
        $this->alias = $this->query->addRelationship($alias, $third_join, $this->definition['base'], $this->relationship);
    }
}
