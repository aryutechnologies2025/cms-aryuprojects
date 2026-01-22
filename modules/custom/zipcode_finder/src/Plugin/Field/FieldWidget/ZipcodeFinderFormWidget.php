<?php

namespace Drupal\zipcode_finder\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\zipcode_finder\Plugin\Field\FieldType\ZipcodeFinderFormItem;

/**
 * Defines the 'zipcode_finder_zipcode_finder_form_widget' field widget.
 *
 * @FieldWidget(
 *   id = "zipcode_finder_zipcode_finder_form_widget",
 *   label = @Translation("Zipcode finder form widget"),
 *   field_types = {"zipcode_finder_zipcode_finder_form"},
 * )
 */
class ZipcodeFinderFormWidget extends WidgetBase
{

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $isDefaultForm = in_array('default_value_input', $element["#field_parents"]);
        $field_name = $this->fieldDefinition->getName();
        $parents = $form['#parents'];
        // Create an ID suffix from the parents to make sure each widget is unique.
        $id_suffix = $parents ? '-' . implode('-', $parents) : '';
        $wrapper_id = $field_name . '-zipcode-finder-form-widget-' . $id_suffix;

        /** @var ZipcodeFinderFormItem $item */
        $item = $items[$delta];
        $element = $element + [
            '#type' => 'fieldset',
            '#attributes' => [
                'id' => $wrapper_id
            ]
        ];

        $states = function($el) use ($wrapper_id, $isDefaultForm) {
            if($isDefaultForm) {
                return [];
            }
            $allSelectors = [
                '#' . $wrapper_id . ' .zipcode-finder-form-widget-submit-text',
                '#' . $wrapper_id . ' .zipcode-finder-form-widget-failure-uri',
                '#' . $wrapper_id . ' .zipcode-finder-form-widget-placeholder'
            ];
            $exclude = [];
            switch($el) {
                case 'placeholder':
                    $exclude[] = '#' . $wrapper_id . ' .zipcode-finder-form-widget-placeholder';
                    break;
                case 'submit_text':
                    $exclude[] = '#' . $wrapper_id . ' .zipcode-finder-form-widget-submit-text';
                    break;
                case 'failure_uri':
                    $exclude[] = '#' . $wrapper_id . ' .zipcode-finder-form-widget-failure-uri';
                    break;
            }
            $return = [
                'required' => []
            ];
            foreach($allSelectors as $selector) {
                if(!in_array($selector, $exclude)) {
                    $return['required'][$selector] = [
                        'filled' => true
                    ];
                }
            }
            return $return;
        };

        $element['placeholder'] = [
            '#type' => 'textfield',
            '#title' => t('Field placeholder text'),
            '#required' => $element['#required'],
            '#default_value' => $item->placeholder ?? 'Enter your zip code',
            '#attributes' => [
                'class' => ['zipcode-finder-form-widget-placeholder']
            ],
            '#states' => $states('placeholder')
        ];

        $element['submit_text'] = [
            '#type' => 'textfield',
            '#title' => t('Submit button text'),
            '#required' => $element['#required'],
            '#default_value' => $item->submit_text ?? 'Go',
            '#attributes' => [
                'class' => ['zipcode-finder-form-widget-submit-text']
            ],
            '#states' => $states('submit_text')
        ];

        //stolen from Drupal\link\Plugin\Field\FieldWidget\LinkWidget

        $descriptionArgs = [
            '%front' => '<front>',
            '%add-node' => '/node/123',
            '%url' => 'http://example.com',
            '%nolink' => '<nolink>',
            '%button' => '<button>'
        ];

        $descriptionText = 'Start typing the title of a piece of content to select it. '
            .'You can also enter an internal path such as %add-node or an external URL such as %url. '
            .'Enter %front to link to the front page. Enter %nolink to display link text only. '
            .'Enter %button to display keyboard-accessible link text only.';

        $element['failure_uri'] = [
            '#type' => 'entity_autocomplete',
            '#title' => $this->t('Destination when no finder can be matched'),
            '#default_value' => (!$item->isEmpty()
                && (\Drupal::currentUser()->hasPermission('link to any page')
                    || $item->getUrl()->access())) ?
                static::getUriAsDisplayableString($item->failure_uri) : null,
            '#element_validate' => [[LinkWidget::class, 'validateUriElement']],
            '#maxlength' => 2048,
            '#required' => $element['#required'],
            '#target_type' => 'node',
            '#process_default_value' => false,
            '#attributes' => [
                'class' => ['zipcode-finder-form-widget-failure-uri'],
                // Disable autocompletion when the first character is '/', '#' or '?'.
                'data-autocomplete-first-character-blacklist' => '/#?'
            ],
            '#description' => $this->t($descriptionText, $descriptionArgs),
            '#states' => $states('failure_uri')
        ];

        return $element;
    }

    /**
     * stolen from LinkWidget
     *
     * Gets the URI without the 'internal:' or 'entity:' scheme.
     *
     * The following two forms of URIs are transformed:
     * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
     * - 'internal:' URIs: the scheme is stripped.
     *
     * This method is the inverse of ::getUserEnteredStringAsUri().
     *
     * @param string $uri
     *   The URI to get the displayable string for.
     *
     * @return string
     *
     * @see static::getUserEnteredStringAsUri()
     */
    protected static function getUriAsDisplayableString($uri) {
        $scheme = parse_url($uri, PHP_URL_SCHEME);

        // By default, the displayable string is the URI.
        $displayable_string = $uri;

        // A different displayable string may be chosen in case of the 'internal:'
        // or 'entity:' built-in schemes.
        if ($scheme === 'internal') {
            $uri_reference = explode(':', $uri, 2)[1];

            // @todo '<front>' is valid input for BC reasons, may be removed by
            //   https://www.drupal.org/node/2421941
            $path = parse_url($uri, PHP_URL_PATH);
            if ($path === '/') {
                $uri_reference = '<front>' . substr($uri_reference, 1);
            }

            $displayable_string = $uri_reference;
        }
        elseif ($scheme === 'entity') {
            [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
            // Show the 'entity:' URI as the entity autocomplete would.
            // @todo Support entity types other than 'node'. Will be fixed in
            //   https://www.drupal.org/node/2423093.
            if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
                $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
            }
        }
        elseif ($scheme === 'route') {
            $displayable_string = ltrim($displayable_string, 'route:');
        }

        return $displayable_string;
    }

}
