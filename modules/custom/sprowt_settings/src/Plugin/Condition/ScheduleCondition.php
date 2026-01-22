<?php

namespace Drupal\sprowt_settings\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Utility\Error;
use Drupal\sprowt_settings\Element\ScheduleElement;

/**
 * Provides a 'Schedule' condition.
 *
 * @Condition(
 *   id = "sprowt_settings_schedule_condition",
 *   label = @Translation("Schedule"),
 * )
 */
class ScheduleCondition extends ConditionPluginBase
{

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration(): array
    {
        return [
            'schedules' => []
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
    {
        $form = parent::buildConfigurationForm($form, $form_state);
        $form['schedules'] = [
            '#type' => 'schedule_element',
            '#title' => $this->t('Schedules'),
            '#default_value' => $this->configuration['schedules'],
        ];
        $form['negate']['#type'] = 'value';
        $form['negate']['#value'] = false;

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
    {
        $this->configuration['schedules'] = $form_state->getValue('schedules');
        parent::submitConfigurationForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function summary(): string
    {
        try {
            $html = '<ul>';
            foreach ($this->configuration['schedules'] as $schedule) {
                if (!empty($schedule['negate'])) {
                    $scheduleStr = 'Hide ';
                } else {
                    $scheduleStr = 'Show ';
                }
                $scheduleStr .= ScheduleElement::scheduleTextSummary($schedule);
                $html .= '<li>' . $scheduleStr . '</li>';
            }
            $html .= '</ul>';
            return Markup::create($html);
        }
        catch (\Exception $e) {
            Error::logException(\Drupal::logger('sprowt_settings'), $e);
            \Drupal::messenger()->addError('There was an error evaluating a schedule condition');
            return 'ERROR';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(): bool
    {
        $date = new \DateTime();
        $schedules = $this->configuration['schedules'] ?? [];
        return ScheduleElement::showOnSchedules($schedules, $date);
    }

}
