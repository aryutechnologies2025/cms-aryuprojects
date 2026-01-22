<?php

namespace Drupal\tag_text_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'html_tag_text_field_formatter_type' formatter.
 *
 * @FieldFormatter(
 *   id = "html_tag_text_field_formatter",
 *   label = @Translation("Html tag text"),
 *   field_types = {
 *     "html_tag_text_field_type"
 *   }
 * )
 */
class HtmlTagTextFieldFormatter extends FormatterBase
{

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [
                // Implement default settings.
            ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        return [
                // Implement settings form.
            ] + parent::settingsForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary()
    {
        $summary = [];
        // Implement settings summary.

        return $summary;
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $elements = [];

        foreach ($items as $delta => $item) {
            // The text value has no text format assigned to it, so the user input
            // should equal the output, including newlines.
            $elements[$delta] = [
                '#type' => 'inline_template',
                '#template' => '{{ value|nl2br }}',
                '#context' => ['value' => $item->value],
            ];
        }

        return $elements;
    }

    /**
     * Generate the output appropriate for one field item.
     *
     * @param \Drupal\Core\Field\FieldItemInterface $item
     *   One field item.
     *
     * @return string
     *   The textual output generated.
     */
    protected function viewValue(FieldItemInterface $item)
    {
        // The text value has no text format assigned to it, so the user input
        // should equal the output, including newlines.
        return nl2br(Html::escape($item->value));
    }

}
