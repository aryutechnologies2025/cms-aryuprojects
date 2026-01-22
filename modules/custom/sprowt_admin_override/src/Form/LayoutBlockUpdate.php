<?php

namespace Drupal\sprowt_admin_override\Form;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\layout_builder\Form\ConfigureBlockFormBase;
use Drupal\layout_builder\Form\UpdateBlockForm;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder_hide\LayoutBuilderHideService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Sprowt Admin Override form.
 */
class LayoutBlockUpdate extends ConfigureBlockFormBase
{
    use LayoutBuilderHighlightTrait;

    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var SectionStorageManagerInterface
     */
    protected $sectionStorageManager;

    public static function create(ContainerInterface $container)
    {
        $instance = parent::create($container);
        $instance->entityTypeManager = $container->get('entity_type.manager');
        $instance->sectionStorageManager = $container->get('plugin.manager.layout_builder.section_storage');
        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_admin_override_layout_block_update';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $entityType = null, $entityId = null, $viewMode = null, $componentUuid = null)
    {
        $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
        if($entityType == 'entity_view_display') {
            $sectionStorage = $this->sectionStorageManager->load('defaults', [
                'display' => EntityContext::fromEntity($entity),
                'view_mode' => new Context(new ContextDefinition('string'), $viewMode ?? 'default'),
            ]);
        }
        else {
            $sectionStorage = $this->sectionStorageManager->load('overrides',[
                'entity' => EntityContext::fromEntity($entity),
                'view_mode' => new Context(new ContextDefinition('string'), $viewMode ?? 'default'),
            ]);
        }
        $sections = $sectionStorage->getSections();
        $delta = null;
        $section = null;
        $component = null;
        foreach($sections as $d => $possibleSection) {
            try {
                $component = $possibleSection->getComponent($componentUuid);
                $section = $possibleSection;
                $delta = $d;
                break;
            }
            catch (\InvalidArgumentException $e) {
                $component = null;
            }
        }

        $inlineBlock = $component->getPlugin();
        if(method_exists($inlineBlock, 'returnEntity')) {
            /** @var BlockContent $block */
            $block = $inlineBlock->returnEntity();
            $form['blockId'] = [
                '#type' => 'item',
                '#title' => 'Block ID',
                '#markup' => $block->id(),
            ];
            $form['blockRevisionId'] = [
                '#type' => 'item',
                '#title' => 'Block Revision ID',
                '#markup' => $block->getRevisionId(),
            ];
            $form['blockUuid'] = [
                '#type' => 'item',
                '#title' => 'Block UUID',
                '#markup' => $block->uuid(),
            ];
        }

        if($entity->getEntityType()->isRevisionable()) {
            $form['revision'] = [
                '#type' => 'details',
                '#title' => 'Set revision message for ' . $entity->getEntityType()->getLabel(),
                '#open' => true,
            ];
            $form['revision']['revisionMessage'] = [
                '#type' => 'textarea',
                '#title' => 'Revision message',
                '#default_value' => 'Layout block updated',
            ];
        }


        $form['#attributes']['data-layout-builder-target-highlight-id'] = $this->blockUpdateHighlightId($componentUuid);
        $form = $this->doBuildForm($form, $form_state, $sectionStorage, $delta, $component);

        $moduleHandler = \Drupal::service('module_handler');
        if($moduleHandler->moduleExists('layout_builder_hide')) {
            /** @var LayoutBuilderHideService $hideService */
            $hideService = \Drupal::service('layout_builder_hide.service');
            $hideService->blockFormAlter($form, $form_state);
        }

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $entity = $this->sectionStorage->getContextValue('entity');
        if($entity->getEntityType()->isRevisionable()) {
            $values = $form_state->getValues();
            $revisionValues = $values['revision'];
            if(!empty($revisionValues['revisionMessage'])) {
                $entity->setNewRevision();
                $entity->setRevisionLogMessage($revisionValues["revisionMessage"]);
                $this->sectionStorage->setContextValue('entity', $entity);
            }
        }
        parent::submitForm($form, $form_state);
        $this->sectionStorage->save();
        $this->layoutTempstoreRepository->get($this->sectionStorage)->save();
        $this->layoutTempstoreRepository->delete($this->sectionStorage);
    }

    /**
     * {@inheritdoc}
     */
    protected function submitLabel() {
        return $this->t('Update');
    }

}
