<?php

namespace Drupal\html_title\Plugin\views\field;

use Drupal\html_title\HtmlTitleFilter;
use Drupal\views\Plugin\views\field\EntityField;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field that displays node html title.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("node_html_title")
 */
class NodeHtmlTitle extends EntityField {

  /**
   * The HTML title filter service.
   *
   * @var \Drupal\html_title\HtmlTitleFilter
   */
  protected $htmlTitleFilter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setHtmlTitleFilter($container->get('html_title.filter'));
    return $instance;
  }

  /**
   * Set the HTML title filter service.
   *
   * @param \Drupal\html_title\HtmlTitleFilter $html_title_filter
   *   The HTML title filter service.
   */
  public function setHtmlTitleFilter(HtmlTitleFilter $html_title_filter) {
    $this->htmlTitleFilter = $html_title_filter;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function renderText($alter) {
    if ($this->options['settings']['link_to_entity'] === TRUE) {
      $alter['rendered']['#title']['#context']['value'] = $this->htmlTitleFilter->decodeToMarkup($alter['rendered']['#title']['#context']['value']);
      $this->last_render = $this->renderer->render($alter['rendered']);
    }
    else {
      $rendered = $this->renderer->render($alter['rendered']);
      $this->last_render = $this->htmlTitleFilter->decodeToMarkup($rendered);
    }

    return parent::renderText($alter);
  }

}
