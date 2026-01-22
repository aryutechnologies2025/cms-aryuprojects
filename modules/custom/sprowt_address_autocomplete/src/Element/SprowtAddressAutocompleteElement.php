<?php

namespace Drupal\sprowt_address_autocomplete\Element;


use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Annotation\FormElement;
use Drupal\sprowt_settings\StateTrait;
use Drupal\webform\Element\WebformCompositeBase;

/**
 * @FormElement("sprowt_address_autocomplete")
 */
class SprowtAddressAutocompleteElement extends WebformCompositeBase
{
    use StateTrait;

    /**
     * {@inheritdoc}
     */
    public function getInfo() {
        $return = parent::getInfo() + ['#theme' => 'sprowt_address_autocomplete'];
        $return['#pre_render'][] = [static::class, 'preRenderAutocompleteElement'];
        $overrideFields = [
            'address',
            'address_2',
            'city',
            'state_province',
            'postal_code',
            'country',
        ];
        foreach ($overrideFields as $field) {
            $compositeKey = 'override__' . $field;
            $return['#' . $compositeKey . '__access'] = false;
        }
        return $return;
    }

    public static function getCompositeElements(array $element)
    {
        $elements = [];

        $elements['hidden_value'] = [
            '#type' => 'hidden',
            '#attributes' => [
                'class' => ['hidden-value']
            ]
        ];

        $elements['address'] = [
            '#type' => 'textfield',
            '#title' => t('Address'),
            '#attributes' => [
                'class' => ['autocomplete-textfield']
            ]
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

        //add hidden fields for conditional logic
        foreach ($detailKeys as $key) {
            $elements['conditional_value_' . $key] = [
                '#type' => 'hidden',
                '#attributes' => [
                    'class' => ['hidden-conditional'],
                    'data-conditional-key' => $key
                ]
            ];
        }

        $overrideFields = [
            'address' => 'Street',
            'address_2' => 'Street (Additional)',
            'city' => 'City',
            'state_province' => 'State/Province',
            'postal_code' => 'Postal Code',
            'country' => 'Country',
        ];

        $override = [];

        foreach ($overrideFields as $overrideField => $overrideFieldTitle) {
            $elements['override__' . $overrideField] = [
                '#type' => 'textfield',
                '#title' => t($overrideFieldTitle),
                '#attributes' => [
                    'class' => ['override-value'],
                    'data-override-field' => $overrideField,
                ]
            ];
            if($overrideField == 'state_province') {
                $elements['override__state_province']['#type'] = 'select';
                $elements['override__state_province']['#options'] = 'state_province_names';
            }
            if($overrideField == 'country') {
                $elements['override__country']['#type'] = 'select';
                $elements['override__country']['#options'] = 'country_names';
            }
        }


        $apiKey = \Drupal::config('sprowt_address_autocomplete.settings')->get('google_maps_api_key');
        if(!empty($apiKey)) {
            $elements['#attached']['drupalSettings'] = [
                'sprowt_address_autocomplete' => [
                    'google_maps_api_key' => $apiKey
                ]
            ];
            $elements['#attached']['library'][] = 'sprowt_address_autocomplete/element';
        }

        return $elements;
    }


    public static function preRenderAutocompleteElement($element) {
        $element['address']['#weight'] = 1;
        $overrideFields = [
            'address',
            'address_2',
            'city',
            'state_province',
            'postal_code',
            'country',
        ];

        $override = [];
        foreach ($overrideFields as $overrideField) {
            $key = 'override__' . $overrideField;
            if(isset($element[$key]) && !empty($element[$key]['#access'])) {
                $override[$key] = $element[$key];
                unset($element[$key]);
            }
        }

        if(!empty($override)) {
            $element['override'] = [
                    '#type' => 'container',
                    '#weight' => 2,
                    '#attributes' => [
                        'class' => ['override-container']
                    ],
                ] + $override;
        }

        return $element;
    }

    public static function formatAddressFromElements(array $elements) {
        $formatFields = [
            'address',
            'address_2',
            'city',
            'state_province_code',
            'country_code',
        ];
        $return = [];
        foreach ($formatFields as $field) {
            $value = $elements[$field] ?? '';
            if($field == 'state_province_code') {
                $value = [$value];
                $value[] = $elements['postal_code'];
                $value = implode(' ', array_filter($value));
            }
            $return[] = $value;
        }
        return implode(', ', array_filter($return));
    }


    public static function validateWebformComposite(&$element, FormStateInterface $form_state, &$complete_form)
    {
        $value = NestedArray::getValue($form_state->getValues(), $element['#parents']);
        $overrideFields = [
            'address',
            'address_2',
            'city',
            'state_province',
            'postal_code',
            'country',
        ];
        $overrideValues = [];
        foreach ($overrideFields as $overrideField) {
            $overrideValues[$overrideField] = $value['override__' . $overrideField] ?? null;
        }


        if(empty($value['address'])) {
            $value = [];
        }
        else {
            if(!empty($value['hidden_value'])) {
                $value = json_decode($value['hidden_value'], true);
            }
            else {
                $value = [
                    'name' => $value['address'],
                    'formattedAddress' => $value['address'],
                    'notFound' => true
                ];
            }
        }
        $overridden = false;
        foreach ($overrideFields as $overrideField) {
            if(!empty($overrideValues[$overrideField])
                && (empty($value[$overrideField])
                        || $value[$overrideField] != $overrideValues[$overrideField]
                )
            ) {
                $value[$overrideField] = $overrideValues[$overrideField];
                if($overrideField == 'state_province') {
                    $value['state_province_code'] = static::getStateCode($value['state_province']);
                }
                if($overrideField == 'country') {
                    $value['country_code'] = static::getCountryCode($value['country']);
                }
                $overridden = true;
            }
        }
        if($overridden) {
            $value['formattedAddress'] = static::formatAddressFromElements($value);
        }

        if(!empty($element['#requireFound'])) {
            if(empty($value['address'])) {
                $form_state->setError($element['address'], t('Address not found by service'));
            }
        }

        // Clear empty composites value.
        if (empty(array_filter($value))) {
            $element['#value'] = [];
            $form_state->setValueForElement($element, []);
        }
        else {
            $form_state->setValueForElement($element, $value);
        }

        parent::validateWebformComposite($element, $form_state, $complete_form);
    }
}
