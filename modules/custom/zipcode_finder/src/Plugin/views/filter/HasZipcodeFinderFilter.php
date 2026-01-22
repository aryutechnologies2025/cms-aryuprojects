<?php

namespace Drupal\zipcode_finder\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Annotation\ViewsFilter;
use Drupal\views\ManyToOneHelper;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views_contextual_filters_or\Plugin\views\query\ExtendedSql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ViewsFilter("zipcode_finder_log_has_zipcode_finder")
 */
class HasZipcodeFinderFilter extends InOperator
{

    public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->joinManager = $join_manager;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('plugin.manager.views.join')
        );
    }

    public function buildExposeForm(&$form, FormStateInterface $form_state)
    {
        parent::buildExposeForm($form, $form_state);
        //this is always single
        $form['expose']['multiple'] = [
            '#type' => 'value',
            '#value' => false
        ];
    }

    protected function defineOptions() {
        $options = parent::defineOptions();

        $options['operator']['default'] = 'or';
        $options['value']['default'] = [];
        $options['multiple']['default'] = false;
        return $options;
    }

    public function getValueOptions()
    {
        if (isset($this->valueOptions)) {
            return $this->valueOptions;
        }

        $this->valueOptions = [
            'yes' => t('Has zipcode finder'),
            'no' => t('Does not have zipcode finder')
        ];
        return $this->valueOptions;
    }

    protected $valueFormType = 'select';

    public function operators()
    {
        $operators = [
            'or' => [
                'title' => $this->t('Is one of'),
                'method' => 'opHasFinder',
                'short' => $this->t('or'),
                'values' => 1,
            ],
        ];
        return $operators;
    }

    protected function getRelationshipAlias() {
        $this->ensureMyTable();
        $relationships = $this->query->relationships ?? [];
        foreach($relationships as $relationshipAlias => $relationship) {
            if($relationship['link'] == $this->tableAlias) {
                if($relationship['table'] == 'zipcode_finder') {
                    return $relationshipAlias;
                }
            }
        }
        $first = [
            'left_table' => $this->tableAlias,
            'left_field' => 'zipcode',
            'table' => 'zipcode_finder__zipcodes',
            'field' => 'zipcodes_value',
            'adjusted' => TRUE,
        ];

        $first_join = $this->joinManager->createInstance('standard', $first);
        $firstAlias = $this->query->addTable($this->definition['field table'], $this->relationship, $first_join);
        $second = [
            'left_table' => $firstAlias,
            'left_field' => 'entity_id',
            'table' => 'zipcode_finder',
            'field' => 'id',
            'adjusted' => TRUE,
        ];
        $second_join = $this->joinManager->createInstance('standard', $second);
        $second_join->adjusted = TRUE;
        $alias = $this->field .  '__' . $this->tableAlias . '__filter';
        return $this->query->addRelationship($alias, $second_join, 'zipcode_finder', $this->relationship);
    }

    protected function valueForm(&$form, FormStateInterface $form_state)
    {
        parent::valueForm($form, $form_state);
        $options = $this->valueOptions;
        $form['value']['#options'] = array_merge(['All' => '- Select -'], $options);
        $form['value']['#multiple'] = false;
    }

    public function opHasFinder()
    {
        $this->ensureMyTable();
        $value = $this->value;
        if(is_array($value)) {
            $value = array_shift($value);
        }
        if(empty($value) || $value == 'All') {
            return;
        }

        $relationshipAlias = $this->getRelationshipAlias();
        $field = "$relationshipAlias.id";
        if ($value == 'no') {
            $operator = "IS NULL";
        }
        else {
            $operator = "IS NOT NULL";
        }

        $this->query->addWhere($this->options['group'], $field, NULL, $operator);

    }


}
