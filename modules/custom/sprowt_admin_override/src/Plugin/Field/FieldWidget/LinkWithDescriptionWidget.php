<?php

namespace Drupal\sprowt_admin_override\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;


/**
 * Plugin implementation of the 'link' widget.
 */
#[FieldWidget(
    id: 'link_with_description',
    label: new TranslatableMarkup('Link w/ description field (aria-label field/alt. text)'),
    field_types: ['link'],
)]
class LinkWithDescriptionWidget extends LinkWidget
{


    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
        $element = parent::formElement($items, $delta, $element, $form, $form_state);

        /** @var \Drupal\link\LinkItemInterface $item */
        $item = $items[$delta];
        $description = null;
        if(!$item->isEmpty()) {
            $options = $item->options ?? [];
            $attributes = $options['attributes'] ?? [];
            $description = $attributes['aria-label'] ?? null;
        }


        $element['aria_label'] = [
            '#type' => 'textfield',
            '#title' => t('Link description'),
            '#default_value' => $description,
            '#maxlength' => 255,
            '#description' => t('If your link text is vague (e.g. "View More" or "Check it out!"), '.
                'please add a thorough description of the link destination to ensure the site is ADA compliant. '.
                'This will appear in the aria-label property of the anchor tag.')
        ];

        return $element;
    }


    /**
     * {@inheritdoc}
     */
    public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
        $values = parent::massageFormValues($values, $form, $form_state);
        foreach ($values as &$value) {
            if(!empty($value['aria_label'])) {
                $options = $value['options'] ?? [];
                $options['attributes']['aria-label'] = $value['aria_label'];
                $value['options'] = $options;;
            }
        }
        return $values;
    }
}
