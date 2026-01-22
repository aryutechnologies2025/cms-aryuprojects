<?php

namespace Drupal\cookie_banner\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Cookie Banner settings for this site.
 */
class SettingsForm extends ConfigFormBase
{

    public static $stateKey = 'cookie_banner.settings';

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'cookie_banner_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['cookie_banner.settings'];
    }

    public static function defaultConfig() {
        return [
            'enabled' => false,
            'acceptButtonText' => 'Accept',
            'bannerText' => '<p>We use cookies to improve your experience. Please read our <a href="/privacy-policy">privacy policy</a> for more info.</p>',
            'expires' => 'never'
        ];
    }

    public static function getConfig() {
        $default = static::defaultConfig();
        $current = \Drupal::state()->get(static::$stateKey, []);
        return array_merge($default, $current);
    }

    protected function setConfig($config) {
        return \Drupal::state()->set(static::$stateKey, $config);
    }

    protected function updateConfig($config) {
        $base = static::getConfig();
        foreach($config as $key => $val) {
            $base[$key] = $val;
        }
        $this->setConfig($base);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->getConfig();
        $form['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable the cookie banner'),
            '#default_value' => $config['enabled'],
            '#attributes' => [
                'class' => ['enable-check']
            ]
        ];

        $form['bannerText'] = [
            '#type' => 'text_format',
            '#title' => 'Banner text',
            '#format' => 'full_html',
            '#allowed_formats' => [
                'full_html'
            ],
            '#default_value' => $config['bannerText'],
            '#description' => 'The text show in the banner',
            '#states' => [
                'required' => [
                    '.enable-check' => [
                        'checked' => true
                    ]
                ]
            ]
        ];

        $form['acceptButtonText'] = [
            '#type' => 'textfield',
            '#title' => 'Accept button text',
            '#default_value' => $config['acceptButtonText'],
            '#description' => 'The text within the accept button',
            '#states' => [
                'required' => [
                    '.enable-check' => [
                        'checked' => true
                    ]
                ]
            ]
        ];

        $expirationOpts = [
            'never' => 'Never',
            'day' => 'after one day',
            'week' => 'after one week',
            'month' => 'after one month',
            'year' => 'after one year'
        ];

        $form['expires'] = [
            '#type' => 'radios',
            '#title' => 'Accept cookie expiration',
            '#options' => $expirationOpts,
            '#description' => 'Length of time until the banner will re-appear after acceptance',
            '#default_value' => $config['expires'],
            '#states' => [
                'required' => [
                    '.enable-check' => [
                        'checked' => true
                    ]
                ]
            ]
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $enabled = $form_state->getValue('enabled');
        $requiredKeys = [
            'bannerText',
            'acceptButtonText',
            'expires'
        ];
        if(!empty($enabled)) {
            foreach($requiredKeys as $requiredKey) {
                $val = $form_state->getValue($requiredKey);
                if(empty($val)) {
                    $element = $form[$requiredKey];
                    $form_state->setError($element, $this->t('Field, "@title", is required', [
                        '@title' => $element['#title']
                    ]));
                }
            }
        }
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $config = [
            'enabled' => !empty($values['enabled']),
            'bannerText' => $values['bannerText']['value'],
            'acceptButtonText' => $values['acceptButtonText'],
            'expires' => $values['expires']
        ];
        $this->updateConfig($config);
        parent::submitForm($form, $form_state);
    }

}
