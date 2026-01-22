<?php

namespace Drupal\html_title\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The Class HtmlTitleSettingConfigForm.
 *
 * @package Drupal\html_title\Form
 */
class HtmlTitleSettingConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'html_title_setting_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['html_title.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('html_title.settings');

    $form['allow_html_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allow html tags'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('allow_html_tags'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('html_title.settings')
      ->set('allow_html_tags', $form_state->getValue('allow_html_tags'))
      ->save();
    $this->configFactory->clearStaticCache();

    parent::submitForm($form, $form_state);
  }

}
