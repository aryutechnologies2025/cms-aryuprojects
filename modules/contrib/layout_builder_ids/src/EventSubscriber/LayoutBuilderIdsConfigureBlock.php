<?php

namespace Drupal\layout_builder_ids\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Drupal\layout_builder_ids\Service\LayoutBuilderIdsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add block ids to layout builder sections.
 */
class LayoutBuilderIdsConfigureBlock implements EventSubscriberInterface {

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
    if (!$config->get('block_id')) {
      return;
    }

    // Get the form from the event.
    $form = &$event->getForm();

    // If we are on a configure section form, alter it.
    if (
      in_array(
        $form['#form_id'],
        ['layout_builder_add_block', 'layout_builder_update_block'],
        TRUE
      )
    ) {

      // Pull out the layout_builder_id from config.
      $layout_builder_id = $event->getFormState()->getFormObject()->getCurrentComponent()->get('layout_builder_id');

      // Add the section id to the configure form.
      $form['settings']['layout_builder_id'] = [
        '#type' => 'textfield',
        '#title' => 'Block ID',
        '#weight' => 99,
        '#default_value' => $layout_builder_id ?: NULL,
        '#description' => t('Block ID is an optional setting which is used to support an anchor link to this block. For example, entering "feature" lets you link directly to this block by adding "#feature" to the end of the URL.</br>IDs should start with a letter, may only contain letters, numbers, underscores, hyphens, and periods, and should be unique on the page.'),
      ];

      // Add the form validation for configure block.
      $form['#validate'][] = 'Drupal\layout_builder_ids\EventSubscriber\LayoutBuilderIdsConfigureBlock::layoutBuilderIdsConfigureBlockFormValidation';

      // Add our custom submit function.
      array_unshift(
        $form['#submit'],
        'Drupal\layout_builder_ids\EventSubscriber\LayoutBuilderIdsConfigureBlock::layoutBuilderIdsConfigureBlockSubmitForm'
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function layoutBuilderIdsConfigureBlockFormValidation(array &$form, FormStateInterface $form_state) {

    // Get the layout builder id from the form,
    // also put the id through the HTML getId to make sure
    // that we form a valid id.
    $layout_builder_id = Html::getId(
      $form_state->getValue(['settings', 'layout_builder_id'])
    );

    // Ensure that we have an actual id to check.
    if ($layout_builder_id !== '' && $layout_builder_id !== NULL) {

      // Check if we have a duplicate id somewhere.
      $found_id = LayoutBuilderIdsService::layoutBuilderIdsCheckIds(
        $layout_builder_id,
        $form_state,
        'block'
      );

      // If we have a duplicate id, then set the form error.
      if ($found_id) {

        // Set the form error on the layout builder id element.
        $form_state->setError($form['settings']['layout_builder_id'], 'There is already a block or section with the ID "' . $layout_builder_id . '".');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function layoutBuilderIdsConfigureBlockSubmitForm(array &$form, FormStateInterface $form_state) {

    // Load in the layout_builder_id.
    $layout_builder_id = $form_state->getValue(['settings', 'layout_builder_id']);

    // If there is in id, save it in config.
    if ($layout_builder_id !== NULL) {

      // Load in the component/block.
      $component = $form_state->getFormObject()->getCurrentComponent();

      // Set the layout_builder_id.
      $component->set('layout_builder_id', Html::getId($layout_builder_id));
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {

    return [
      FormHookEvents::FORM_ALTER => 'alterForm',
    ];
  }

}
