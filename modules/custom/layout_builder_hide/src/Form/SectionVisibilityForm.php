<?php

declare(strict_types=1);

namespace Drupal\layout_builder_hide\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\Form\WorkspaceSafeFormTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\sprowt_settings\EntityVisibilityFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Layout Builder Hide form.
 */
class SectionVisibilityForm extends FormBase
{

    use AjaxFormHelperTrait;
    use LayoutBuilderContextTrait;
    use LayoutBuilderHighlightTrait;
    use LayoutRebuildTrait;
    use WorkspaceSafeFormTrait;
    use EntityVisibilityFormTrait;

    protected $visibilityFormItemKey = 'layout_builder_hide__visibility';

    /**
     * The layout tempstore repository.
     *
     * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
     */
    protected $layoutTempstoreRepository;

    /**
     * The plugin being configured.
     *
     * @var \Drupal\Core\Layout\LayoutInterface|\Drupal\Core\Plugin\PluginFormInterface
     */
    protected $layout;

    /**
     * The section being configured.
     *
     * @var \Drupal\layout_builder\Section
     */
    protected $section;

    /**
     * The plugin form manager.
     *
     * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
     */
    protected $pluginFormFactory;

    /**
     * The section storage.
     *
     * @var \Drupal\layout_builder\SectionStorageInterface
     */
    protected $sectionStorage;

    /**
     * The field delta.
     *
     * @var int
     */
    protected $delta;

    /**
     * The plugin ID.
     *
     * @var string
     */
    protected $pluginId;

    /**
     * Indicates whether the section is being added or updated.
     *
     * @var bool
     */
    protected $isUpdate;

    /**
     * Constructs a new ConfigureSectionForm.
     *
     * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
     *   The layout tempstore repository.
     * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_manager
     *   The plugin form manager.
     */
    public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, PluginFormFactoryInterface $plugin_form_manager) {
        $this->layoutTempstoreRepository = $layout_tempstore_repository;
        $this->pluginFormFactory = $plugin_form_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('layout_builder.tempstore_repository'),
            $container->get('plugin_form.factory')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'layout_builder_hide_section_visibility';
    }

    /**
     * Retrieves the section being modified by the form.
     *
     * @return \Drupal\layout_builder\Section
     *   The section for the current form.
     */
    public function getCurrentSection(): Section {
        if (!isset($this->section)) {
            if ($this->isUpdate) {
                $this->section = $this->sectionStorage->getSection($this->delta);
            }
            else {
                $this->section = new Section($this->pluginId);
            }
        }

        return $this->section;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm($form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $plugin_id = NULL)
    {

        $this->sectionStorage = $section_storage;
        $this->delta = $delta;
        $this->isUpdate = is_null($plugin_id);
        $this->pluginId = $plugin_id;

        $section = $this->getCurrentSection();

        if ($this->isUpdate) {
            if ($label = $section->getLayoutSettings()['label']) {
                $form['#title'] = $this->t('Configure visibility for @section', ['@section' => $label]);
            }
        }

        // Passing available contexts to the layout plugin here could result in an
        // exception since the layout may not have a context mapping for a required
        // context slot on creation.
        $this->layout = $section->getLayout();
        $configuration = $this->layout->getConfiguration();
        $visibilityValue = $configuration[$this->getVisibilityFormItemKey()] ?? [];

        $form[$this->getVisibilityFormItemKey()] = $this->buildLayoutBuilderHideVisibilityInterface([], $form_state, $visibilityValue);

        $form['actions'] = [
            '#type' => 'actions',
            '#weight' => 16,
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Save'),
            ],
        ];

        if ($this->isAjax()) {
            $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
        }

        $form['#validate'] ??= [];

        array_unshift($form['#validate'], [$this, 'validateVisibility']);

        return $form;
    }

    public function buildLayoutBuilderHideVisibilityInterface(array $form, FormStateInterface $form_state, array $visibilityValue)
    {
        $visibilityForm = $this->buildVisibilityInterface($form, $form_state, [], $visibilityValue);
        return $visibilityForm;
    }

    /**
     * Helper function to independently submit the visibility UI.
     *
     * @param array $form
     *   A nested array form elements comprising the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function setVisibilityValue($form, FormStateInterface $form_state) {
        foreach ($form_state->getValue($this->getVisibilityFormItemKey()) as $condition_id => $values) {
            // Allow the condition to submit the form.
            $condition = $form_state->get(['visibilityConditions', $condition_id]);
            $condition->submitConfigurationForm($form[$this->getVisibilityFormItemKey()][$condition_id], SubformState::createForSubform($form[$this->getVisibilityFormItemKey()][$condition_id], $form, $form_state));

            $condition_configuration = $condition->getConfiguration();
            $this->setVisibilityConfig($condition_id, $condition_configuration, $form_state);
        }
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


    /**
     * {@inheritdoc}
     */
    public function setVisibilityConfig($instance_id, array $configuration, FormStateInterface $form_state) {
        $visibilityValue = $form_state->get('visibilityValue') ?? [];
        $conditions = new ConditionPluginCollection($this->conditionPluginManager(), $visibilityValue);;
        if (!$conditions->has($instance_id)) {
            $configuration['id'] = $instance_id;
            $conditions->addInstanceId($instance_id, $configuration);
        }
        else {
            $conditions->setInstanceConfiguration($instance_id, $configuration);
        }
        $form_state->set('visibilityValue', $conditions->getConfiguration());
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        // @todo Validate the form here.
        // Example:
        // @code
        //   if (mb_strlen($form_state->getValue('message')) < 10) {
        //     $form_state->setErrorByName(
        //       'message',
        //       $this->t('Message should be at least 10 characters.'),
        //     );
        //   }
        // @endcode
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $this->setVisibilityValue($form, $form_state);
        $visibilityValue = $form_state->get('visibilityValue') ?? [];
        $configuration = $this->layout->getConfiguration();
        $configuration[$this->getVisibilityFormItemKey()] = $visibilityValue;

        $section = $this->getCurrentSection();
        $section->setLayoutSettings($configuration);
        if (!$this->isUpdate) {
            $this->sectionStorage->insertSection($this->delta, $section);
        }

        $this->layoutTempstoreRepository->set($this->sectionStorage);
        $form_state->setRedirectUrl($this->sectionStorage->getLayoutBuilderUrl());
    }

    protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state)
    {
        $response = $this->rebuildAndClose($this->sectionStorage);
        $response->addCommand(new CloseDialogCommand());
        return $response;
    }
}
