<?php

namespace Drupal\html_title\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\html_title\HtmlTitleFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'html_title' formatter.
 *
 * @FieldFormatter(
 *   id = "html_title",
 *   label = @Translation("HTML-title text"),
 *   field_types = {
 *     "string",
 *   }
 * )
 */
class HtmlTitleFormatter extends StringFormatter {

  /**
   * The HTML title filter.
   *
   * @var \Drupal\html_title\HtmlTitleFilter
   */
  protected $htmlTitleFilter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition);
    $instance->setHtmlTitleFilter($container->get('html_title.filter'));
    return $instance;
  }

  /**
   * Set the HTML title filter service.
   *
   * @param \Drupal\html_title\HtmlTitleFilter $html_title_filter
   *   The HTML title filter service.
   */
  protected function setHtmlTitleFilter(HtmlTitleFilter $html_title_filter) {
    $this->htmlTitleFilter = $html_title_filter;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    return ['#markup' => $this->htmlTitleFilter->decodeToMarkup($item->value)];
  }

}
