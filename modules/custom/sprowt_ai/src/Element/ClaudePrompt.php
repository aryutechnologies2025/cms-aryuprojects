<?php

namespace Drupal\sprowt_ai\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ScrollTopCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;
use Drupal\sprowt_ai\AiService;
use Drupal\sprowt_ai\Claude3Service;
use Drupal\sprowt_ai\Entity\AiSystem;
use Drupal\sprowt_ai\Form\GeneratedContentModalForm;
use Drupal\sprowt_ai\Form\UnexpandedPromptForm;

/**
 * Provides a render element to display a claude prompt.
 *
 * Properties:
 * - #foo: Property description here.
 *
 * Usage Example:
 * @code
 * $build['claude_prompt'] = [
 *   '#type' => 'claude_prompt',
 *   '#foo' => 'Some value.',
 * ];
 * @endcode
 *
 * @\Drupal\Core\Render\Annotation\FormElement("claude_prompt")
 */
class ClaudePrompt extends FormElement
{

    /**
     * {@inheritdoc}
     */
    public function getInfo(): array
    {
        $class = static::class;
        return [
            '#input' => true,
            '#element_validate' => [
                [
                    $class,
                    'validateElement',
                ],
            ],
            '#process' => [
                [$class, 'processPromptField'],
                [
                    $class,
                    'processGroup',
                ],
            ],
            '#pre_render' => [
                [
                    $class,
                    'preRenderGroup',
                ],
            ],
            '#wrapper_attributes' => [
                'class' => ['claude-prompt']
            ],
            '#description_display' => 'before',
            '#title_display' => 'before',
            '#theme_wrappers' => [
                'form_element',
            ],
            '#value_callback' => [$this, 'valueCallback'],
            '#insert' => null,
            '#system' => null,
            '#max_tokens' => 1024,
            '#mediaTypes' => ['document'],
            '#prompt_options' => [],
            '#expanded' => false,
            '#legacyGenerate' => false,
            '#references' => []
        ];
    }

    public static function processPromptField(&$element, FormStateInterface $formState, &$form)
    {
        $disable = $formState->get('disableClaudeBuild') ?? false;
        if($disable) {
            return $element;
        }
        $defaultValue = $element['#default_value'] ?? '';
        $element['#tree'] = true;

        $system = null;
        if(!empty($element['#system'])) {
            if($element['#system'] instanceof AiSystem) {
                $system = $element['#system'];
            }
            elseif(is_numeric($element['#system'])
                || preg_match('/^[\d]+$/', $element['#system'])
            ) {
                $system = AiSystem::load($element['#system']);
            }
            else {
                $system = AiSystem::loadByUuid($element['#system']);
            }
        }
        $insert = $element['#insert'] ?? [];

        $options = isset($element['#prompt_options']) && is_array($element['#prompt_options']) ? $element['#prompt_options'] : [];

        $options = array_merge([
            'insertParents' => $insert,
            'id' => $element["#attributes"]["data-drupal-selector"],
            'max_tokens' => $element['#max_tokens'] ?? 1024,
        ], $options);


        if(!empty($insert)) {
            $keyExists = false;
            $insertField = NestedArray::getValue($form, $insert, $keyExists);
            if($keyExists) {
                $insertField['#attributes']['data-prompt-inserter'] = $options['id'];
                NestedArray::setValue($form, $insert, $insertField);
                $formObj = $formState->getFormObject();
                $formStateCopy = clone $formState;
                $formStateCopy->set('disableClaudeBuild', true);
                $rawForm = \Drupal::formBuilder()->retrieveForm(get_class($formObj), $formStateCopy);
                $insertElement = NestedArray::getValue($rawForm, $insert);

                $options['insertSelector'] = '[data-prompt-inserter="'.$options['id'].'"]';
                $options['insertElement'] = $insertElement;
            }
        }

        $element['#wrapper_attributes']['data-prompt-field-selector'] = $options['id'];

        if($system instanceof AiSystem) {
            $options['systemId'] = $system->id();
        }

        $element['options'] = [
            '#type' => 'hidden',
            '#value' => json_encode($options),
            '#attributes' => [
                'class' => ['options-value']
            ]
        ];

        $element['#prompt_options'] = $options;

        if(!empty($options['references'])) {
            $element['#references'] = $options['references'];
        }

        $element['references'] = [
            '#type' => 'hidden',
            '#default_value' => !empty($element['#references']) ? json_encode($element['#references']) : '{}',
            '#attributes' => [
                'class' => ['references-value']
            ]
        ];

        $expanded = $element['#expanded'] ?? false;
        if($expanded) {
            $element = static::processPromptFieldExpanded($element, $formState, $form);
        }
        else {
            $element = static::processPromptFieldUnExpanded($element, $formState, $form);
        }

        return $element;
    }

