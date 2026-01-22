<?php

namespace Drupal\sprowt_admin_override\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Annotation\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pathauto\PathautoWidget;

/**
 * Defines the 'sprowt_path_widget' field widget.
 *
 * @FieldWidget(
 *   id = "sprowt_path_widget",
 *   label = @Translation("Sprowt Url Alias"),
 *   field_types = {"path"},
 * )
 */
class SprowtPathWidget extends PathautoWidget
{

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [
                'in_sidebar' => false,
            ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {

        $element['in_sidebar'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('In Sidebar By Itself'),
            '#default_value' => $this->getSetting('in_sidebar'),
        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary()
    {
        $inSidebar = $this->getSetting('in_sidebar');
        $summary = [];
        if(!empty($inSidebar)) {
            $summary[] = $this->t('In sidebar');
        }
        return $summary;
    }

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {

        $element = parent::formElement($items, $delta, $element, $form, $form_state);
        $inSidebar = $this->getSetting('in_sidebar');
        if(empty($inSidebar) && $element['#group'] == 'advanced') {
            unset($element['#group']);
        }

        return $element;
    }

}
