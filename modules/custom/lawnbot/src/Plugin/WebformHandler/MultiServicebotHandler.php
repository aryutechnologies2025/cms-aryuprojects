<?php

namespace Drupal\lawnbot\Plugin\WebformHandler;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\lawnbot\Entity\Servicebot;
use Drupal\webform\WebformSubmissionInterface;

/**
 * @WebformHandler(
 *  id = "multiservicebot",
 *  label = @Translation("Multiple ServiceBot integration"),
 *  category = @Translation("ServiceBot"),
 *  description = @Translation("Integrates this webform with multiple ServiceBots"),
 *  cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *  results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *  submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED
 * )
 */
class MultiServicebotHandler extends LawnbotWebformHandler
{
    /**
     * @var Servicebot | null
     */
    protected $selectedServiceBot;

    public function getLinkedServiceBot()
    {
        return $this->selectedServiceBot;
    }

    public function setLinkedServiceBot($botOrUuid)
    {
        return null;
    }

    public function botEnabled() {
        if($this->selectedServiceBot instanceof Servicebot) {
            return $this->selectedServiceBot->isEnabled();
        }
        return true;
    }

    public function getSummary()
    {
        $summary =  parent::getSummary();
        if($this->handlerEnabled()) {
            $mappingArray = $this->getSetting('servicebot_mapping') ?? [];
            if(!empty($mappingArray)) {
                $storage = \Drupal::entityTypeManager()->getStorage('servicebot');
                $bots = $storage->loadMultiple();
                $webform = $this->getWebform();
                $elements = $webform->getElementsDecodedAndFlattened();
                $elementOptions = [];
                foreach ($elements as $key => $element) {
                    switch ($element['#type']) {
                        case 'webform_markup':
                        case 'captcha':
                        case 'webform_actions':
                        case 'fieldset':
                        case 'webform_address':
                            break;
                        default:
                            $elementOptions[$key] = $element['#title'];
                    }
                }

                $values = [];
                $defaultBotUuid = $mappingArray['default'] ?? null;
                if($defaultBotUuid == '<none>') {
                    $values['Default Servicebot'] = 'No default bot';
                }
                else {
                    /** @var Servicebot $bot */
                    foreach($bots as $bot) {
                        if($defaultBotUuid == $bot->uuid()) {
                            $values['Default Servicebot'] = $bot->label();
                            if(!$bot->isEnabled()) {
                                $values['Default Servicebot'] .= ' (Disabled)';
                            }
                        }
                    }
                }
                /** @var Servicebot $bot */
                foreach($bots as $bot) {
                    $mapping = $mappingArray[$bot->uuid()] ?? [
                        'mapping' => '<none>',
                        'value' => '<none>'
                    ];
                    $elementTitle = 'No mapping element';
                    if(!empty($mapping['mapping']) && $mapping['mapping'] != '<none>') {
                        $elementTitle = $elementOptions[$mapping['mapping']] ?? 'Invalid element selected';
                    }
                    $elementValue = $mapping['value'];
                    if($elementValue == '<none>') {
                        $elementValue = 'none';
                    }
                    $values['Servicebot - ' . $bot->label() . ' Status'] = $bot->isEnabled() ? 'Enabled' : 'Disabled';
                    $values['Servicebot - ' . $bot->label() . ' Element'] = $elementTitle;
                    $values['Servicebot - ' . $bot->label() . ' Element Value'] = $elementValue ?? 'none';
                }
                $list = [];
                foreach($values as $label => $value) {
                    $list[] = "<strong>$label:</strong> $value";
                }
                $markup = "<div>" . implode('<br>', $list) . '</div>';
                $summary['multiSummary'] = [
                    '#type' => 'markup',
                    '#markup' => Markup::create($markup)
                ];
            }
        }

        return $summary;
    }

    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $storage = \Drupal::entityTypeManager()->getStorage('servicebot');
        $bots = $storage->loadMultiple();