    public static function processPromptFieldUnExpanded(&$element, FormStateInterface $formState, &$form)
    {
        $options = $element['#prompt_options'];
        $defaultValue = $element['#default_value'] ?? '';
        $fieldset = [
            '#type' => 'fieldset',
            '#title' => 'Current prompt value',
        ];

        $fieldset['dialogButton'] = [
            '#type' => 'button',
            '#value' => t('Edit prompt'),
            '#attributes' => [
                'data-selector' => static::fieldSelector($options),
                'class' => ['button', 'unexpanded-prompt-button', 'button--extrasmall'],
            ],
            '#ajax' => [
                'event' => 'click',
                'callback' => [static::class, 'openUnexpandedDialog']
            ],
            '#validate' => [[static::class, 'limitValidationErrors']]
        ];

        if(!empty($options['insertParents'])) {
            $element['dialogButton']['#value'] = t('Generate content');
        }

        $fieldset['#description'] = 'The current prompt. This field is read only. Click "'. $fieldset['dialogButton']['#value'].'" to edit.';

        $fieldset['textarea'] = [
            '#type' => 'textarea',
            '#default_value' => $defaultValue,
            '#required' => !empty($element['#required']),
            '#attributes' => [
                'class' => ['unexpanded-prompt-text'],
                'readonly' => 'readonly'
            ],
            '#wrapper_attributes' => [
                'class' => ['form-item--disabled']
            ]
        ];

        $element['promptValueWrapper'] = $fieldset;

        $element['#attached']['library'][] = 'sprowt_ai/unexpanded_widget';
        return $element;

    }

