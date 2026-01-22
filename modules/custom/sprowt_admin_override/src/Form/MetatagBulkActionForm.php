<?php

declare(strict_types=1);

namespace Drupal\sprowt_admin_override\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\metatag\MetatagManager;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Sprowt Admin Override form.
 */
class MetatagBulkActionForm extends FormBase
{

    /**
     * The tempstore factory.
     *
     * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
     */
    protected $tempStoreFactory;

    /**
     * User.
     *
     * @var \Drupal\Core\Session\AccountInterface
     */
    protected $currentUser;

    /**
     * @var MetatagManager
     */
    protected $metatagManager;

    protected $entities;

    protected $step = 1;

    public function __construct(
        PrivateTempStoreFactory $temp_store_factory,
        AccountInterface $current_user,
        MetatagManager $metatag_manager
    )
    {
        $this->tempStoreFactory = $temp_store_factory;
        $this->currentUser = $current_user;
        $this->metatagManager = $metatag_manager;
    }


    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('tempstore.private'),
            $container->get('current_user'),
            $container->get('metatag.manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_admin_override_metatag_bulk_action';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $this->entities = $this->tempStoreFactory->get('edit_meta_tags_entity_ids')
            ->get($this->currentUser->id());

        switch($this->step) {
            case 1:
                $form['#title'] = $this->t('Select metatags to edit');
                $form['#description'] = $this->t('You can select multiple tags to edit at once. This will apply the changes to all selected entities.');
                $form['tagSelect'] = $this->metatagSelectForm($form, $form_state);
                $submitText = $this->t('Next');
                break;
            case 2:
                $form['#title'] = $this->t('Edit selected metatags');
                $form['tagValues'] = $this->metatagForm($form, $form_state);
                $submitText = $this->t('Next');
                break;
        }

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $submitText,
            ],
        ];

        return $form;
    }

    public function metatagForm(array &$completeForm, FormStateInterface $formState)
    {
        $selectedTagsWithGroups = $this->tempStoreFactory->get('edit_meta_tags_selected_tags')
            ->get($this->currentUser->id());
        $selectedGroups = [];
        $selectedTags = [];
        if (!empty($selectedTagsWithGroups)) {
            foreach ($selectedTagsWithGroups as $groupName => $tagNames) {
                $selectedGroups[] = $groupName;
                $selectedTags = array_merge($selectedTags, $tagNames);
            }
        }

        $selectedTags = array_unique($selectedTags);

        $element = [
            '#tree' => true,
            '#open' => true,
            '#title' => 'Metatags',
        ];
        $form = $this->metatagManager->form(
            [],
            $element,
            [],
            $selectedGroups,
            $selectedTags
        );

        foreach($form as $groupName => $group) {
            if (isset($group['#type']) && $group['#type'] === 'details') {
                $form[$groupName]['#open'] = true;
                $hasFields = false;
                foreach ($group as $tagFieldName => $tagField) {
                    if (is_array($tagField) && isset($tagField['#type'])) {
                        $hasFields = true;
                        break;
                    }
                }
                if(!$hasFields) {
                    unset($form[$groupName]);
                }
            }
        }

        $stop = true;

        return $form;
    }

    public function metatagSelectForm(array &$completeForm, FormStateInterface $formState)
    {
        $tagGroups = $this->metatagManager->sortedGroupsWithTags();

        $form = [
            '#tree' => true,

        ];
        foreach ($tagGroups as $groupName => $tagGroup) {
            $tags = $tagGroup['tags'];
            $form[$groupName] = [
                '#type' => 'details',
                '#title' => $tagGroup['label'],
                '#description' => $tagGroup['description'],
                '#open' => true,
            ];



            $form[$groupName]['tags'] = [
                '#type' => 'tableselect',
                '#title' => $this->t('Select tags'),
                '#header' => [
                    'label' => $this->t('Tag'),
                    'description' => $this->t('Description'),
                ],
                '#options' => $tags,
                '#multiple' => TRUE,
                '#empty' => $this->t('No tags available.'),
            ];
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        // @todo Validate the form here.
        // Example:
        // @code
        //   if (mb_strlen($form_state->getValue('message')) < 10) {
        //     $form_state->setErrorByName(
        //       'message',
        //       $this->t('Message should be at least 10 characters.'),
        //     );
        //   }
        // @endcode
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        switch($this->step) {
            case 1:
                // Save the selected tags to the temp store.
                $selectedTags = [];
                foreach ($form_state->getValue('tagSelect') as $group => $tags) {
                    if (isset($tags['tags']) && is_array($tags['tags'])) {
                        $selectedTags[$group] = array_filter($tags['tags']);
                    }
                }
                $this->tempStoreFactory->get('edit_meta_tags_selected_tags')
                    ->set($this->currentUser->id(), $selectedTags);
                $this->step = 2;
                $form_state->setRebuild();
                break;
            case 2:
                $values = $form_state->getValue('tagValues');
                $tagValues = [];
                foreach ($values as $groupName => $tags) {
                    $tagValues = array_merge($tagValues, $tags);
                }

                $batchBuilder = new BatchBuilder();
                $batchBuilder->setTitle("Saving metatag values")
                    ->setInitMessage("Saving metatag values...")
                    ->setFinishCallback([static::class, 'batchFinished']);
                /** @var Node $entity */
                foreach($this->entities as $entity) {
                    $batchBuilder->addOperation([static::class, 'setMetatagValuesBatch'], [
                        $entity->getEntityTypeId(),
                        $entity->id(),
                        $tagValues,
                    ]);
                }
                batch_set($batchBuilder->toArray());
                break;
        }
    }

    public static function setMetatagValuesBatch($entityTypeId, $entityId, $tagValues, &$context) {
        $entity = \Drupal::entityTypeManager()
            ->getStorage($entityTypeId)
            ->load($entityId);
        /** @var MetatagManager $metaTagManager */
        $metaTagManager = \Drupal::service('metatag.manager');
        $tagsFromEntity = $metaTagManager->tagsFromEntity($entity);
        $default_tags = $metaTagManager->defaultTagsFromEntity($entity);
        $current_tags = $tagValues + $tagsFromEntity;
        $tagsToSave = [];
        foreach ($current_tags as $tag_id => $tag_value) {
            if (!isset($default_tags[$tag_id]) || ($tag_value != $default_tags[$tag_id])) {
                $tagsToSave[$tag_id] = $tag_value;
            }
        }
        // Sort the values prior to saving. so that they are easier to manage.
        ksort($tagsToSave);
        $fieldDefinitions = $entity->getFieldDefinitions();
        $metatagFieldDefinition = null;
        foreach ($fieldDefinitions as $fieldDefinition) {
            if($fieldDefinition->getType() == 'metatag') {
                $metatagFieldDefinition = $fieldDefinition;
                break;
            }
        }
        if(isset($metatagFieldDefinition)) {
            $fieldName = $metatagFieldDefinition->getName();
            $entity->set($fieldName, [
                'value' => metatag_data_encode($tagsToSave),
            ]);
            $entity->save();
            if(empty($context['results'])) {
                $context['results'] = [];
            }
            $context['results'][] = [
                'entity_type' => $entityTypeId,
                'entity_id' => $entityId
            ];
            $context['message'] = t('Metatags updated for @entity_type: "@title"', [
                '@entity_type' => $entityTypeId,
                '@title' => $entity->label()
            ]);
        }
    }

    public static function batchFinished($success, $results, $operations) {
        \Drupal::messenger()->addStatus(t('Metatags updated successfully.'));
        foreach ($results as $result) {
            $entity = \Drupal::entityTypeManager()->getStorage($result['entity_type'])->load($result['entity_id']);
            $link = $entity->toUrl('edit-form')->toString();
            \Drupal::messenger()->addStatus(t('Updated <a href="@link" target="_blank">@title</a>', [
                '@link' => $link,
                '@title' => $entity->label(),
            ]));
        }

        \Drupal::service('tempstore.private')
            ->get('edit_meta_tags_entity_ids')
            ->delete(\Drupal::currentUser()->id());
        \Drupal::service('tempstore.private')
            ->get('edit_meta_tags_selected_tags')
            ->delete(\Drupal::currentUser()->id());
    }

}
