<?php

namespace Drupal\section_library\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\section_library\Entity\SectionLibraryTemplateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\section_library\Entity\SectionLibraryTemplate;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Markup;

/**
 * Defines a controller to choose a section from library.
 */
class ChooseSectionFromLibraryController implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use LayoutBuilderContextTrait;
  use LayoutBuilderHighlightTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The extension list module service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The file url generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The section library config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The Drupal Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * ChooseSectionFromLibraryController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The extension list module service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file url generator service.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration entity manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The Drupal Logger Factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, FileUrlGeneratorInterface $file_url_generator, ConfigManagerInterface $config_manager, LoggerChannelFactoryInterface $loggerFactory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->fileUrlGenerator = $file_url_generator;
    $this->config = $config_manager->getConfigFactory()->get('section_library.settings');
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('file_url_generator'),
      $container->get('config.manager'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Provides the UI for choosing a new block.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   A render array.
   */
  public function build(SectionStorageInterface $section_storage, int $delta) {
    $description = $this->getDescription();
    if (!empty($description)) {
      $build['description'] = [
        '#markup' => '<div class="section-library-description">' . $description . '</div>',
      ];
    }

    $build['filter'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter by section label'),
      '#title_display' => 'invisible',
      '#size' => 30,
      '#placeholder' => $this->t('Filter by label'),
      '#attributes' => [
        'class' => [
          'section-library-filter',
          'js-layout-builder-section-library-filter',
        ],
        'title' => $this->t('Enter a part of the section label to filter by.'),
      ],
      '#prefix' => '<div class="section-library-filters">',
    ];

    // Use configured labels.
    $sectionLabel = $this->config->get('section_label') ?? 'Section';
    $templateLabel = $this->config->get('template_label') ?? 'Template';
    $template_types = [
      'any' => $this->t('- Any -'),
      'section' => $sectionLabel,
      'template' => $templateLabel,
    ];

    $build['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#title_display' => 'invisible',
      '#options' => $template_types,
      '#attributes' => [
        'class' => [
          'section-library-filter-type',
          'js-layout-builder-section-library-filter-type',
        ],
        'title' => $this->t('Filter by type.'),
      ],
      '#suffix' => '</div>',
    ];

    $build['sections'] = $this->getSectionLinks($section_storage, $delta);

    // Attach the library directly in case this is in an iframe.
    $build['#attached']['library'][] = 'section_library/section_library';
    return $build;
  }

  /**
   * Gets a render array of section links.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The region the section is going in.
   *
   * @return array
   *   The section links render array.
   */
  protected function getSectionLinks(SectionStorageInterface $section_storage, int $delta) {
    // Get the configured image style for section images.
    $templateDisplay = $this->entityTypeManager->getStorage('entity_view_display')->load('section_library_template.section_library_template.default');
    if ($templateDisplay && $templateDisplay->getRenderer('image')) {
      $image_style = $templateDisplay->getRenderer('image')->getSetting('image_style');
    }
    if (!isset($image_style)) {
      $image_style = '';
    }

    // Get default image path.
    // Separate method to allow extending this class to use a different image.
    $default_path = $this->getDefaultImagePath();

    // Load the section library template options.
    // Separate method to allow extending this class with different criteria.
    // Pass parameters in case the context is needed for logic.
    $sections = $this->getSections($section_storage, $delta);

    $links = [];
    foreach ($sections as $section_id => $section) {
      $attributes = $this->getAjaxAttributes();
      $attributes['class'][] = 'js-layout-builder-section-library-link';
      // Add type of template as a data attribute to allow filtering.
      $attributes['data-section-type'] = strtolower($section->get('type')->value);

      $link_params = [];
      if ($this->moduleHandler->moduleExists('layout_builder_iframe_modal')) {
        $link_params = ['query' => ['destination' => Url::fromRoute('layout_builder_iframe_modal.redirect')->toString()]];
      }
      try {
        $link = [
          'title' => $this->getSectionLinkLabel($section, $default_path, $image_style),
          'url' => Url::fromRoute('section_library.import_section_from_library',
            [
              'section_library_id' => $section_id,
              'section_storage_type' => $section_storage->getStorageType(),
              'section_storage' => $section_storage->getStorageId(),
              'delta' => $delta,
            ],
            $link_params,
          ),
          'attributes' => $attributes,
        ];

        $links[] = $link;
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('section_library')->error($e->getMessage());
      }
    }
    return [
      '#theme' => 'links',
      '#links' => $links,
      '#attributes' => [
        'class' => [
          'section-library-links',
        ],
      ],
    ];
  }

  /**
   * Get the label displayed for section links.
   *
   * This default label display can be overridden in a subclass.
   *
   * @param \Drupal\section_library\Entity\SectionLibraryTemplateInterface $section
   *   The section being linked.
   * @param string $default_path
   *   The default image path.
   * @param string $image_style
   *   The configured image style if it exists.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   Rendered link label.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSectionLinkLabel(SectionLibraryTemplateInterface $section, string $default_path = '', string $image_style = ''): MarkupInterface|string {
    if ($fid = $section->get('image')->target_id) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      $img_path = $file->getFileUri();
      // Use the configured image style if it exists.
      if (!empty($image_style)) {
        $img_render_array = [
          '#theme' => 'image_style',
          '#style_name' => $image_style,
          '#uri' => $img_path,
        ];
      }
      else {
        // Use the original image.
        $img_render_array = [
          '#theme' => 'image',
          '#uri' => $img_path,
        ];
      }
      $img = $this->renderer->render($img_render_array)->__toString();
    }
    else {
      // Fallback: use default library image from module.
      $img = '<img src="' . $default_path . '"/>';
    }
    return Markup::create('<span class="section-library-link-img">' . $img . '</span><span class="section-library-link-label">' . $section->label() . '</span>');
  }

  /**
   * Get the path for the default image when no thumbnail is available.
   *
   * This is the module's image by default but can be overridden in a subclass.
   *
   * @return string
   *   The path to the default image.
   */
  protected function getDefaultImagePath() {
    $img_path = $this->moduleHandler->getModule('section_library')->getPath() . '/images/default.png';
    return $this->fileUrlGenerator->generateString($img_path);
  }

  /**
   * Get the section options to insert into the layout.
   *
   * This is all templates by default but can be overridden in a subclass.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The region the section is going in.
   *
   * @return array
   *   An array of sections.
   */
  protected function getSections(SectionStorageInterface $section_storage, int $delta) {
    // Load all section library templates.
    return SectionLibraryTemplate::loadMultiple();
  }

  /**
   * Get the description for the dialog.
   *
   * This is empty by default but can be overridden in a subclass.
   *
   * @return string
   *   The dialog description.
   */
  protected function getDescription() {
    return '';
  }

  /**
   * Get dialog attributes if an ajax request.
   *
   * @return array
   *   The attributes array.
   */
  protected function getAjaxAttributes() {
    if ($this->isAjax()) {
      return [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ];
    }
    return [];
  }

}