    public static function processPromptFieldExpanded(&$element, FormStateInterface $formState, &$form)
    {
        $element['#wrapper_attributes']['class'][] = 'expanded';
        $options = $element['#prompt_options'];
        $defaultValue = $element['#default_value'] ?? '';
        $element['insert_examples'] = [
            '#type' => 'link',
            '#title' => 'Insert example(s) into prompt',
            '#url' => Url::fromRoute('sprowt_ai.choose_example')->setOption('query', ['element_id' => $options['id']]),
            '#attributes' => [
                'class' => ['button', 'insert-examples']
            ],
            '#ajax' => [
                'dialogType' => 'modal',
                'dialog' => [
                    'height' => 800,
                    'width' => 900,
                    'classes' => [
                        'ui-dialog' => 'ui-corner-all ui-widget ui-widget-content ui-front ui-dialog-buttons choose-examples-dialog'
                    ],
                    'autoResize' => false,
                    'resizable' => true,
                    'draggable' => true
                ],
            ],
        ];

        $element['insert_documents'] = static::mediaLibraryButton($element, $options);

        $element['insert_contexts'] = [
            '#type' => 'link',
            '#title' => 'Insert context(s) into prompt',
            '#url' => Url::fromRoute('sprowt_ai.choose_context')->setOption('query', ['element_id' => $options['id']]),
            '#attributes' => [
                'class' => ['button', 'insert-examples']
            ],
            '#ajax' => [
                'dialogType' => 'modal',
                'dialog' => [
                    'height' => 800,
                    'width' => 900,
                    'classes' => [
                        'ui-dialog' => 'ui-corner-all ui-widget ui-widget-content ui-front ui-dialog-buttons choose-contexts-dialog'
                    ],
                    'autoResize' => false,
                    'resizable' => true,
                    'draggable' => true
                ],
            ],
        ];

        $referenceId = $options['id'] ?? '';
        $referenceId .= '--references-form';
        $element['referencesButton'] = [
            '#type' => 'button',
            '#value' => 'Insert reference',
            '#ajax' => [
                'callback' => [static::class, 'insertReferenceAjax'],
                'progress' => [
                    'type' => 'throbber',
                    'message' => 'Please wait...',
                ],
            ],
            '#name' => $referenceId,
            '#attributes' => [
                'class' => ['button', 'reference-button'],
            ],
            '#limit_validation_errors' => []
        ];
        if(empty($element['#references'])) {
            $element['referencesButton']['#attributes']['class'][] = 'hidden';
        }

        $element['textarea'] = [
            '#type' => 'textarea',
            '#required' => !empty($element['#required']),
            '#default_value' => $defaultValue,
            '#attributes' => [
                'class' => ['claude-prompt-textarea']
            ]
        ];
        $element['attachedEntities'] = static::attachedEntitiesElement($options, $defaultValue);

        if(!empty($options['insertSelector']) && !empty($element['#legacyGenerate'])) {
            $generateId = $options['id'] ?? '';
            $generateId .= '--generate-content';

            $element['generate_content'] = [
                '#type' => 'button',
                '#value' => 'Generate content',
                '#ajax' => [
                    'callback' => [static::class, 'generateContentAjax'],
                    'progress' => [
                        'type' => 'throbber',
                        'message' => 'Generating...',
                    ],
                ],
                '#name' => $generateId,
                '#attributes' => [
                    'class' => ['button', 'generate-content'],
                ],
                '#validate' => [[static::class, 'limitValidationErrors']]
            ];

            $element['generate_content_modal'] = [
                '#type' => 'link',
                '#title' => 'Hidden link',
                '#url' => Url::fromRoute('sprowt_ai.generate_content')->setOption('query', ['element_id' => $options['id']]),
                '#attributes' => [
                    'class' => ['button', 'hidden', 'generate-content']
                ],
                '#ajax' => [
                    'dialogType' => 'modal',
                    'dialog' => [
                        'height' => 900,
                        'width' => 700,
                        'classes' => [
                            'ui-dialog' => 'ui-corner-all ui-widget ui-widget-content ui-front ui-dialog-buttons generate-content-dialog'
                        ]
                    ],
                ],
                '#prefix' => '<div class="hidden">',
                '#suffix' => '</div>'
            ];
        }

        $element['addAttachedEntities'] = [
            '#type' => 'button',
            '#value' => 'Attach entities hidden button',
            '#ajax' => [
                'callback' => [static::class, 'attachEntities'],
                'progress' => [
                    'type' => 'throbber',
                    'message' => 'Generating...',
                ],
                'wrapper' => $options['id'] . '--attached-entities',
            ],
            '#name' => $options['id'] . '--attached-entities',
            '#attributes' => [
                'class' => ['button', 'attach-entities'],
            ],
            '#prefix' => '<div class="hidden">',
            '#suffix' => '</div>',
            '#validate' => [[static::class, 'limitValidationErrors']]
        ];

        if(!empty($element['#id'])) {
            $element['#wrapper_attributes']['id'] = $element['#id'];
        }

        $element['#attached']['library'][] = 'sprowt_ai/claude-prompt-element';
        return $element;
    }

    public static function limitValidationErrors(&$form, FormStateInterface $form_state) {
        $error = $form_state->getErrors();
        if(!empty($error)) {
            $form_state->clearErrors();
        }
    }

