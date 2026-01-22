<?php

declare(strict_types=1);

namespace Drupal\sprowt_settings\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\sprowt_settings\SprowtSettings;

/**
 * Provides a Sprowt Settings form.
 */
class ConditionalTokenForm extends FormBase
{

    use AjaxFormHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_settings_conditional_token';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $request = \Drupal::request();
        $key = $request->get('key') ?? '';
        $value = $request->get('value') ?? '';
        $visibility = $request->get('visibility') ?? [];
        if(!empty($visibility) && is_string($visibility)) {
            $visibility = json_decode($visibility, true);
        }

        $form['#title'] = 'Add Conditional Token';
        if(!empty($key)) {
            $form['#title'] = 'Edit Conditional Token';
        }

        $form['key'] = [
            '#type' => 'machine_name',
            '#title' => $this->t('Machine name'),
            '#description' => 'The machine name key for this token. It must only contain lowercase letters, numbers, and underscores.',
            '#default_value' => $key,
            '#required' => true,
            '#machine_name' => [
                'exists' => [$this, 'conditionalTokenExists']
            ],
        ];

        $form['value'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Value'),
            '#default_value' => $value
        ];

        $form['visibility'] = $this->buildVisibilityInterface([], $form_state, [], $visibility);

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Save'),
            ],
        ];
        if($this->isAjax()) {
            $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
        }

        $form['#attached']['library'][] = 'sprowt_settings/conditional_token_form';

        return $form;
    }

    /**
     * @return SprowtSettings
     */
    public function getSprowtSettings() {
        if(isset($this->sprowtSettings)) {
            return $this->sprowtSettings;
        }
        $this->sprowtSettings = \Drupal::service('sprowt_settings.manager');
        return $this->sprowtSettings;
    }

    /**
     * Helper function for building the visibility UI form.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form array with the visibility UI added in.
     */
    protected function buildVisibilityInterface(array $form, FormStateInterface $form_state, $contextExtra = [], $visibility = []) {
        $form['#tree'] = true;
        $form['#weight'] = 15;
        $form['visibility_tabs'] = [
            '#type' => 'vertical_tabs',
            '#title' => t('Visibility'),
            '#parents' => ['visibility_tabs'],
        ];

        $manager = \Drupal::service('plugin.manager.condition');
        $contextRepository = \Drupal::service('context.repository');
        $contexts = $contextRepository->getAvailableContexts();
        $form_state->setTemporaryValue('gathered_contexts', $contexts);
        $contextConsumer = static::class . '--visibility';
        if(method_exists($this, 'getFormId')) {
            $contextConsumer = $this->getFormId() . '--visibility';
        }
        $definitions = $manager->getFilteredDefinitions($contextConsumer, $contexts, $contextExtra);
        foreach ($definitions as $condition_id => $definition) {
            // Don't display the language condition until we have multiple languages.
            if ($condition_id == 'language' && !\Drupal::service('language_manager')->isMultilingual()) {
                continue;
            }

            // Don't display the deprecated node type condition
            if ($condition_id == 'node_type') {
                continue;
            }

            /** @var \Drupal\Core\Condition\ConditionInterface $condition */
            $condition = $manager->createInstance($condition_id, $visibility[$condition_id] ?? []);
            $form_state->set(['visibilityConditions', $condition_id], $condition);
            $condition_form = $condition->buildConfigurationForm([], $form_state);
            $condition_form['#type'] = 'details';
            $condition_form['#title'] = $condition->getPluginDefinition()['label'];
            $condition_form['#group'] = 'visibility_tabs';

            //copied this from the block ui
            switch($condition_id) {
                case 'user_role':
                    $condition_form['#title'] = t('Roles');
                    unset($condition_form['roles']['#description']);
                    $condition_form['negate']['#type'] = 'value';
                    $condition_form['negate']['#value'] =  $condition->isNegated();
                    break;
                case 'request_path':
                    $condition_form['#title'] = t('Pages');
                    $condition_form['negate']['#type'] = 'radios';
                    $condition_form['negate']['#default_value'] = (int) $condition->isNegated();
                    $condition_form['negate']['#title_display'] = 'invisible';
                    $condition_form['negate']['#options'] = [
                        t('Show for the listed pages'),
                        t('Hide for the listed pages'),
                    ];
                    break;
                case 'language':
                    $condition_form['negate']['#type'] = 'value';
                    $condition_form['negate']['#value'] = $condition->isNegated();
                    break;
                case 'current_theme':
                    $condition_form["theme"]['#empty_option'] = t('- Any -');
                    break;
            }


            if(!empty($condition_form)) {
                $form[$condition_id] = $condition_form;
            }
        }

        return $form;
    }

    public function conditionalTokenExists($key) {
        return $this->getSprowtSettings()->isConditionalToken($key);
    }

    /**
     * Helper function to independently validate the visibility UI.
     *
     * @param array $form
     *   A nested array form elements comprising the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function validateVisibility(array $form, FormStateInterface $form_state) {
        // Validate visibility condition settings.
        $visibilityValues = $form_state->getValue('visibility');
        foreach ($visibilityValues as $condition_id => $values) {
            // All condition plugins use 'negate' as a Boolean in their schema.
            // However, certain form elements may return it as 0/1. Cast here to
            // ensure the data is in the expected type.
            if (array_key_exists('negate', $values)) {
                $form_state->setValue(['visibility', $condition_id, 'negate'], (bool) $values['negate']);
            }

            // Allow the condition to validate the form.
            $condition = $form_state->get(['visibilityConditions', $condition_id]);
            $condition->validateConfigurationForm($form['visibility'][$condition_id], SubformState::createForSubform($form['visibility'][$condition_id], $form, $form_state));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        $this->validateVisibility($form, $form_state);
    }

    public function submitVisibility(array $form, FormStateInterface $form_state)
    {
        foreach ($form_state->getValue('visibility') as $condition_id => $values) {
            // Allow the condition to submit the form.
            $condition = $form_state->get(['visibilityConditions', $condition_id]);
            $condition->submitConfigurationForm($form['visibility'][$condition_id], SubformState::createForSubform($form['visibility'][$condition_id], $form, $form_state));

            $condition_configuration = $condition->getConfiguration();
            $this->setVisibilityConfig($form_state, $condition_id, $condition_configuration);
        }
    }

    protected function conditionPluginManager() {
        if (!isset($this->conditionPluginManager)) {
            $this->conditionPluginManager = \Drupal::service('plugin.manager.condition');
        }
        return $this->conditionPluginManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibilityConditions(FormStateInterface $form_state) {
        $visibilityValue = $form_state->get('visibilityValue') ?? [];
        if (!isset($this->visibilityCollection)) {
            $this->visibilityCollection = new ConditionPluginCollection($this->conditionPluginManager(), $visibilityValue);
        }
        return $this->visibilityCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibilityConfig(FormStateInterface $form_state, $instance_id, array $configuration) {
        $conditions = $this->getVisibilityConditions($form_state);
        if (!$conditions->has($instance_id)) {
            $configuration['id'] = $instance_id;
            $conditions->addInstanceId($instance_id, $configuration);
        }
        else {
            $conditions->setInstanceConfiguration($instance_id, $configuration);
        }
        $form_state->set('visibilityValue', $conditions->getConfiguration());
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->submitVisibility($form, $form_state);
        $visibilityValue = $form_state->get('visibilityValue') ?? [];
        $key = $form_state->getValue('key');
        $value = $form_state->getValue('value');

        $sprowtSettings = $this->getSprowtSettings();
        $sprowtSettings->setConditionalToken($key, $value, $visibilityValue);
        token_clear_cache();
    }


    /**
     * Submit form dialog #ajax callback.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An AJAX response that display validation error messages or represents a
     *   successful submission.
     */
    public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
        if ($form_state->hasAnyErrors()) {
            $form['status_messages'] = [
                '#type' => 'status_messages',
                '#weight' => -1000,
            ];
            $form['#sorted'] = FALSE;
            $response = new AjaxResponse();
            $response->addCommand(new ReplaceCommand('form.sprowt-settings-conditional-token', $form));
        }
        else {
            $response = $this->successfulAjaxSubmit($form, $form_state);
        }
        return $response;
    }

    protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state)
    {
        $response = new AjaxResponse();
        $response->addCommand(new MessageCommand('Conditional token saved', null, [
            'type' => 'status'
        ]));
        $response->addCommand(new InvokeCommand('.rebuildConditionalTokens--button', 'trigger', [
            'click'
        ]));
        $response->addCommand(new CloseModalDialogCommand());
        return $response;
    }
}
