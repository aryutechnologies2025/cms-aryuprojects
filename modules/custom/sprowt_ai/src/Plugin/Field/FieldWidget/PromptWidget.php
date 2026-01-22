<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;
use Drupal\sprowt_ai\AiService;
use Drupal\sprowt_ai\Element\ClaudePrompt;
use Drupal\sprowt_ai\Entity\AiSystem;
use Drupal\sprowt_ai\Plugin\Field\FieldType\PromptItem;
use Drupal\sprowt_ai_prompt_library\Entity\Prompt;

/**
 * Defines the 'sprowt_ai_prompt' field widget.
 *
 * @FieldWidget(
 *   id = "sprowt_ai_prompt",
 *   label = @Translation("AI Prompt"),
 *   field_types = {"sprowt_ai_prompt"},
 * )
 */
class PromptWidget extends WidgetBase
{

    public static $statePrefix = 'sprowt_ai.prompt_widget';

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array
    {

        $entity = $items->getEntity();
        $defaultSystemId = null;
        $defaultSystem = sprowt_ai_default_system($entity);
        if($defaultSystem instanceof AiSystem) {
            $defaultSystemId = $defaultSystem->id();
        }

        $valueElement = array_merge($element, [
            '#type' => 'claude_prompt',
            '#system' => $defaultSystemId,
            '#max_tokens' => $this->fieldDefinition->getSetting('max_tokens') ?? 1024,
            '#default_value' => $items[$delta]->value ?? NULL,
            '#attributes' => [
                'data-delta' => $delta,
            ],
            '#prompt_options' => [
                'widgetFieldName' => $this->fieldDefinition->getName(),
                'temperature' => $this->fieldDefinition->getSetting('temperature') ?? 1.0
            ]
        ]);


        $entity = $items->getEntity();

        if($entity instanceof ContentEntityBase) {
            $valueElement['#prompt_options']['references'] = AiService::extractReferencesFromEntity($entity);
        }

        $valueElement['#prompt_options']['entity_type'] = $entity->getEntityTypeId();
        $valueElement['#prompt_options']['entity_uuid'] = $entity->uuid();

        $tokenData = [
            $entity->getEntityTypeId() => $entity->uuid()
        ];
        $routeMatch = \Drupal::routeMatch();
        $routeEntityType = $routeMatch->getParameter('entityType') ?? null;
        $routeEntityId = $routeMatch->getParameter('entityId') ?? null;
        if(!empty($routeEntityId) && !empty($routeEntityType) && !isset($tokenData[$routeEntityType])) {
            $tokenData[$routeEntityType] = $routeEntityId;
        }
        $routeNode = $routeMatch->getParameter('node') ?? null;
        if(!empty($routeNode) && !isset($tokenData['node'])) {
            if($routeNode instanceof NodeInterface) {
                $tokenData['node'] = $routeNode->id();
            }
            else {
                $tokenData['node'] = $routeNode;
            }
        }
        $valueElement['#prompt_options']['tokenData'] = $tokenData;

        $insertField = $this->fieldDefinition->getSetting('insertField');
        if(!empty($insertField)) {
            $form['#process'][] = [
                $this, 'processInsertField'
            ];
        }

        $element['#attached']['library'][] = 'sprowt_ai/widget';

        $element['value'] = $valueElement;
        return $element;
    }

    public function processInsertField($element, FormStateInterface $form_state, $form) {
        if(!empty($element["#parents"][0]) && $element["#parents"][0] == 'default_value_input') {
            //default value form. No insert field provided
            return;
        }
        $insertField = $this->fieldDefinition->getSetting('insertField');
        $fieldElements = &$element[$this->fieldDefinition->getName()]['widget'];
        $parents = [
            $this->fieldDefinition->getName(),
            'widget'
        ];
        $fieldId = implode('--', [
            $this->fieldDefinition->getTargetEntityTypeId(),
            $this->fieldDefinition->getName()
        ]);
        foreach($fieldElements as $delta => $value) {
            if(is_int($delta) || preg_match('/^[\d]+$/', $delta)) {
                $fieldElement = $value['value'];
                $fieldElementId = $fieldId . '--' . $delta;
                $fieldElementParents = array_merge($parents, [$delta, 'value']);
                if(isset($element[$insertField])) {
                    $insertFieldElements = $element[$insertField]['widget'];
                }
                else {
                    $insertFieldElements = [];
                }
                if(isset($insertFieldElements[$delta])) {
                    $insertFieldElementParents = [$insertField, 'widget', $delta];
                    $selector = '[data-prompt-selector="' . $fieldElementId . '"]';
                    $insertFieldElement = &$insertFieldElements[$delta];
                    $insertWidgetValueParents = _sprowt_ai_get_insert_element_parents($insertFieldElement, $insertFieldElementParents);
                    $insertFieldValueElement = NestedArray::getValue($element, $insertWidgetValueParents);
                    $insertFieldValueElement['#attributes']['data-prompt-selector'] = $fieldElementId;
                    NestedArray::setValue($element, $insertWidgetValueParents, $insertFieldValueElement);
                    $fieldElement['#insert'] = $insertWidgetValueParents;
                }
                else {
                    $fieldElement['generate'] = [
                        '#type' => 'checkbox',
                        '#title' => 'Generate content on save'
                    ];
                    $element['#validate'][] = [$this, 'entityFormValidate'];
                }

                NestedArray::setValue($element, $fieldElementParents, $fieldElement);
            }
        }
        return $element;
    }

