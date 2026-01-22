<?php

namespace Drupal\tag_text_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tag_text_field\Plugin\Field\FieldType\HtmlTagTextFieldType;

/**
 * Plugin implementation of the 'html_tag_text_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "html_tag_text_field_widget",
 *   module = "tag_text_field",
 *   label = @Translation("Html tag text field widget"),
 *   field_types = {
 *     "html_tag_text_field_type"
 *   }
 * )
 */
class HtmlTagTextFieldWidget extends WidgetBase
{

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [
                'size' => 60,
                'placeholder' => '',
            ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        $elements = [];

        $elements['size'] = [
            '#type' => 'number',
            '#title' => t('Size of textfield'),
            '#default_value' => $this->getSetting('size'),
            '#required' => TRUE,
            '#min' => 1,
        ];
        $elements['placeholder'] = [
            '#type' => 'textfield',
            '#title' => t('Placeholder'),
            '#default_value' => $this->getSetting('placeholder'),
            '#description' => t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
        ];

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary()
    {
        $summary = [];

        $summary[] = t('Textfield size: @size', ['@size' => $this->getSetting('size')]);
        if (!empty($this->getSetting('placeholder'))) {
            $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder')]);
        }

        return $summary;
    }

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $element['value'] = [
            '#type' => 'textfield',
            '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
            '#size' => $this->getSetting('size'),
            '#placeholder' => $this->getSetting('placeholder'),
            '#maxlength' => $this->getFieldSetting('max_length'),
        ];
        $element['tag'] = [
            '#title' => t('Tag'),
            '#type' => 'select',
            '#default_value' => isset($items[$delta]->tag) ? $items[$delta]->tag : $this->getFieldSetting('defaultTag'),
            '#options' => HtmlTagTextFieldType::tagOptionsStringToArray($this->getFieldSetting('tagList')),
            '#attributes' => [
                'class' => ['html-tag-text-field-tag-field']
            ],
        ];

        $element['#type'] = 'fieldset';
        return $element;
    }
}
