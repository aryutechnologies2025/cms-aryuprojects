<?php

namespace Drupal\sprowt_subsite\Plugin\Condition;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\sprowt_subsite\Plugin\Field\FieldType\SubsiteReferenceItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Subsite Condition' condition.
 *
 * @Condition(
 *   id = "sprowt_subsite_condition",
 *   label = @Translation("Subsite condition"),
 *   context_definitions = {
 *     "node" = @ContextDefinition(
 *       "entity:node",
 *        label = @Translation("Node")
 *      )
 *   }
 * )
 *
 * @DCG prior to Drupal 8.7 the 'context_definitions' key was called 'context'.
 */
class SubsiteCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface
{

    protected EntityTypeManagerInterface $entityTypeManager;

    protected EntityFieldManagerInterface $entityFieldManager;

    protected $subsiteOptions;

    protected $fieldMap;

    /**
     * Creates a new SubsiteCondition instance.
     *
     * @param array $configuration
     *   The plugin configuration, i.e. an array with configuration values keyed
     *   by configuration option name. The special key 'context' may be used to
     *   initialize the defined contexts by setting it to an array of context
     *   values keyed by context names.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
     *   The date formatter.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(
        array $configuration,
              $plugin_id,
              $plugin_definition,
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager'),
            $container->get('entity_field.manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        $default = [
            'use' => false,
            'value' => [],
            'negate' => false,
            'hasNoFields' => 'hide'
        ];
        return $default + parent::defaultConfiguration();
    }

    public function getSubSiteoptions() {
        if(isset($this->subsiteOptions)) {
            return $this->subsiteOptions;
        }

        $baseOptions = [
            '_main' => 'Main site'
        ];
        $options = [];
        $subsites = $this->entityTypeManager->getStorage('node')->loadByProperties([
            'type' => 'subsite'
        ]);
        /** @var Node $subsite */
        foreach($subsites as $subsite) {
            $label = $subsite->label();
            $key = array_search($label, $options);
            if($key !== false) {
                $otherSubsite = $subsites[$key];
                $options[$key] = $otherSubsite->label() . " [{$otherSubsite->id()}]";
                $label .= " [{$subsite->id()}]";
            }
            $options[$subsite->uuid()] = $label;
        }
        asort($options);
        $this->subsiteOptions = array_merge($baseOptions, $options);
        return $this->subsiteOptions;
    }

    public function getFieldMap() {
        if(isset($this->fieldMap)) {
            return $this->fieldMap;
        }
        $fieldMapRaw = $this->entityFieldManager->getFieldMapByFieldType('sprowt_subsite_reference');
        $fieldMap = [];
        foreach($fieldMapRaw['node'] ?? [] as $fieldName => $def) {
            $fieldMap[$fieldName] = $def;
        }
        $this->fieldMap = $fieldMap;
        return $this->fieldMap;
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {

        $form['summaryHidden'] = [
            '#type' => 'hidden',
            '#default_value' => $this->summary(),
            '#attributes' => [
                'id' => 'summaryHidden'
            ]
        ];

        $form['#weight'] = 10;
        $form['description'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>Condition based on values of subsite fields found on nodes.</p>')
        ];

        $default = $this->configuration ?? [];

        $form['use'] = [
            '#type' => 'checkbox',
            '#title' => 'Use this condition?',
            '#default_value' => $default['use'] ?? false,
            '#attributes' => [
                'class' => ['in_use']
            ]
        ];

        $fieldSet = [
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['field-wrap', 'subsite-field-fieldset']
            ],
            '#states' => [
                'visible' => [
                    '.in_use' => [
                        'checked' => true
                    ]
                ]
            ]
        ];

        $containTitle = 'Subsite field contains';
        $notContainTitle = 'Subsite field does not contain';
        $title = empty($default['negate']) ? $containTitle : $notContainTitle;
        $containDescription = 'Condition is true if the node is tagged with any of these subsites';
        $notContainDescription = 'Condition is true if the node is not tagged with any of these subsites';
        $description = empty($default['negate']) ? $containDescription : $notContainDescription;

        $fieldSet['fieldValue'] = [
            '#type' => 'select',
            '#title' => Markup::create('<span class="subsite-value-field-title">'.$title.'</span>'),
            '#description' => Markup::create('<div class="subsite-value-field-description">'.$description.'</div>'),
            '#options' => $this->getSubSiteoptions(),
            '#multiple' => true,
            '#default_value' => $default['value'] ?? [],
            '#attributes' => [
                'class' => ['subsite-value-field'],
                'data-contain-title' => $containTitle,
                'data-not-contain-title' => $notContainTitle,
                'data-contain-description' => $containDescription,
                'data-not-contain-description' => $notContainDescription
            ],
            '#states' => [
                'required' => [
                    '.in_use' => [
                        'checked' => true
                    ]
                ]
            ],
        ];


        $fieldSet['hasNoFields'] = [
            '#title' => 'If the node has no subsite fields',
            '#type' => 'radios',
            '#options' => [
                'hide' => 'Hide',
                'show' => 'Show'
            ],
            '#default_value' => $default['hasNoFields'] ?? 'hide',
        ];

        $fieldSet['negate'] = [
            '#type' => 'checkbox',
            '#title' => 'Negate this condition?',
            '#default_value' => $default['negate'] ?? false,
            '#attributes' => [
                'class' => ['subsite-field-negate'],
            ],
            '#states' => [
                'visible' => [
                    '.in_use' => [
                        'checked' => true
                    ]
                ]
            ]
        ];

        $form['fieldset'] = $fieldSet;

        $form = parent::buildConfigurationForm($form, $form_state);

        unset($form['negate']);

        $form['#attached']['library'][] = 'sprowt_subsite/condition';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        if(!empty($values['fieldset'])) {
            $values = array_merge($values, $values['fieldset']);
        }
        $this->configuration['use'] = !empty($values['use']);
        $this->configuration['value'] = $values['fieldValue'] ?? [];
        $this->configuration['hasNoFields'] = $values['hasNoFields'] ?? 'hide';
        parent::submitConfigurationForm($form, $form_state);
        $this->configuration['negate'] = !empty($values['negate']);
    }

    /**
     * {@inheritdoc}
     */
    public function summary()
    {
        $config = $this->configuration ?? [];
        if(empty($config['use'])) {
            return 'Not used';
        }
        $list = [];
        $subsiteOptions = $this->getSubSiteoptions();
        foreach($config['value'] as $subsite) {
            $list[] = $subsiteOptions[$subsite];
        }

        return $this->t('Node @contains @subsites', [
            '@contains' => !empty($config['negate']) ? 'is not tagged with' : 'tagged with',
            '@subsites' => implode(', ', $list)
        ]);
    }
    /**
     * {@inheritdoc}
     */
    public function evaluate()
    {
        $config = $this->configuration ?? [];
        if(isset($config['fields'])) {
            return $this->evaluateLegacy();
        }
        if(empty($config['use'])) {
            return true;
        }
        try {
            $node = $this->getContextValue('node');
        }
        catch (ContextException $e) {
            //sometimes the context isn't passed for some reason
            //always use the routematched node if no node context passed
            $routeMatch =\Drupal::routeMatch();
            $node = $routeMatch->getParameter('node') ?? null;
            if(isset($node) && !$node instanceof Node) {
                $node = Node::load($node);
            }
        }

        if(!$node instanceof Node) {
            // no node found. So return true
            return true;
        }

        $tagged = false;
        $fieldMap = $this->getFieldMap();
        $hasAnyField = false;
        foreach (array_keys($fieldMap) as $fieldName) {
            $hasField = $node->hasField($fieldName);
            if(!$hasField) {
                // node doesn't have field. So skip.
                continue;
            }
            $hasAnyField = true;
            $testValues = $config['value'] ?? [];
            $value = [];
            $itemList = $node->get($fieldName);
            /** @var SubsiteReferenceItem $item */
            foreach($itemList as $item) {
                $value[] = $item->target;
            }
            foreach ($testValues as $test) {
                if(in_array($test, $value)) {
                    $tagged = true;
                }
            }
        }
        if(!$hasAnyField) {
            return $config['hasNoFields'] == 'hide' ? false : true;
        }

        $ret = $tagged;
        if(!empty($config['negate'])) {
            return !$ret;
        }

        return $ret;
    }

    /**
     * legacy evaluate function with old system
     */
    public function evaluateLegacy()
    {

        $fields = $this->configuration['fields'];
        $used = [];
        foreach($fields as $fieldName => $field) {
            if(!empty($field['use'])) {
                $used[$fieldName] = $field;
            }
        }
        // no fields are used. So return true
        if(empty($used)) {
            return true;
        }

        // no node found. So return true
        $node = $this->getContextValue('node');
        if(!$node instanceof Node) {
            return true;
        }
        $ret = true;
        $hasAnyField = false;
        foreach($used as $fieldName => $field) {
            $hasField = $node->hasField($fieldName);
            if(!$hasField) {
                // node doesn't have field. So skip.
                continue;
            }
            $hasAnyField = true;
            $testValues = $field['value'];
            $negate = !empty($field['negate']);
            $value = [];
            $itemList = $node->get($fieldName);
            /** @var SubsiteReferenceItem $item */
            foreach($itemList as $item) {
                $value[] = $item->target;
            }
            $hasValue = false;
            $doesNotHaveValue = true;
            foreach ($testValues as $test) {
                if(in_array($test, $value)) {
                    $hasValue = true;
                    $doesNotHaveValue = false;
                }
            }
            if(empty($negate)) {
                $ret &= $hasValue;
            }
            else {
                $ret &= $doesNotHaveValue;
            }
        }

        if(empty($hasAnyField)) {
            $hasNoField = $this->configuration['hasNoFields'] ?? 'hide';
            if($hasNoField == 'hide') {
                return false;
            }
            return true;
        }

        return $ret;
    }

}
