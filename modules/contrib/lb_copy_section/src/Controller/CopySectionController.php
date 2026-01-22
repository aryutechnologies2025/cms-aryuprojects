<?php

namespace Drupal\lb_copy_section\Controller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\lb_copy_section\DeepCloningTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class CopySectionController.
 *
 * Performs the copy and paste actions for layout builder sections.
 */
class CopySectionController implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use LayoutRebuildTrait;
  use DeepCloningTrait;
  use StringTranslationTrait;

  /**
   * Private storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStore;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * CopySectionController constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp_store
   *   Private storage.
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID generator.
   */
  public function __construct(PrivateTempStoreFactory $private_temp_store, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, UuidInterface $uuid) {
    $this->privateTempStore = $private_temp_store->get('lb_copy_section');
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->uuidGenerator = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('layout_builder.tempstore_repository'),
      $container->get('uuid'),
    );
  }

  /**
   * Copy a given section.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   A render array.
   */
  public function copy(SectionStorageInterface $section_storage, $delta) {
    if (isset($section_storage) && isset($delta)) {
      $section = $section_storage->getSections()[$delta];
      $layout_settings = $section->getLayoutSettings();
      $section_label = !empty($layout_settings['label']) ? $layout_settings['label'] : $this->t('Section @section', ['@section' => $delta + 1]);

      \Drupal::moduleHandler()->alter('lb_copy_section_copy', $section);

      // Put the sections and its label in the tempstore for future use.
      $this->privateTempStore->set('copied_section', $section);
      $this->privateTempStore->set('copied_section_label', $section_label);
    }

    return $this->rebuild($section_storage);
  }

  /**
   * Paste the section stored in tempstore.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   A render array.
   */
  public function paste(SectionStorageInterface $section_storage, $delta) {
    $tempstore_section = $this->privateTempStore->get('copied_section');

    // Pasting is only possible if there is a section in the tempstore.
    if (!empty($tempstore_section)) {
      $tempstore_section_array = $tempstore_section->toArray();

      // Clone section.
      $cloned_section = new Section(
        $tempstore_section->getLayoutId(),
        $tempstore_section->getLayoutSettings(),
        $tempstore_section->getComponents(),
        $tempstore_section_array['third_party_settings']
      );

      // Replace section components with new instances
      // (thanks to section_library authors).
      $deep_cloned_section = $this->cloneAndReplaceSectionComponents($cloned_section);

      \Drupal::moduleHandler()->alter('lb_copy_section_paste', $deep_cloned_section, $tempstore_section);

      // Create a new section.
      $section_storage->insertSection($delta, $deep_cloned_section);

      // Update layout builder tempstore so everything keeps working properly.
      $this->layoutTempstoreRepository->set($section_storage);
    }

    return $this->rebuild($section_storage);
  }

  /**
   * Reload the page either using AJAX or a refresh.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array
   *   A render array.
   */
  private function rebuild(SectionStorageInterface $section_storage) {
    if ($this->isAjax()) {
      return $this->rebuildAndClose($section_storage);
    }
    else {
      $url = $section_storage->getLayoutBuilderUrl();
      return new RedirectResponse($url->setAbsolute()->toString());
    }
  }

}
