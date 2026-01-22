<?php

namespace Drupal\sprowt_ckeditor_buttons\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginBase;

/**
 * Defines the "sprowtbutton" plugin.
 *
 * @CKEditorPlugin(
 *   id = "sprowtbutton",
 *   label = @Translation("Sprowt Button"),
 *   module = "sprowt_ckeditor_buttons"
 * )
 */
class sprowtbutton extends CKEditorPluginBase {

  /**
   * Get path to library folder.
   */
  protected function getLibraryPath() {
    if (\Drupal::moduleHandler()->moduleExists('libraries')) {
      return libraries_get_path('sprowtbutton');
    }
    else {
      return 'libraries/ckeditor/plugins/sprowtbutton';
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
      'sprowtbutton' => [
        'label' => $this->t('Buttons'),
        'image' => $this->getLibraryPath() . '/icons/sprowtbutton.png',
		'command' => 'sprowtbutton'
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
