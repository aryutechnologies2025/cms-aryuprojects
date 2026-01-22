<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\sprowt_ai\Element\ClaudePrompt;
use Drupal\sprowt_ai\Entity\Context;
use Drupal\sprowt_ai\Entity\SprowtAiExample;

/**
 * Provides a Sprowt AI form.
 */
final class ChooseContextForm extends FormBase
{

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

        $form['#title'] = 'Insert context(s)';

        $filters = [
            '#type' => 'fieldset',
            '#title' => 'Filters'
        ];

        $filterValues = [
            'label' => $form_state->getValue('filterByLabel'),
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
        $terms = $storage->loadTree(Context::$vocab, 0, 1);
        $termOpts = [];
        foreach ($terms as $term) {
            $termOpts[$term->tid] = $term->name;
        }

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

        $contexts = \Drupal::entityTypeManager()->getStorage('sprowt_ai_context')->loadMultiple();
        $contextOpts = [];
        $contextIdMap = [];
        /** @var SprowtAiExample $example */
        foreach ($contexts as $context) {
            $label = $context->label();
            $filtered = false;
            if(!empty($filterValues['label'])) {
                if(strpos(strtolower($label), strtolower($filterValues['label'])) === FALSE) {
                    $filtered = true;
                }
            }
            $foundUuid = array_search($context->label(), $contextOpts);
            if($foundUuid !== false) {
                $foundId = $contextIdMap[$foundUuid];
                $contextOpts[$foundUuid] .= " [{$foundId}]";
                $label .= " [{$context->id()}]";
            }
            $hasTags = false;
            $tags = $context->get('tags')->referencedEntities();
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
            $opt = [
                'label' => $label,
                'tags' => implode(', ', $tagStr)
            ];

            if(!$filtered) {
                $contextOpts[$context->uuid()] = $opt;
            }
            $contextIdMap[$context->uuid()] = $context->id();
        }

        $addContext = Url::fromRoute('entity.sprowt_ai_context.add_form');

        $form['addExample'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>To add a new context go <a href="'.$addContext->toString().'" target="_blank">here</a>. And then close and re-open this modal.</p>')
        ];

        $form['wrapInXml'] = [
            '#type' => 'checkbox',
            '#title' => 'Wrap in XML',
            '#description' => "If checked the inserted context will be inserted wrapped in XML. Otherwise it'll just be a token pulling in the context content.",
            '#default_value' => true
        ];

        $form['contexts'] = [
            '#type' => 'tableselect',
            '#title' => $this->t('Contexts'),
            '#options' => $contextOpts,
            '#header' => [
                'label' => 'Label',
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

    public function dialogTrigger(array &$form, FormStateInterface $formState)
    {
        $contextUuidVals = $formState->getValue('contexts') ?? [];
        $contextUuids = [];
        foreach ($contextUuidVals as $contextUuid) {
            if(!empty($contextUuid)) {
                $contextUuids[] = $contextUuid;
            }
        }
        $elementId = $formState->getValue('elementId') ?? '';
        $wrapInXml = $formState->getValue('wrapInXml') ?? false;
        $options = ['id' => $elementId];
        $response = new AjaxResponse();
        $response->addCommand(new InvokeCommand(
            ClaudePrompt::fieldSelector($options),
            'trigger',
            [
                'insertContexts',
                [array_values($contextUuids), $elementId, $wrapInXml]
            ]
        ));
        $response->addCommand(new CloseModalDialogCommand());

        return $response;
    }

    public function filterTrigger(array &$form, FormStateInterface $formState)
    {
        return $form['contexts'];
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
