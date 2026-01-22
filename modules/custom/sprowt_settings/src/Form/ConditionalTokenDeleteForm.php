<?php

declare(strict_types=1);

namespace Drupal\sprowt_settings\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\sprowt_settings\SprowtSettings;

/**
 * @todo Add a description for the form.
 */
class ConditionalTokenDeleteForm extends ConfirmFormBase
{

    use AjaxFormHelperTrait;

    protected $key;

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_settings_conditional_token_delete';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): TranslatableMarkup
    {
        return $this->t('Are you sure you want to do this?');
    }

    public function getDescription(): TranslatableMarkup
    {
        return $this->t("Are you sure you want to delete the token with the machine name: \"{$this->key}\"? This action cannot be undone.");
    }

    public function buildForm(array $form, FormStateInterface $form_state, $key = null)
    {
        $this->key = $key;
        $form = parent::buildForm($form, $form_state);

        $form['actions']['submit']['#attributes']['class'][] = 'button--danger';

        if($this->isAjax()) {
            $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): Url
    {
        return new Url('sprowt_settings.sprowt_settings');
    }

    /**
     * @return SprowtSettings
     */
    public function getSprowtSettings() {
        if(isset($this->sprowtSettings)) {
            return $this->sprowtSettings;
        }
        $this->sprowtSettings = \Drupal::service('sprowt_settings.manager');
        return $this->sprowtSettings;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->getSprowtSettings()->deleteConditionalToken($this->key);
        token_clear_cache();
    }


    protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state)
    {
        $response = new AjaxResponse();
        $response->addCommand(new MessageCommand('Conditional token deleted', null, [
            'type' => 'status'
        ]));
        $response->addCommand(new InvokeCommand('.rebuildConditionalTokens--button', 'trigger', [
            'click'
        ]));
        $response->addCommand(new CloseModalDialogCommand());
        return $response;
    }
}
