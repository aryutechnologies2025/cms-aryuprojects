<?php


namespace Drupal\relative_date_widget\Plugin\Field\FieldWidget;


use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Annotation\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase;

/**
 * Plugin implementation of the 'datetime_default' widget.
 *
 * @FieldWidget(
 *   id = "datetime_relative",
 *   label = @Translation("Relative date"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class DateTimeRelativeDateWidget extends DateTimeWidgetBase
{
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $element['value'] = $element + [
                '#type' => 'textfield',
                '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
                '#size' => 60,
                '#maxlength' => 1000,
                '#attributes' => ['class' => ['js-text-full', 'text-full']],
                '#description' => 'A value in a valid format as listed <a href="https://www.php.net/manual/en/datetime.formats.php" target="_blank">here</a>.'
            ];
        $element['#theme_wrappers'][] = 'fieldset';

        return $element;
    }

    public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
    {
        return $values;
    }
}
