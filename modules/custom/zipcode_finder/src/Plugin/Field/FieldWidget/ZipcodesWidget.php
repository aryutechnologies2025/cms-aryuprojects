<?php

namespace Drupal\zipcode_finder\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\zipcode_finder\Form\ZipcodeFinderForm;
use Drupal\zipcode_finder\ZipcodeFinderService;

/**
 * Defines the 'zipcodes_widget' field widget.
 *
 * @FieldWidget(
 *   id = "zipcodes_widget",
 *   label = @Translation("Zipcodes widget"),
 *   field_types = {"string"},
 *   multiple_values = TRUE
 * )
 */
class ZipcodesWidget extends OptionsWidgetBase
{

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $element = parent::formElement($items, $delta, $element, $form, $form_state);
        $selectedOptions = $this->getSelectedOptions($items);
        $value = json_encode($selectedOptions, JSON_PRETTY_PRINT);

        $field_name = $this->fieldDefinition->getName();
        $parents = $form['#parents'];
        // Create an ID suffix from the parents to make sure each widget is unique.
        $id_suffix = $parents ? '-' . implode('-', $parents) : '';
        $wrapper_id = $field_name . '-zipcodes-widget-' . $id_suffix;

        $fieldset = [
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['zipcodes-widget-fieldset'],
                'id' => $wrapper_id
            ]
        ];

        $fieldset['value'] = [
            '#type' => 'hidden',
            '#default_value' => $value,
            '#attributes' => [
                'class' => ['zipcodes-widget-value']
            ]
        ];

        $fieldset['error'] = [
            '#type' => 'hidden',
            '#default_value' => '[]',
            '#attributes' => [
                'class' => ['zipcodes-widget-errors']
            ]
        ];

        $fieldset['elementWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['zipcodes-widget-single-element-wrap']
            ],
        ];

        $template = [];
        $template['singleElementWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['zipcodes-widget-single-element']
            ]
        ];

        $template['singleElementWrap']['removeCheck'] = [
            '#type' => 'html_tag',
            '#tag' => 'input',
            '#attributes' => [
                'class' => ['zipcodes-widget-bulk-remove-check', 'form-checkbox', 'form-boolean', 'form-boolean--type-checkbox'],
                'type' => 'checkbox'
            ]
        ];

        $template['singleElementWrap']['zipValue'] = [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#attributes' => [
                'class' => ['zipcodes-widget-zip-value']
            ]
        ];

        $template['singleElementWrap']['removeButton'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => t('Remove'),
            '#attributes' => [
                'class' => ['zipcodes-widget-remove-button', 'button', 'button--small', '.action-link', 'action-link--small', 'action-link--danger', 'action-link--icon-trash'],
                'type' => 'button'
            ]
        ];

        $fieldset['template'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'class' => ['zipcodes-widget-single-element-template'],
                'type' => 'text/html+template'
            ],
            'template' => $template
        ];
        $fieldset['buttonWrap'] = [
            '#type' => 'actions',
            '#attributes' => [
                'class' => ['button-wrap']
            ]
        ];
        $fieldset['buttonWrap']['addButton'] = [
            '#type' => 'button',
            '#value' => t('Bulk Add Zipcode(s)'),
            '#attributes' => [
                'class' => ['zipcodes-widget-add-button', 'button', 'button--small'],
                'type' => 'button'
            ],
            '#name' => $field_name . '-zipcodes-widget-' . $id_suffix,
            '#limit_validation_errors' => [],
            '#ajax' => [
                'callback' => [static::class, 'updateWidget'],
                'wrapper' => $wrapper_id,
                'progress' => [
                    'type' => 'throbber',
                    'message' => $this->t('Opening bulk add form'),
                ],
            ],
        ];

        $fieldset['buttonWrap']['bulkRemoveButton'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => 'Bulk Remove',
            '#attributes' => [
                'class' => ['zipcodes-widget-bulk-remove-button', 'button', 'button--small', '.action-link', 'action-link--small', 'action-link--danger', 'action-link--icon-trash'],
                'type' => 'button'
            ],
        ];

        $fieldset['buttonWrap']['clearButton'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => 'Clear',
            '#attributes' => [
                'class' => ['zipcodes-widget-clear-button', 'button', 'button--small', '.action-link', 'action-link--small', 'action-link--danger', 'action-link--icon-trash'],
                'type' => 'button'
            ],
        ];

        $fieldset['#attached']['library'][] = 'zipcode_finder/widget';

        $element += $fieldset;

        $formObj = $form_state->getFormObject();
        if($formObj instanceof ZipcodeFinderForm) {
            $element['#element_validate'][] = [static::class, 'validateUniqueZipcodes'];
        }



        return $element;
    }

    public static function updateWidget(array $form, FormStateInterface $form_state) {
        $triggering_element = $form_state->getTriggeringElement();
        $wrapper_id = $triggering_element['#ajax']['wrapper'];
        $dialogOptions = [
            'dialogClass' => 'zipcodes-widget-modal',
            'title' => t('Add or select media'),
            'height' => '75%',
            'width' => '75%',
            'buttons' => [
                [
                    'text'=> 'dummy',
                    'class' => 'zipcodes-widget-modal-dummy-button hidden'
                ],
            ]
        ];

        $ui = static::bulkUpdateUi($wrapper_id);
        return (new AjaxResponse())
            ->addCommand(new OpenModalDialogCommand($dialogOptions['title'], $ui, $dialogOptions));
    }

    public static function bulkUpdateUi($wrapper_id) {
        $form = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['zipcodes-widget-bulk-add-ui-wrap']
            ]
        ];
        $form['textarea'] = [
            '#type' => 'textarea',
            '#title' => 'Bulk add zipcodes',
            '#field_prefix' => Markup::create('<div class="fieldset__description">Add one zipcode per line</div>'),
            '#attributes' => [
                'class' => ['zipcodes-widget-bulk-add-ui-textarea'],
                'data-wrapper-id' => $wrapper_id
            ],
            '#value' => null
        ];

        return $form;
    }

    protected function getSelectedOptions(FieldItemListInterface $items)
    {
        $selected_options = [];
        foreach ($items as $item) {
            $value = $item->{$this->column};
            $selected_options[] = $value;
        }

        return $selected_options;
    }

    public static function validateElement(array $element, FormStateInterface $form_state)
    {
        $stringValue = $element['value']['#value'];
        $array = json_decode($stringValue, true);
        if (!isset($array)) {
            $form_state->setError($element, new TranslatableMarkup('@name field must be in proper json markup.', ['@name' => $element['#title']]));
        } else {
            $val = [];
            foreach ($array as $value) {
                $zip = ZipcodeFinderService::normalizeZipcode($value);
                if(!in_array($zip, $val)) {
                    $val[] = $zip;
                }
            }
            $element['#value'] = $val;
            parent::validateElement($element, $form_state);
        }
    }

    public static function getCurrentValue(array $element, FormStateInterface $form_state) {
        $currentValueArray = $form_state->getValue($element['#parents']);
        $return = [];
        foreach($currentValueArray as $item) {
            $return[] = $item[$element['#key_column']];
        }
        return $return;
    }

    public static function validateUniqueZipcodes(array &$element, FormStateInterface $form_state) {
        /** @var ZipcodeFinderForm $formObj */
        $formObj = $form_state->getFormObject();
        $zipcodeFinder = $formObj->getEntity();
        $entityId = $zipcodeFinder->id();
        $params = [];
        $sql = "
            SELECT zipcodes_value
            FROM {zipcode_finder__zipcodes} zipcodes
        ";
        if(!empty($entityId)) {
            $sql .= "
                WHERE zipcodes.entity_id != :entityId
            ";
            $params['entityId'] = $entityId;
        }
        $dbZipcodes = \Drupal::database()->query($sql, $params)->fetchCol();
        $currentZipcodes = static::getCurrentValue($element, $form_state);
        $errors = [];
        foreach($currentZipcodes as $currentZipcode) {
            if(in_array($currentZipcode, $dbZipcodes)) {
                $errors[] = $currentZipcode;
            }
        }
        if(!empty($errors)) {
            $currentErrorStr = $element['error']['#value'] ?? '[]';
            $currentErrors = json_decode($currentErrorStr, true);
            $currentErrors = array_merge($currentErrors, $errors);
            $element['error']['#value'] = json_encode($currentErrors);
            $form_state->setRebuild(true);
            $form_state->setErrorByName('uniqueZipcodes', t('Zipcodes already exist in other zipcode finders. Please remove and try again.'));
        }
    }

}
