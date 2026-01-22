<?php

namespace Drupal\lawnbot\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\State;
use Drupal\lawnbot\Entity\Servicebot;

/**
 * Configure Lawnbot settings for this site.
 */
class SettingsForm extends ConfigFormBase
{

    protected $configName = 'lawnbot.settings';

    protected $state;

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'lawnbot_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [$this->configName];
    }

    protected function getConfig() {
        return $this->config($this->configName);
    }

    protected function state() {
        if($this->state instanceof State) {
            return $this->state;
        }
        $this->state = \Drupal::state();
        return $this->state;
    }

    protected function getServiceBots() {
        $entityTypeManager = \Drupal::entityTypeManager();
        $bots = $entityTypeManager->getStorage('servicebot')->loadMultiple();
        $return = [];
        /** @var Servicebot $bot */
        foreach($bots as $bot) {
            $array = [
                'id' => $bot->id(),
                'enabled' => $bot->isEnabled(),
                'label' => $bot->label(),
                'customerId' => $bot->getCustomerId(),
                'botId' => $bot->getBotId(),
            ];
            $return[] = $array;
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['testing'] = [
            '#title' => t('Testing'),
            '#type' => 'checkbox',
            '#weight' => 0,
            '#description' => t('Check this off to disable the servicebot page, but keep the functionality. For testing purposes.'),
            '#default_value' => $this->state()->get('lawnbot.testing') ?? false
        ];

        $bots = $this->getServiceBots();
        $form['serviceBots'] = [
            '#type' => 'hidden',
            '#default_value' => empty($bots) ? '[]' : json_encode($bots),
            '#attributes' => [
                'id' => 'serviceBots'
            ]
        ];

        $form['toDelete'] = [
            '#type' => 'hidden',
            '#default_value' => '[]',
            '#attributes' => [
                'id' => 'toDelete'
            ]
        ];

        $template = [
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['bot']
            ]
        ];

        $template['enabled'] = [
            '#type' => 'checkbox',
            '#title' => 'Enable this service bot',
            '#attributes' => [
                'class' => ['enabled', 'check-action']
            ]
        ];

        $template['label'] = [
            '#type' => 'textfield',
            '#title' => 'Label for this service bot',
            '#attributes' => [
                'class' => ['label', 'text-action'],
                'data-required' => '1'
            ]
        ];

        $template['customerId'] = [
            '#type' => 'textfield',
            '#title' => 'Customer Id',
            '#attributes' => [
                'class' => ['customerId', 'text-action'],
                'data-required' => '1'
            ]
        ];

        $template['botId'] = [
            '#type' => 'textfield',
            '#title' => 'Bot Id',
            '#attributes' => [
                'class' => ['botId', 'text-action'],
                'data-required' => '1'
            ]
        ];

        $template['actions'] = [
            '#type' => 'actions'
        ];

        $template['actions']['remove'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => 'Remove',
            '#attributes' => [
                'class' => ['action-link', 'action-link--danger', 'action-link--icon-trash', 'remove-button']
            ]
        ];

        $template['actions']['view'] = [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#value' => 'View',
            '#attributes' => [
                'target' => '_blank',
                'href' => '',
                'class' => ['action-link', 'action-link--icon-show', 'view-button', 'hidden']
            ]
        ];

        $template['actions']['edit'] = [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#value' => 'Edit',
            '#attributes' => [
                'target' => '_blank',
                'href' => '',
                'class' => ['action-link', 'action-link--icon-cog', 'edit-button', 'hidden']
            ]
        ];

        $form['botTemplate'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'id' => 'botTemplate',
                'type' => 'text/html+template'
            ],
            'template' => $template
        ];

        $form['botWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'id' => 'bot-wrap'
            ],
        ];



        $form['#attached']['library'][] = 'lawnbot/global_settings';

        $form = parent::buildForm($form, $form_state);
        $form['actions']['addBot'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => 'Add ServiceBot',
            '#attributes' => [
                'type' => 'button',
                'id' => 'addBot',
                'class' => ['button']
            ],
            '#weight' => -10
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $botsJson = $form_state->getValue('serviceBots') ?? '[]';
        $bots = json_decode($botsJson, true);
        $missingLabel = false;
        $missingCustomerId = false;
        $missingBotId = false;
        foreach($bots as $bot) {
            if(empty($bot['label'])) {
                $missingLabel = true;
            }
            if(empty($bot['customerId'])) {
                $missingCustomerId = true;
            }
            if(empty($bot['botId'])) {
                $missingBotId = true;
            }
        }
        if($missingLabel) {
            $form_state->setErrorByName('botLabel', 'ServiceBot label is required');
        }
        if($missingCustomerId) {
            $form_state->setErrorByName('customerId', 'ServiceBot Customer Id is required');
        }
        if($missingBotId) {
            $form_state->setErrorByName('botId', 'ServiceBot Bot Id is required');
        }

        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $botsJson = $form_state->getValue('serviceBots') ?? '[]';
        $bots = json_decode($botsJson, true);
        $entityTypeManager = \Drupal::entityTypeManager();
        $storage = $entityTypeManager->getStorage('servicebot');
        foreach($bots as $botArray) {
            if(!empty($botArray['id'])) {
                /** @var Servicebot $bot */
                $bot = $storage->load($botArray['id']);
                $bot->setNewRevision(true);
                $bot->setRevisionLogMessage("ServiceBot settings saved");
            }
            else {
                $bot = Servicebot::create();
            }
            $bot->setTitle($botArray['label']);
            $bot->set('status', !empty($botArray['enabled']));
            $bot->set('customer_id', [
                'value' => $botArray['customerId']
            ]);
            $bot->set('bot_id', [
                'value' => $botArray['botId']
            ]);
            $bot->save();
        }
        $toDelete = $form_state->getValue('toDelete');
        if(!empty($toDelete)) {
            $toDelete = json_decode($toDelete, true);
            foreach ($toDelete as $botId) {
                $dBot = $storage->load($botId);
                $dBot->delete();
            }
        }

        $testing = $form_state->getValue('testing');
        \Drupal::state()->set('lawnbot.testing', !empty($testing));

        drupal_flush_all_caches();
        parent::submitForm($form, $form_state);
    }

}
