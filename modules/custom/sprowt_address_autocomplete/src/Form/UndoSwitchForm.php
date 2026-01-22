<?php

namespace Drupal\sprowt_address_autocomplete\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UndoSwitchForm extends FormBase
{

    public function getFormId()
    {
        return 'sprowt_address_autocomplete_undo_switch_form';
    }

    public static function undoAble(Webform $webform)
    {
        $hasField = self::hasAddressAutocompleteField($webform);
        $keymapStateKey = 'sprowt_address_autocomplete.webform_switch_keymap.' . $webform->id();
        $keyMap = \Drupal::state()->get($keymapStateKey);
        $return = !empty($keyMap);
        if(!empty($return) && empty($hasField)) {
            //must've removed the field manually??
            $webformId = $webform->id();
            $elementStateKey = 'sprowt_address_autocomplete.webform_switch_elements.' . $webformId;
            $keymapStateKey = 'sprowt_address_autocomplete.webform_switch_keymap.' . $webformId;
            \Drupal::state()->delete($elementStateKey);
            \Drupal::state()->delete($keymapStateKey);
            return false;
        }
        return $return;
    }

    public static function hasAddressAutocompleteField(Webform $webform) {
        $elements = $webform->getElementsInitializedAndFlattened();
        $hasAddressField = false;
        foreach($elements as $key => $elementInfo) {
            if(!empty($hasAddressField)) {
                break;
            }
            if($elementInfo['#type'] == 'sprowt_address_autocomplete') {
                $hasAddressField = true;
            }
        }
        return $hasAddressField;
    }

