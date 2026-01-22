<?php

namespace Drupal\sprowt_address_autocomplete\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Configure Sprowt address autocomplete settings for this site.
 */
class SettingsForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_address_autocomplete_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['sprowt_address_autocomplete.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('sprowt_address_autocomplete.settings');
        $form['google_maps_api_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Google maps API key'),
            '#default_value' => $config->get('google_maps_api_key'),
            '#description' => Markup::create('<p>API key gotten from <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">here</a>.</p>Used for the google maps javascript library.<p></p>')
        ];
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $data = [];
        $apiKey = $form_state->getValue('google_maps_api_key');
        if(!empty($apiKey)) {
            $data['google_maps_api_key'] = $apiKey;
        }
        if(empty($data)) {
            $this->config('sprowt_address_autocomplete.settings')->delete();
        }
        else {
            $config = $this->config('sprowt_address_autocomplete.settings');
            foreach ($data as $key => $val) {
                $config->set($key, $val);
            }
            $config->save();
        }
        parent::submitForm($form, $form_state);
    }

}
