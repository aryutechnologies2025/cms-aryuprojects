<?php

namespace Drupal\sprowt_subsite\Plugin\views\relationship;

use Drupal\views\Plugin\views\relationship\Standard;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A relationship handlers which reverse subsite references.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("subsite_relationship")
 */
class SubsiteFieldRelationship extends Standard
{

    /**
     * Constructs an EntityReverse object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param array $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\views\Plugin\ViewsHandlerManager $join_manager
     *   The views plugin join manager.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->joinManager = $join_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('plugin.manager.views.join')
        );
    }

    public function query()
    {
        // Figure out what base table this relationship brings to the party.
        $table_data = Views::viewsData()->get($this->definition['base']);
        $base_field = empty($this->definition['base field']) ? $table_data['table']['base']['field'] : $this->definition['base field'];

        $this->ensureMyTable();
        $def = $this->definition;

        $alias = $this->definition['base'] . '_' . $this->table;
        $firstAlias = $alias . '__' . 'node';

        $first = [
            'left_table' => $this->tableAlias,
            'left_field' => $this->realField,
            'table' => 'node',
            'field' => 'uuid',
            'adjusted' => TRUE,
        ];

        if (!empty($this->options['required'])) {
            $first['type'] = 'INNER';
        }

        $first_join = $this->joinManager->createInstance('standard', $first);
        $this->first_alias = $this->query->addTable($this->definition['field table'], $this->relationship, $first_join, $firstAlias);


        $def['table'] = $this->definition['base'];
        $def['field'] = $base_field;
        $def['left_table'] = $this->first_alias;
        $def['left_field'] = 'nid';
        $def['adjusted'] = TRUE;

        $id = 'standard';
        $join = $this->joinManager->createInstance($id, $def);
        $join->adjusted = true;

        // use a short alias for this:
        $alias = $def['table'] . '_' . $this->table;

        $this->alias = $this->query->addRelationship($alias, $join, $this->definition['base'], $this->relationship);

        // Add access tags if the base table provide it.
        if (empty($this->query->options['disable_sql_rewrite']) && isset($table_data['table']['base']['access query tag'])) {
            $access_tag = $table_data['table']['base']['access query tag'];
            $this->query->addTag($access_tag);
        }
    }

}
