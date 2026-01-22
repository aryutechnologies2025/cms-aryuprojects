<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai\Entity\Context;
use Drupal\sprowt_ai\Entity\SprowtAiExample;
use Drupal\sprowt_ai_prompt_library\Entity\Prompt;

/**
 * Provides a Sprowt AI form.
 */
class CloneForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_clone';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $entity_type = null, $entity_id = null): array
    {

        $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
        /** @var Context|SprowtAiExample $entity */
        $entity = $storage->load($entity_id);
        $definition = \Drupal::entityTypeManager()->getDefinition($entity_type);
        $entityTypeLabel = $definition->getLabel();


        $form['#title'] = 'Clone '.$entityTypeLabel.': ' . $entity->label();

        $form['entity_type'] = [
            '#type' => 'value',
            '#value' => $entity_type,
        ];

        $form['entity_id'] = [
            '#type' => 'value',
            '#value' => $entity->id(),
        ];

        $cloneTemplate = [
            '#type' => 'fieldset'
        ];
        $cloneTemplate['title'] = [
            '#type' => 'textfield',
            '#title' => $entityTypeLabel . ' title',
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
        $id = $form_state->getValue('entity_id');
        $type = $form_state->getValue('entity_type');
        $clones = $form_state->getValue('clones');
        $batchBuilder = new BatchBuilder();
        $batchBuilder->setTitle("Cloning prompts");
        foreach ($clones as $clone) {
            $batchBuilder->addOperation([static::class, 'importClone'], [
                $id,
                $type,
                $clone['title']
            ]);
        }

        $batchBuilder->setFinishCallback([static::class, 'batchFinished']);
        $batchBuilder->setProgressive(true);
        batch_set($batchBuilder->toArray());
    }


    public static function importClone($id, $type, $title, &$context)
    {
        $sandbox = &$context['sandbox'];
        if(!empty($sandbox['processing'])) {
            return;
        }
        $sandbox['processing'] = true;
        $storage = \Drupal::entityTypeManager()->getStorage($type);
        $original = $storage->load($id);
        $clone = $original->createDuplicate();
        $clone->set('label', $title);
        $clone->save();
        $context['results'][] = [
            'entity_type' => $type,
            'entity_id' => $clone->id(),
        ];
        $sandbox['processing'] = false;
        $context['finished'] = 1;
    }

    public static function batchFinished($success, $results, $operations)
    {
        $firstResult = $results[0] ?? [];
        if(!empty($firstResult)) {
            $storage = \Drupal::entityTypeManager()->getStorage($firstResult['entity_type']);
            $definition = \Drupal::entityTypeManager()->getDefinition($firstResult['entity_type']);
            $typeLabelPlural = $definition->getPluralLabel();
            $message = [
                '#type' => 'markup',
                '#markup' => Markup::create('<p>' . t('The following '.$typeLabelPlural.' have been created:') . '</p>'),
                'list' => [
                    '#type' => 'html_tag',
                    '#tag' => 'ul'
                ]
            ];
            foreach ($results as $result) {
                $entity = $storage->load($result['entity_id']);
                $message['list'][$entity->id()] = [
                    '#type' => 'html_tag',
                    '#tag' => 'li',
                    'result' => [
                        '#type' => 'link',
                        '#title' => $entity->label(),
                        '#url' => $entity->toUrl('edit-form'),
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

}
