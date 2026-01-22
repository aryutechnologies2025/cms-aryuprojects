<?php declare(strict_types=1);

namespace Drupal\layout_builder_hide;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Form\ConfigureBlockFormBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\sprowt_settings\EntityVisibilityFormTrait;
use Drupal\system\Plugin\Condition\RequestPath;
use Drupal\user\Plugin\Condition\UserRole;

/**
 * @todo Add class description.
 */
class LayoutBuilderHideService implements TrustedCallbackInterface
{
protected $conditionPluginManager;
protected $entity;

    use EntityVisibilityFormTrait;

    protected $visibilityFormItemKey = 'layout_builder_hide__visibility';


    public function blockFormAlter(array &$form, FormStateInterface $form_state)
    {

        /** @var ConfigureBlockFormBase $formObj */
        $formObj = $form_state->getFormObject();

        /** @var SectionComponent $component */
        $component = $formObj->getCurrentComponent();
        $form['settings']['layout_builder_hide'] = [
            '#type' => 'checkbox',
            '#title' => 'Hide this component',
            '#default_value' => $component->get('layout_builder_hide') ?? false,
            '#weight' => 0,
            '#description' => 'If checked, this component will not be rendered on the front end',
        ];

        $visibilityValue = $component->get($this->getVisibilityFormItemKey()) ?? [];
        $form[$this->getVisibilityFormItemKey()] = $this->buildLayoutBuilderHideVisibilityInterface([], $form_state, $visibilityValue);
        $form[$this->getVisibilityFormItemKey()]['#weight'] = 99;
        $form[$this->getVisibilityFormItemKey()]['#type'] = 'fieldset';
        $form[$this->getVisibilityFormItemKey()]['#title'] = 'Block Visibility';

        $form['#submit'] ??= [];
        $form['#validate'] ??= [];

        array_unshift($form['#submit'], [$this, 'blockFormSubmit']);
        array_unshift($form['#validate'], [$this, 'validateVisibility']);
    }

    public function buildLayoutBuilderHideVisibilityInterface(array $form, FormStateInterface $form_state, array $visibilityValue)
    {
        $visibilityForm = $this->buildVisibilityInterface($form, $form_state, [], $visibilityValue, null, ['sprowt_settings_schedule_condition']);
        return $visibilityForm;
    }

    public function hideBlock($build, $inPreview = false)
    {
        if($inPreview) {
            $build['#attributes']['data-hidden-component'] = 'true';
            $build['content']['hiddenTitle'] = [
                '#type' => 'markup',
                '#markup' => Markup::create('<div class="hidden-component-callout">Hidden Component:</div>'),
                '#weight' => -10
            ];
            $build['#attached']['library'][] = 'layout_builder_hide/preview';
        }
        else {
            $build['#access'] = false;
        }
        return $build;
    }

    /**
     * Gets the condition plugin manager.
     *
     * @return \Drupal\Core\Executable\ExecutableManagerInterface
     *   The condition plugin manager.
     */
    protected function conditionPluginManager() {
        if (!isset($this->conditionPluginManager)) {
            $this->conditionPluginManager = \Drupal::service('plugin.manager.condition');
        }
        return $this->conditionPluginManager;
    }

    public function isComponentVisible($component, $node = null)
    {
        $hidden = $component->get('layout_builder_hide');
        if($hidden) {
            return false;
        }

        $visibilityValue = $component->get($this->getVisibilityFormItemKey()) ?? [];
        return $this->isVisible($visibilityValue, $node);
    }