    public static function openUnexpandedDialog(array $form, FormStateInterface $form_state)
    {
        $triggeringEl = $form_state->getTriggeringElement();
        $parent = $triggeringEl['#parents'];
        array_pop($parent); //get parent parents
        array_pop($parent); //get parent parent parents
        $input = $form_state->getUserInput();
        $elementInput = NestedArray::getValue($input, $parent);
        if(isset($elementInput['promptValueWrapper']['textarea'])) {
            $promptText = $elementInput['promptValueWrapper']['textarea'];
        }
        else {
            $promptText = $elementInput['textarea'];
        }
        $options = json_decode($elementInput['options'] ?? '{}', true);
        if(!empty($options['insertParents'])) {
            $insertValue = $form_state->getValue($options['insertParents']);
        }
        $selector = static::fieldSelector($options);
        $refResponse = AiService::updateEntityReferencesAjax($form, $form_state);
        $references = [];
        foreach ($refResponse->getCommands() as $command) {
            if($command instanceof InvokeCommand) {
                $render = $command->render();
                if($render['method'] == 'trigger') {
                    $args = $render['arguments'];
                    $event = $args[0];
                    if($event == 'updateEntityReferences') {
                        $references = array_shift($args[1]);
                        $preprompt = array_shift($args[1]);
                    }
                }
            }
        }
        $options['references'] = $references;
        if(!empty($preprompt)) {
            $options['preprompt'] = $preprompt;
        }


        $sessionData = [
            'options' => $options,
            'prompt' => $promptText,
            'insertValue' => $insertValue ?? null,
        ];
        UnexpandedPromptForm::saveSessionData($sessionData);
        $response = new AjaxResponse();
        $response->addCommand(new InvokeCommand($selector, 'trigger', ['openUnexpandedForm']));
        return $response;
    }

    public static function mediaLibraryButton($element, $options) {
        $allowedMediaTypes = $element['#mediaTypes'] ?? ['document'];
        $selectedMediaType = $options['mediaType'] ?? reset($allowedMediaTypes);
        $openerId = 'sprowt_ai.media_library_opener';

        $state = MediaLibraryState::create($openerId, $allowedMediaTypes, $selectedMediaType, -1, [
            'element_id' => $options['id']
        ]);
        return [
            '#type' => 'button',
            '#value' => 'Insert document(s) into prompt',
            '#name' => $options['id'] . '--media-library-open-button',
            '#attributes' => [
                'class' => [
                    'js-media-library-open-button',
                ],
            ],
            '#media_library_state' => $state,
            '#ajax' => [
                'callback' => [MediaLibraryWidget::class, 'openMediaLibrary'],
                'progress' => [
                    'type' => 'throbber',
                    'message' => 'Opening media library.',
                ],
            ],
            // Allow the media library to be opened even if there are form errors.
            '#limit_validation_errors' => [],
        ];
    }

    public static function validateElement(array &$element, FormStateInterface $formState)
    {
        //set form_state value to element value
        $value = $element['#value'] ?? null;
        $formState->setValue($element['#parents'], $value);
    }

    public static function insertReferenceAjax(array $form, FormStateInterface $form_state)
    {
        $triggeringEl = $form_state->getTriggeringElement();
        $parent = $triggeringEl['#parents'];
        array_pop($parent); //get parent parents
        $input = $form_state->getUserInput();
        $elementInput = NestedArray::getValue($input, $parent);
        $references = $elementInput['references'];
        if(is_string($references)) {
            $references = json_decode($references, true);
        }
        $options = json_decode($elementInput['options'] ?? '{}', true);
        $tempStore = \Drupal::service('tempstore.private')->get('sprowt_ai');
        $tempStore->set('sprowt_ai_tmp_references', [
            'selector' => static::fieldSelector($options),
            'references' => $references,
        ]);
        $response = new AjaxResponse();
        $response->addCommand(new InvokeCommand(static::fieldSelector($options),
            'trigger',
            [
                'openReferenceModal',
            ]
        ));
        return $response;
    }

