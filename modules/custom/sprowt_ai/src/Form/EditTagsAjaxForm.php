<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ctools\Form\AjaxFormTrait;
use Drupal\sprowt_ai\Entity\Context;
use Drupal\sprowt_ai\Entity\SprowtAiExample;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a Sprowt AI form.
 */
class EditTagsAjaxForm extends FormBase
{

    protected $entity;

    use AjaxFormHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_edit_tags_ajax';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $entityTypeId = null, $entityId = null): array
    {

        /** @var Context|SprowtAiExample $entity */
        $entity = \Drupal::entityTypeManager()->getStorage($entityTypeId)->load($entityId);

        $this->entity = $entity;
        $tagList = $entity->get('tags');
        $currentVal = [];
        foreach ($tagList as $tag) {
            $currentVal[] = $tag->target_id;
        }


        $tagOpts = [];

        $form['vid'] = [
            '#type' => 'value',
            '#value' => $entity::$vocab
        ];

        $tags = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
            'vid' => $form['vid']['#value'],
        ]);
        foreach ($tags as $tag) {
            $tagOpts[$tag->id()] = $tag->label();
        }
        asort($tagOpts);

        $form['tags'] = [
            '#type' => 'select',
            '#title' => $this->t('Tags'),
            '#options' => $tagOpts,
            '#default_value' => $currentVal,
            '#empty_value' => '',
            '#empty_option' => $this->t('- Select or Add -'),
            '#multiple' => true,
            '#attributes' => [
                'data-tags' => 'true',
            ]
        ];

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#ajax' => [
                    'callback' => '::ajaxSubmit',
                    'disable-refocus' => true
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
        $vid = $form_state->getValue('vid');
        $value = $form_state->getValue('tags');
        $element = &$form['tags'];
        if(is_string($value)) {
            $value = trim($value);
            if(!empty($value) && $value != '_none') {
                $value = [$value => $value];
            }
            else {
                $value = [];
            }
        }

        $options = $element["#options"] ?? [];
        $set = false;
        foreach(array_keys($value) as $tagId) {
            if(empty($tagId) || $tagId == '_none') {
                continue;
            }
            $tag = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tagId);
            if (empty($tag)) {
                $tag = Term::create([
                    'name' => $tagId,
                    'vid' => $vid
                ]);
                $tag->save();
                unset($value[$tagId]);
                $value[$tag->id()] = $tag->id();
                $options[$tag->id()] = $tag->label();
                $set = true;
            }
            $tvid = $tag->get('vid')->target_id ?? '';
            if ($tvid != $vid) {
                $vocab = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vid);
                $form_state->setError($element, 'You can only add tags from the vocabulary: ' . $vocab->label() . '.');
            }
        }
        if(!empty($set)) {
            $element['#value'] = $value;
            $element['#options'] = $options;
            $form_state->setValueForElement($element, $value);
            $error = $form_state->getError($element);
            if(!empty($error)) {
                $errorStr = (string) $error;
                if(strpos($errorStr, 'The submitted value') === 0
                    && strpos($errorStr, 'element is not allowed.') !== false
                ) {
                    $errors = $form_state->getErrors();
                    $form_state->clearErrors();
                    foreach ($errors as $key => $arrayError) {
                        if ($error == $arrayError) {
                            unset($errors[$key]);
                        }
                        else {
                            $form_state->setErrorByName($key, $arrayError);
                        }
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $entity = $this->entity;
        $value = $form_state->getValue('tags');
        $vid = $form_state->getValue('vid');
        $targetIds = array_keys($value);
        $newVal = [];
        foreach ($targetIds as $targetId) {
            $newVal[] = [
                'target_id' => $targetId,
            ];
        }
        $entity->set('tags', $newVal);
        $entity->save();
        $this->entity = $entity;
    }


    public static function tagListOutput($linkList, $entity) {

        $dialogHref = '/sprowt-ai/edit-tags-ajax/'.$entity->getEntityTypeId().'/' . $entity->id();
        $dialogOptions = [
            'height' => '400',
            'width' => '800',
            'draggable' => true,
            'resizable' => true,
            'autoResize' => false,
        ];

        $link = "<a style='margin-left: 15px;' href='{$dialogHref}' class='use-ajax button button--extrasmall tags-field-edit'" .
                " data-dialog-type='modal' data-dialog-options='".json_encode($dialogOptions)."'" .
            ">Edit</a>";

        $output = "<span class='tags-field-wrap' data-entity-type='{$entity->getEntityTypeId()}' data-entity-id='{$entity->id()}'><span class='tags-field'>$linkList</span>".
            $link .
            "</span>";
        $output = Markup::create($output);
        return $output;
    }

    protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
        $entity = $this->entity;
        $tags = $entity->get('tags')->referencedEntities();
        $links = [];
        /** @var Term $tag */
        foreach ($tags as $tag) {
            $link = '<a href="'.$tag->toUrl()->toString().'" target="_blank">' . $tag->label() . '</a>';
            $links[] = $link;
        }
        $linkList = implode(', ', $links);
        $output = static::tagListOutput($linkList, $entity);

        $response = new AjaxResponse();
        $selector = '.tags-field-wrap[data-entity-type="'.$entity->getEntityTypeId().'"][data-entity-id="'.$entity->id().'"]';
        $response->addCommand(new ReplaceCommand($selector, $output));
        $response->addCommand(new CloseModalDialogCommand());
        return $response;
    }
}