    public static function handlerNoteMarkup($webform = null) {
        if(empty($webform)) {
            $webform = \Drupal::routeMatch()->getParameter('webform');
        }
        return Markup::create('<p><strong>NOTE:</strong> You will need to update any field mapping for any handlers that map this field (Pestpac, Sa5, etc). Handlers can be found <a href="/admin/structure/webform/manage/'.$webform->id().'/handlers" target="_blank">here</a></p>');
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        /** @var Webform $webform */
        $webform = \Drupal::routeMatch()->getParameter('webform');

        $form['subtitle'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<h2>Are you sure?</h2>')
        ];

        $form['description'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>This will switch all "<strong>Sprowt address autocomplete</strong>" fields on this form back to "<strong>Webform address</strong>" fields.</p>')
        ];

        $form['note'] = [
            '#type' => 'markup',
            '#markup' => static::handlerNoteMarkup($webform)
        ];

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t("Yes I'm sure"),
                '#submit' => [
                    [$this, 'doSwitch']
                ]
            ],
            'cancel' => [
                '#type' => 'submit',
                '#button_type' => 'danger',
                '#value' => $this->t("Cancel"),
                '#submit' => [
                    [$this, 'cancel']
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

    }

    public function doSwitch(&$form, FormStateInterface $formState)
    {
        $batchBuilder = new BatchBuilder();
        $batchBuilder->setTitle('Switching webform address fields');
        $batchBuilder->setFinishCallback([static::class, 'batchFinished']);
        /** @var Webform $webform */
        $webform = \Drupal::routeMatch()->getParameter('webform');
        $submissions = \Drupal::entityTypeManager()->getStorage('webform_submission')->loadByProperties([
            'webform_id' => $webform->id()
        ]);
        $batchBuilder->addOperation([static::class, 'batchAddField'], [$webform->id()]);
        foreach($submissions as $submission) {
            $batchBuilder->addOperation([static::class, 'batchUpdateSubmission'], [$submission->id()]);
        }
        $handlers = $webform->getHandlers();
        /** @var \Drupal\webform\Plugin\WebformHandlerBase $handler */
        foreach($handlers as $handler) {
            $batchBuilder->addOperation([static::class, 'batchUpdateHandler'], [$webform->id(), $handler->getHandlerId()]);
        }

        $batchBuilder->addOperation([static::class, 'batchRemoveOldField'], [$webform->id()]);
        $batch = $batchBuilder->toArray();
        batch_set($batch);
    }

    public static function batchAddField($webformId, &$context) {
        $storage = &$context['sandbox'];
        $context['message'] = 'Adding new field(s)';
        if(empty($context['results']['webformId'])) {
            $context['results']['webformId'] = $webformId;
        }
        if(!empty($storage['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $storage['processing'] = true;
        $webform = Webform::load($webformId);
        $elements = $webform->getElementsInitializedAndFlattened();
        $rawElements = $webform->getElementsDecoded();
        $elementStateKey = 'sprowt_address_autocomplete.webform_switch_elements.' . $webformId;
        $oldElements = \Drupal::state()->get($elementStateKey, $elements);
        $keymapStateKey = 'sprowt_address_autocomplete.webform_switch_keymap.' . $webformId;
        $keyMap = \Drupal::state()->get($keymapStateKey);
        $keyMapFlip = array_flip($keyMap);
        foreach($elements as $key => $element) {
            if(in_array($key, array_keys($keyMapFlip))) {
                $oldKey = $keyMapFlip[$key];
                $oldElement = $oldElements[$oldKey];
                \Drupal\sprowt_settings\ArrayUtil::insertAfterKey($rawElements, $element["#webform_parents"], $oldElement, $oldKey);
            }
        }
        $webform->setElements($rawElements);
        $webform->save();
        $context['results']['keyMap'] = $keyMapFlip;
        $context['message'] = 'New field(s) added!';
        $context['finished'] = 1;
        $storage['processing'] = false;
    }

    public static function batchUpdateSubmission($submissionId, &$context) {
        $storage = &$context['sandbox'];
        if(empty($context['results']['submission_count'])) {
            $submission = WebformSubmission::load($submissionId);
            $submissions = \Drupal::entityTypeManager()->getStorage('webform_submission')->loadByProperties([
                'webform_id' => $submission->getWebform()->id()
            ]);
            $context['results']['submission_count'] = count($submissions);
        }
        if(empty($context['results']['submissions_completed'])) {
            $context['results']['submissions_completed'] = 0;
        }
        $currentNum =  $context['results']['submissions_completed'] + 1;
        $submissionCount = $context['results']['submission_count'];
        $context['message'] = "Updating {$currentNum} out of {$submissionCount} submissions";
        if(!empty($storage['processing'])) {
            $context['finished'] = 0;
            return;
        }
        if(empty($submission)) {
            $submission = WebformSubmission::load($submissionId);
        }
        $storage['processing'] = true;
        $keyMap = $context['results']['keyMap'] ?? [];
        foreach($keyMap as $oldKey => $newKey) {
            $data = $submission->getElementData($oldKey) ?? [];
            if(!empty($data)) {
                $fieldKeys = [
                    'address',
                    'address_2',
                    'city',
                    'state_province',
                    'postal_code',
                    'country'
                ];
                $newData = [];
                foreach ($data as $key => $value) {
                    if (in_array($key, $fieldKeys)) {
                        $newData[$key] = $value;
                    }
                }
                $submission->setElementData($newKey, $newData);
                $submission->save();
            }
        }
        $context['message'] = "Updated {$currentNum} out of {$submissionCount} submissions";
        $storage['processing'] = false;
        $context['finished'] = 1;
    }

    public static function batchUpdateHandler($webformId, $handlerId, &$context)
    {
        $storage = &$context['sandbox'];
        $context['message'] = 'Updating service bot handler(s)';
        if(!empty($storage['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $storage['processing'] = true;
        $webform = Webform::load($webformId);
        $handler = $webform->getHandler($handlerId);
        $keyMap = $context['results']['keyMap'] ?? [];
        \Drupal::moduleHandler()->invokeAll('sprowt_address_autocomplete_undo_replace_webform_address_fields_handler_update',  [
            $webform,
            $handler,
            $keyMap
        ]);
        $context['message'] = 'Updated handler: ' . $handlerId;
        $storage['processing'] = false;
        $context['finished'] = 1;
    }

    public static function batchRemoveOldField($webformId, &$context) {
        $storage = &$context['sandbox'];
        $context['message'] = 'Removing old address field(s)';
        if(!empty($storage['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $storage['processing'] = true;
        $webform = Webform::load($webformId);
        $elements = $webform->getElementsInitializedAndFlattened();
        $rawElements = $webform->getElementsDecoded();

        $keyMap = $context['results']['keyMap'] ?? [];
        foreach($keyMap as $oldKey => $newKey) {
            $oldElement = $elements[$oldKey];
            \Drupal\sprowt_settings\ArrayUtil::unsetValue($rawElements, $oldElement["#webform_parents"]);
            $webform->setElements($rawElements);
            $webform->save();
        }
        $context['message'] = 'Removed old address field(s)';
        $storage['processing'] = false;
        $context['finished'] = 1;
    }

    public static function batchFinished($success, $results, $operations) {
        $webformId = $results['webformId'];
        $webform = Webform::load($webformId);
        $elementStateKey = 'sprowt_address_autocomplete.webform_switch_elements.' . $webformId;
        $keymapStateKey = 'sprowt_address_autocomplete.webform_switch_keymap.' . $webformId;
        \Drupal::state()->delete($elementStateKey);
        \Drupal::state()->delete($keymapStateKey);
        if($success) {
            \Drupal::messenger()->addStatus("Webform address field(s) switched back");
        }
        else {
            $error_operation = reset($operations);
            $message = t('An error occurred while processing %error_operation with arguments: @arguments', array('%error_operation' => $error_operation[0], '@arguments' => print_r($error_operation[1], TRUE)));
            \Drupal::messenger()->addError($message);
        }
        \Drupal::messenger()->addWarning(static::handlerNoteMarkup($webform));
        $url = Url::fromRoute('entity.webform.edit_form', ['webform' => $webformId]);
        return new RedirectResponse($url->toString());
    }

    public function cancel(&$form, FormStateInterface $formState)
    {
        $formState->setRedirect('entity.webform.edit_form', [
            'webform' => \Drupal::routeMatch()->getParameter('webform')->id()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {

    }
}
