<?php

declare(strict_types=1);

namespace Drupal\sprowt_subsite\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\sprowt_settings\Form\ConditionalTokenForm as BaseConditionalTokenForm;
use Drupal\sprowt_subsite\SettingsManager;

/**
 * Provides a Sprowt Subsite form.
 */
 class SubsiteConditionalTokenForm extends BaseConditionalTokenForm
{

    protected $subsite;

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_subsite_conditional_token';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $subsite = null): array
    {
        if(!$subsite instanceof Node) {
            $subsite = Node::load($subsite);
            if($subsite->bundle() != 'subsite') {
                $subsite = sprowt_subsite_get_subsite($subsite);
            }
        }
        $this->subsite = $subsite;

        $form = parent::buildForm($form, $form_state);
        return $form;
    }

     /**
      * @return SettingsManager
      */
     public function getSprowtSettings() {
         if(isset($this->sprowtSettings)) {
             return $this->sprowtSettings;
         }
         $this->sprowtSettings = \Drupal::service('sprowt_subsite.settings_manager');
         return $this->sprowtSettings;
     }

     public function conditionalTokenExists($key) {
         return $this->getSprowtSettings()->isConditionalToken($this->subsite, $key);
     }

     /**
      * {@inheritdoc}
      */
     public function submitForm(array &$form, FormStateInterface $form_state): void
     {
         $this->submitVisibility($form, $form_state);
         $visibilityValue = $form_state->get('visibilityValue') ?? [];
         $key = $form_state->getValue('key');
         $value = $form_state->getValue('value');

         $sprowtSettings = $this->getSprowtSettings();
         $sprowtSettings->setConditionalToken($this->subsite, $key, $value, $visibilityValue);
         token_clear_cache();
     }

}
