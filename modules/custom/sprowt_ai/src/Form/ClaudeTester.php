<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai\Entity\AiSystem;

/**
 * Provides a Sprowt AI form.
 */
final class ClaudeTester extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_claude_tester';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $form['description'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>Use this form to test using claude 3 prompts</p>')
        ];

        $aiSystems = AiSystem::loadMultiple();
        $aiSystemOpts = [];
        /** @var AiSystem $aiSystem */
        foreach ($aiSystems as $aiSystem) {
            if($aiSystem->isEnabled()) {
                $aiSystemOpts[$aiSystem->id()] = $aiSystem->label();
            }
        }

        $form['system'] = [
            '#type' => 'select',
            '#title' => 'System user',
            '#options' => $aiSystemOpts,
            '#required' => false,
            '#empty_option' => 'None',
            '#attributes' => [
                'class' => ['system-user'],
            ]
        ];

        $form['maxTokens'] = [
            '#type' => 'number',
            '#title' => 'Max tokens',
            '#description' => 'The maximum number of tokens to be consumed by the api.'
                . ' Tokens represent a sequence of characters.'
                . ' The max number defined here determines the size of the generated content.'
                . ' For more info see <a href="https://lunary.ai/anthropic-tokenizer" target="_blank">here</a>.',
            '#min' => 4,
            '#max' => 4096,
            '#step' => 1,
            '#default_value' => 1024,
            '#required' => true
        ];

        $form['temperature'] = [
            '#type' => 'number',
            '#title' => t('Temperature'),
            '#default_value' => 1.0,
            '#description' => 'Amount of randomness injected into the response.'
                . ' Ranges from 0.0 to 1.0.'
                . ' Use temperature closer to 0.0 for analytical / multiple choice, and closer to 1.0 for creative and generative tasks.'
                . ' Note that even with temperature of 0.0, the results will not be fully deterministic.',
            '#min' => 0,
            '#max' => 1,
            '#step' => 0.1,
            '#required' => true
        ];

        $form['tokensUsed'] = [
            '#title' => 'Tokens used',
            '#type' => 'fieldset',
            '#description' => 'Tokens used generating the content.',
            '#prefix' => '<div class="tokens-used hidden">',
            '#suffix' => '</div>',
            'inputTokens' => [
                '#type' => 'textfield',
                '#title' => 'Input tokens',
                '#attributes' => [
                    'class' => ['input-tokens'],
                    'readonly' => 'readonly'
                ]
            ],
            'outputTokens' => [
                '#type' => 'textfield',
                '#title' => 'Output tokens',
                '#attributes' => [
                    'class' => ['output-tokens'],
                    'readonly' => 'readonly'
                ]
            ]
        ];

        $form['prompt'] = [
            '#type' => 'claude_prompt',
            '#title' => 'Prompt',
            '#insert' => ['result'],
            '#description' => 'For help crafting a good prompt, see this <a href="https://docs.anthropic.com/en/docs/prompt-engineering" target="_blank">documentation</a>.',
        ];

        $form['result'] = [
            '#type' => 'text_format',
            '#title' => 'Result',
            '#attributes' => [
                'class' => ['.result-field']
            ]
        ];

        $form['#attached']['library'][] = 'sprowt_ai/claude-tester';

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
        $this->messenger()->addStatus($this->t('The message has been sent.'));
        $form_state->setRedirect('<front>');
    }

}
