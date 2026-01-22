<?php

namespace Drupal\sprowt_subsite\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\node\Entity\Node;

/**
 * Defines the 'sprowt_subsite_reference_selector' field widget.
 *
 * @FieldWidget(
 *     id = "sprowt_subsite_reference_selector",
 *     label = @Translation("Subsite Reference Selector"),
 *     field_types = {"sprowt_subsite_reference"},
 *     multiple_values = TRUE
 * )
 */
class SubsiteReferenceSelectorWidget extends OptionsSelectWidget
{

//    /**
//     * {@inheritdoc}
//     */
//    public static function defaultSettings()
//    {
//        return [
//                //'foo' => 'bar',
//            ] + parent::defaultSettings();
//    }
//
//    /**
//     * {@inheritdoc}
//     */
//    public function settingsForm(array $form, FormStateInterface $form_state)
//    {
//
//        $element = [];
////        $element['foo'] = [
////            '#type' => 'textfield',
////            '#title' => $this->t('Foo'),
////            '#default_value' => $this->getSetting('foo'),
////        ];
//
//        return $element;
//    }
//
//    /**
//     * {@inheritdoc}
//     */
//    public function settingsSummary()
//    {
//        $summary = [];
//        //$summary[] = $this->t('Foo: @foo', ['@foo' => $this->getSetting('foo')]);
//        return $summary;
//    }

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {

        $element = parent::formElement($items, $delta, $element, $form, $form_state);
        $element['#empty_option'] = '- Select -';
        $element['#empty_value'] = '';
        return $element;
    }

    public function getOptions(FieldableEntityInterface $entity)
    {
        if (!isset($this->options)) {
            $baseOptions = [
                '_main' => 'Main site'
            ];
            $options = [];
            $subsitesFromDb = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
                'type' => 'subsite'
            ]);
            $subsites = [];
            foreach($subsitesFromDb as $subsite) {
                $subsites[$subsite->uuid()] = $subsite;
            }
            /** @var Node $subsite */
            foreach($subsites as $subsite) {
                $label = $subsite->label();
                $key = array_search($label, $options);
                if($key !== false) {
                    $otherSubsite = $subsites[$key];
                    $options[$key] = $otherSubsite->label() . " [{$otherSubsite->id()}]";
                    $label .= " [{$subsite->id()}]";
                }
                $options[$subsite->uuid()] = $label;
            }
            asort($options);
            $options = array_merge($baseOptions, $options);
            $module_handler = \Drupal::moduleHandler();
            $context = [
                'fieldDefinition' => $this->fieldDefinition,
                'entity' => $entity,
            ];
            $module_handler->alter('options_list', $options, $context);

            array_walk_recursive($options, [$this, 'sanitizeLabel']);

            // Options might be nested ("optgroups"). If the widget does not support
            // nested options, flatten the list.
            if (!$this->supportsGroups()) {
                $options = OptGroup::flattenOptions($options);
            }

            $this->options = $options;
        }
        return $this->options;
    }
}
