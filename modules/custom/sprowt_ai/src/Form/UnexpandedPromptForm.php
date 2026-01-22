<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai\AiService;
use Drupal\sprowt_ai\Element\ClaudePrompt;
use Drupal\sprowt_ai\Entity\AiSystem;

/**
 * Provides a Sprowt AI form.
 */
class UnexpandedPromptForm extends FormBase
{

    use AjaxFormHelperTrait;

    protected $sessionData;

    public static $sessionKey = 'sprowt_ai.unexpanded_prompt';

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_unexpanded_prompt';
    }

    public function getSessionData() {
        if(isset($this->sessionData)) {
            return $this->sessionData;
        }

        $this->sessionData = static::getSessionDataStatic();
        return $this->sessionData;
    }

    public static function getSessionDataStatic() {
        $session = \Drupal::service('session');
        $sessionData = $session->get(static::$sessionKey) ?? [];
        return $sessionData;
    }

    public static function saveSessionData($sessionData)
    {
        $session = \Drupal::service('session');
        $session->set(static::$sessionKey, $sessionData);
        return $sessionData;
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {


        $form['#attributes']['class'][] = 'widget-prompt-form';

        $sessionData = $this->getSessionData();

        $options = $sessionData['options'] ?? [];
        $parentForm = $sessionData['parentForm'] ?? [];
        $insertParents = $options['insertParents'] ?? null;
        $prompt = $sessionData['prompt'] ?? null;

        if(!empty($insertParents)) {
            $form['#title'] = 'Generate content';
        }

        $defaultSystemId = null;
        $defaultSystem = sprowt_ai_default_system();
        if($defaultSystem instanceof AiSystem) {
            $defaultSystemId = $defaultSystem->id();
        }

        $form['promptContainer'] = [
            '#type' => 'details',
            '#open' => true,
            '#title' => t('Prompt details'),
            '#attributes' => [
                'class' => ['sprowt-ai-prompt-container']
            ]
        ];

        $form['promptContainer']['system'] = [
            '#type' => 'select',
            '#title' => t('System user'),
            '#description' => 'Override the default system user',
            '#options' => AiService::AiSystemOptions(),
            '#empty_option' => t('Use default'),
            '#empty_value' => '',
            '#default_value' => $options['systemId'] ?? '',
            '#attributes' => [
                'class' => ['system-field']
            ]
        ];

        $form_state->set('default_system', $defaultSystemId ?? null);

        $form['promptContainer']['prompt'] = [
            '#title' => 'Prompt',
            '#type' => 'claude_prompt',
            '#prompt_options' => $options,
            '#description' => 'For help crafting a good prompt, see this <a href="https://docs.anthropic.com/en/docs/prompt-engineering" target="_blank">documentation</a>.',
            '#default_value' => $prompt,
            '#expanded' => true
        ];

        $form_state->set('options', $form['promptContainer']['prompt']['#prompt_options']);

        $form['generateButtonWrap'] = [
            '#type' => 'container',
            '#attributes' => [
                'class' => ['button-container']
            ],
            'generate' => [
                '#type' => 'submit',
                '#value' => 'Generate',
                '#ajax' => [
                    'callback' => [$this, 'generate'],
                    'disable-refocus' => false,
                    'event' => 'click',
                    'wrapper' => 'contents-container',
                    'progress' => [
                        'type' => 'throbber',
                        'message' => t('Generating...'),
                    ],
                ],
            ]
        ];

        $form['removeGeneratedContents'] = [
            '#type' => 'container',
            '#attributes' => [
                'class' => ['hidden']
            ],
            'removeGeneratedContentKey' => [
                '#type' => 'hidden',
                '#default_value' => '',
                '#attributes' => [
                    'class' => ['remove-generated-content-key']
                ]
            ],
            'removeGeneratedContentSubmit' => [
                '#type' => 'submit',
                '#value' => 'Remove',
                '#name' => 'removeGeneratedContentSubmit',
                '#ajax' => [
                    'callback' => [$this, 'removeContent'],
                    'disable-refocus' => false,
                    'event' => 'click',
                    'wrapper' => 'contents-container',
                    'progress' => [
                        'type' => 'throbber',
                        'message' => t('Removing...'),
                    ],
                ],
                '#attributes' => [
                    'class' => ['remove-generated-content-submit']
                ]
            ]
        ];

        $form['contents'] = static::getContentOptions($form_state);

        if(!empty($insertParents)) {
            $form['insertContainer'] = [
                '#type' => 'fieldset',
                '#title' => 'Result',
                '#description' => 'Copy all or sections of the generated content above and paste it in the Result field to finalize the content that will appear in this field.',
                '#description_display' => 'before'
            ];

            $insert = $options['insertElement'];
            if(!empty($insert['#required'])) {
                $insert['#required'] = false;
            }
            if(isset($insert['#attributes']['data-prompt-inserter'])) {
                unset($insert['#attributes']['data-prompt-inserter']);
            }
            $insert['#default_value'] = $sessionData['insertValue'] ?? null;
            if(is_array($insert['#default_value'])  && isset($insert['#default_value']['value'])) {
                $insert['#default_value'] = $insert['#default_value']['value'];
            }

            $form['insertContainer']['insert'] = $insert;
            $form['insertContainer']['insert']['#attributes'] = [
                'class' => ['sprowt-ai-insert-container']
            ];
        }

        $form['actions'] = $this->actions($form, $form_state);

        $form['#attached']['library'][] = 'sprowt_ai/widget_prompt_form';

        return $form;

    }

    public function actions(array $form, FormStateInterface $form_state) {
        $sessionData = $this->getSessionData();

        $options = $sessionData['options'] ?? [];
        $insertParents = $options['insertParents'] ?? null;

        $actions = [
            '#type' => 'actions',
        ];

        $actions['savePrompt'] = [
            '#type' => 'submit',
            '#value' => $this->t('Update prompt'),
            '#name' => 'savePrompt',
            '#ajax' => [
                'callback' => [$this, 'ajaxSubmit'],
                'disable-refocus' => false,
                'event' => 'click',
            ]
        ];

        if(!empty($insertParents)) {
            $actions['savePrompt']['#value'] = $this->t('Update prompt only');
            $actions['insertContent'] = [
                '#type' => 'submit',
                '#value' => $this->t('Insert result only'),
                '#name' => 'insertContent',
                '#ajax' => [
                    'callback' => [$this, 'ajaxSubmit'],
                    'disable-refocus' => false,
                    'event' => 'click',
                ]
            ];

            $actions['saveAndInsert'] = [
                '#type' => 'submit',
                '#value' => $this->t('Update prompt and insert result'),
                '#name' => 'saveAndInsert',
                '#ajax' => [
                    'callback' => [$this, 'ajaxSubmit'],
                    'disable-refocus' => false,
                    'event' => 'click',
                ]
            ];
        }


        return $actions;
    }

    public static function isHtml($content)
    {
        $stripped = strip_tags($content);
        $isHtml = $stripped != $content;
        return $isHtml;
    }

    public static function getContentOptions(FormStateInterface $form_state): array
    {
        $form = [
            '#type' => 'fieldset',
            '#title' => 'Generated Content Clipboard',
            '#attributes' => [
                'class' => ['contents-container'],
                'id' => 'contents-container',
            ],
        ];

        $data = static::getSessionDataStatic();
        if(!empty($data['usage'])) {
            $usage = $data['usage'];
            $form['usage'] = [
                '#type' => 'fieldset',
                '#title' => 'Api Usage'
            ];
            $form['usage']['inputTokens'] = [
                '#type' => 'item',
                '#title' => 'Input tokens',
                '#markup' => $usage['input_tokens'] ?? '--'
            ];
            $form['usage']['outputTokens'] = [
                '#type' => 'item',
                '#title' => 'Output tokens',
                '#markup' => $usage['output_tokens'] ?? '--'
            ];
        }
        $content = $data['contents'] ?? [];
        foreach($content as $key => $item) {
            if(is_array($item)) {
                $item = array_shift($item);
            }
            $isHtml = static::isHtml($item);
            if($isHtml) {
                $markup = Markup::create('<div class="generated-content-content" data-generated-content="'.$key.'">' . $item . '</div>');
            }
            else {
                $markup = Markup::create('<div class="generated-content-content" data-generated-content="'.$key.'"><pre>' . $item . '</pre></div>');
            }
            $form['generated-content--' . $key] = [
                '#type' => 'container',
                '#attributes' => [
                    'class' => ['generated-content'],
                    'data-content-key' => $key
                ],
                'content' => [
                    '#type' => 'markup',
                    '#markup' => $markup,
                ],
                'buttons' => [
                    '#type' => 'container',
                    '#attributes' => [
                        'class' => ['button-container'],
                    ],
                    'useButton' . $key => [
                        '#type' => 'html_tag',
                        '#value' => 'Use this content',
                        '#tag' => 'button',
                        '#attributes' => [
                            'class' => ['button', 'use-button'],
                            'data-generated-content-key' => $key,
                            'type' => 'button'
                        ]
                    ],
                    'removeButton' . $key => [
                        '#type' => 'html_tag',
                        '#tag' => 'button',
                        '#value' => 'Remove',
                        '#attributes' => [
                            'class' => ['button', 'remove-button', 'action-link', 'action-link--icon-trash', 'action-link--danger'],
                            'data-generated-content-key' => $key,
                        ],
                    ]
                ],
            ];
        }
        return $form;
    }

    public static function removeContent(array $form, FormStateInterface $form_state)
    {
        $sessionData = static::getSessionDataStatic();
        $contents = $sessionData['contents'] ?? [];
        $key = $form_state->getValue('removeGeneratedContentKey');
        if(isset($contents[$key]) && $key !== '') {
            unset($contents[$key]);
            $sessionData['contents'] = $contents;
            static::saveSessionData($sessionData);
        }
        Html::setIsAjax(true);
        $form_state->setRebuild();
        return static::getContentOptions($form_state);
    }

    public static function generate(array $form, FormStateInterface $form_state)
    {
        $messenger = \Drupal::messenger();
        $messages = $messenger->all();
        $data = static::getSessionDataStatic();
        $contents = $data['contents'] ?? [];
        /** @var AiService $aiService */
        $aiService = \Drupal::service('sprowt_ai.service');
        $options = $form_state->get('options');
        $defaultSystemId = $form_state->get('default_system');
        $systemId = $form_state->getValue('system');
        $prompt = $form_state->getValue('prompt');
        $options['systemId'] = $systemId;
        if(empty($options['systemId'])) {
            $options['systemId'] = $defaultSystemId;
        }
        $content = $aiService->generateContent($prompt, $options);
        if(!empty($content)) {
            $contents[] = array_shift($content);
        }
        $data['contents'] = $contents;
        $state = \Drupal::state();
        $lastUsage = $state->get('claude3.last_usage') ?? [];
        $currentUsage = $data['usage'] ?? [];
        $currentUsage['input_tokens'] = ($currentUsage['input_tokens'] ?? 0) + ($lastUsage['input_tokens'] ?? 0);
        $currentUsage['output_tokens'] = ($currentUsage['output_tokens'] ?? 0) + ($lastUsage['output_tokens'] ?? 0);
        $data['usage'] = $currentUsage;
        static::saveSessionData($data);
        Html::setIsAjax(true);
        $form_state->setRebuild();
        return static::getContentOptions($form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        // @todo Validate the form here.
        // Example:
        // @code
        //   if (mb_strlen($form_state->getValue('message')) < 10) {
        //     $form_state->setErrorByName(
        //       'message',
        //       $this->t('Message should be at least 10 characters.'),
        //     );
        //   }
        // @endcode
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        //nothing here
    }

    protected function closeDialogCommand(array $form, FormStateInterface $form_state)
    {
        return new CloseDialogCommand('.unexpanded-prompt-dialog > .ui-dialog-content');
    }

    protected function savePromptCommand(array $form, FormStateInterface $form_state)
    {
        $prompt = $form_state->getValue('prompt');
        $options = $form_state->get('options');
        return new InvokeCommand(ClaudePrompt::fieldSelector($options), 'trigger', ['savePrompt', [$prompt]]);
    }

    protected function insertResultCommand(FormStateInterface $form_state)
    {
        $result = $form_state->getValue('insert');
        if(is_array($result)) {
            if(!empty($result['value'])) {
                $result = $result['value'];
            }
            else {
                $result = '';
            }
        }
        $result = $result ?? '';
        $options = $form_state->get('options');
        return new InvokeCommand(ClaudePrompt::fieldSelector($options), 'trigger', ['insertResult', [$result]]);
    }

    protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state)
    {
        $trigger = $form_state->getTriggeringElement();
        $response = new AjaxResponse();
        switch($trigger['#name']) {
            case 'savePrompt':
                $response->addCommand($this->savePromptCommand($form, $form_state));
                break;
            case 'insertContent':
                $response->addCommand($this->insertResultCommand($form_state));
                break;
            case 'saveAndInsert':
                $response->addCommand($this->savePromptCommand($form, $form_state));
                $response->addCommand($this->insertResultCommand($form_state));
                break;
        }
        $response->addCommand($this->closeDialogCommand($form, $form_state));

        return $response;
    }
}
