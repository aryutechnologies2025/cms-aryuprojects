<?php

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Url;
use Drupal\inline_block_content\Plugin\Block\InlineBlock;
use Drupal\layout_builder\LayoutTempstoreRepository;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\sprowt_ai\AiService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RegenerateLayoutBlockForm extends ConfirmFormBase
{

    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var SectionStorageManagerInterface
     */
    protected $sectionStorageManager;

    /**
     * The section storage.
     *
     * @var \Drupal\layout_builder\SectionStorageInterface
     */
    protected $sectionStorage;

    /**
     * The field delta.
     *
     * @var int
     */
    protected $delta;

    /**
     * The UUID of the component.
     *
     * @var string
     */
    protected $uuid;

    /**
     * The parent viewMode.
     *
     * @var string
     */
    protected $viewMode;

    /**
     * Entity containing the layout
     */
    protected $parent;


    /**
     * Component block
     */
    protected $block;

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_regenerate_layout_block';
    }

    public static function create(ContainerInterface $container)
    {
        $instance = parent::create($container);
        $instance->entityTypeManager = $container->get('entity_type.manager');
        $instance->sectionStorageManager = $container->get('plugin.manager.layout_builder.section_storage');
        return $instance;
    }


    public function getQuestion() {
        return $this->t('Are you sure?');
    }

    public function getDescription()
    {
        return $this->t('This will re-generate content for all prompted fields. This action cannot be undone.');
    }

    /**
     * Returns the route to go to if the user cancels the action.
     *
     * @return \Drupal\Core\Url
     *   A URL object.
     */
    public function getCancelUrl() {
        $entity = $this->parent;
        if(empty($entity)) {
            return Url::fromRoute('<front>');
        }

        return $entity->toUrl();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $entity_type = null, $entity_id = null, $viewMode = null, $componentUuid = null)
    {
        $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
        if($entity_type == 'entity_view_display') {
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

        $this->sectionStorage = $sectionStorage;
        $this->delta = $delta;
        $this->uuid = $component->getUuid();
        $this->block = $component->getPlugin();
        $this->parent = $entity;
        $this->viewMode = $viewMode;

        return parent::buildForm($form, $form_state);
    }


    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $block = $this->block;
        $parent = $this->parent;
        $batchBuilder = new BatchBuilder();
        $batchBuilder->setTitle('Regenerating content for block: "' . $block->label() . '"');
        $batchBuilder->setInitMessage('Processing...');

        $batchBuilder->addOperation([static::class, 'regenerateContent'], [
            $parent->getEntityTypeId(),
            $parent->id(),
            $this->viewMode,
            $this->uuid,
            $this->delta
        ]);

        $batchBuilder->setFinishCallback([static::class, 'batchFinished']);
        $batchBuilder->setProgressive(true);
        batch_set($batchBuilder->toArray());
    }


    public static function regenerateContent($entityType, $entityId, $viewMode, $componentUuid, $delta, &$context)
    {

        $sectionStorageManager = \Drupal::service('plugin.manager.layout_builder.section_storage');

        $entity = \Drupal::entityTypeManager()->getStorage($entityType)->load($entityId);
        /** @var SectionStorageInterface $sectionStorage */
        if($entityType == 'entity_view_display') {
            $sectionStorage = $sectionStorageManager->load('defaults', [
                'display' => EntityContext::fromEntity($entity),
                'view_mode' => new Context(new ContextDefinition('string'), $viewMode ?? 'default'),
            ]);
        }
        else {
            $sectionStorage = $sectionStorageManager->load('overrides',[
                'entity' => EntityContext::fromEntity($entity),
                'view_mode' => new Context(new ContextDefinition('string'), $viewMode ?? 'default'),
            ]);
        }

        $section = $sectionStorage->getSection($delta);
        $component = $section->getComponent($componentUuid);
        $block = $component->getPlugin();


        /** @var AiService $service */
        $service = \Drupal::service('sprowt_ai.service');
        if($block instanceof InlineBlock) {
            $inlineBlock = $block->returnEntity();
            $updated = $service->generateContentForEntity($inlineBlock);
            $block->setConfigurationValue('block_serialized', serialize($inlineBlock));
            $block->saveBlockContent();
        }
        else {
            $updated = $service->generateContentForEntity($block);
        }

        if($updated) {
            $configuration = $block->getConfiguration();
            $section->getComponent($componentUuid)->setConfiguration($configuration);
            $sectionStorage->save();
        }


        $context['results']['redirectUrl'] = $entity->toUrl('edit-form')->toString();
        $context['results']['updated'] = $updated;
    }

    public static function batchFinished($success, $results, $operations) {


        $url = $results['redirectUrl'];
        $updated = !empty($results['updated']);
        $message = $updated ? 'Content regenerated successfully!' : 'Content was not regenerated.';
        \Drupal::messenger()->addStatus($message);

        return new RedirectResponse($url);
    }
}
