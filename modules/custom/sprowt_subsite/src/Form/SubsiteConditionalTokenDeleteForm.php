<?php

namespace Drupal\sprowt_subsite\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\sprowt_settings\Form\ConditionalTokenDeleteForm as BaseConditionalTokenDeleteForm;
use Drupal\sprowt_subsite\SettingsManager;

class SubsiteConditionalTokenDeleteForm extends BaseConditionalTokenDeleteForm
{

    protected $subsite;

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_subsite_conditional_token_delete';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $key = null, $subsite = null): array
    {
        if(!$subsite instanceof Node) {
            $subsite = Node::load($subsite);
            if($subsite->bundle() != 'subsite') {
                $subsite = sprowt_subsite_get_subsite($subsite);
            }
        }
        $this->subsite = $subsite;

        $form = parent::buildForm($form, $form_state, $key);
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

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->getSprowtSettings()->deleteConditionalToken($this->subsite, $this->key);
        token_clear_cache();
    }
}