    public function entityFormValidate(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {
        $field_name = $this->fieldDefinition->getName();
        $path = array_merge($form['#parents'], [$field_name]);
        $key_exists = NULL;
        $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);
        if($key_exists) {
            $promptAttrs = static::getFromTemporaryStorage('sprowt_ai_prompt_field_widget') ?? [];
            foreach ($values as $delta => $value) {
                $input = NestedArray::getValue($form_state->getUserInput(), array_merge($path, [$delta, 'value']));
                $generate = $input['generate'];
                if(empty($generate)) {
                    continue;
                }
                $options = json_decode($input['options'], true);
                $key = implode('::', [
                    $options['entity_type'],
                    $options['entity_uuid'],
                    $field_name,
                ]);
                if(empty($promptAttrs[$key])) {
                    $attr = [
                        'fieldName' => $field_name,
                        'entity_type' => $options['entity_type'],
                        'entity_uuid' => $options['entity_uuid'],
                        'promptText' => $value['value'],
                        'options' => $options
                    ];
                    $promptAttrs[$key] = $attr;
                }
            }
            static::setToTemporaryStorage('sprowt_ai_prompt_field_widget', $promptAttrs);
        }
    }

    public static function setToTemporaryStorage($key, $value)
    {
        $stateKey = implode('::', [
            static::$statePrefix,
            $key
        ]);
        \Drupal::state()->set($stateKey, $value);
    }

    public static function getFromTemporaryStorage($key) {
        $stateKey = implode('::', [
            static::$statePrefix,
            $key
        ]);
        return \Drupal::state()->get($stateKey, null);
    }

    public static function clearTemporaryStorage($key)
    {
        $stateKey = implode('::', [
            static::$statePrefix,
            $key
        ]);
        \Drupal::state()->delete($stateKey);
    }

    public static function postEntitySaveUpdate(EntityInterface $entity, $newRevision = false)
    {
        $prompts = static::getFromTemporaryStorage('sprowt_ai_prompt_field_widget') ?? [];
        /** @var AiService $service */
        $service = \Drupal::service('sprowt_ai.service');
        $update = false;
        foreach($prompts as $key => $promptInfo) {
            if($entity->uuid() == $promptInfo['entity_uuid'] && $entity->getEntityTypeId() == $promptInfo['entity_type']) {
                $options = $promptInfo['options'];
                $fieldName = $promptInfo['fieldName'];
                $updated = $service->generateContentWithPromptEntityField($entity, $fieldName, $options, false, $newRevision);
                $fieldSettings = $entity->getFieldDefinition($fieldName)->getSettings();
                $insertField = $fieldSettings['insertField'] ?? null;
                if(!empty($insertField) && $updated) {
                    $insertFieldDefinition = $entity->getFieldDefinition($insertField);
                    $insertFieldName = $insertFieldDefinition->getName();
                    $entityType = $entity->getEntityTypeId();
                    $label = $entity->label();
                    \Drupal::messenger()->addStatus("Content generated for field: $insertFieldName on $entityType, \"{$label}\"");
                }
                unset($prompts[$key]);
                $update = true;
            }
        }
        if(!empty($update)) {
            if(empty($prompts)) {
                static::clearTemporaryStorage('sprowt_ai_prompt_field_widget');
            }
            else {
                static::setToTemporaryStorage('sprowt_ai_prompt_field_widget', $prompts);
            }
            $entity->save();
        }
    }
    public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
        $values = parent::massageFormValues($values, $form, $form_state);
        $newValues = [];
        foreach ($values as $key => $value) {
            if(is_array($value['value']) && isset($value['value']['promptValueWrapper'])) {
                $value['value'] = $value["value"]["promptValueWrapper"]["textarea"] ?? '';
            }
            $newValues[$key] = $value;
        }
        return $newValues;
    }
}