    public function isVisible($visibilityValue, $node = null)
    {
        if(!isset($node)) {
            $routeMatch = \Drupal::routeMatch();
            $node = $routeMatch->getParameter('node');
            if(!empty($node) && !$node instanceof \Drupal\node\Entity\Node) {
                $node = \Drupal\node\Entity\Node::load($node);
            }
        }
        /** @var ContextRepositoryInterface $contextRepo */
        $contextRepo = \Drupal::service('context.repository');
        $contextHandler = \Drupal::service('context.handler');
        $conditions = new ConditionPluginCollection($this->conditionPluginManager(), $visibilityValue);;
        $show = true;
        /** @var ConditionInterface $condition */
        foreach ($conditions as $condition) {
            $missing_value = false;
            $missing_context = false;
            if($condition instanceof ContextAwarePluginInterface) {
                try {
                    $contexts = $contextRepo->getRuntimeContexts(array_values($condition->getContextMapping()));
                    $contextHandler->applyContextMapping($condition, $contexts);
                }
                catch (MissingValueContextException $e) {
                    $missing_value = true;
                }
                catch (ContextException $e) {
                    $missing_context = true;
                }
            }
            if($node instanceof Node && $condition instanceof ConditionPluginBase) {
                //apply provided node as a context
                $nodeContext = EntityContext::fromEntity($node, 'entity:node');
                $condition->setContext('node', $nodeContext);
            }
            if($condition instanceof RequestPath) {
                $config = $condition->getConfig();
                if(empty($config['pages']) && !empty($config['negate'])) {
                    //empty pages and negate (hide for listed) not working working correctly for some reason.
                    //so set pages to some random value
                    $config['pages'] = '/' . sha1(time() . rand() . 'show all pages');
                    $condition->setConfiguration($config);
                }
            }

            try {
                $show = $show && $condition->execute();
            }
            catch (ContextException $e) {
                // If a condition is missing context and is not negated, consider that a
                // fail.
                $show = $condition->isNegated();
            }
            catch (\Exception $e) {
                \Drupal::logger('entity_visibility')->error("Visibility condition error: " . $e->getMessage() . ' @backtrace_string', [
                    '@backtrace_string' => $e->getTraceAsString()
                ]);
                $show = $condition->isNegated();
            }
        }

        return $show;
    }

    /**
     * Helper function to independently submit the visibility UI.
     *
     * @param array $form
     *   A nested array form elements comprising the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function submitComponentVisibility($component, $form, FormStateInterface $form_state) {
        foreach ($form_state->getValue($this->getVisibilityFormItemKey()) as $condition_id => $values) {
            // Allow the condition to submit the form.
            $condition = $form_state->get(['visibilityConditions', $condition_id]);
            $condition->submitConfigurationForm($form[$this->getVisibilityFormItemKey()][$condition_id], SubformState::createForSubform($form[$this->getVisibilityFormItemKey()][$condition_id], $form, $form_state));

            $condition_configuration = $condition->getConfiguration();
            $this->setVisibilityConfig($condition_id, $condition_configuration, $component);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibilityConfig($instance_id, array $configuration, $component) {
        $visibilityValue = $component->get($this->getVisibilityFormItemKey()) ?? [];
        $conditions = new ConditionPluginCollection($this->conditionPluginManager(), $visibilityValue);;
        if (!$conditions->has($instance_id)) {
            $configuration['id'] = $instance_id;
            $conditions->addInstanceId($instance_id, $configuration);
        }
        else {
            $conditions->setInstanceConfiguration($instance_id, $configuration);
        }
        $component->set($this->getVisibilityFormItemKey(), $conditions->getConfiguration());
        return $this;
    }

    public function blockFormSubmit(array &$form, FormStateInterface $form_state)
    {
        /** @var ConfigureBlockFormBase $formObj */
        $formObj = $form_state->getFormObject();

