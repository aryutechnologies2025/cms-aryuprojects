<?php

declare(strict_types=1);

namespace Drupal\sprowt_admin_override\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\text\Plugin\Field\FieldWidget\TextareaWithSummaryWidget;

/**
 * Defines the 'sprowt_admin_override_text_area_with_a_formatted_summary' field widget.
 *
 * @FieldWidget(
 *   id = "sprowt_admin_override_text_area_with_a_formatted_summary",
 *   label = @Translation("Text area with a formatted summary"),
 *   field_types = {"text_with_summary"},
 * )
 */
class TextAreaWithAFormattedSummaryWidget extends TextareaWithSummaryWidget
{

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings(): array
    {
        $setting = [
            'use_editor' => false,
        ];
        return $setting + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state): array
    {
        $element = parent::settingsForm($form, $form_state);
        $element['use_editor'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Use HTML editor in the summary.'),
            '#default_value' => $this->getSetting('use_editor'),
        ];
        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary() {
        $summary = parent::settingsSummary();

        if($this->getSetting('use_editor')) {
            $summary[] = $this->t('Use HTML editor in the summary: true');
        }
        else {
            $summary[] = $this->t('Use HTML editor in the summary: false');
        }

        return $summary;
    }

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array
    {
        $element = parent::formElement($items, $delta, $element, $form, $form_state);
        $display_summary = $items[$delta]->summary || $this->getFieldSetting('display_summary');
        if($display_summary && $this->getSetting('use_editor')) {
            $element['summary']['#type'] = 'text_format';
        }

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
        foreach($values as $delta => $value) {
            if(is_array($value['summary'])) {
                $values[$delta]['summary'] = $value['summary']['value'] ?? '';
            }
        }
        return $values;
    }

}
