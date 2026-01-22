<?php

namespace Drupal\section_library\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\section_library\DeepCloningTrait;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a form for adding a section to the library.
 */
class AddSectionToLibraryForm extends ContentEntityForm {

  use AjaxFormHelperTrait;
  use LayoutBuilderHighlightTrait;
  use DeepCloningTrait;
  use LayoutRebuildTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected LayoutTempstoreRepositoryInterface $layoutTempstoreRepository;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected SectionStorageInterface $sectionStorage;

  /**
   * The field delta.
   *
   * @var int
   */
  protected int $delta;

  /**
   * The template type - section or template.
   *
   * @var string
   */
  protected string $templateType;

  /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuidGenerator;

  /**
   * The section library config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a new AddSectionToLibraryForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID generator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration entity manager.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    LayoutTempstoreRepositoryInterface $layout_tempstore_repository,
    UuidInterface $uuid,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigManagerInterface $config_manager,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->uuidGenerator = $uuid;
    $this->entityTypeManager = $entity_type_manager;
    $this->entity = $this->entityTypeManager->getStorage('section_library_template')->create([]);
    $this->operation = 'create';
    $this->config = $config_manager->getConfigFactory()->get('section_library.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('layout_builder.tempstore_repository'),
      $container->get('uuid'),
      $container->get('entity_type.manager'),
      $container->get('config.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    // Ensure we act on the translation object corresponding to the current form
    // language.
    $this->initFormLangcodes($form_state);
    $langcode = $this->getFormLangcode($form_state);
    $this->entity = $this->entity->hasTranslation($langcode) ? $this->entity->getTranslation($langcode) : $this->entity->addTranslation($langcode);

    $form_display = EntityFormDisplay::collectRenderDisplay($this->entity, $this->getOperation());
    $this->setFormDisplay($form_display, $form_state);

    // Skip EntityForm::init().
    // Flag that this form has been initialized.
    $form_state->set('entity_form_initialized', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $template_type = 'section', ?SectionStorageInterface $section_storage = NULL, ?int $delta = NULL) {
    $this->sectionStorage = $section_storage;
    $this->delta = $delta;
    $this->templateType = $template_type;

    $form = parent::buildForm($form, $form_state);

    if (isset($form['actions']['submit'])) {
      // Use configured labels.
      $sectionLabel = strtolower($this->config->get('section_label') ?? 'section');
      $templateLabel = strtolower($this->config->get('template_label') ?? 'template');
      if ($this->templateType === 'template') {
        $form['actions']['submit']['#value'] = $this->t('Add :templateLabel', [':templateLabel' => $templateLabel]);
      }
      else {
        $form['actions']['submit']['#value'] = $this->t('Add :sectionLabel', [':sectionLabel' => $sectionLabel]);
      }

      if ($this->isAjax()) {
        $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
      }
    }

    $form['#attributes']['data-layout-builder-target-highlight-id'] = $this->sectionAddHighlightId($delta);

    // Mark this as an administrative page for JavaScript ("Back to site" link).
    $form['#attached']['drupalSettings']['path']['currentPathIsAdmin'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    if ($this->templateType === 'template') {
      $sections = $this->sectionStorage->getSections();
      $deep_cloned_section = $this->deepCloneSections($sections);
    }
    else {
      $current_section = $this->sectionStorage->getSection($this->delta);
      $deep_cloned_section = $this->deepCloneSection($current_section);
    }

    $layout_entity = NULL;
    foreach (['entity', 'display'] as $name) {
      try {
        $layout_entity = $this->sectionStorage->getContextValue($name);
      }
      catch (ContextException $e) {
        // Let's try again.
      }
    }
    if (!$layout_entity) {
      throw new ContextException('', 0, $e);
    }

    $entity->set('layout_section', $deep_cloned_section);
    $entity->set('type', $this->templateType);
    $entity->set('entity_type', $layout_entity->getEntityTypeId());
    $entity->set('entity_id', $layout_entity->id());

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->layoutTempstoreRepository->set($this->sectionStorage);
    $form_state->setRedirectUrl($this->sectionStorage->getLayoutBuilderUrl());
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state): AjaxResponse {
    return $this->rebuildAndClose($this->sectionStorage);
  }

  /**
   * Callback for setting the route title.
   *
   * @param string $template_type
   *   Template type from the route.
   *
   * @return string
   *   Title to use for the route.
   */
  public function titleCallback(string $template_type = 'section'): string {
    $label = $this->config->get($template_type . '_label') ?? 'Section';
    return $this->t('Add %type to Library', ['%type' => $label]);
  }

}
