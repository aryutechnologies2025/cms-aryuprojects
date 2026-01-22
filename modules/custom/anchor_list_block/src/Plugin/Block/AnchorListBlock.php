<?php

namespace Drupal\anchor_list_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\AttributeArray;

/**
 * Provides a 'AnchorListBlock' block.
 *
 * @Block(
 *  id = "anchor_list_block",
 *  admin_label = @Translation("Anchor list block"),
 * )
 */
class AnchorListBlock extends BlockBase implements TrustedCallbackInterface
{

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
            'heading' => '',
            'heading_tag' => 'h2',
            'links' => []
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state)
    {
        $form['heading'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Heading'),
            '#default_value' => $this->configuration['heading'],
            '#maxlength' => 1000,
            '#size' => 64,
            '#weight' => 0,
        ];
        $form['heading_tag'] = [
            '#type' => 'select',
            '#title' => $this->t('Heading Tag'),
            '#default_value' => $this->configuration['heading_tag'],
            '#options' => [
                'h1' => 'H1',
                'h2' => 'H2',
                'h3' => 'H3',
                'h4' => 'H4',
                'h5' => 'H5',
                'h6' => 'H6',
                'div' => 'Div'
            ],
            '#weight' => 0,
            '#attributes' => [
                'class' => ['anchor-list-block-select']
            ]
        ];

        $form['links_fieldset'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Link list'),
            '#prefix' => '<div id="link-list-fieldset">',
            '#suffix' => '</div>',
            'links' => [
                '#type' => 'table',
                '#attributes' => [
                    'id' => 'link-value-table'
                ],
                '#tabledrag' => [
                    [
                        'action' => 'order',
                        'relationship' => 'sibling',
                        'group' => 'link-field-weight'
                    ]
                ]
            ],
            'addLink' => [
                '#type' => 'submit',
                '#value' => $this->t('Add Link'),
                '#submit' => [[$this, 'addLink']],
                '#name' => 'addLink',
                '#ajax' => [
                    'callback' => [$this, 'replaceTableCallback'],
                    'wrapper' => 'link-list-fieldset'
                ]
            ]
        ];

        //weirdo workaround from this issue: https://www.drupal.org/project/drupal/issues/2798261
        if($form_state instanceof SubformStateInterface) {
            $settings = $form_state->getCompleteFormState()->getValue('settings');
        }
        else {
            $settings = $form_state->getValues();
        }
        $links = $settings['links_fieldset']['links'] ?? null;
        if(!is_array($links)) {
            $links = [];
            if(!empty($this->configuration['links'])) {
                foreach($this->configuration['links'] as $config) {
                    $linkVal = [];
                    $linkVal['weight'] = $config['weight'];
                    $linkVal['linkFields'] = $config;
                    $links[] = $linkVal;
                }
            }
        }
        uasort($links, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
        $linkFields = [];
        foreach($links as $delta => $link) {
            $linkValues = $link['linkFields'] ?? [];
            $linkFields[$delta] = [
                'linkFields' => [
                    'linkText' => [
                        '#type' => 'textfield',
                        '#title' => $this->t('Link Text'),
                        '#default_value' => $linkValues['linkText'] ?? '',
                    ],
                    'linkUrlSelect' => [
                        '#type' => 'select',
                        '#title' => $this->t('Link to Section'),
                        '#options' => [
                            'custom' => 'Custom'
                        ],
                        '#default_value' => 'custom',
                        '#after_build' => [[$this, 'linkUrlAfterBuild']],
                        '#attributes' => [
                            'class' => ['link-url-select', 'anchor-list-block-select']
                        ]
                    ],
                    'linkUrl' => [
                        '#type' => 'textfield',
                        '#title' => $this->t('Link URL'),
                        '#default_value' => $linkValues['linkUrl'] ?? '',
                        '#attributes' => [
                            'class' => ['link-url'],
                            //'style' => 'display: none;'
                        ]
                    ],
                    'linkTargetBlank' => [
                        '#type' => 'checkbox',
                        '#title' => $this->t('Open link in new tab'),
                        '#default_value' => $linkValues['linkTargetBlank'] ?? false
                    ],
                    'linkNoFollow' => [
                        '#type' => 'checkbox',
                        '#title' => $this->t('No Follow Link'),
                        '#default_value' => $linkValues['linkNoFollow'] ?? false
                    ]
                ],
                'linkActions' => [
                    'removeLink' => [
                        '#type' => 'submit',
                        '#value' => $this->t('Remove'),
                        '#submit' => [[$this, 'removeLink']],
                        '#name' => 'removeLink--' . $delta,
                        '#ajax' => [
                            'callback' => [$this, 'replaceTableCallback'],
                            'wrapper' => 'link-list-fieldset'
                        ]
                    ]
                ],
                'weight' => [
                    '#type' => 'weight',
                    '#title' => $this->t('Weight'),
                    '#title_display' => 'invisible',
                    '#default_value' => $link['weight'] ?? 0,
                    '#attributes' => [
                        'class' => ['link-field-weight']
                    ]
                ],
                '#attributes' => [
                    'class' => ['draggable', 'anchor-list-block-link-value']
                ],
                '#weight' => $link['weight'] ?? 0
            ];
        }

        $form['links_fieldset']['links'] += $linkFields;

        $form['#attached']['library'][] = 'anchor_list_block/config';

        return $form;
    }

    public function linkUrlAfterBuild($element) {
        unset($element['#needs_validation']);

        return $element;
    }

    public function addLink(&$form, FormStateInterface &$formState) {
        $settings = $formState->getValue('settings', []);
        if(empty($settings['links_fieldset']['links'])) {
            $settings['links_fieldset']['links'] = [];
        }
        $settings['links_fieldset']['links'][] = [
            'linkFields' => [],
            'weight' => 0
        ];
        $formState->setValue('settings', $settings);
        $formState->setRebuild();
    }

    public function removeLink(&$form, FormStateInterface &$formState) {
        $trigger = $formState->getTriggeringElement();
        $delta = str_replace('removeLink--', '', $trigger['#name']);
        $settings = $formState->getValue('settings', []);
        unset($settings['links_fieldset']['links'][$delta]);
        $formState->setValue('settings', $settings);
        $formState->setRebuild();
    }

    public function replaceTableCallback(&$form, FormStateInterface &$formState) {
        return $form['settings']['links_fieldset'];
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state)
    {
        //weirdo workaround from this issue: https://www.drupal.org/project/drupal/issues/2798261
        if($form_state instanceof SubformStateInterface) {
            $settings = $form_state->getCompleteFormState()->getValue('settings');
        }
        else {
            $settings = $form_state->getValues();
        }
        $links = $settings['links_fieldset']['links'] ?? null;

        $linkValues = [];
        foreach($links as $link) {
            $linkValue = $link['linkFields'];
            $linkValue['weight'] = $link['weight'];
            $linkValues[] = $linkValue;
        };
        $this->configuration['links'] = $linkValues;
        $this->configuration['heading'] = $settings['heading'];
        $this->configuration['heading_tag'] = $settings['heading_tag'];
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $build = [];
        $build['#theme'] = 'anchor_list_block';
        $build['#heading'] = $this->configuration['heading'];
        $build['#heading_tag'] = $this->configuration['heading_tag'];
        $build['#heading_attributes'] = new Attribute([
            'class' => ['anchor-list-block-heading']
        ]);
        $build['#links'] = [];
        foreach($this->configuration['links'] as $i => $link) {
            $class = preg_replace('/[^a-z\-]+/', '-', strtolower($link['linkText']));
            $class = trim($class, '-');
            $target = (empty($link['linkTargetBlank'])) ? '_self' : '_blank';
            $nofollow = (empty($link['linkNoFollow'])) ? '' : 'nofollow';
            $link_attributes = [
                'href' => $link['linkUrl'],
                'class' => ['anchor-list-block-link', $class . '--link'],
                'data-text' => $link['linkText'],
                'target' => $target,
                'rel' => $nofollow
            ];
            $listItemAttributes = [
                'class' => ['anchor-list-block-list-item', $class . '--list-item'],
            ];
            if($i == 0) {
                $listItemAttributes['class'][] = 'first';
            }
            if(($i + 1) == count($this->configuration['links'])) {
                $listItemAttributes['class'][] = 'last';
            }
            if(count($this->configuration['links']) == 1) {
                $listItemAttributes['class'][] = 'only';
            }
            $link['link_attributes'] = new Attribute($link_attributes);
            $link['list_item_attributes'] = new Attribute($listItemAttributes);
            $build['#links'][] = $link;
        }
        $build['#attributes'] = new Attribute([
            'class' => ['anchor-list-block']
        ]);
        $build['#list_attributes'] = new Attribute([
            'class' => ['anchor-list-block-list']
        ]);

        return $build;
    }

    public static function trustedCallbacks()
    {
        return ['layoutBuilderPreRender'];
    }

    public static function layoutBuilderPreRender($element) {
        $sections = [];
        foreach($element['layout_builder'] as $possibleSection) {
            if(isset($possibleSection['layout-builder__section'])) {
                $settings = $possibleSection['layout-builder__section']['#settings'];
                $sections[] = $settings;
            }
        }
        $element['layout_builder']['#attributes']['data-section-settings'] = json_encode($sections);
        return $element;
    }

}
