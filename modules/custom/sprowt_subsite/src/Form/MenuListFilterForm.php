<?php

namespace Drupal\sprowt_subsite\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Laminas\Diactoros\Response\RedirectResponse;

/**
 * Provides a Sprowt Subsite form.
 */
class MenuListFilterForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_subsite_menu_list_filter';
    }

    public function getSubsiteOptions() {
        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'type' => 'subsite'
        ]);
        $options = [];
        foreach($nodes as $node) {
            $options[$node->id()] = $node->label();
        }
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $query = \Drupal::request()->query->all() ?? [];
        $form['type'] = [
            '#title' => 'Menu type',
            '#type' => 'select',
            '#options' => [
                'client' => 'Front end',
                'admin' => 'Admin',
                'all' => 'All'
            ],
            '#name' => 'type',
            '#default_value' => $query['type'] ?? 'client',
            '#wrapper_attributes' => [
                'class' => ['fake-views-item', 'views-exposed-form__item']
            ]
        ];

        $subsiteOptions = $this->getSubsiteOptions();
        if(!empty($subsiteOptions)) {
            $form['subsite'] = [
                '#title' => 'Subsite',
                '#type' => 'select',
                '#options' => $this->getSubsiteOptions(),
                '#empty_value' => '',
                '#empty_option' => 'None',
                '#name' => 'subsite',
                '#default_value' => $query['subsite'] ?? '',
                '#wrapper_attributes' => [
                    'class' => ['fake-views-item', 'views-exposed-form__item']
                ]
            ];
        }

        $form['#attributes'] = [
            'class' => ['fake-exposed-form', 'views-exposed-form']
        ];

        $form['#attached']['library'][] = 'sprowt_subsite/menu_list';

        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => [
                'class' => ['fake-views-item', 'views-exposed-form__item', 'views-exposed-form__item--actions']
            ]
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => 'Apply'
        ];

        return $form;
    }

    public static function processSelect(&$element, FormStateInterface $formState, &$complete_form) {
        if(empty($element['#wrapper_attributes'])) {
            $element['#wrapper_attributes'] = [];
        }
        if(empty($element['#wrapper_attributes']['class'])) {
            $element['#wrapper_attributes']['class'] = [];
        }
        $element['#wrapper_attributes']['class'][] = 'fake-view-form-item';
        $element['#wrapper_attributes']['class'][] = 'views-exposed-form__item';
        $element['#attached']['library'][] = 'claro/views';
        return $element;
    }

    public static function changeType(&$form, FormStateInterface $formState) {
        $type = $formState->getValue('type');
        $query = [];
        $urlStr = \Drupal::request()->getRequestUri();
        $url = Url::fromUri('internal:' . $urlStr);
        $urlq = $url->getOption('query');
        if(!empty($urlq['subsite'])) {
            $query['subsite'] = $urlq['subsite'];
        }
        if(!empty($type)) {
            $query['type'] = $type;
        }
        else {
            if(isset($query['type'])) {
                unset($query['type']);
            }
        }
        $url->setOption('query', $query);
        $url->setAbsolute(true);
        $response = new AjaxResponse();
        $response->addCommand(new RedirectCommand($url->toString()));
        return $response;
    }

    public static function changeSubsite(&$form, FormStateInterface $formState) {
        $subsite = $formState->getValue('subsite');
        $query = [];
        $urlStr = \Drupal::request()->getRequestUri();
        $url = Url::fromUri('internal:' . $urlStr);
        $urlq = $url->getOption('query');
        if(!empty($urlq['type'])) {
            $query['type'] = $urlq['type'];
        }
        if(!empty($subsite)) {
            $query['subsite'] = array_values($subsite);
        }
        else {
            if(isset($query['subsite'])) {
                unset($query['subsite']);
            }
        }
        $url->setOption('query', $query);
        $url->setAbsolute(true);
        $response = new AjaxResponse();
        $response->addCommand(new RedirectCommand($url->toString()));
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $type = $form_state->getValue('type');
        $subsite = $form_state->getValue('subsite');
        $urlStr = \Drupal::request()->getRequestUri();
        $url = Url::fromUri('internal:' . $urlStr);
        $query = [];
        if(!empty($type)) {
            $query['type'] = $type;
        }
        else {
            if(isset($query['type'])) {
                unset($query['type']);
            }
        }
        if(!empty($subsite)) {
            $query['subsite'] = is_array($subsite) ? array_values($subsite) : $subsite;
        }
        else {
            if(isset($query['subsite'])) {
                unset($query['subsite']);
            }
        }
        $url->setOption('query', $query);
        $form_state->setRedirectUrl($url);
    }

}
