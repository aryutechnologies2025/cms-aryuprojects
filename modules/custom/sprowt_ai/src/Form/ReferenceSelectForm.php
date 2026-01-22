<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Sprowt AI form.
 */
class ReferenceSelectForm extends FormBase
{

    use AjaxFormHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_reference_select';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $form['#title'] = 'Insert reference(s)';

        $tempStore = \Drupal::service('tempstore.private')->get('sprowt_ai');
        $referencesArray = $tempStore->get('sprowt_ai_tmp_references') ?? [];
        $selector = $referencesArray['selector'] ?? '';
        $references = $referencesArray['references'] ?? [];
        $options = [];
        foreach($references as $refrenceKey => $reference) {
            $options[$refrenceKey] = $reference['label'];
        }


        $form['selector'] = [
            '#type' => 'value',
            '#value' => $selector,
        ];

        $form['selectedReferences'] = [
            '#type' => 'select',
            '#title' => $this->t('Select reference(s) to insert'),
            '#options' => $options,
            '#multiple' => TRUE,
            '#required' => TRUE,
        ];

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Insert'),
                '#ajax' => [
                    'callback' => '::ajaxSubmit',
                    'disable-refocus' => true
                ]
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
    }

    protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state)
    {
        $response = new AjaxResponse();
        $response->addCommand(new InvokeCommand(
            $form_state->getValue('selector'),
            'trigger',
            [
                'insertReferences',
                [$form_state->getValue('selectedReferences')]
            ]
        ));
        $response->addCommand(new CloseModalDialogCommand());
        return $response;
    }

}