        /** @var SectionComponent $component */
        $component = $formObj->getCurrentComponent();
        $hide = $form_state->getValue(['settings', 'layout_builder_hide']);
        $component = $this->hideBlockComponent($component, $hide);
        $this->submitComponentVisibility($component, $form, $form_state);
    }

    public function hideBlockComponent(SectionComponent $component, $hide)
    {
        $component->set('layout_builder_hide', (bool) $hide);
        return $component;
    }

    public function sectionFormAlter(array &$form, FormStateInterface $form_state)
    {
        $routeMatch = \Drupal::routeMatch();
        $sectionStorageType = $routeMatch->getParameter('section_storage_type');
        /** @var OverridesSectionStorage $sectionStorage */
        $sectionStorage = $routeMatch->getParameter('section_storage');
        $delta = $routeMatch->getParameter('delta');
        $pluginId = $routeMatch->getParameter('plugin_id');

        $formObject = $form_state->getFormObject();

        $layout = $formObject->getCurrentLayout();

        $configuration = $layout->getConfiguration();

        $form['layout_settings']['layout_builder_hide'] = [
            '#type' => 'checkbox',
            '#title' => 'Hide this section',
            '#default_value' => $configuration['layout_builder_hide'] ?? false,
            '#weight' => 0,
            '#description' => 'If checked, this section will not be rendered on the front end',
        ];
    }

    public function sectionFormSubmit(array &$form, FormStateInterface $form_state)
    {

        $formObject = $form_state->getFormObject();
        $layout = $formObject->getCurrentLayout();
        $hide = $form_state->getValue(['layout_settings', 'layout_builder_hide']);
        $layout = $this->hideSectionPlugin($layout, $hide);
        $configuration = $layout->getConfiguration();

        $component = new SectionComponent('stub', 'stub');
        $this->submitComponentVisibility($component, $form, $form_state);
        $configuration[$this->getVisibilityFormItemKey()] = $component->get($this->getVisibilityFormItemKey());
        $layout->setConfiguration($configuration);
    }

    public function hideSectionPlugin(LayoutInterface $layout, $hide){
        $configuration = $layout->getConfiguration();
        $configuration['layout_builder_hide'] = (bool) $hide;
        $layout->setConfiguration($configuration);
        return $layout;
    }


    public function hideSection(&$build, $inPreview = false)
    {
        if($inPreview) {
            $build['content']['#attributes']['data-hidden-section'] = 'true';
            $build['#settings']['label'] .= ' [hidden]';
        }
        else {
            $build['#access'] = false;
        }
        return $build;
    }

    public static function alterLayoutBuilderRender($element)
    {
        /** @var \Drupal\layout_builder\SectionStorageInterface $sectionStorage */
        $sectionStorage = $element['#section_storage'];
        $sectionStorageType = $sectionStorage->getStorageType();
        $pluginId = $sectionStorage->getPluginId();

        $layout = &$element['layout_builder'];
        foreach (Element::children($layout) as $id) {

            // Check what kind of element we are looping over.
            $is_add_link = isset($layout[$id]['link']) && isset($layout[$id]['link']['#url']);
            $is_section = isset($layout[$id]['configure']) && isset($layout[$id]['configure']['#url']);

            if ($is_add_link) {
                // do nothing
            }
            elseif ($is_section) {
                // Collect parameters to generate the link.
                $parameters = $layout[$id]['configure']['#url']->getRouteParameters();

                $url = Url::fromRoute('layout_builder_hide.section_visibility', $parameters);

                $layout[$id]['hide_button'] = [
                    '#type' => 'link',
                    '#title' => 'Edit visibility',
                    '#url' => $url,
                    '#attributes' => [
                        'class' => ['layout-builder__link', 'layout-builder__link--copy-section', 'layout-builder__link--section--visibility'],
                    ],
                    '#ajax' => [
                        'dialogType' => 'modal',
                        'dialog' => [
                            'width' => '85%',
                            'maxWidth' => '1300px',
                            'maxHeight' => '50%',
                            'autoResize' => false,
                            'resizable' => true,
                            'draggable' => true
                        ]
                    ],
                    '#weight' => 5
                ];

                $stop = true;
                $layout[$id]['layout-builder__section']['#weight'] = 6;
            }
        }

        return $element;

    }

    /**
     * @{inheritDoc}
     */
    public static function trustedCallbacks() {
        return [
            'alterLayoutBuilderRender'
        ];
    }
}
