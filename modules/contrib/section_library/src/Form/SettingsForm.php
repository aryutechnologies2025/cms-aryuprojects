<?php

namespace Drupal\section_library\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Section template settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'section_template_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'section_library.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Label settings.
    $form['label_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Template type labels'),
      '#open' => TRUE,
    ];
    $form['label_settings']['section_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Section label'),
      '#config_target' => 'section_library.settings:section_label',
      '#description' => $this->t('The name used for single section templates.'),
      '#required' => TRUE,
    ];
    $form['label_settings']['template_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Template label'),
      '#config_target' => 'section_library.settings:template_label',
      '#description' => $this->t('The name used for multi-section templates.'),
      '#required' => TRUE,
    ];
    return $form;
  }

}
