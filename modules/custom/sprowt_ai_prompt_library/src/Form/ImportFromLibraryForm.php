<?php declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Sprowt AI prompt library form.
 */
class ImportFromLibraryForm extends FormBase
{
    protected $entityTypeId;

    protected $entityType;

    protected $collection;

    /**
     * @var \Drupal\sprowt_ai_prompt_library\AiPromptLibraryService
     */
    protected $aiPromptLibraryService;


    public static function create(ContainerInterface $container)
    {
        $static = parent::create($container);
        $static->aiPromptLibraryService = $container->get('sprowt_ai_prompt_library.service');
        return $static;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_prompt_library_import_from_library';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $entityType = null): array
    {
        $this->entityTypeId = $entityType;
        $this->entityType = \Drupal::entityTypeManager()->getDefinition($entityType);
        $this->collection = $this->aiPromptLibraryService->aiContentCollectionFromSource($entityType);

        $collectionName = $this->entityType->getCollectionLabel();
        $collectionName = strtolower((string) $collectionName);

        $form['description'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>Import '.$collectionName.' from source that don\'t exist locally.</p>' .
                "<p>If you don't see the prompt you're looking for. It's probably already been imported onto the site and possibly re-named.</p>" .
                "<p>If you need to update or import a prompt that has already been imported, ".
                "you'll have to do it manually by copying and pasting from source's <a href=\"https://source.sprowt.us/admin/config/services/sprowt-ai/ai-prompt\" target=\"_blank\">prompt library</a>.</p>"
            ),
        ];

        $filter = [
            '#type' => 'fieldset',
            '#title' => t('Filter'),
        ];

        $filter['prompt_name'] = [
            '#type' => 'textfield',
            '#title' => t('Filter by label'),
        ];
        $tagOptions = [];
        foreach ($this->collection as $item) {
            if(!empty($item['tags'])) {
                foreach ($item['tags'] as $tag) {
                    $tagOptions[$tag] = $tag;
                }
            }
        }
        asort($tagOptions);
        $filter['tags'] = [
            '#type' => 'select',
            '#title' => t('Filter by tag'),
            '#options' => $tagOptions,
            '#empty_option' => t('- Any -'),
            '#multiple' => TRUE,
        ];

        $filter['apply'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => t('Apply'),
            '#attributes' => [
                'type' => 'button',
                'class' => ['button', 'filter-apply']
            ]
        ];

        $form['filter'] = $filter;

        $tableOptions = [];
        foreach ($this->collection as $item) {
            $values = $item;
            $item['tagList'] = implode(', ', $item['tags'] ?? []);
            $item['#attributes'] = [
                'data-row-values' => json_encode($values),
                'data-id' => $item['id'],
                'data-tags' => json_encode($item['tags'])
            ];
            $item['linkUrl'] = [
                'data' => [
                    '#type' => 'operations',
                    '#links' => [
                        'edit' => [
                            'title' => 'View on source',
                            'url' => Url::fromUri($item['link'], [
                                'attributes' => [
                                    'target' => '_blank',
                                ]
                            ]),
                        ]
                    ]
                ]
            ];
            $tableOptions[$item['uuid']] = $item;
        }

        $form['importUuids'] = [
            '#title' => 'Select ' . $collectionName . ' to import',
            '#type' => 'tableselect',
            '#header' => [
                'title' => 'Label',
                'linkUrl' => 'Link',
                'tagList' => 'Tags',
            ],
            '#options' => $tableOptions,
        ];


        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Import'),
            ]
        ];

        $form['#attached']['library'][] = 'sprowt_ai_prompt_library/import_form';

        return $form;
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
        $uuids = $form_state->getValue('importUuids');
        $uuids = array_filter($uuids);
        $uuids = array_values($uuids);
        $session = \Drupal::service('session');
        $session->set('sprowt_ai_prompt_library.importCache', [
            'entityType' => $this->entityTypeId,
            'uuids' => $uuids
        ]);

        $form_state->setRedirect('sprowt_ai_prompt_library.import_from_library_confirm');
    }

}
