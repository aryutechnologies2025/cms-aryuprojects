<?php

namespace Drupal\template_field\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Provides a render element to display an entity.
 *
 * Properties:
 * - #entity_type: The entity type.
 * - #entity_id: The entity ID.
 * - #view_mode: The view mode that should be used to render the entity.
 * - #langcode: For which language the entity should be rendered.
 *
 * Usage Example:
 * @code
 * $build['node'] = [
 *   '#type' => 'entity',
 *   '#entity_type' => 'node',
 *   '#entity_id' => 1,
 *   '#view_mode' => 'teaser,
 *   '#langcode' => 'en',
 * ];
 * @endcode
 *
 * @\Drupal\Core\Render\Annotation\FormElement("template_field")
 */
class TemplateField extends FormElementBase
{

    /**
     * {@inheritdoc}
     */
    public function getInfo()
    {
        $class = get_class($this);
        return [
            '#input' => true,
            '#element_validate' => [
                [
                    $class,
                    'validateElement',
                ],
            ],
            '#template' => null,
            '#no_remove' => false,
            '#object_def' => [],
            '#add_button_position' => null,
            '#object_name' => 'item',
            '#process' => [
                [$class, 'processTemplateField'],
                [
                    $class,
                    'processGroup',
                ],
            ],
            '#pre_render' => [
                [
                    $class,
                    'preRenderGroup',
                ],
            ],
            '#wrapper_attributes' => [
                'class' => ['template-field-wrap']
            ],
            '#description_display' => 'before',
            '#title_display' => 'before',
            '#theme_wrappers' => [
                'form_element',
            ],
            '#value_callback' => [$this, 'valueCallback'],
            '#carryOver' => [],
            '#bulkAdd' => false,
            '#bulkAddRoute' => null,
            '#bulkAddRouteParameters' => [],
            '#bulkAddRouteOptions' => [],
            '#bulkAddButtonText' => 'Bulk add items',
            '#bulkAddDialogType' => 'modal',
            '#bulkAddDialogOptions' => ['height' => 400, 'width' => 700]
        ];
    }

    public static function setGarbage(&$element) {
        if(!is_array($element)) {
            return;
        }
        if(isset($element['#type'])) {
            $element['#input'] = false;
        }
        foreach($element as $key => &$subElement) {
            if(strpos($key, '#') !== 0) {
                static::setGarbage($subElement);
            }
        }
    }

    public static function templateRequired(&$element) {
        if(!is_array($element)) {
            return;
        }
        if(!empty($element['#required'])) {
            $element['#required'] = false;
            if(empty($element['#attributes'])) {
                $element['#attributes'] = [];
            }
            $element['#attributes']['data-js-required'] = "true";
        }
        foreach($element as $key => &$subElement) {
            if(strpos($key, '#') !== 0) {
                static::templateRequired($subElement);
            }
        }
    }

    public static function processTemplateField(&$element, FormStateInterface $formState, &$form) {
        $element['#tree'] = true;
        $subElement = [];
        $subElement['valueField'] = [
            '#type' => 'hidden',
            '#default_value' => json_encode($element['#default_value'] ?? []),
            '#attributes' => [
                'class' => ['template-field-hidden-value']
            ],
            '#weight' => 10,
        ];

        $addButtonPosition = $element['#add_button_position'] ?? 'top';
        $subElement['valueWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['template-field-value-wrap']
            ],
            '#weight' => 5
        ];

        $subElement['elementActions'] = [
            '#type' => 'actions',
            '#weight' => $addButtonPosition == 'bottom' ? 15 : 1,
        ];

        $subElement['elementActions']['addButton'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => 'Add ' . $element['#object_name'],
            '#attributes' => [
                'class' => ['template-field-add-button', 'button', 'button--small'],
                'type' => 'button'
            ]
        ];

        if(!empty($element['#bulkAdd']) && !empty($element['#bulkAddRoute'])) {
            $options = $element['#bulkAddRouteOptions'] ?? [];
            $query = $options['query'] ?? [];
            $query['fieldSelector'] = '[data-drupal-selector="'.$element["#attributes"]["data-drupal-selector"].'"]';
            $options['query'] = $query;
            $url = Url::fromRoute($element['#bulkAddRoute'], $element['#bulkAddRouteParameters'], $options);
            $subElement['elementActions']['bulkAddButton'] = [
                '#type' => 'link',
                '#title' => $element['#bulkAddButtonText'],
                '#url' => $url,
                '#attributes' => [
                    'class' => ['template-field-bulk-add-button', 'button', 'button--small'],
                ],
                '#ajax' => [
                    'dialogType' => $element['#bulkAddDialogType'],
                    'dialog' => $element['#bulkAddDialogOptions'],
                ]
            ];
        }

        $template = !empty($element['#template']) ? $element['#template'] : [];
        if(!is_array($template)) {
            $template = [];
            $template['markup'] = [
                '#type' => 'markup',
                '#markup' => Markup::create($template)
            ];
        }
        static::templateRequired($template);

        if(empty($element['#no_remove'])) {
            $template['removeButton'] = [
                '#type' => 'html_tag',
                '#tag' => 'button',
                '#weight' => $addButtonPosition == 'bottom' ? 15 : 1,
                '#value' => 'Remove ' . $element['#object_name'],
                '#attributes' => [
                    'class' => ['remove-button', 'button', 'button--danger', 'button--small'],
                    'type' => 'button'
                ]
            ];
        }


        $subElement['template'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'class' => ['template-field-template'],
                'type' => 'text/html+template_field',
                'data-object-def' => json_encode($element['#object_def'] ?? []),
                'data-carry-over' => !empty($element['#carryOver']) ? json_encode($element['#carryOver']) : '[]',
            ],
            'templateInterior' => $template,
        ];

        $element['#wrapper_attributes'] = array_merge($element['#attributes'], $element['#wrapper_attributes']);

        $element['subElement'] = $subElement;
        $element['#attached']['library'][] = 'template_field/field';

        return $element;
    }

    public static function valueCallback(&$element, $input, FormStateInterface $form_state)
    {
        if($input !== false) {
            $json = $input["subElement"]["valueField"] ?? '[]';
            $input = json_decode($json, true);
        }
        else {
            $input = $element['#default_value'] ?? [];
        }
        return $input;
    }

    public static function validateElement(&$element, FormStateInterface $form_state, &$complete_form)
    {
        $input_exists = false;
        $input = NestedArray::getValue($form_state
            ->getValues(), $element['#parents'], $input_exists);
        if($input_exists) {
            if(is_array($input) && isset($input['subElement'])) {
                unset($input['subElement']);
                $form_state->setValueForElement($element, $input);
            }
        }
        if(empty($input)) {
            $form_state->setValueForElement($element, null);
        }
    }

}
