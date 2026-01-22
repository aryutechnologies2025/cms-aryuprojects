<?php

namespace Drupal\sprowt_subsite\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Plugin implementation of the 'Subsite Reference View' formatter.
 *
 * @FieldFormatter(
 *   id = "sprowt_subsite_reference_title",
 *   label = @Translation("Subsite Reference Title"),
 *   field_types = {
 *     "sprowt_subsite_reference"
 *   }
 * )
 */
class SubsiteReferenceTitleFormatter extends FormatterBase
{

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [
                'link' => FALSE,
            ] + parent::defaultSettings();
    }

    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        $elements['link'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Link title to subsite?'),
            '#default_value' => $this->getSetting('link'),
        ];
        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $elements = [];
        $link = $this->getSetting('link');
        foreach ($items as $delta => $item) {
            if($item->target == '_main') {
                $title = 'Main site';
            }
            else {
                $entity = $item->entity;
                if($entity instanceof Node) {
                    $title = $entity->label();
                    $url = $entity->toUrl('edit-form');
                }
                else {
                    $title = 'Subsite';
                }
            }
            if(!empty($link) && !empty($url)) {
                $elements[$delta] = [
                    '#type' => 'link',
                    '#title' => $title,
                    '#url' => $url,
                    '#attributes' => [
                        'class' => ['subsite-title'],
                        'target' => '_blank'
                    ]
                ];
            }
            else {
                $elements[$delta] = [
                    '#type' => 'html_tag',
                    '#tag' => 'span',
                    '#value' => $title,
                    '#attributes' => [
                        'class' => ['subsite-title']
                    ]
                ];
            }
        }

        return $elements;
    }
}
