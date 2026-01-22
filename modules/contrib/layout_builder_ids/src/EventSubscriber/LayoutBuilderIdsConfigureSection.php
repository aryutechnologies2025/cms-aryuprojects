<?php

namespace Drupal\layout_builder_ids\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Drupal\layout_builder_ids\Service\LayoutBuilderIdsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add section id to layout builder sections.
 */
class LayoutBuilderIdsConfigureSection implements EventSubscriberInterface {

  /**
   * Layout builder ids service.
   *
   * @var \Drupal\layout_builder_ids\Service\LayoutBuilderIdsService
   */
  protected $layoutBuilderIdsService;

  /**
   * Constructor for event subscriber for configure block.
   *
   * @param \Drupal\layout_builder_ids\Service\LayoutBuilderIdsService $layoutBuilderIdsService
   *   Layout builder ids service.
   */
  public function __construct(LayoutBuilderIdsService $layoutBuilderIdsService) {
    $this->layoutBuilderIdsService = $layoutBuilderIdsService;
  }

  /**
   * Alter form.
   *
   * @param \Drupal\core_event_dispatcher\Event\Form\FormAlterEvent $event
   *   The event.
   */
  public static function alterForm(FormAlterEvent $event): void {
    // Ignore form alter according to module configuration.
    $config = \Drupal::config('layout_builder_ids.settings');
    if (!$config->get('section_id')) {
      return;
    }

    // Get the form from the event.
    $form = &$event->getForm();

    // If we are on a configure section form, alter it.
    if ($form['#form_id'] == 'layout_builder_configure_section') {

      // These next two lines are needed until this issue gets fixed:
      // https://www.drupal.org/project/drupal/issues/3103812.
      // Once this issue gets fixed in core then we can use the
      // proper validate procedures.  Until then we need to add the
      // form id without the random value.
      $form_state = $event->getFormState();
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);

      // Get the config for the section.
      $config = $event->getFormState()->getFormObject()->getCurrentLayout()->getConfiguration();

      // Add the section id to the configure form.
      $form['layout_settings']['layout_builder_id'] = [
        '#type' => 'textfield',
        '#title' => 'Section ID',
        '#weight' => 99,
        '#default_value' => $config['layout_builder_id'] ?? NULL,
        '#description' => t('Section ID is an optional setting which is used to support an anchor link to this block. For example, entering "feature" lets you link directly to this section by adding "#feature" to the end of the URL.</br>IDs should start with a letter, may only contain letters, numbers, hyphens, and should be unique on the page (underscores will be replaced by hyphens, and periods will be removed from the rendered ID).'),
      ];

      // Add the form validation for configure block.
      $form['#validate'][] = 'Drupal\layout_builder_ids\EventSubscriber\LayoutBuilderIdsConfigureSection::layoutBuilderIdsConfigureSectionFormValidation';

      // Add our custom submit function.
      array_unshift(
        $form['#submit'],
        'Drupal\layout_builder_ids\EventSubscriber\LayoutBuilderIdsConfigureSection::layoutBuilderIdsConfigureSectionSubmitForm'
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function layoutBuilderIdsConfigureSectionFormValidation(array &$form, FormStateInterface $form_state) {

    // Get the layout builder id from the form.
    $layout_builder_id = $form_state->getValue(
      [
        'layout_settings',
        'layout_builder_id',
      ]
    );

    // Ensure that we are not checking for blank layout builder id.
    if ($layout_builder_id !== '') {

      // Put the id through the HTML getId to make sure
      // that we form a valid id.
      $layout_builder_id = Html::getId($layout_builder_id);

      // Check if we have a duplicate id somewhere.
      $found_id = layoutBuilderIdsService::layoutBuilderIdsCheckIds($layout_builder_id, $form_state, 'section');

      // If we have a duplicate id, then set the form error.
      if ($found_id) {

        // Set the form error on the layout builder id form element.
        $form_state->setError($form['layout_settings']['layout_builder_id'], 'There is already a block or section with the ID "' . $layout_builder_id . '".');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function layoutBuilderIdsConfigureSectionSubmitForm(array &$form, FormStateInterface $form_state) {

    // Get the layout builder id from the form.
    $layout_builder_id = $form_state->getValue(
      [
        'layout_settings',
        'layout_builder_id',
      ],
      NULL
    );

    // If there is a layout builder id, store it.
    if ($layout_builder_id !== NULL) {

      // Get the form object from the form state.
      $formObject = $form_state->getFormObject();

      // Get the layout.
      $layout = $formObject->getCurrentLayout();

      // Load in the config for this section.
      $configuration = $layout->getConfiguration();

      // Set the layout builder id in config variable.
      $configuration['layout_builder_id'] = Html::getId($layout_builder_id);

      // Set the config for this section.
      $layout->setConfiguration($configuration);
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      FormHookEvents::FORM_ALTER => 'alterForm',
    ];
  }

}
