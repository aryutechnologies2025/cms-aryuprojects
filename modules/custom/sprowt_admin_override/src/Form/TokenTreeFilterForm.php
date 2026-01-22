<?php

namespace Drupal\sprowt_admin_override\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\AttributeArray;
use Drupal\token\TreeBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TokenTreeFilterForm extends FormBase
{

    /**
     * @var \Drupal\token\TreeBuilderInterface
     */
    protected $treeBuilder;

    public function __construct(TreeBuilderInterface $tree_builder) {
        $this->treeBuilder = $tree_builder;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('token.tree_builder')
        );
    }

    public function getFormId()
    {
        return 'token_tree_filter_form';
    }


    public function buildForm(array $form, FormStateInterface $form_state, $rows = [])
    {

        $rowArrays = [];
        foreach ($rows as $row) {
            $rowArray = [];
            $cells = $row['cells'] ?? [];
            /** @var Attribute $attributes */
            $attributes = $row['attributes'];
            foreach($attributes->getIterator() as $attribute => $value) {
                $rowArray[$attribute] = $value->value();
            }
            $rowArray['columns'] = [];
            foreach ($cells as $cellName => $cell) {
                $cell['content'] = $cell['content'] ?? '';
                $rowArray['columns'][$cellName] = (string) $cell['content'];
            }
            $rowArrays[$rowArray['data-tt-id']] = $rowArray;
        }

        $stop = true;

        $filters = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['token-tree-filter-wrap'],
                'data-rows' => json_encode($rowArrays)
            ],
            'filterByToken' => [
                '#type' => 'textfield',
                '#placeholder' => 'Filter',
                '#attributes' => [
                    'class' => ['token-tree-filter'],
                ],
            ]
        ];
        $form['filters'] = $filters;
        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        //do nothing
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        //do nothing
    }
}
