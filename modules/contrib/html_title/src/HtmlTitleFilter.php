<?php

namespace Drupal\html_title;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;

// cspell:ignore NOENT

/**
 * The HtmlTitleFilter class.
 */
class HtmlTitleFilter {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * HtmlTitleFilter constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(ConfigFactoryInterface $configFactory, RendererInterface $renderer) {
    $this->configFactory = $configFactory;
    $this->renderer = $renderer;
  }

  /**
   * Helper function to help filter out unwanted XSS opportunities.
   *
   * Use this function if you expect to have junk or incomplete html. It uses
   * the same strategy as the "Fix Html" filter option in configuring the HTML
   * filter in the text format configuration.
   */
  protected function filterXss($title) {
    $dom = new \DOMDocument();
    // Ignore warnings during HTML soup loading.
    @$dom->loadHTML('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>' . $title . '</body></html>', LIBXML_NOENT);
    $xp = new \DOMXPath($dom);
    $q = "//body//text()";
    $nodes = $xp->query($q);

    foreach ($nodes as $n) {
      $n->nodeValue = htmlspecialchars($n->nodeValue, ENT_QUOTES);
    }
    $body = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
    // $dom->saveHTML() escapes & as &amp; for all entities that were replaced
    // using htmlspecialchars(). Undo this double-escaping.
    $body = str_replace('&amp;', '&', $body);

    return Xss::filter($body, $this->getAllowHtmlTags());
  }

  /**
   * Decode a string to text.
   */
  public function decodeToText($str) {
    if (is_array($str)) {
      $str = $this->renderer->renderPlain($str);
    }
    return trim($this->filterXss(Html::decodeEntities((string) $str)));
  }

  /**
   * Decode a string to markup.
   */
  public function decodeToMarkup($str) {
    return Markup::create($this->decodeToText($str));
  }

  /**
   * Get allow html tags array.
   */
  public function getAllowHtmlTags() {
    $matches = [];
    $allow_html_tags = $this->configFactory->get('html_title.settings')->get('allow_html_tags');
    preg_match_all('/<(.+?)\/?>/', $allow_html_tags, $matches);
    return $matches[1];
  }

}
