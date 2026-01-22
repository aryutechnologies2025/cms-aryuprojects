<?php

namespace Drupal\layout_builder_ids\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Layout Builder IDs settings for this site.
 */
class LayoutBuilderIdsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_ids_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['layout_builder_ids.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('layout_builder_ids.settings');

    $form['block_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block ID field.'),
      '#description' => $this->t('Add an ID field to <em>block</em> configuration form in Layout Builder.'),
      '#default_value' => $config->get('block_id'),
    ];

    $form['section_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Section ID field.'),
      '#description' => $this->t('Add an ID field to <em>section</em> configuration form in Layout Builder.'),
      '#default_value' => $config->get('section_id'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('layout_builder_ids.settings')
      ->set('block_id', $form_state->getValue('block_id'))
      ->set('section_id', $form_state->getValue('section_id'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
