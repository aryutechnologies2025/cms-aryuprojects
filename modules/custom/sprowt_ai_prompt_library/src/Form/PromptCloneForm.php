<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library\Form;

use Drupal\content_library\Form\ContentLibraryConfirmImport;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\Batch;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai_prompt_library\Entity\Prompt;

/**
 * Provides a Sprowt AI prompt library form.
 */
final class PromptCloneForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_prompt_library_prompt_clone';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, ?Prompt $prompt = null): array
    {

        $form['#title'] = 'Clone prompt: ' . $prompt->label();

        $form['description'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>This form will clone the selected prompt. This will create new node(s) with the same content as the current node.</p>'),
        ];

        $form['id'] = [
            '#type' => 'value',
            '#value' => $prompt->id(),
        ];

        $cloneTemplate = [
            '#type' => 'fieldset'
        ];
        $cloneTemplate['title'] = [
            '#type' => 'textfield',
            '#title' => 'Prompt title',
            '#required' => true,
            '#attributes' => [
                'class' => ['node-title']
            ]
        ];
        $objectDef = ['title' => '.node-title'];
        $defaultVal = [
            'title' => ''
        ];

        $carryOver = [];

        $cloneTemplate['enabled'] = [
            '#type' => 'checkbox',
            '#title' => 'Enabled',
            '#attributes' => [
                'class' => ['node-published'],
            ]
        ];
        $objectDef['enabled'] = '.node-published';
        $carryOver[] = 'published';

        $defaultVal['enabled'] = true;

        $form['clones'] = [
            '#type' => 'template_field',
            '#title' => 'Clones',
            '#description' => 'Add clones to create.',
            '#template' => $cloneTemplate,
            '#object_def' => $objectDef,
            '#default_value' => [$defaultVal],
            '#carryOver' => $carryOver,
            '#object_name' => 'clone'
        ];

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Clone'),
            ],
        ];

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
        $id = $form_state->getValue('id');
        $clones = $form_state->getValue('clones');
        $batchBuilder = new BatchBuilder();
        $batchBuilder->setTitle("Cloning prompts");
        foreach ($clones as $clone) {
            $batchBuilder->addOperation([static::class, 'importClone'], [
                $id,
                $clone['title'],
                $clone['enabled']
            ]);
        }

        $batchBuilder->setFinishCallback([static::class, 'batchFinished']);
        $batchBuilder->setProgressive(true);
        batch_set($batchBuilder->toArray());
    }


    public static function importClone($id, $title, $enabled, &$context)
    {
        $sandbox = &$context['sandbox'];
        if(!empty($sandbox['processing'])) {
            return;
        }
        $sandbox['processing'] = true;
        $original = Prompt::load($id);
        $clone = $original->createDuplicate();
        $clone->set('label', $title);
        $clone->set('status', [
            'value' => !empty($enabled),
        ]);
        $clone->save();
        $context['results'][] = $clone->id();
        $sandbox['processing'] = false;
        $context['finished'] = 1;
    }

    public static function batchFinished($success, $results, $operations)
    {
        $message = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>' . t('The following prompts have been created:') . '</p>'),
            'list' => [
                '#type' => 'html_tag',
                '#tag' => 'ul'
            ]
        ];
        foreach($results as $result) {
            $prompt = Prompt::load($result);
            $message['list'][$prompt->id()] = [
                '#type' => 'html_tag',
                '#tag' => 'li',
                'result' => [
                    '#type' => 'link',
                    '#title' => $prompt->label(),
                    '#url' => $prompt->toUrl('edit-form'),
                    '#attributes' => [
                        'target' => '_blank'
                    ]
                ]
            ];
        }
        $message = \Drupal::service('renderer')->render($message);
        $messenger = \Drupal::messenger();
        $messenger->addStatus(Markup::create($message));
    }

}