        if(empty($bots)) {
            $form['message'] = [
                '#type' => 'markup',
                '#markup' => Markup::create('
                    <p>You must <a href="/admin/config/services/lawnbot">add at least one servicebot</a> before you can edit these settings.</p>
                ')
            ];
            return $form;
        }
        $form = parent::buildConfigurationForm($form, $form_state);
        $form['servicebot'] = [
            '#weight' => 0,
            '#type' => 'fieldset',
            '#title' => 'Map ServiceBot field',
            '#description' => 'This is where you map a field to set which service bot is used for this submission'
        ];

        $botOptions = [];
        /** @var Servicebot $bot */
        foreach($bots as $bot) {
            $botOptions[$bot->uuid()] = $bot->label();
            if(!$bot->isEnabled()) {
                $botOptions[$bot->uuid()] .= ' (Disabled)';
            }
        }

        $webform = $this->getWebform();
        $elements = $webform->getElementsDecodedAndFlattened();
        $elementOptions = [];
        $addressFields = [];
        foreach($elements as $key => $element) {
            switch($element['#type']) {
                case 'webform_markup':
                case 'captcha':
                case 'webform_actions':
                case 'fieldset':
                case 'sprowt_address_autocomplete':
                    //do nothing
                    break;
                case 'webform_address':
                    $addressFields[$key] = $element;
                    break;
                default:
                    $elementOptions[$key] = $element['#title'];
            }
        }

        $mappingArray = $this->getSetting('servicebot_mapping') ?? [];

        $form['servicebot']['servicebot_default_mapping'] = [
            '#title' => 'Default Servicebot',
            '#description' => 'This is the bot that will be used if no bot is mapped according to these rules.',
            '#type' => 'select',
            '#options' => array_merge(['<none>' => 'No default bot'], $botOptions),
            '#required' => true,
            '#default_value' => $mappingArray['default'] ?? null
        ];

        foreach($bots as $bot) {
            $mapping = $mappingArray[$bot->uuid()] ?? null;
            $key = 'servicebot_field_' . $bot->id();
            $title = $bot->label();
            if(!$bot->isEnabled()) {
                $title .= ' (Disabled)';
            }
            $fieldset = [
                '#type' => 'fieldset',
                '#title' => $title
            ];
            $fieldset[$key . '_mapping'] = [
                '#title' => 'Field to map to ServiceBot selection',
                '#description' => 'This is the field that will determine which service bot to use',
                '#type' => 'select',
                '#options' => array_merge(['<none>' => 'Do not map to a field'], $elementOptions),
                '#required' => true,
                '#default_value' => $mapping['mapping'] ?? null
            ];
            $fieldset[$key . '_value'] = [
                '#title' => 'Field value',
                '#description' => 'This is the value from the mapped field which will determine which servicebot to use. Use "<none>" to indicate an empty value.',
                '#type' => 'textfield',
                '#required' => true,
                '#default_value' => $mapping['value'] ?? null
            ];
            $form['servicebot'][$key . '_wrap'] = $fieldset;
        }


        return $form;
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        $values = $form_state->getValue('servicebot');
        $mappingArray = [];
        $mappingArray['default'] = $values['servicebot_default_mapping'] ?? '<none>';
        $storage = \Drupal::entityTypeManager()->getStorage('servicebot');
        $bots = $storage->loadMultiple();

        foreach($bots as $bot) {
            $key = 'servicebot_field_' . $bot->id();
            $wrapValue = $values[$key . '_wrap'];
            $mapping = [
                'mapping' => $wrapValue[$key . '_mapping'] ?? '<none>',
                'value' => $wrapValue[$key . '_value'] ?? '<none>'
            ];
            $mappingArray[$bot->uuid()] = $mapping;
        }
        $this->setSetting('servicebot_mapping', $mappingArray);
    }

    public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        $storage = \Drupal::entityTypeManager()->getStorage('servicebot');
        $bots = $storage->loadMultiple();
        $mappingArray = $this->getSetting('servicebot_mapping') ?? [];
        $selectedBot = null;
        $defaultBot = null;
        if(!empty($mappingArray['default']) && $mappingArray['default'] != '<none>') {
            /** @var Servicebot $bot */
            foreach($bots as $bot) {
                if($bot->uuid() == $mappingArray['default']) {
                    $defaultBot = $bot;
                }
            }
        }
        foreach($bots as $bot) {
            if($selectedBot instanceof Servicebot) {
                break;
            }
            $array = $mappingArray[$bot->uuid()] ?? [
                'mapping' => '<none>',
                'value' => '<none>'
            ];
            if(!empty($array['mapping']) && $array['mapping'] != '<none>') {
                $data = $webform_submission->getElementData($array['mapping']);
                if(empty($data) && isset($array['value']) && $array['value'] == '<none>') {
                    $selectedBot = $bot;
                }
                elseif(isset($array['value']) && $array['value'] != '<none>') {
                    if((string) $data == (string) $array['value']) {
                        $selectedBot = $bot;
                    }
                }
            }
        }
        if(!$selectedBot instanceof Servicebot) {
            $selectedBot = $defaultBot;
        }
        $this->selectedServiceBot = $selectedBot;
        if(!$this->selectedServiceBot instanceof Servicebot) {
            //don't do a servicebot if none were found
            return null;
        }

        return parent::confirmForm($form, $form_state, $webform_submission);
    }

}
