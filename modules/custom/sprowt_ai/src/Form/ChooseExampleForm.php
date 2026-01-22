<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\sprowt_ai\Element\ClaudePrompt;
use Drupal\sprowt_ai\Entity\SprowtAiExample;

/**
 * Provides a Sprowt AI form.
 */
class ChooseExampleForm extends FormBase
{

    use AjaxFormHelperTrait;


    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_choose_example';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $form['#title'] = 'Insert example(s)';

        $form['#attributes']['class'][] = 'sprowt-ai-choose-example-form';

        $filters = [
            '#type' => 'fieldset',
            '#title' => 'Filters'
        ];

        $filterValues = [
            'label' => $form_state->getValue('filterByLabel'),
            'description' => $form_state->getValue('filterByDescription'),
            'tags' => $form_state->getValue('filterByTag'),
        ];
        if(!empty($filterValues['tags'])) {
            $filterValues['tags'] = array_filter($filterValues['tags']);
        }

        $filters['filterByLabel'] = [
            '#type' => 'textfield',
            '#title' => 'Filter by label',
            '#default_value' => $filterValues['label'],
        ];
        $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
        $terms = $storage->loadTree(SprowtAiExample::$vocab, 0, 1);
        $termOpts = [];
        foreach ($terms as $term) {
            $termOpts[$term->tid] = $term->name;
        }

        $filters['filterByDescription'] = [
            '#type' => 'textfield',
            '#title' => 'Filter by description',
            '#default_value' => $filterValues['description'],
        ];

        $filters['filterByTag'] = [
            '#type' => 'select',
            '#title' => 'Filter by tag',
            '#empty_option' => '- Select -',
            '#empty_value' => '',
            '#options' => $termOpts,
            '#default_value' => $filterValues['tags'],
            '#multiple' => true,
        ];

        $filters['actions'] = [
            '#type' => 'container',
            'apply' => [
                '#type' => 'button',
                '#value' => 'Apply',
                '#name' => 'filterApply',
                '#ajax' => [
                    'callback' => [$this, 'filterTrigger'],
                    'disable-refocus' => true,
                    'wrapper' => 'example-table',
                ]
            ],
            'clear' => [
                '#type' => 'button',
                '#value' => 'Clear',
                '#name' => 'filterClear',
            ],
        ];

        $form['filters'] = $filters;
        $form['#attached']['library'][] = 'sprowt_ai/filter_clear';

        $examples = \Drupal::entityTypeManager()->getStorage('sprowt_ai_example')->loadMultiple();
        $exampleOpts = [];
        $exampleIdMap = [];
        /** @var SprowtAiExample $example */
        foreach ($examples as $example) {
            $filtered = false;
            $label = $example->label();
            if(!empty($filterValues['label'])) {
                if(strpos(strtolower($label), strtolower($filterValues['label'])) === FALSE) {
                    $filtered = true;
                }
            }

            $description = $example->get('description')->value;
            if(!empty($filterValues['description'])) {
                if(strpos(strtolower($description), strtolower($filterValues['description'])) === FALSE) {
                    $filtered = true;
                }
            }

            $foundUuid = array_search($example->label(), $exampleOpts);
            if($foundUuid !== false) {
                $foundId = $exampleIdMap[$foundUuid];
                $exampleOpts[$foundUuid] .= " [{$foundId}]";
                $label .= " [{$example->id()}]";
            }
            $opt = [
                'label' => $label,
                'description' => Markup::create($example->get('description')->value)
            ];


            $hasTags = false;
            $tags = $example->get('tags')->referencedEntities();
            $tagStr = [];
            foreach ($tags as $tag) {
                $tagStr[] = $tag->label();
                if(!empty($filterValues['tags']) && in_array($tag->id(), $filterValues['tags'])) {
                    $hasTags = true;
                }
            }
            if(!empty($filterValues['tags']) && !$hasTags) {
                $filtered = true;
            }
            $opt['tags'] = implode(', ', $tagStr);
            if(!$filtered) {
                $exampleOpts[$example->uuid()] = $opt;
            }
            $exampleIdMap[$example->uuid()] = $example->id();
        }
        $addExample = Url::fromRoute('entity.sprowt_ai_example.add_form');
        $form['addExample'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>To add a new example go <a href="'.$addExample->toString().'" target="_blank">here</a>. And then close and re-open this modal.</p>')
        ];

        $form['examples'] = [
            '#type' => 'tableselect',
            '#title' => $this->t('Examples'),
            '#options' => $exampleOpts,
            '#header' => [
                'label' => 'Label',
                'description' => 'Description',
                'tags' => 'Tags',
            ],
            '#multiple' => true,
            '#prefix' => '<div id="example-table">',
            '#suffix' => '</div>',
        ];



        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Insert'),
                '#button_type' => 'primary',
                '#ajax' => [
                    'callback' => [$this, 'dialogTrigger'],
                    'disable-refocus' => true,
                    'event' => 'mousedown'
                ]
            ]
        ];

        if(!empty($_GET['element_id'] ?? null)) {
            $form['elementId'] = [
                '#type' => 'value',
                '#value' => $_GET['element_id']
            ];
        }

        return $form;
    }

    public function filterTrigger(array &$form, FormStateInterface $formState)
    {
        return $form['examples'];
    }

    public function clearFilter(array &$form, FormStateInterface $formState)
    {
        $formState->setValue('filterByDescription', '');
        $formState->setValue('filterByTag', '');
        $formState->setValue('filterByLabel', '');
        $form = $this->buildForm($form, $formState);
        $response = new AjaxResponse();
        $response->addCommand(new ReplaceCommand('.sprowt-ai-choose-example-form', $form));
        return $response;
    }

    public function dialogTrigger(array &$form, FormStateInterface $formState)
    {
        $exampleUuidValue = $formState->getValue('examples') ?? [];
        $exampleUuids = [];
        foreach ($exampleUuidValue as $exampleUuid) {
            if(!empty($exampleUuid)) {
                $exampleUuids[] = $exampleUuid;
            }
        }
        $elementId = $formState->getValue('elementId') ?? '';
        $options = ['id' => $elementId];
        $response = new AjaxResponse();
        $response->addCommand(new InvokeCommand(
            ClaudePrompt::fieldSelector($options),
            'trigger',
            [
                'insertExamples',
                [array_values($exampleUuids), $elementId]
            ]
        ));
        $response->addCommand(new CloseModalDialogCommand());

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

    public function successfulAjaxSubmit(array $form, FormStateInterface $form_state)
    {
        // TODO: Implement successfulAjaxSubmit() method.
    }

}
