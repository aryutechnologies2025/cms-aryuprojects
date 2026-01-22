<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai\Claude3Service;
use Drupal\sprowt_ai\Element\ClaudePrompt;

/**
 * Provides a Sprowt AI form.
 */
class GeneratedContentModalForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_generated_content_modal';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $sessionKey = 'claude3_generated_content';
        $session = \Drupal::service('session');
        $generatedContent = $session->get($sessionKey);
        $form_state->set('options', $generatedContent['options']);
        $form_state->set('promptText', $generatedContent['promptText']);
        $form_state->set('contents', $generatedContent['contents']);

        $usage = $generatedContent['usage'] ?? [];


        $options = $form_state->get('options');

        $form['options'] = [
            '#type' => 'value',
            '#value' => $options,
        ];

        $promptText = $form_state->get('promptText');
        $form['promptText'] = [
            '#type' => 'value',
            '#value' => $promptText
        ];

        $form['description'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>Select the desired generated content to insert into your form.</p>')
        ];

        $contents = $form_state->get('contents');

        $form['content'] = static::contentTable($contents);

        if(!empty($usage)) {
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


        $form['actions'] = [
            '#type' => 'actions',
            'regenerate' => [
                '#type' => 'submit',
                '#value' => $this->t('Regenerate'),
                '#name' => 'regenerate',
                '#ajax' => [
                    'callback' => [static::class, 'regenerate'],
                    'wrapper' => 'content-wrap',
                    'event' => 'mousedown'
                ]
            ],
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Insert content'),
                '#button_type' => 'primary',
                '#name' => 'insertContent',
                '#ajax' => [
                    'callback' => [static::class, 'insertContent'],
                    'wrapper' => 'content-wrap',
                    'event' => 'mousedown'
                ]
            ],
        ];

        return $form;
    }

    public static function isHtml($content)
    {
        $stripped = strip_tags($content);
        $isHtml = $stripped != $content;
        return $isHtml;
    }

    public static function contentTableOptions($contents) {
        $opts = [];
        foreach($contents as $id => $content) {
            $isHtml = static::isHtml($content);
            if($isHtml) {
                $markup = Markup::create('<div class="generated-content--'.$id.'">' . $content . '</div>');
            }
            else {
                $markup = Markup::create('<div class="generated-content--'.$id.'"><pre>' . $content . '</pre></div>');
            }

            $opts[$id] = [
                'content' => $markup
            ];
        }
        return $opts;
    }

    public static function contentTable($contents)
    {
        $tableHeaders = [
            'content' => 'Content'
        ];

        $table = [
            '#type' => 'tableselect',
            '#header' => $tableHeaders,
            '#empty' => t('No content generated'),
            '#prefix' => '<div id="content-wrap">',
            '#suffix' => '</div>',
            '#required' => true,
            '#multiple' => false
        ];


        $table['#options'] = static::contentTableOptions($contents);


        return $table;
    }

    public static function regenerate(array &$form, FormStateInterface $form_state) {
        /** @var Claude3Service $service */
        $service = \Drupal::service('sprowt_ai.claude_3');

        $options = $form_state->get('options');
        $promptText = $form_state->get('promptText');
        $return = $service->generateContent($promptText, $options);

        if(!empty($return['error'])) {
            $response = new AjaxResponse();
            $response->addCommand(new CloseModalDialogCommand());
            $response->addCommand(new MessageCommand($return['message'], null, ['type' => 'error']));
            return $response;
        }
        else {
            $contents = $service->extractContentsFromReturn($return);
            $form_state->set('contents', $contents);
        }

        $sessionKey = 'claude3_generated_content';
        $session = \Drupal::service('session');
        $generatedContent = $session->get($sessionKey);
        $generatedContent['contents'] = $contents;
        $generatedContent['usage'] = $return['usage'] ?? [];
        $session->set($sessionKey, $generatedContent);


        $response = new AjaxResponse();
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new InvokeCommand(
            ClaudePrompt::fieldSelector($options),
            'trigger',
            [
                'claude3GenerateContentResponse',
                [$return]
            ]
        ));
        return $response;
    }

    public static function insertContent(array &$form, FormStateInterface $form_state)
    {
        $contentId = $form_state->getValue('content');
        $contentOpts = $form_state->get('contents');
        $content = $contentOpts[$contentId];
        $options = $form_state->get('options');
        $options['isHtml'] = static::isHtml($content);
        $response = new AjaxResponse();
        $response->addCommand(
            new InvokeCommand(
                ClaudePrompt::fieldSelector($options),
                'trigger',
                [
                    'insertContent',
                    [$content, $options]
                ]
            )
        );
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
        // do nothing
    }

}
