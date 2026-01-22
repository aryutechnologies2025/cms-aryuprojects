<?php

namespace Drupal\sprowt_settings;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;

trait WebformHandlerMapperTrait
{
    public function mapFieldElement($key, $addressPart = null) {
        switch($addressPart) {
            case 'address':
                $options = $this->getAddressOptions();
                break;
            case 'zip':
                $options = $this->getZipOptions();
                break;
            case 'city':
                $options = $this->getCityOptions();
                break;
            case 'state':
                $options = $this->getStateOptions();
                break;
            default:
                $options = $this->getElementOptions();
        }

        asort($options);

        $return = [
            $key => [
                '#type' => 'select',
                '#options' => $options,
                '#default_value' => $this->getSetting($key),
                '#empty_value' => ''
            ]
        ];
        if(!empty($addressPart)) {
            $return[$key . '__addressPart'] = [
                '#type' => 'value',
                '#value' => $addressPart
            ];
        }
        return $return;
    }

    public function getElementOptions() {
        if(method_exists($this, 'getWebform')) {
            $webform = $this->getWebform();
            $elements = $webform->getElementsDecodedAndFlattened();
        }
        else {
            $elements = [];
        }
        $elementOptions = [];
        foreach($elements as $key => $element) {
            switch($element['#type']) {
                case 'webform_markup':
                case 'captcha':
                case 'webform_actions':
                case 'fieldset':
                    //do nothing
                    break;
                default:
                    $elementOptions[$key] = $element['#title'];
            }
        }

        return $elementOptions;
    }

    public function getAddressFields() {
        if(method_exists($this, 'getWebform')) {
            $webform = $this->getWebform();
            $elements = $webform->getElementsDecodedAndFlattened();
        }
        else {
            $elements = [];
        }
        $addressFields = [];
        foreach($elements as $key => $element) {
            switch($element['#type']) {
                case 'webform_address':
                    $addressFields[$key] = $element;
                    break;
            }
        }
        return $addressFields;
    }

    public function getAddressOptions() {
        $elementOptions = $this->getElementOptions();
        $addressFields = $this->getAddressFields();
        $addressOptions = $elementOptions;

        foreach($addressFields as $key => $addressField) {
            if(!(isset($addressField['#address__access']) && empty($addressField['#address__access']))) {
                $addressOptions[$key] = $addressField['#title'];
            }
        }
        return $addressOptions;
    }

    public function getZipOptions() {
        $elementOptions = $this->getElementOptions();
        $addressFields = $this->getAddressFields();
        $zipOptions = $elementOptions;

        foreach($addressFields as $key => $addressField) {
            if(!(isset($addressField['#postal_code__access']) && empty($addressField['#postal_code__access']))) {
                $zipOptions[$key] = $addressField['#title'];
            }
        }

        return $zipOptions;
    }

    public function getCityOptions() {
        $elementOptions = $this->getElementOptions();
        $addressFields = $this->getAddressFields();
        $cityOptions = $elementOptions;

        foreach($addressFields as $key => $addressField) {
            if(!(isset($addressField['#city__access']) && empty($addressField['#city__access']))) {
                $cityOptions[$key] = $addressField['#title'];
            }
        }
        return $cityOptions;
    }

    public function getStateOptions() {
        $elementOptions = $this->getElementOptions();
        $addressFields = $this->getAddressFields();
        $stateOptions = $elementOptions;

        foreach($addressFields as $key => $addressField) {
            if(!(isset($addressField['#state_province__access']) && empty($addressField['#state_province__access']))) {
                $stateOptions[$key] = $addressField['#title'];
            }
        }

        return $stateOptions;
    }

    public function addMapToSettings(FormStateInterface $formState, $key) {
        $values = $formState->getValues();
        $mapKey = $this->getKeyValueFromFormstateValues($values, $key);
        $addressPart = $this->getKeyValueFromFormstateValues($values, $key . '__addressPart');
        $this->setSetting($key, $mapKey);
        if(!empty($addressPart)) {
            $this->setSetting($key . '__addressPart', $addressPart);
        }
    }

    protected function getKeyValueFromFormstateValues($values, $key) {
        if(is_array($values)) {
            $keys = array_keys($values);
            if(in_array($key, $keys)) {
                return $values[$key];
            }
            else {
                foreach($values as $k => $val) {
                    if(is_array($val)) {
                        $return = $this->getKeyValueFromFormstateValues($val, $key);
                        if(isset($return)) {
                            return $return;
                        }
                    }
                }
            }
        }

        return null;
    }

    public function getSubmissionValueFromConfig(WebformSubmissionInterface $webformSubmission, $key, $addressPart = null) {
        $mapKey = $this->getSetting($key);
        if(empty($addressPart)) {
            $addressPart = $this->getSetting($key . '__addressPart');
        }
        if(empty($mapKey)) {
            return null;
        }
        return $this->getSubmissionValueFromKey($webformSubmission, $mapKey, $addressPart);
    }

    public function getFormstateValueFromConfig(FormStateInterface $formState, $key, $addressPart = null) {
        $mapKey = $this->getSetting($key);
        if(empty($addressPart)) {
            $addressPart = $this->getSetting($key . '__addressPart');
        }
        if(empty($mapKey)) {
            return null;
        }
        return $this->getFormstateValueFromKey($formState, $mapKey, $addressPart);
    }

    public function getSubmissionValueFromKey(WebformSubmissionInterface $webformSubmission, $key, $addressPart = null) {
        $data = $webformSubmission->getElementData($key);
        $element = $this->getWebform()->getElement($key);
        if($element['#type'] == 'webform_address' && !empty($addressPart)) {
            switch($addressPart) {
                case 'zip':
                    $addressPart = 'postal_code';
                    break;
                case 'state':
                    $addressPart = 'state_province';
                    break;
            }

            return $data[$addressPart];
        }
        return $data;
    }

    public function getFormstateValueFromKey(FormStateInterface $formState, $key, $addressPart = null) {
        $values = $formState->getValues();
        $data = $this->getKeyValueFromFormstateValues($values, $key);
        $element = $this->getWebform()->getElement($key);
        if($element['#type'] == 'webform_address' && !empty($addressPart)) {
            switch($addressPart) {
                case 'zip':
                    $addressPart = 'postal_code';
                    break;
                case 'state':
                    $addressPart = 'state_province';
                    break;
            }

            return $data[$addressPart];
        }
        return $data;
    }

    public function searchForFormElement(&$form, $key) {
        foreach($form as $formKey => &$element) {
            if($key === $formKey) {
                return $element;
            }
            if(is_array($element)) {
                $return = $this->searchForFormElement($element, $key);
                if(!empty($return)) {
                    return $return;
                }
            }
        }
        return null;
    }

    public function updateFormElement(&$form, $key, $element) {
        foreach($form as $formKey => &$formElement) {
            if($formKey === $key) {
                $form[$formKey] = $element;
                return $element;
            }
            if(is_array($formElement)) {
                $return = $this->updateFormElement($formElement, $key, $element);
                if(!empty($return)) {
                    return $return;
                }
            }
        }
        return false;
    }
}
