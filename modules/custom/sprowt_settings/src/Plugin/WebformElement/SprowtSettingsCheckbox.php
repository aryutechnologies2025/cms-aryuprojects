<?php

namespace Drupal\sprowt_settings\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sprowt_settings\Form\SprowtSettingsForm;
use Drupal\webform\Plugin\WebformElement\Checkbox;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'checkbox' element.
 *
 * @WebformElement(
 *   id = "sprowt_settings_checkbox",
 *   label = @Translation("Sprowt Settings Checkbox"),
 *   description = @Translation("Provides a form element for a single checkbox who's visibility is controlled by a Sprowt setting."),
 *   category = @Translation("Basic elements"),
 * )
 */
class SprowtSettingsCheckbox extends Checkbox
{

    protected function defineDefaultProperties()
    {
        $properties = [
                'title_override' => null,
                'sprowt_setting' => null,
                'negate' => false
        ] + parent::defineDefaultProperties();
        return $properties;
    }

    public function form(array $form, FormStateInterface $form_state)
    {
        $form = parent::form($form, $form_state);

        $settingsDefs = SprowtSettingsForm::getSettingsDefinitionsByType('checkbox');
        $settingsOpts = [];
        foreach ($settingsDefs as $key => $setting) {
            $settingsOpts[$key] = $setting['title'];
        }

        $form['element']['sprowt_setting'] = [
            '#title' => $this->t('Sprowt Setting'),
            '#type' => 'select',
            '#options' => $settingsOpts,
            '#description' => $this->t('Select a Sprowt setting to control the visibility of this checkbox. If no setting is selected, the checkbox will always be visible.'),
            '#weight' => -1,
        ];

        $form['element']['negate'] = [
            '#title' => $this->t('Negate the setting'),
            '#type' => 'checkbox',
            '#description' => $this->t('Select this option to negate the setting. If the setting is enabled, the checkbox will be hidden. If the setting is disabled, the checkbox will be visible.'),
            '#weight' => -1,
        ];

        $form['element']['title']['#title'] = $this->t('Admin title');
        $form['element']['title']['#weight'] = -2;

        $form['element']['title_override'] = [
            '#title' => $this->t('Title Override'),
            '#type' => 'textfield',
            '#description' => $this->t('Override the title of the checkbox. If left empty, the admin title will be used.'),
            '#weight' => -2,
            '#required' => false
        ] + $form['element']['title'];

        return $form;
    }

    public function finalize(array &$element, WebformSubmissionInterface $webform_submission = NULL)
    {
        parent::finalize($element, $webform_submission);
        $titleOverride = $element['#title_override'] ?? null;
        if(!empty($titleOverride)) {
            $element['#title'] = $titleOverride;
        }
    }

}