    public static function generateContentAjax(array $form, FormStateInterface $form_state) {
        $triggeringEl = $form_state->getTriggeringElement();
        $parent = $triggeringEl['#parents'];
        array_pop($parent); //get parent parents
        $input = $form_state->getUserInput();
        $elementInput = NestedArray::getValue($input, $parent);
        $promptText = $elementInput['textarea'];

        $response = new AjaxResponse();
        if(empty($promptText)) {
            $response->addCommand(
                new MessageCommand("No prompt text provided so no content generated.", null, [
                    'type' => 'warning',
                ])
            );

            $response->addCommand(new ScrollTopCommand('body'));

            return $response;
        }

        $options = json_decode($elementInput['options'] ?? '{}', true);
        /** @var Claude3Service $service */
        $service = \Drupal::service('sprowt_ai.claude_3');
        $return = $service->generateContent($promptText, $options);
        if(!empty($return['error'])) {
            $response->addCommand(
                new MessageCommand("Error fetching data from api", null, [
                    'type' => 'error',
                ])
            );
            $response->addCommand(new ScrollTopCommand('body'));
            return $response;
        }


        $contents = $service->extractContentsFromReturn($return);


        $sessionKey = 'claude3_generated_content';
        $session = \Drupal::service('session');
        $generatedContent = [
            'contents' => $contents,
            'options' => $options,
            'promptText' => $promptText,
            'usage' => $return['usage'] ?? []
        ];
        $session->set($sessionKey, $generatedContent);


        $response->addCommand(new InvokeCommand(
            static::fieldSelector($options),
            'trigger',
            [
                'claude3GenerateContentResponse',
                [$return]
            ]
        ));
        return $response;
    }

    public static function valueCallback(&$element, $input, FormStateInterface $form_state)
    {
        if(isset($input["promptValueWrapper"]["textarea"])) {
            return $input["promptValueWrapper"]["textarea"];
        }
        if(isset($input['textarea'])) {
            return $input['textarea'];
        }
        return '';
    }

    public static function fieldSelector($options)
    {
        return '.claude-prompt[data-prompt-field-selector="' . $options['id'] . '"]';
    }

    public static function attachEntities(array $form, FormStateInterface $form_state) {
        $triggeringEl = $form_state->getTriggeringElement();
        $parent = $triggeringEl['#parents'];
        array_pop($parent); //get parent parents
        $input = $form_state->getUserInput();
        $elementInput = NestedArray::getValue($input, $parent);
        $options = json_decode($elementInput['options'] ?? '{}', true);
        $promptText = $elementInput['textarea'];

        $wrapperId = $triggeringEl["#ajax"]["wrapper"];
        $response = new AjaxResponse();

        $content = static::attachedEntitiesElement($options, $promptText);
        $response->addCommand(new HtmlCommand('#' . $wrapperId, $content));
        $response->addCommand(new InvokeCommand(
            static::fieldSelector($options),
            'trigger',
            [
                'claude3AttachEntities',
                []
            ]
        ));

        return $response;
    }

    public static function attachedEntitiesElement($options, $promptText) {
        /** @var AiService $service */
        $service = \Drupal::service('sprowt_ai.service');
        $return = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'id' => $options['id'] . '--attached-entities',
                'class' => ['attached-entities-wrap']
            ]
        ];
        $entityUuids = [];
        if(!empty($promptText)) {
            $entityArray = $service->extractEntitiesFromPromptText($promptText);
            if(!empty($entityArray)) {
                $links = [
                    '#type' => 'item',
                    '#title' => 'Inserted Entities',
                    'details' => [
                        '#type' => 'html_tag',
                        '#tag' => 'ul',
                        '#attributes' => [
                            'class' => ['claude-attached-entities']
                        ]
                    ]
                ];

                foreach ($entityArray as $entityType => $entities) {
                    /** @var EntityInterface $entity */
                    foreach ($entities as $entity) {
                        $entityUuids[] = $entity->uuid();
                        $url = $entity->toUrl('edit-form');
                        $link = [
                            '#type' => 'link',
                            '#title' => Markup::create($entity->label() . " <span class='uuid'>[{$entity->uuid()}]</span>"),
                            '#url' => $url,
                            '#options' => [
                                'attributes' => [
                                    'target' => '_blank',
                                    'data-entity-uuid' => $entity->uuid(),
                                    'data-entity-id' => $entity->id()
                                ]
                            ]
                        ];
                        $links['details']['li--' . $entity->uuid()] = [
                            '#type' => 'html_tag',
                            '#tag' => 'li',
                        ];
                        $links['details']['li--' . $entity->uuid()]['link'] = $link;
                    }
                }
                $return['links'] = $links;
            }
        }


        return $return;
    }

}
