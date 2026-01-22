<?php
namespace Drupal\sprowt_settings;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

trait EntityVisibilityFormTrait
{
    protected function modifyConditionform(&$form, FormStateInterface $form_state, $condition, $condition_id) {
        $skip = [
            'webform',
            'current_theme'
        ];
        if(strpos($condition_id, 'entity_bundle:') === 0) {
            $entityType = str_replace('entity_bundle:', '', $condition_id);
            if($entityType != 'node') {
                return false;
            }
        }
        if(in_array($condition_id, $skip)) {
            return false;
        }
        return $form;
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
    protected function buildVisibilityInterface(array $form, FormStateInterface $form_state, $contextExtra = [], $visibility = [], $exclude = null, $include = null) {
        $form['#tree'] = true;
        $form['#weight'] = 15;
        $form['visibility_tabs'] = [
            '#type' => 'vertical_tabs',
            '#title' => t('Visibility'),
            '#parents' => ['visibility_tabs'],
        ];

        if(isset($this->entity)) {
            $visibility = $this->entity->getVisibility() ?? [];
        }
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

            if(isset($exclude) && in_array($condition_id, $exclude)) {
                continue;
            }

            if(isset($include) && !in_array($condition_id, $include)) {
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
            }

            $condition_form = $this->modifyConditionform($condition_form, $form_state, $condition, $condition_id);
            if(!empty($condition_form)) {
                $form[$condition_id] = $condition_form;
            }
        }

        return $form;
    }

    protected function getVisibilityFormItemKey() {
        if(isset($this->visibilityFormItemKey)) {
            return $this->visibilityFormItemKey;
        }
        return 'visibility';
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
        $visibilityValues = $form_state->getValue($this->getVisibilityFormItemKey());
        foreach ($visibilityValues as $condition_id => $values) {
            // All condition plugins use 'negate' as a Boolean in their schema.
            // However, certain form elements may return it as 0/1. Cast here to
            // ensure the data is in the expected type.
            if (array_key_exists('negate', $values)) {
                $form_state->setValue([$this->getVisibilityFormItemKey(), $condition_id, 'negate'], (bool) $values['negate']);
            }

            // Allow the condition to validate the form.
            $condition = $form_state->get(['visibilityConditions', $condition_id]);
            $condition->validateConfigurationForm($form[$this->getVisibilityFormItemKey()][$condition_id], SubformState::createForSubform($form[$this->getVisibilityFormItemKey()][$condition_id], $form, $form_state));
        }
    }

    /**
     * Helper function to independently submit the visibility UI.
     *
     * @param array $form
     *   A nested array form elements comprising the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function submitVisibility(array $form, FormStateInterface $form_state) {
        foreach ($form_state->getValue($this->getVisibilityFormItemKey()) as $condition_id => $values) {
            // Allow the condition to submit the form.
            $condition = $form_state->get(['visibilityConditions', $condition_id]);
            $condition->submitConfigurationForm($form[$this->getVisibilityFormItemKey()][$condition_id], SubformState::createForSubform($form[$this->getVisibilityFormItemKey()][$condition_id], $form, $form_state));

            $condition_configuration = $condition->getConfiguration();
            $this->entity->setVisibilityConfig($condition_id, $condition_configuration);
        }

        $form_state->setValue($this->getVisibilityFormItemKey(), $this->entity->getVisibility());
    }
}
