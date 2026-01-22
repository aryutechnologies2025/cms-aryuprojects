<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai\AiService;
use Drupal\sprowt_ai\Entity\AiSystem;

/**
 * Provides a Sprowt AI form.
 */
class WidgetPromptForm extends FormBase
{

    use AjaxFormHelperTrait;

    /** @var ContentEntityBase */
    protected $entity;

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_widget_prompt';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $widgetKey = null): array
    {

        $form['#attributes']['data-whole-form-selector'] = $widgetKey;
        $form['#attributes']['class'][] = 'widget-prompt-form';
        $tempStore = \Drupal::service('tempstore.private')->get('sprowt_ai');
        $data = $tempStore->get('prompt_data.' . $widgetKey) ?? [];
        $this->data = $data;
        $widgetKeyParts = explode('__', $widgetKey);
        $this->entityType = $widgetKeyParts[0];
        $this->fieldName = $widgetKeyParts[1];
        $this->delta = $widgetKeyParts[2];
        $this->entityId = $widgetKeyParts[3] ?? null;
        if(!empty($this->entityId)) {
            $this->entity = \Drupal::entityTypeManager()->getStorage($this->entityType)->load($this->entityId);
        }
        else {
            $definition =\Drupal::entityTypeManager()->getDefinition($this->entityType);
            $uuidKey = $definition->getKey('uuid');
            $createArray = [
                $uuidKey => $data['entityUuid']
            ];
            if(!empty($this->data['entityBundle'])) {
                $bundleKey = $definition->getKey('bundle');
                $createArray[$bundleKey] = $this->data['entityBundle'];
            }
            $this->entity = \Drupal::entityTypeManager()->getStorage($this->entityType)->create($createArray);
        }

        $property = $this->data['fieldProperty'] ?? 'value';

        $form_state->set('entity', $this->entity);

        $list = $this->entity->get($this->fieldName);
        $definition = $list->getFieldDefinition();
        $storageDefinition = $definition->getFieldStorageDefinition();
        $settings = $definition->getThirdPartySettings('sprowt_ai');
        /** @var EntityDisplayRepository $displayRepo */
        $displayRepo = \Drupal::service('entity_display.repository');
        $display = $displayRepo->getFormDisplay($this->entityType, $this->data['entityBundle'], 'default');
        $widget = $display->getRenderer($this->fieldName);

        $defaultSystemId = null;
        $defaultSystem = sprowt_ai_default_system($this->entity);
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
            '#title' => t('Override system user'),
            '#description' => 'Override the system user',
            '#options' => AiService::AiSystemOptions(),
            '#empty_option' => t('Use default'),
            '#empty_value' => '',
            '#default_value' => $data['systemId'] ?? '',
            '#attributes' => [
                'class' => ['system-field']
            ]
        ];

        $options = $data['options'] ?? [];

        $maxLength = $storageDefinition->getSetting('max_length') ?? 0;
        if ($maxLength > 0) {
            $options['charLimit'] = $maxLength;
        }

        if(empty($options['charLimit'])) {
            $schema = $storageDefinition->getSchema();
            $columns = $schema['columns'] ?? [];
            $propertyColumn = $columns[$property] ?? [];
            if (!empty($propertyColumn['length'])) {
                $options['charLimit'] = $propertyColumn['length'];
            }
        }

        $form['promptContainer']['prompt'] = [
            '#title' => 'Prompt',
            '#type' => 'claude_prompt',
            '#max_tokens' => $settings['details']['max_tokens'] ?? 1024,
            '#system' => $data['systemId'] ?? $defaultSystemId ?? null,
            '#prompt_options' => $options,
            '#description' => $settings['details']['description'] ?? 'For help crafting a good prompt, see this <a href="https://docs.anthropic.com/en/docs/prompt-engineering" target="_blank">documentation</a>.',
            '#default_value' => $data['prompt'] ?? null,
            '#expanded' => true
        ];



        $form_state->set('options', $form['promptContainer']['prompt']['#prompt_options']);
        $form_state->set('default_system', $defaultSystemId ?? null);
        $form_state->set('widgetKey', $widgetKey);

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
        $form['insertContainer'] = [
            '#type' => 'fieldset',
            '#title' => 'Result',
            '#description' => 'Copy all or sections of the generated content above and paste it in the Result field to finalize the content that will appear in this field.',
            '#description_display' => 'before'
        ];
        $list = $this->entity->get($this->fieldName);
        $element = [];
        $elementForm = [];
        while(!isset($list[$this->delta])) {
            $list->appendItem();
        }
        $widgetForm = $widget->formElement($list, $this->delta, $element, $elementForm, $form_state);


