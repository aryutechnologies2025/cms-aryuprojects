<?php

namespace Drupal\section_library\Plugin\Field\FieldFormatter;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'section_library_template_type' formatter.
 *
 * @FieldFormatter(
 *   id = "section_library_template_type",
 *   label = @Translation("Section library template type"),
 *   field_types = {
 *     "list_string"
 *   }
 * )
 */
#[FieldFormatter(
  id: 'section_library_template_type',
  label: new TranslatableMarkup('Section library template type'),
  field_types: [
    'list_string',
  ],
)]
class SectionLibraryTemplateTypeFormatter extends FormatterBase {

  /**
   * The section library config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Create an instance of SectionLibraryTemplateTypeFormatter.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ConfigManagerInterface $config_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->config = $config_manager->getConfigFactory()->get('section_library.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('config.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      if (strtolower($item->value) === 'section') {
        $label = $this->config->get('section_label') ?? 'Section';
      }
      else {
        $label = $this->config->get('template_label') ?? 'Template';
      }
      $elements[$delta] = [
        '#markup' => $label,
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if ($field_definition->getTargetEntityTypeId() == 'section_library_template' && $field_definition->getName() == 'type') {
      return TRUE;
    }
    return FALSE;
  }
}
