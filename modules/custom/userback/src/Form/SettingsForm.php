<?php

namespace Drupal\userback\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Userback settings for this site.
 */
class SettingsForm extends ConfigFormBase
{

    public static $defaultSettings = [
        'enabled' => false,
        'access_token' => null,
        'environments' => ['dev']
    ];

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'userback_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['userback.settings'];
    }

    protected function getSetting($key, $default = null) {
        return $this->config('userback.settings')->get($key) ?? $default;
    }

    protected function setSetting($key, $value) {
        $this->config('userback.settings')->set($key, $value);
        $this->config('userback.settings')->save();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['enable_userback'] = [
            '#title' => $this->t('Enable Userback'),
            '#type' => 'checkbox',
            '#default_value' => $this->getSetting('enabled', false)
        ];

        $environments = $this->getSetting('environments', ['dev']);

        $form['environments'] = [
            '#title' => $this->t('Enabled Environments'),
            '#description' => $this->t('SprowtHQ environments where the script will be enabled (if no environment is detected, i.e. it is local, then it will be enabled by default). One per line.'),
            '#type' => 'textarea',
            '#default_value' => implode("\n", $environments)
        ];

        $form['access_token'] = [
            '#title' => $this->t('Access Token'),
            '#type' => 'textfield',
            '#default_value' => $this->getSetting('access_token', false),
            '#description' => '
                <div class="get-your-token">
                    <a href="https://app.userback.io/dashboard/?get_code=1" target="_blank">Get your access token here</a>
                </div>

                <div><strong>Note</strong>: The highlighted area is your access token.</div>
                <p><img class="code-example" src="/'.\Drupal::service('extension.path.resolver')->getPath('module', 'userback') . '/assets/code_sample.png"></p>
        '
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
        $values = $form_state->getValues();
        $settings = [
            'enabled' => !empty($values['enable_userback']),
            'access_token' => $values['access_token']
        ];
        $environments = [];
        if(!empty($values['environments'])) {
            foreach(explode("\n", $values['environments']) as $env) {
                $env = trim($env);
                if(!empty($env)) {
                    $environments[] = $env;
                }
            }
        }
        $settings['environments'] = $environments;
        $config = $this->config('userback.settings');
        foreach($settings as $key => $value) {
            $config->set($key, $value);
        }
        $config->save();
        $this->messenger()->addStatus($this->t('Userback configurations saved.'));
    }

}
