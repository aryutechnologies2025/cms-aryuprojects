<?php

namespace Drupal\tocbot\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tocbot\TocbotHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Block\Attribute\Block;

/**
 * Provides a block with a TOC from tocbot.
 */
#[Block(
  id: 'tocbot_block',
  admin_label: new TranslatableMarkup("Tocbot TOC"),
)]
class TocbotBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a TocbotBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $options = [];
    $settingsOptions = TocbotHelper::getSettingsOptions();
    $config = $this->configFactory->get('tocbot.settings');
    foreach ($settingsOptions as $setting => $option) {
      $options[$option] = $config->get($setting);
    }

    return [
      '#markup' => '<div class="js-toc-block"></div>',
      '#attached' => [
        'library' => [
          'tocbot/drupal.tocbot',
          TocbotHelper::getLibrary(),
        ],
        'drupalSettings' => [
          'tocbot' => $options,
        ],
      ],
    ];
  }

}
