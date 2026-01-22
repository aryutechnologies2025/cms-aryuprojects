<?php

namespace Drupal\sprowt_settings\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

class SprowtSettingsBulkAddIndustriesForm extends FormBase
{

    use AjaxFormHelperTrait;
    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_settings_bulk_add_industries_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $taxonomy_vocabulary = Vocabulary::load('industry');

        $vocabName = $taxonomy_vocabulary->label();
        $form['#title'] = 'Bulk add industries';

        $form['vid'] = [
            '#type' => 'value',
            '#value' => $taxonomy_vocabulary->id(),
        ];

        $form['bulk_add'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Bulk add terms'),
            '#required' => TRUE,
            '#description' => 'Bulk add terms by name. One term name per line.'
        ];

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Add'),
            ],
        ];

        if($this->isAjax()) {
            $form['actions']['submit']['#ajax'] = [
                'callback' => '::ajaxSubmit',
                'disable-refocus' => true
            ];
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        $vid = $form_state->getValue('vid');
        $termString = trim($form_state->getValue('bulk_add'));
        $terms = explode("\n", $termString);
        $currentTerms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]);
        $currentTermNames = array_map(function ($term) {
            return $term->label();
        }, $currentTerms);
        $newTerms = [];
        foreach ($terms as $term) {
            $term = trim($term);
            if(empty($term)) {
                continue;
            }
            if (!in_array($term, $currentTermNames)) {
                $newTerms[] = $term;
            }
        }
        if (empty($newTerms)) {
            $form_state->setErrorByName('bulk_add', $this->t('No new terms to add.'));
        }
        else {
            $form_state->set('newTerms', $newTerms);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $vid = $form_state->getValue('vid');
        $vocab = Vocabulary::load($vid);
        $newTerms = $form_state->get('newTerms');
        $count = 0;
        $errors = [];
        foreach ($newTerms as $term) {
            $term = Term::create([
                'name' => $term,
                'vid' => $vocab->id()
            ]);
            $term->setPublished();
            $term->field_is_company_industry->value = true;
            try {
                $term->save();
                ++$count;
            }
            catch (\Exception $e) {
                Error::logException(\Drupal::logger('sprowt_admin_override'), $e);
                $errors[$term->label()] = $e->getMessage();
            }
        }
        if(!empty($errors)) {
            foreach($errors as $term => $error) {
                $this->messenger()->addError("Error adding term '$term'. See logs for details.");
            }
            $form_state->setErrorByName('bulk_add', $this->t('Errors adding terms. See logs for details.'));
        }
    }


    protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
        $response = new AjaxResponse();
        $sprowtSettingsForm = \Drupal::formBuilder()->getForm(SprowtSettingsForm::class);
        $response->addCommand(new ReplaceCommand('#company-industry-wrapper', $sprowtSettingsForm["company_info"]["company_type"]));
        $response->addCommand(new CloseModalDialogCommand());
        return $response;
    }
}
