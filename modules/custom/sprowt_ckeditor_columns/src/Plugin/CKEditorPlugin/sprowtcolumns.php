<?php

namespace Drupal\sprowt_ckeditor_columns\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginBase;

/**
 * Defines the "sprowtcolumns" plugin.
 *
 * @CKEditorPlugin(
 *   id = "sprowtcolumns",
 *   label = @Translation("Sprowt columns"),
 *   module = "sprowt_ckeditor_columns"
 * )
 */
class sprowtcolumns extends CKEditorPluginBase {

  /**
   * Get path to library folder.
   */
  protected function getLibraryPath() {
    if (\Drupal::moduleHandler()->moduleExists('libraries')) {
      return libraries_get_path('sprowtcolumns');
    }
    else {
      return 'libraries/ckeditor/plugins/sprowtcolumns';
    }
  }

  /**
   * Implements \Drupal\ckeditor\Plugin\CKEditorPluginInterface::getFile().
   */
  public function getFile() {
    return $this->getLibraryPath() . '/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'sprowtcolumns' => [
        'label' => $this->t('Columns'),
        'image' => $this->getLibraryPath() . '/icons/sprowtcolumns.png',
		'command' => 'sprowtcolumns'
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }
}
