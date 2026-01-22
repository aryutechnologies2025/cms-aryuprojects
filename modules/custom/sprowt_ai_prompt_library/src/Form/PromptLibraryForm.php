<?php declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManager;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai\Element\ClaudePrompt;
use Drupal\sprowt_ai_prompt_library\AiPromptLibraryService;
use Drupal\sprowt_ai_prompt_library\Entity\Prompt;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Provides a Sprowt AI prompt library form.
 */
final class PromptLibraryForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_prompt_library_prompt_library';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $options = [];
        $sourceOptions = [];
        $map = \Drupal::service('sprowt_ai_prompt_library.service')->promptMap();

        $pagerLimit = 2;

        $pages = array_chunk($map, $pagerLimit);
        $currentPage = $_GET['page'] ?? 1;
        $tagOptions = [];
        foreach ($pages as $delta => $pageMap) {
            $page = $delta + 1;
            foreach ($pageMap as $item) {
                $key = implode('::', [
                    $item['source'],
                    $item['uuid']
                ]);

                $truncateTextByWords = function ($text, $length = 100) {
                    $text = strip_tags($text);
                    $text = preg_replace('/\s+/', ' ', $text);
                    $words = explode(' ', $text, $length + 1);
                    if (count($words) > $length) {
                        array_pop($words);
                        $text = implode(' ', $words) . '...';
                    }
                    return $text;
                };

                $truncate = function($text) use ($truncateTextByWords){
                    $truncated = $truncateTextByWords($text, 15);
                    return Markup::create("<span title='{$text}'>{$truncated}</span>");
                };

                $tagStr = '';
                if(!empty($item['tags'])) {
                    $tagStr = implode(', ', $item['tags']);
                    foreach ($item['tags'] as $tagName) {
                        $tagOptions[$tagName] = $tagName;
                    }
                }

                $row = [
                    '#attributes' => [
                        'class' => ['hidden'],
                    ],
                    'source' => [
                        'data-filter-key' => 'source',
                        'data' => $item['source']
                    ],
                    'label' => [
                        'data-filter-key' => 'label',
                        'data' => $truncate($item['label'])
                    ],
                    'description' => [
                        'data-filter-key' => 'description',
                        'data' => $truncate($item['description'])
                    ],
                    'tags' => [
                        'data-filter-key' => 'tag',
                        'data' => $truncate($tagStr)
                    ]
                ];
                if(!in_array($item['source'], $sourceOptions)) {
                    $sourceOptions[$item['source']] = $item['source'];
                }

                $options[$key] = $row;
            }
        }

        $form['description'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>This is a list of all the prompts that are available in the prompt library. Select the prompt you want to add and click apply to add it.</p>'),
        ];

        $headers = [
            'source' => 'Source',
            'label' => 'Label',
            'description' => 'Description',
            'tags' => 'Tags'
        ];

        asort($tagOptions);

        /** @var Session $session */
        $session = \Drupal::service('session');
        $outPutAsTokenDefault = $session->get('promptLibraryForm::outputAsToken', null);
        if(isset($outPutAsTokenDefault)) {
            $session->remove('promptLibraryForm::outputAsToken');
        }
        else {
            $outPutAsTokenDefault = true;
        }


        $form['outputAsToken'] = [
            '#type' => 'checkbox',
            '#title' => 'Output the prompt as a token',
            '#description' => 'Will insert a token representing the backend prompt in the library. This way you can edit it in one place rather than per field.',
            '#default_value' => $outPutAsTokenDefault
        ];

        $form['filtersWrap'] = [
            '#type' => 'fieldset',
            'filterBySource' => [
                '#type' => 'select',
                '#title' => 'Filter by source',
                '#options' => $sourceOptions
            ],
            'filterByLabel' => [
                '#type' => 'textfield',
                '#title' => 'Filter by label',
            ],
            'filterByDescription' => [
                '#type' => 'textfield',
                '#title' => 'Filter by description',
            ],
            'filterByTag' => [
                '#type' => 'select',
                '#title' => 'Filter by Tag',
                '#options' => $tagOptions,
                '#empty_option' => '- Select -',
                '#empty_value' => '_none',
            ],
            '#attributes' => [
                'class' => ['filters-wrap']
            ]
        ];

        $form['prompt'] = [
            '#type' => 'tableselect',
            '#header' => $headers,
            '#options' => $options,
            '#empty' => $this->t('No prompts found.'),
            '#multiple' => false,
            '#js_select' => false,
            '#sticky' => true,
            '#attributes' => [
                'data-current-page' => $currentPage,
            ]
        ];
        if(count($pages) > 1) {
            $form['pager'] = [
                '#type' => 'html_tag',
                '#tag' => 'nav',
                '#attributes' => [
                    'class' => ['pager', 'js-pager'],
                ],
                'list' => [
                    '#type' => 'html_tag',
                    '#tag' => 'ul',
                    '#attributes' => [
                        'class' => ['pager__items'],
                        'data-total-pages' => count($pages),
                    ],
                ]
            ];
            $items = [
                'first' => 'First',
                'previous' => 'Previous',
            ];
//            foreach (range(1, count($pages)) as $idx => $page) {
//                $items[$page] = $page;
//            }
            $items['next'] = 'Next';
            $items['last'] = 'Last';

            foreach($items as $type => $text) {
                $liClasses = ['pager__item'];
                $anchorClasses  = ['pager__link'];
                $title = $text;
                $spanClasses = [];
                $query = 'page=' . $type;
                switch($type) {
                    case 'first':
                        $liClasses[] = 'pager__item--action';
                        $liClasses[] = 'pager__item--first';
                        $anchorClasses[] = 'pager__link--action-link';
                        $spanClasses[] = 'pager__item-title';
                        $spanClasses[] = 'pager__item-title--backwards';
                        break;
                    case 'previous':
                        $liClasses[] = 'pager__item--action';
                        $liClasses[] = 'pager__item--previous';
                        $anchorClasses[] = 'pager__link--action-link';
                        $spanClasses[] = 'pager__item-title';
                        $spanClasses[] = 'pager__item-title--backwards';
                        break;
                    case 'next':
                        $liClasses[] = 'pager__item--action';
                        $liClasses[] = 'pager__item--next';
                        $anchorClasses[] = 'pager__link--action-link';
                        $spanClasses[] = 'pager__item-title';
                        $spanClasses[] = 'pager__item-title--forward';
                        break;
                    case 'last':
                        $liClasses[] = 'pager__item--action';
                        $liClasses[] = 'pager__item--last';
                        $anchorClasses[] = 'pager__link--action-link';
                        $spanClasses[] = 'pager__item-title';
                        $spanClasses[] = 'pager__item-title--forward';
                        break;
                    default:
                        $liClasses[] = 'pager__item--number';
                        if($currentPage == $type) {
                            $liClasses[] = 'pager__item--active';
                            $anchorClasses[] = 'is-active';
                        }
                }


                $form['pager']['list']['page__' . $type] = [
                    '#type' => 'html_tag',
                    '#tag' => 'li',
                    '#attributes' => [
                        'class' => $liClasses,
                    ],
                    'link' => [
                        '#type' => 'html_tag',
                        '#tag' => 'a',
                        '#attributes' => [
                            'class' => $anchorClasses,
                            'href' => '?' . $query
                        ],
                        'title' => [
                            '#type' => 'html_tag',
                            '#tag' => 'span',
                            '#attributes' => [
                                'class' => $spanClasses
                            ],
                            '#value' => $title
                        ]
                    ]
                ];

                //hide the pager for now.
                $form['pager']['#access'] = false;
            }

        }

        if(!empty($_GET['element_id'] ?? null)) {
            $options = ['id' => $_GET['element_id']];
            $form['elementSelector'] = [
                '#type' => 'hidden',
                '#value' => ClaudePrompt::fieldSelector($options),
                '#attributes' => [
                    'class' => ['element-selector']
                ]
            ];
        }

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Apply'),
                '#button_type' => 'primary',
                '#ajax' => [
                    'callback' => [$this, 'applyPrompt'],
                    'disable-refocus' => true,
                    'event' => 'mousedown'
                ]
            ],
            'refresh' => [
                '#type' => 'submit',
                '#value' => $this->t('Refresh cache'),
                '#ajax' => [
                    'callback' => [$this, 'refreshCache'],
                    'disable-refocus' => true,
                    'event' => 'mousedown'
                ]
            ]
        ];

        $form['#attached']['library'][] = 'sprowt_ai_prompt_library/library_form';

        return $form;
    }


    public static function applyPrompt(&$form, FormStateInterface $formState)
    {
        $outputAsToken = $formState->getValue('outputAsToken');
        $elementSelector = $formState->getValue('elementSelector') ?? '.claude-prompt';
        $promptKey = $formState->getValue('prompt') ?? '';
        $response = new AjaxResponse();
        if(empty($promptKey)) {
            $response->addCommand(new CloseModalDialogCommand());
            return $response;
        }
        /** @var AiPromptLibraryService $service */
        $service = \Drupal::service('sprowt_ai_prompt_library.service');

        $parts = explode('::', $promptKey);
        $source = $parts[0];
        $uuid = $parts[1];

        /** @var Prompt $prompt */
        $prompt = $service->loadPrompt($source, $uuid);
        if(empty($prompt)) {
            $response->addCommand(new MessageCommand(
                'No prompt found',
                null,
                [
                    'type' => 'error'
                ]
            ));

            $response->addCommand(new CloseModalDialogCommand());
            return $response;
        }

        $response->addCommand(new InvokeCommand(
            $elementSelector,
            'trigger',
            [
                'applyPrompt',
                [!empty($outputAsToken), $source, $prompt->uuid(), $prompt->get('prompt')->value]
            ]
        ));


        $response->addCommand(new CloseModalDialogCommand());
        return $response;
    }

    public static function refreshCache(&$form, FormStateInterface $formState)
    {
        /** @var Session $session */
        $session = \Drupal::service('session');
        if(!$session->isStarted()) {
            $session->start();
        }
        $session->set('promptLibraryForm::outputAsToken', $formState->getValue('outputAsToken'));
        $elementSelector = $formState->getValue('elementSelector') ?? '.claude-prompt';
        $response = new AjaxResponse();

        /** @var AiPromptLibraryService $service */
        $service = \Drupal::service('sprowt_ai_prompt_library.service');

        $service->promptMap(true);
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new InvokeCommand(
            $elementSelector,
            'trigger',
            [
                'refreshPromptLibrary',
                []
            ]
        ));
        $response->addCommand(new MessageCommand(
            'Prompt cache refreshed',
            null,
            [
                'type' => 'status'
            ]
        ));
        return $response;
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
//        $this->messenger()->addStatus($this->t('The message has been sent.'));
//        $form_state->setRedirect('<front>');
    }

}
