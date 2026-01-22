<?php

namespace Drupal\sprowt_address_autocomplete\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides an 'address' element.
 *
 * @WebformElement(
 *   id = "sprowt_address_autocomplete",
 *   label = @Translation("Sprowt autocomplete address"),
 *   description = @Translation("Provides a form element to collect address information (street, city, state, zip) via google autocomplete"),
 *   category = @Translation("Composite elements"),
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class SprowtAddressAutocompleteElement extends WebformCompositeBase
{

    protected function defineDefaultProperties()
    {
        $return = parent::defineDefaultProperties() + [
            'requireFound' => false
        ];

        $overrideFields = [
            'address',
            'address_2',
            'city',
            'state_province',
            'postal_code',
            'country',
        ];
        foreach ($overrideFields as $overrideField) {
            $compositeKey = 'override__' . $overrideField;
            $return[$compositeKey . '__access'] = false;
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
        $text =  $this->formatTextItem($element, $webform_submission, $options);
        if(!empty($_GET['show-full-address'])) {
            return [
                '#type' => 'markup',
                '#markup' => Markup::create('<pre>' . $text . '</pre>')
            ];
        }

        return $text;
    }


    public function form(array $form, FormStateInterface $form_state)
    {
        $config = \Drupal::config('sprowt_address_autocomplete.settings');
        if(empty($config->get('google_maps_api_key'))) {
            $link = Link::createFromRoute('here', 'sprowt_address_autocomplete.settings_form', [], [
                'attributes' => [
                    'target' => '_blank'
                ]
            ]);
            $message = 'Google api key not set! Set it ' . $link->toString() . ' or else this field will not work.';
            \Drupal::messenger()->addWarning(Markup::create(t($message)));
        }
        $form = parent::form($form, $form_state);
        $form["composite"]["element"]['#type'] = 'fieldset';
        $hiddenFields = [
            'hidden_value',
        ];
        $detailKeys = [
            'placeName',
            'placeId',
            'formattedAddress',
            'lat',
            'lng',
            'address',
            'address_2',
            'city',
            'state_province',
            'state_province_code',
            'country',
            'country_code',
            'postal_code'
        ];
        foreach ($detailKeys as $key) {
            $hiddenFields[] = 'conditional_value_' . $key;
        }

        foreach($hiddenFields as $key) {
            $form["composite"]["element"][$key][$key . "__access"]['#type'] = 'value';
            $form["composite"]["element"][$key][$key . "__access"]['#value'] = true;
            $form["composite"]["element"][$key]["labels"]["data"][$key . "__title_display"]['#type'] = 'value';
            $form["composite"]["element"][$key]["labels"]["data"][$key . "__title_display"]['#value'] = 'invisible';
            $form["composite"]["element"][$key]['#access'] = false;
        }

        $form["composite"]["element"]["address"]["labels"]["data"]["address__key"]['#access'] = false;

        $form["composite"]["element"]["address"]["address__access"]['#type'] = 'value';
        $form["composite"]["element"]["address"]["address__access"]['#value'] = true;
        unset($form["composite"]["element"]["address"]["labels"]["data"]["address__title"]["#title_display"]);
        unset($form["composite"]["element"]["address"]["labels"]["data"]["address__title"]["#description_display"]);

        $compositeHide = [
            'flexbox',
            'select2',
            'choices',
            'chosen'
        ];

        foreach($compositeHide as $hideKey) {
            $form["composite"][$hideKey]['#access'] = false;
        }

        $form["composite"]['autompleteOptions'] = [
            '#type' => 'fieldset'
        ];

        $form["composite"]['autompleteOptions']['requireFound'] = [
            '#type' => 'checkbox',
            '#return_value' => true,
            '#title' => $this->t('Require address to be found'),
            '#description' => 'It\'s possible the field could have a value but not from the service. Check this to only validate if the field is filled with a found autocompleted address from the service.',
        ];


        return $form;
    }

    public function getConfigurationFormProperties(array &$form, FormStateInterface $form_state)
    {
        return parent::getConfigurationFormProperties($form, $form_state);
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
    }

    protected function formatTextItem(array $element, WebformSubmissionInterface $webform_submission, array $options = [])
    {
        $key = $element["#webform_key"];
        $data = $webform_submission->getElementData($key) ?? [];
        if(!empty($_GET['show-full-address'])) {
            return json_encode($data, JSON_PRETTY_PRINT);
        }

        return $data['formattedAddress'] ?? '';
    }

    public function setDefaultValue(array &$element)
    {
        $submission = $this->getWebformSubmission();
        $defaultValue = [];
        if($submission instanceof WebformSubmission) {
            $key = $element["#webform_key"];
            $data = $submission->getElementData($key);
            if(!empty($data)) {
                if(!empty($data['placeId'])) {
                    $defaultValue["hidden_value"] = json_encode($data);
                }
                if(!empty($data['formattedAddress'])) {
                    $defaultValue["address"] = $data['formattedAddress'];
                }
                foreach($data as $key => $value) {
                    $conditionalKey = 'conditional_value_' . $key;
                    if($key == 'name') {
                        $conditionalKey = 'conditional_value_placeName';
                    }
                    $defaultValue[$conditionalKey] = $value;
                }
            }
        }
        $element['#default_value'] = $defaultValue;
        parent::setDefaultValue($element);
    }

    public function getInitializedCompositeElement(array $element, $composite_key = NULL)
    {
        $return = parent::getInitializedCompositeElement($element, $composite_key);
        if(isset($return)) {
            return $return;
        }
        if(isset($composite_key)) {
            $conditionalKey = 'conditional_value_' . $composite_key;
            if($composite_key == 'name') {
                $conditionalKey = 'conditional_value_placeName';
            }
            return parent::getInitializedCompositeElement($element, $conditionalKey);
        }
        return $return;
    }
}
