<?php

namespace Drupal\section_library\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\section_library\Entity\SectionLibraryTemplateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to preview a section.
 *
 * @internal
 *   Controller classes are internal.
 */
class PreviewSectionController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Builds "PreviewSectionController" controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   Context repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ContextRepositoryInterface $context_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('context.repository')
    );
  }

  /**
   * Title callback (section label).
   *
   * @param \Drupal\section_library\Entity\SectionLibraryTemplateInterface $section_library_template
   *   The section library template.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   Page title.
   */
  public function title(SectionLibraryTemplateInterface $section_library_template) {
    return $section_library_template->label();
  }

  /**
   * Build preview for section library template.
   *
   * @param \Drupal\section_library\Entity\SectionLibraryTemplateInterface $section_library_template
   *   The section library template.
   *
   * @return array
   *   A render array.
   */
  public function build(SectionLibraryTemplateInterface $section_library_template) {
    $source_entity_type = $section_library_template->hasField('entity_type') && !$section_library_template->get('entity_type')->isEmpty() ? $section_library_template->get('entity_type')->getString() : NULL;
    $source_entity_id = $section_library_template->hasField('entity_id') && !$section_library_template->get('entity_id')->isEmpty() ? $section_library_template->get('entity_id')->getString() : NULL;
    // Use source entity when possible, template as fallback.
    // @note \Drupal::service('layout_builder.sample_entity_generator')
    // ->get($entity_type_id, $entity_bundle_id) could not be used due required
    // info is not available, that should be included into main section library
    // template to use example content instead, example: node, article.
    // Also @see "https://drupal.org/i/3263496" where entity_type can be
    // "entity_view_display".
    $source_entity = !empty($source_entity_type) && !empty($source_entity_id) ? $this->entityTypeManager->getStorage($source_entity_type)->load($source_entity_id) : NULL;
    $context_entity = $source_entity instanceof ContentEntityInterface ? $source_entity : $section_library_template;
    $contexts = [
      'view_mode' => new Context(ContextDefinition::create('string'), 'default'),
      'entity' => EntityContext::fromEntity($context_entity),
    ] + $this->contextRepository->getRuntimeContexts(array_keys($this->contextRepository->getAvailableContexts()));

    $label = new TranslatableMarkup('@entity being viewed', [
      '@entity' => $context_entity->getEntityType()->getSingularLabel(),
    ]);
    $contexts['layout_builder.entity'] = EntityContext::fromEntity($context_entity, $label);

    $sections = $section_library_template->get('layout_section')->getSections();
    $build = [];
    foreach ($sections as $delta => $section) {
      $build[$delta] = $section->toRenderArray($contexts);
    }
    $cacheability = new CacheableMetadata();
    $cacheability->applyTo($build);
    return $build;
  }

}
