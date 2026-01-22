<?php declare(strict_types=1);

namespace Drupal\sprowt_admin_override\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Field\FieldDefinition;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;
use Drupal\field\Entity\FieldConfig;
use Drupal\metatag\MetatagTagPluginManager;
use Drupal\node\Entity\Node;
use Drupal\sprowt_ai\AiService;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Sprowt Admin Override form.
 */
class MetatagDashboardForm extends FormBase
{

    /**
     * @var \Drupal\metatag\MetatagManager
     */
    protected $metatagManager;

    /**
     * @var AiService
     */
    protected $aiService;

    protected static $tags = [
        'basic' => [
            'title',
            'keywords'
        ],
        'open_graph' => [
            'og_title',
            'og_description'
        ]
    ];

    public static function create(ContainerInterface $container)
    {
        $static = parent::create($container);
        $static->metatagManager = $container->get('metatag.manager');
        $static->aiService = $container->get('sprowt_ai.service');
        return $static;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_admin_override_metatag_dashboard';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {



        $filters = [
            'filterByTitle' => null,
            'filterByBundle' => [],
            'filterByStatus' => 'all',
            'filterByIndustry' => [],
            'filterBySubsite' => [],
            'filterByRegion' => []
        ];
        foreach($filters as $filter => $value) {
            $filters[$filter] = $form_state->getValue($filter) ?? $value;
        }


        $filterForm = [
            '#type' => 'fieldset',
            '#title' => 'Filters',
            '#attributes' => [
                'class' => ['filter-form-fieldset']
            ]
        ];

        $filterForm['filterByTitle'] = [
            '#type' => 'textfield',
            '#title' => 'Filter by title',
            '#default_value' => $filters['filterByTitle'],
            '#attributes' => [
                'class' => ['filter-field']
            ]
        ];

        $bundleOptions = [];
        $bundles = static::getNodeBundles();
        foreach ($bundles as $bundleId => $bundle) {
            $bundleOptions[$bundleId] = $bundle->label();
        }

        asort($bundleOptions);

        $filterForm['filterByBundle'] = [
            '#type' => 'select',
            '#title' => 'Filter by content type',
            '#options' => $bundleOptions,
            '#default_value' => $filters['filterByBundle'],
            '#multiple' => true,
            '#attributes' => [
                'class' => ['filter-field']
            ]
        ];
        $filterForm['filterByStatus'] = [
            '#type' => 'select',
            '#title' => 'Filter by published status',
            '#options' => [
                'all' => 'All',
                'published' => 'Published',
                'unpublished' => 'Unpublished',
            ],
            '#attributes' => [
                'class' => ['filter-field']
            ]
        ];

        $industryOptions = [];
        $industries = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('industry');
        foreach ($industries as $industry) {
            $industryOptions[$industry->tid] = $industry->name;
        }
        asort($industryOptions);

        $filterForm['filterByIndustry'] = [
            '#type' => 'select',
            '#title' => 'Filter by industry',
            '#options' => $industryOptions,
            '#multiple' => true,
            '#default_value' => $filters['filterByIndustry'],
            '#attributes' => [
                'class' => ['filter-field']
            ]
        ];

        $regionOptions = [];
        $regions = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
            'vid' => 'region'
        ]);
        foreach ($regions as $region) {
            $regionOptions[$region->id()] = $region->label();
        }
        asort($regionOptions);

        $filterForm['filterByRegion'] = [
            '#type' => 'select',
            '#title' => 'Filter by region',
            '#options' => $regionOptions,
            '#multiple' => true,
            '#default_value' => $filters['filterByRegion'],
            '#attributes' => [
                'class' => ['filter-field']
            ]
        ];

        $subsites = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'type' => 'subsite'
        ]);
        $subsiteOptions = [
            '_main' => 'Main site'
        ];
        /** @var Node $subsite */
        foreach($subsites as $subsite) {
            $subsiteOptions[$subsite->uuid()] = $subsite->label();
        }
        $filterForm['filterBySubsite'] = [
            '#type' => 'select',
            '#title' => 'Filter by subsite',
            '#options' => $subsiteOptions,
            '#multiple' => true,
            '#default_value' => $filters['filterBySubsite'],
            '#attributes' => [
                'class' => ['filter-field']
            ]
        ];

        $filterForm['actions'] = [
            '#type' => 'actions',
            'filterSubmit' => [
                '#type' => 'submit',
                '#value' => 'Apply',
                '#submit' => [[$this, 'submitFilter']],
                '#ajax' => [
                    'callback' => [static::class, 'ajaxFilterReturn'],
                    'wrapper' => 'nodes-wrapper',
                    'effect' => 'fade',
                    'progress' => [
                        'type' => 'throbber',
                        'message' => t('Applying filters...'),
                    ],
                ],
                '#prefix' => '<div class="submit-filter-wrap">',
                '#suffix' => '</div>',
            ],
            'filterClear' => [
                '#type' => 'html_tag',
                '#tag' => 'button',
                '#value' => 'Clear',
                '#attributes' => [
                    'class' => ['button', 'clear-button'],
                    'type' => 'button'
                ]
            ]
        ];

        $form['backToTop'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<a href="#top" class="back-to-top">Back to top</a>'),
        ];

        $form['filters'] = $filterForm;


        $form['nodes'] = [
            '#type' => '#container',
            '#prefix' => '<div id="nodes-wrapper">',
            '#suffix' => '</div>',
        ];

        $this->setNodeElements($form, $form_state, $filters);

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Save All'),
                '#submit' => [[$this, 'submitAll']]
            ],
        ];

        $form['#attached']['library'][] = 'sprowt_admin_override/metatag_dashboard';

        return $form;
    }

    public static function getNodeBundles() {
        /** @var EntityFieldManager $fieldManager */
        $fieldManager = \Drupal::service('entity_field.manager');

        $fieldMap = $fieldManager->getFieldMapByFieldType('metatag');
        $bundles = &drupal_static(__FUNCTION__);
        if(empty($bundles)) {
            $bundles = [];

            foreach ($fieldMap as $entityType => $fields) {
                foreach ($fields as $fieldName => $fieldInfo) {
                    foreach ($fieldInfo['bundles'] as $bundleId) {
                        if (!isset($bundles[$bundleId])) {
                            $bundles[$bundleId] = \Drupal::entityTypeManager()->getStorage('node_type')->load($bundleId);
                        }
                    }
                }
            }
        }
        return $bundles;
    }

    public static function filterNode(Node $node, $filters)
    {
        /**
         * $filters = [
         * 'filterByTitle' => null,
         * 'filterByBundle' => [],
         * 'filterByStatus' => 'all',
         * 'filterByIndustry' => [],
         * 'filterBySubsite' => [],
         * 'filterByRegion' => [],
         * ];
         */

        $ret = true;
        if (!empty($filters['filterByTitle'])) {
            $title = strtolower($node->label());
            $words = explode(' ', strtolower($filters['filterByTitle']));
            foreach($words as $word) {
                if (strpos($title, $word) === false) {
                    $ret = false;
                    break;
                }
            }
        }

        if(!empty($filters['filterByBundle'])) {
            if (!in_array($node->bundle(), $filters['filterByBundle'])) {
                $ret = false;
            }
        }


        if (!empty($filters['filterByStatus'])) {
            if ($filters['filterByStatus'] == 'published' && !$node->isPublished()) {
                $ret = false;
            }
            if ($filters['filterByStatus'] == 'unpublished' && $node->isPublished()) {
                $ret = false;
            }
        }
        if (!empty($filters['filterByIndustry'])) {
            if(!$node->hasField('field_industry')) {
                $ret = false;
            }
            else {
                $industries = $node->get('field_industry')->referencedEntities();
                $found = false;
                foreach ($industries as $industry) {
                    if (in_array($industry->id(), $filters['filterByIndustry'])) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $ret = false;
                }
            }
        }
        if (!empty($filters['filterBySubsite'])) {
            if(!$node->hasField('field_subsite')) {
                $ret = false;
            }
            else {
                $subsite = $node->get('field_subsite')->referencedEntities();
                $found = false;
                foreach ($subsite as $subsite) {
                    $test = $subsite;
                    if($test instanceof Node) {
                        $test = $test->uuid();
                    }
                    if (in_array($test, $filters['filterBySubsite'])) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $ret = false;
                }
            }
        }
        if(!empty($filters['filterByRegion'])) {
            $region_fields = self::getRegionFields();
            $hasRegion = false;
            foreach ($region_fields as $region_field) {
                if(!$node->hasField($region_field)) {
                    continue;
                }
                $regions = $node->get($region_field)->referencedEntities();
                $found = false;
                /** @var Term $region */
                foreach ($regions as $region) {
                    $test = $region->id();
                    if (in_array($test, $filters['filterByRegion'])) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $hasRegion = true;
                    break;
                }
            }
            if (!$hasRegion) {
                $ret = false;
            }
        }

        return $ret;
    }

    public static function getRegionFields() {
        $region_fields = &drupal_static('sprowt_metatag_dashboard_region_fields');
        if(empty($region_fields)) {
            $region_fields = [];
            $bundles = self::getNodeBundles();
            /** @var EntityFieldManager $fieldManager */
            $fieldManager = \Drupal::service('entity_field.manager');
            foreach ($bundles as $bundle => $bundleDef) {
                $fieldDefinitions = $fieldManager->getFieldDefinitions('node', $bundle);
                foreach ($fieldDefinitions as $field_name => $fieldDefinition) {
                    if($fieldDefinition instanceof FieldConfig) {
                        if($fieldDefinition->getType() == 'entity_reference') {
                            $targetType = $fieldDefinition->getSetting('target_type');
                            if($targetType == 'taxonomy_term') {
                                $handlerSettings = $fieldDefinition->getSetting('handler_settings');
                                if(in_array('region', $handlerSettings['target_bundles'])) {
                                    if(!in_array($field_name, $region_fields)) {
                                        $region_fields[] = $field_name;
                                    }
                                }
                            }

                        }
                    }

                }
            }

        }

        return $region_fields;
    }


    public static function getNodeMap($bundles = [], $filters = [])
    {
        if(empty($bundles)) {
            $bundles = self::getNodeBundles();
        }
        $nodeMap = [];

        $allNodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'type' => array_keys($bundles),
        ]);
        $allNodes = array_filter($allNodes, function ($node) use ($filters) {
            return static::filterNode($node, $filters);
        });
        /** @var Node $node */
        foreach ($allNodes as $node) {
            $bundle = $node->bundle();
            if (empty($nodeMap[$bundle])) {
                $nodeMap[$bundle] = [];
            }
            $nodeMap[$bundle][] = $node;
        }

        ksort($nodeMap);

        return $nodeMap;
    }

    public function setNodeElements(&$form, FormStateInterface $form_state, $filters)
    {
        $bundles = static::getNodeBundles();
        $nodeMap = static::getNodeMap($bundles, $filters);

        $bundleCount = [];
        $nodeCount = 0;
        foreach ($nodeMap as $bundleId => $nodes) {
            $nodeCount += count($nodes);
            $bundleCount[] = [
                'bundleId' => $bundleId,
                'count' => count($nodes),
                'name' => $bundles[$bundleId]->label()
            ];
        }

        $countMarkup = [
            '#type' => 'html_tag',
            '#tag' => 'ul',
            '#attributes' => [
                'class' => ['bundle-count'],
            ],
            'total' => [
                '#type' => 'html_tag',
                '#tag' => 'li',
                'value' => [
                    '#type' => 'markup',
                    '#markup' => Markup::create('<strong>Total: </strong> <span>'.$nodeCount.'</span>'),
                ]
            ]
        ];
        foreach ($bundleCount as $bundle) {
            $countMarkup[$bundle['bundleId']] = [
                '#type' => 'html_tag',
                '#tag' => 'li',
                'value' => [
                    '#type' => 'markup',
                    '#markup' => Markup::create('<strong><a class="bundle-title-link" href="#'.$bundle['bundleId'].'-title">' . $bundle['name'] . '</a>: </strong> <span>' . $bundle['count'] . '</span>'),
                ]
            ];
        }

        $form['nodes']['count'] = $countMarkup;

        foreach ($nodeMap as $bundleId => $nodes) {
            $form['nodes'][$bundleId] = [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => [
                    'class' => ['bundle-wrap'],
                    'data-bundle' => $bundleId
                ]
            ];
            $form['nodes'][$bundleId]['jumpLink'] = [
                '#type' => 'markup',
                '#markup' => Markup::create('<a id="' . $bundleId . '-title" class="jump-link"></a>'),
            ];
            $form['nodes'][$bundleId]['title'] = [
                '#type' => 'markup',
                '#markup' => Markup::create('<h3>' . $bundles[$bundleId]->label() . '</h3>'),
            ];
            foreach ($nodes as $node) {
                $element = static::nodeFieldSet($node, $form_state);
                $form['nodes'][$bundleId]['node:' . $node->id()] = $element;
            }
        }
    }

    public static function addTokenTreeToElement(array &$element, $tokenTypes = [])
    {
        $token_tree = [
            '#theme' => 'token_tree_link',
            '#token_types' => $tokenTypes,
        ];
        $rendered_token_tree = \Drupal::service('renderer')->render($token_tree);
        $original_description = $element['#description'] ?? '';
        if(!empty($original_description)) {
            $original_description .= '<br>';
        }
        $markup = \Drupal\Core\Render\Markup::create(t((string) $original_description  . 'This field supports tokens. @browse_tokens_link', [
                '@browse_tokens_link' => $rendered_token_tree,
            ]));

        $element['#description'] = $markup;

        return $element;
    }

    public static function nodeFieldSet(Node $node, $form_state)
    {
        /** @var \Drupal\metatag\MetatagManager $metatagManager */
        $metatagManager = \Drupal::service('metatag.manager');
        $idPrefix = $node->id() . '--';
        $wrapperId = $idPrefix . 'node-fieldset-wrapper';

        $metatags = $metatagManager->tagsFromEntityWithDefaults($node);

        $fieldset = [
            '#type' => 'fieldset',
            '#title' => Markup::create('<span class="title">' . $node->label() . ' [' . $node->id() . ']</span> <a href="'.$node->toUrl('edit-form')->toString().'" target="_blank">edit</a>'),
            '#idPrefix' => $idPrefix,
            '#nid' => $node->id(),
        ];

        $wrapperAttributes = [
            'id' => $wrapperId,
            'class' => ['node-fieldset-wrapper'],
            'data-node-id' => $node->id(),
            'data-bundle' => $node->bundle(),
        ];
        $fieldset['published'] = [
            '#type' => 'item',
            '#title' => 'Is published?',
            '#description' => $node->isPublished() ? 'Yes' : 'No',
            '#description_toggle' => false
        ];
        if($node->hasField('field_industry')) {
            $industryTerm = $node->get('field_industry')->entity;
            if($industryTerm instanceof \Drupal\taxonomy\Entity\Term) {
                $wrapperAttributes['data-industry-uuid'] = $node->get('field_industry')->entity->uuid();
                $fieldset['industry'] = [
                    '#type' => 'item',
                    '#title' => 'Industry',
                    '#description' => $industryTerm->label(),
                    '#description_toggle' => false
                ];
            }
        }

        if($node->hasField('field_subsite')) {
            /** @var Node $subsite */
            $subsite = $node->get('field_subsite')->target;
            if($subsite == '_main') {
                $wrapperAttributes['data-subsite-uuid'] = $subsite;
                $fieldset['subsite'] = [
                    '#type' => 'item',
                    '#title' => 'Subsite',
                    '#description' => 'Main',
                    '#description_toggle' => false
                ];
            }
            else {
                $subsiteId = $node->get('field_subsite')->target_id;
                if(!empty($subsiteId)) {
                    $subsite = Node::load($subsiteId);
                }
            }
            if($subsite instanceof \Drupal\node\Entity\Node) {
                $wrapperAttributes['data-subsite-uuid'] = $node->get('field_subsite')->entity->uuid();
                $fieldset['subsite'] = [
                    '#type' => 'item',
                    '#title' => 'Subsite',
                    '#value' => $subsite->label(),
                ];
            }
        }

        $wrapperAttributes = new Attribute($wrapperAttributes);
        $fieldset['#prefix'] = '<div ' . $wrapperAttributes . '>';
        $fieldset['#suffix'] = '</div>';

        $tokenTypes = ['node'];

        $metaTagFormElement = [];

        $metaTagFormElement = $metatagManager->form($metatags, $metaTagFormElement, ['node'], array_keys(static::$tags));

        foreach(static::$tags as $tagCat => $tagIds) {
            $fieldset[$idPrefix . $tagCat] = [
                '#type' => 'details',
                '#title' => $metaTagFormElement[$tagCat]['#title'],
                '#open' => true,
            ];
            foreach($tagIds as $tagId) {
                $fieldset[$idPrefix . $tagCat][$idPrefix . $tagId] = $metaTagFormElement[$tagCat][$tagId];
                $fieldset[$idPrefix . $tagCat][$idPrefix . $tagId]['#attributes']['class'][] = 'metatag-' . $tagId . '-field';
                $fieldset[$idPrefix . $tagCat][$idPrefix . $tagId]['#attributes']['data-original-value'] = (string)$fieldset[$idPrefix . $tagCat][$idPrefix . $tagId]['#default_value'];
                $fieldset[$idPrefix . $tagCat][$idPrefix . $tagId] = static::addTokenTreeToElement($fieldset[$idPrefix . $tagCat][$idPrefix . $tagId], $tokenTypes);
            }
        }

        $fieldset[$idPrefix .'basic'][$idPrefix . 'description--wrap'] = static::descriptionMetatagField($node, $idPrefix, $form_state);

        $fieldset['actions'] = [
            '#type' => 'actions',
        ];
        $fieldset['actions'][$idPrefix . 'submit'] = [
            '#type' => 'submit',
            '#value' => 'Save node metatags',
            '#name' => $idPrefix . 'submit',
            '#idPrefix' => $idPrefix,
            '#nid' => $node->id(),
            '#submit' => [[static::class, 'ajaxSaveNodeMetatags']],
            '#ajax' => [
                'callback' => [static::class, 'ajaxReturnNodeFieldSet'],
                'wrapper' => $wrapperId,
                'effect' => 'fade',
                'progress' => [
                    'type' => 'throbber',
                    'message' => t('Saving...'),
                ],
            ]
        ];

        return $fieldset;
    }

    public static function descriptionMetatagField(Node $node, $idPrefix, $form_state) {
        if(!$node->hasField('field_meta_description')) {
            return null;
        }
        /** @var AiService $aiService */
        $aiService = \Drupal::service('sprowt_ai.service');
        $definition = $node->getFieldDefinition('field_meta_description');
        /** @var EntityDisplayRepository $displayRepo */
        $displayRepo = \Drupal::service('entity_display.repository');
        $display = $displayRepo->getFormDisplay('node', $node->bundle(), 'default');
        $widget = $display->getRenderer('field_meta_description');
        $list = $node->get('field_meta_description');
        if(!isset($list[0])) {
            $list->appendItem();
        }
        $element = [
            '#title' => 'Meta description',
            '#process' => [[static::class, 'processMetatagField']],
            '#node' => $node,
            '#idPrefix' => $idPrefix,
        ];
        $elementForm = [];
        $widgetForm = $widget->formElement($list, 0, $element, $elementForm, $form_state);
        $tokenTypes = ['node'];
        $wrap = [
            '#type' => 'container',
            '#attributes' => [
                'class' => ['description-metatag-field-wrapper'],
            ],
        ];
        $wrap[$idPrefix . 'description'] = $widgetForm;
//        $settings = $definition->getThirdPartySettings('sprowt_ai');
//        $promptValue = $aiService->getWidgetPrompt($node, 'field_meta_description', 0, 'value');
//        $systemValue = $aiService->getWidgetSystem($node, 'field_meta_description', 0, 'value');
//        $itemValue = $node->get('field_meta_description')->value;
//

//        $stop = true;
//        if(!empty($settings['enabled'])) {
//            $options = [];
//            $options['temperature'] = $settings['details']['temperature'] ?? 1.0;
//            $options['tokenData'] = [
//                'node' => $node->id()
//            ];
//            $wrap['promptWrap'] = [
//                '#type' => 'details',
//                '#title' => 'AI Prompt',
//                '#description' => 'Show the AI prompt in the field',
//                '#open' => !empty($promptValue)
//            ];
//            $wrap['promptWrap'][$idPrefix . 'description-system'] = [
//                '#type' => 'select',
//                '#title' => t('Override system user'),
//                '#description' => 'Override the system user set in the field settings',
//                '#options' => AiService::AiSystemOptions(),
//                '#empty_option' => t('Use default'),
//                '#empty_value' => '',
//                '#default_value' => $systemValue,
//                '#attributes' => [
//                    'class' => ['system-field']
//                ]
//            ];
//
//            $options['insertSelector'] = '[data-prompt-selector="'.$idPrefix . 'description'.'"]';
//            $options['insertParents'] = [true];
//
//            $wrap['promptWrap'][$idPrefix . 'description-prompt'] = [
//                '#title' => 'Prompt',
//                '#type' => 'claude_prompt',
//                '#max_tokens' => $settings['details']['max_tokens'] ?? 1024,
//                '#system' => $settings['details']['system'] ?? null,
//                '#weight' => 99,
//                '#prompt_options' => $options,
//                '#description' => 'For help crafting a good prompt, see this <a href="https://docs.anthropic.com/en/docs/prompt-engineering" target="_blank">documentation</a>.',
//                '#default_value' => $promptValue,
//            ];
//        }
//
//
//        $wrap[$idPrefix . 'description'] = [
//            '#type' => 'textarea',
//            '#title' => 'Meta Description',
//            '#default_value' => $itemValue,
//            '#description' => 'The meta description is used by search engines to display a short description of your page.',
//            '#attributes' => [
//                'data-prompt-selector' => $idPrefix . 'description',
//            ]
//        ];

//        $wrap['promptWrap'][$idPrefix . 'description-prompt']['#prompt_options']['insertElement'] = $wrap[$idPrefix . 'description'];

//        static::addTokenTreeToElement($wrap[$idPrefix . 'description'], $tokenTypes);
        return $wrap;
    }

    public static function processMetatagField(&$element, FormStateInterface $form_state, &$complete_form) {
        /** @var AiService $aiService */
        $aiService = \Drupal::service('sprowt_ai.service');
        $node = $element['#node'];
        $items = $node->get('field_meta_description');
        $definition = $items->getFieldDefinition();
        $settings = $definition->getThirdPartySettings('sprowt_ai');
        $options = [];
        $options['temperature'] = $settings['details']['temperature'] ?? 1.0;
        $options['tokenData'] = [
            'node' => $node->id()
        ];
        $options['references'] = AiService::extractReferencesFromEntity($node);


        $idPrefix = $element['#idPrefix'];
        $dataKey = implode('__', [
            'node',
            'field_meta_description',
            0,
            $node->id()
        ]);

        $fieldElementId = $dataKey . '__value';
        $widgetValueElement = $element['value'];
        unset($element['value']);
        unset($widgetValueElement['#process']);
        $element[$fieldElementId] = $widgetValueElement;
        $widgetValueElement = &$element[$fieldElementId];
        $selector = '[data-prompt-selector="' . trim($fieldElementId) . '"]';
        $widgetValueElement['#attributes']['data-prompt-selector'] = $fieldElementId;

        $text = 'Enable AI';
        $prompt = $aiService->getWidgetPrompt($node, 'field_meta_description', 0, 'value');
        if(!empty($prompt)) {
            $text = 'Generate content';
        }
        $system = $aiService->getWidgetSystem($node, 'field_meta_description', 0, 'value');

        $fieldPrefix = '<button type="button" class="sprowt-ai-generate-content-button button button--small" ' .
            'data-widget-key="' . $dataKey . '" ' .
            'data-selector="'.Html::escape($selector).'" ' .
            'data-options="' . Html::escape(json_encode($options)).'" ' .
            'data-entity-uuid="' . Html::escape($node->uuid()) . '" ' .
            'data-entity-bundle="' . Html::escape($node->bundle()) . '" ' .
            'data-entity-type="' . Html::escape($node->getEntityTypeId()) . '" ' .
            'data-widget-value-element="' . Html::escape(json_encode($widgetValueElement)) . '" ' .
            'data-widget-value="'  . Html::escape($widgetValueElement['#default_value'] ?? '') . '" ' .
            'data-field-property="' .  Html::escape('value') . '" ' .
            '>'.$text.'</button>';
        $widgetValueElement['#field_prefix'] = Markup::create($fieldPrefix);
        $referenceId = $dataKey . '__reference_button';
        $referenceButton = [
            '#type' => 'button',
            '#value' => 'Insert reference -- ' . $referenceId,
            '#ajax' => [
                'callback' => [AiService::class, 'updateEntityReferencesAjax'],
                'event' => 'click',
                'progress' => [
                    'type' => 'throbber',
                    'message' => 'Please wait...',
                ],
            ],
            '#name' => $referenceId,
            '#attributes' => [
                'class' => ['button', 'reference-button'],
                'data-selector' => $selector,
                'data-reference-button' => $dataKey,
            ],
            '#prefix' => '<div class="sprowt-ai-reference-button-wrapper hidden" data-key="' . $dataKey . '">',
            '#suffix' => '</div>',
            '#validate' => []
        ];
        $element[$referenceId] = $referenceButton;
        $details = [
            '#type' => 'container',
            '#attributes' => [
                'class' => ['sprowt-ai-details', 'hidden'],
            ]
        ];


        $details[$dataKey . '__system'] = [
            '#type' => 'hidden',
            '#attributes' => [
                'class' => ['system-field'],
                'data-system-field' => $dataKey,
            ]
        ];
        $details[$dataKey . '__prompt'] = [
            '#type' => 'hidden',
            '#attributes' => [
                'class' => ['prompt-field'],
                'data-prompt-field' => $dataKey,
            ]
        ];

        if (!empty($prompt)) {
            $details[$dataKey . '__prompt']['#default_value'] = $prompt;
        }
        if (!empty($system)) {
            $details[$dataKey . '__system']['#default_value'] = $system;
        }
        $element[$dataKey . '__prompt_details'] = $details;
        $complete_form['#attached']['library'][] = 'sprowt_ai/altered_widget';

        return $element;
    }

    public static function saveNodeMetatags(Node $node, FormStateInterface $form_state, ?\Drupal\metatag\MetatagManager $metaTagManager = null)
    {
        if(!isset($metaTagManager)) {
            /** @var \Drupal\metatag\MetatagManager $metatagManager */
            $metatagManager = \Drupal::service('metatag.manager');
        }
        $nodeId = $node->id();
        $values = $form_state->get('valueMap')[$nodeId] ?? [];
        $originalValues = $metatagManager->tagsFromEntityWithDefaults($node);
        $newValues = array_merge($originalValues, $values);
        $newValues = array_filter($newValues, function ($value) {
            return !empty($value);
        });
        if($originalValues !== $newValues) {
            $node->set('field_meta_tags', serialize($newValues));
            $node->setNewRevision();
            $node->setRevisionUserId(\Drupal::currentUser()->id());
            $node->setRevisionLogMessage('Metatags updated by ' . \Drupal::currentUser()->getAccountName());
            $node->save();
        }
        return $node;
    }

    public static function saveNodeMetaDescription(Node $node, FormStateInterface $form_state) {
        $dataKey = implode('__', [
            'node',
            'field_meta_description',
            0,
            $node->id()
        ]);
        $systemValue = $form_state->getValue($dataKey . '__system');
        $promptValue = $form_state->getValue($dataKey . '__prompt');
        $itemValue = $form_state->getValue($dataKey . '__value');
        $original = $node->get('field_meta_description')->value;
        if($original !== $itemValue) {
            $node->set('field_meta_description', $itemValue);
            $node->setNewRevision();
            $node->setRevisionUserId(\Drupal::currentUser()->id());
            $node->setRevisionLogMessage('Metatag description updated by ' . \Drupal::currentUser()->getAccountName());
            $node->save();
        }

        AiService::saveWidgetSystem($node, 'field_meta_description', 0, 'value', $systemValue);
        AiService::saveWidgetPrompt($node, 'field_meta_description', 0, 'value', $promptValue);
        return $node;
    }

    public static function ajaxReturnNodeFieldSet(array &$form, FormStateInterface $form_state)
    {
        $trigger = $form_state->getTriggeringElement();
        $parents = $trigger['#array_parents'];
        array_pop($parents); //remove button
        array_pop($parents); //remove actions
        $fieldset = NestedArray::getValue($form, $parents);
        return $fieldset;
    }

    public static function ajaxSaveNodeMetatags(array &$form, FormStateInterface $form_state)
    {
        $trigger = $form_state->getTriggeringElement();
        $nodeId = $trigger['#nid'];
        $idPrefix = $trigger['#idPrefix'];
        $node = Node::load($nodeId);
        $rid = $node->getRevisionId();
        $node = static::saveNodeMetatags($node, $form_state);
        $node = static::saveNodeMetaDescription($node, $form_state);
        $newRid = $node->getRevisionId();
        if($rid != $newRid) {
            \Drupal::messenger()->addMessage('Metatags saved');
            $form_state->setRebuild();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        /** @var MetatagTagPluginManager $tagManager */
        $tagManager = \Drupal::service('plugin.manager.metatag.tag');
        $valueMap = [];
        foreach($form['nodes'] as $bundleId => $nodeFieldsets) {
            if(is_array($nodeFieldsets)) {
                foreach ($nodeFieldsets as $fieldSetName => $fieldset) {
                    if (strpos((string)$fieldSetName, 'node:') !== 0) {
                        continue;
                    }
                    $nodeId = $fieldset['#nid'];
                    $idPrefix = $fieldset['#idPrefix'];

                    $tagDefs = static::$tags;
                    $valueMap[$nodeId] = [];
                    foreach ($tagDefs as $tagCat => $tagIds) {
                        foreach ($tagIds as $tagId) {
                            $tag = $tagManager->createInstance($tagId);
                            $tag->setValue($form_state->getValue($idPrefix . $tagId));
                            $val = $tag->value();
                            $valueMap[$nodeId][$tagId] = $val;
                        }
                    }
                }
            }
        }
        $form_state->set('valueMap', $valueMap);
    }

    public function submitAll(array &$form, FormStateInterface $form_state)
    {
        $valueMap = $form_state->get('valueMap');
        $nodes = Node::loadMultiple(array_keys($valueMap));
        foreach ($nodes as $node) {
            $node = static::saveNodeMetatags($node, $form_state);
            $node = static::saveNodeMetaDescription($node, $form_state);
        }
        \Drupal::messenger()->addMessage('All metatags saved');
    }

    public function submitFilter(array &$form, FormStateInterface $form_state)
    {
        $form_state->setRebuild(true);
    }

    public static function ajaxFilterReturn(array &$form, FormStateInterface $form_state)
    {
        return $form['nodes'];
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {

    }

}
