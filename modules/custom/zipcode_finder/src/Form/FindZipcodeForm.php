<?php

namespace Drupal\zipcode_finder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\link\Plugin\Field\FieldType\LinkItem;
use Drupal\zipcode_finder\Entity\ZipcodeFinder;
use Drupal\zipcode_finder\ZipcodeFinderService;

/**
 * Provides a Zipcode Finder form.
 */
class FindZipcodeForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'zipcode_finder_find_zipcode';
    }

    public static function triggerName(FormStateInterface $formState) {
        $settings = $formState->get('settings') ?? [];
        if(!empty($settings['triggerName'])) {
            return $settings['triggerName'];
        }

        return $formState->getFormObject()->getFormId() . '__submit_trigger';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $settings = null)
    {
        if(!empty($settings)) {
            $form_state->set('settings', $settings);
        }
        $settings = $form_state->get('settings') ?? [];

        $form['zip'] = [
            '#type' => 'textfield',
            '#required' => true,
            '#attributes' => [
                'placeholder' => t($settings['placeholder'] ?? 'Enter your zip code'),
                'class' => ['zipcode-text-field']
            ]
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t($settings['submitText'] ?? 'Go'),
            '#attributes' => [
                'class' => ['zipcode-submit']
            ]
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        /** @var ZipcodeFinderService $service */
        $service = \Drupal::service('zipcode_finder.service');
        $zip = $form_state->getValue('zip');
        $zip = ZipcodeFinderService::normalizeZipcode($zip);
        $service->logZipcode($zip);

        $finder = ZipcodeFinder::findByZipcode($zip);
        $request = \Drupal::request();
        if($finder instanceof ZipcodeFinder) {
            $linkItems = $finder->get('link');
            if(!$linkItems->isEmpty()) {
                $url = $linkItems->first()->getUrl();
            }
        }
        if(empty($url)) {
            $settings = $form_state->get('settings') ?? [];
            $uriStr = $settings['failureUri'] ?? $request->getUri();
            if(strpos($uriStr, '/') === 0) {
                if(strpos($uriStr, '//') === 0) {
                    $uriStr = 'internal:/' . ltrim($uriStr, '/');
                }
            }
            $url = Url::fromUri($uriStr);
        }

        $urlOptions = $url->getOptions();
        $query = array_merge($request->query->all(), $urlOptions['query'] ?? []);
        $query['postal_code'] = $zip;
        $url->setOption('query', $query);
        $form_state->setRedirectUrl($url);
    }

}