        $form['insertContainer']['insert'] = [];
        if(!empty($widgetForm[$property])) {
            $form['insertContainer']['insert'][$property] = $widgetForm[$property];
        }
        else {
            $form['insertContainer']['insert'][$property] = $widgetForm;
        }
        $form['insertContainer']['insert']['#tree'] = true;
        $form['insertContainer']['insert'][$property]['#default_value'] = $this->data['widgetValue'] ?? null;
        $form['insertContainer']['#attributes'] = [
            'class' => ['sprowt-ai-insert-container']
        ];

        $form['actions'] = $this->actions($form, $form_state);

        $form['#attached']['library'][] = 'sprowt_ai/widget_prompt_form';

        return $form;
    }

    public function actions(array $form, FormStateInterface $form_state) {
        $actions = [
            '#type' => 'actions',
        ];

        $actions['savePrompt'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save prompt only'),
            '#name' => 'savePrompt',
            '#ajax' => [
                'callback' => [$this, 'ajaxSubmit'],
                'disable-refocus' => false,
                'event' => 'click',
            ]
        ];

        $actions['insertContent'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save content only'),
            '#name' => 'insertContent',
            '#ajax' => [
                'callback' => [$this, 'ajaxSubmit'],
                'disable-refocus' => false,
                'event' => 'click',
            ]
        ];

        $actions['saveAndInsert'] = [
          '#type' => 'submit',
            '#value' => $this->t('Save all'),
            '#name' => 'saveAndInsert',
            '#ajax' => [
                'callback' => [$this, 'ajaxSubmit'],
                'disable-refocus' => false,
                'event' => 'click',
            ]
        ];

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
        $widgetKey = $form_state->get('widgetKey');
        $tempStore = \Drupal::service('tempstore.private')->get('sprowt_ai');
        $data = $tempStore->get('prompt_data.' . $widgetKey) ?? [];
        $content = $data['contents'] ?? [];
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
        $widgetKey = $form_state->get('widgetKey');
        $tempStore = \Drupal::service('tempstore.private')->get('sprowt_ai');
        $data = $tempStore->get('prompt_data.' . $widgetKey) ?? [];
        $contents = $data['contents'] ?? [];
        $key = $form_state->getValue('removeGeneratedContentKey');
        if(isset($contents[$key]) && $key !== '') {
            unset($contents[$key]);
            $data['contents'] = $contents;
            $tempStore->set('prompt_data.' . $widgetKey, $data);
        }
        Html::setIsAjax(true);
        $form_state->setRebuild();
        return static::getContentOptions($form_state);
    }

    public static function generate(array $form, FormStateInterface $form_state)
    {
        $widgetKey = $form_state->get('widgetKey');
        $tempStore = \Drupal::service('tempstore.private')->get('sprowt_ai');
        $data = $tempStore->get('prompt_data.' . $widgetKey) ?? [];
        $contents = $data['contents'] ?? [];
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
        $tempStore->set('prompt_data.' . $widgetKey, $data);
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
        //do nothing
    }

    protected function closeDialogCommand(array $form, FormStateInterface $form_state)
    {
        return new CloseDialogCommand('.widget-prompt-dialog > .ui-dialog-content');
    }

    protected function savePromptCommand(array $form, FormStateInterface $form_state)
    {
        $prompt = $form_state->getValue('prompt');
        $systemId = $form_state->getValue('system');
        return new InvokeCommand($this->data['selector'], 'trigger', ['savePrompt', [$prompt, $systemId]]);
    }

    protected function insertResultCommand(FormStateInterface $form_state)
    {
        $result = $form_state->getValue('insert');
        $property = $this->data['fieldProperty'];
        if(is_array($result)) {
            if(!empty($result[$property])) {
                $result = $result[$property];
            }
            else {
                $result = '';
            }
        }

        if(is_array($result)) {
            if(!empty($result[$property])) {
                $result = $result[$property];
            }
            else {
                $result = '';
            }
        }
        $result = $result ?? '';
        return new InvokeCommand($this->data['selector'], 'trigger', ['insertResult', [$result]]);
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
