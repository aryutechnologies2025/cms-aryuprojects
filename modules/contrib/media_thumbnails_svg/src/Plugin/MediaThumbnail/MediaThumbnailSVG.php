<?php

namespace Drupal\media_thumbnails_svg\Plugin\MediaThumbnail;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media_thumbnails\Plugin\MediaThumbnailBase;
use SVG\SVG;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Media thumbnail plugin for svg documents.
 *
 * @\Drupal\media_thumbnails\Annotation\MediaThumbnail(
 *   id = "media_thumbnail_svg",
 *   label = @Translation("Media Thumbnail SVG"),
 *   mime = {
 *     "image/svg",
 *     "image/svg+xml",
 *   }
 * )
 */
class MediaThumbnailSVG extends MediaThumbnailBase {

  /**
   * The thumbnail width.
   *
   * @var int
   */
  protected int $width;

  /**
   * The thumbnail background colour.
   *
   * @var string
   */
  protected string $bgColor;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected FileRepositoryInterface $fileRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): MediaThumbnailBase {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileRepository = $container->get('file.repository');
    return $instance;
  }

  /**
   * Creates a managed thumbnail file using the passed source file uri.
   *
   * {@inheritdoc}
   */
  public function createThumbnail($sourceUri) {

    // Not all rasterizers support stream wrappers, use absolute paths.
    $path = $this->fileSystem->realpath($sourceUri);

    // Get width and background color, if any.
    $this->bgColor = $this->configuration['bgcolor_active'] ? $this->configuration['bgcolor_value'] : 'transparent';
    $this->width = $this->configuration['width'] ?? 500;

    // Create a thumbnail image blob using the best rasterizer available.
    if (_media_thumbnails_svg_has_graphics_magick()) {
      $image = $this->createThumbnailGraphicsMagick($path);
    }
    elseif (_media_thumbnails_svg_has_image_magick()) {
      $image = $this->createThumbnailImageMagick($path);
    }
    else {
      $image = $this->createThumbnailGd($path);
    }

    // Return a new managed file object using the generated thumbnail.
    return $image ? $this->fileRepository
      ->writeData($image, $sourceUri . '.png', FileSystemInterface::EXISTS_REPLACE) : NULL;

  }

  /**
   * Create thumbnail with GraphicsMagick.
   *
   * @param string $path
   *   The SVG image path.
   *
   * @return false|string
   *   The SVG as a PNG.
   */
  protected function createThumbnailGraphicsMagick(string $path) {

    $source = escapeshellarg($path);
    $target = escapeshellarg($path . '.png');

    $width = escapeshellarg($this->width);
    $bg_colour = escapeshellarg($this->bgColor);

    shell_exec(
      sprintf(
        'gm convert -background %s -size %s -quality 100 -strip %s %s',
        $bg_colour, $width, $source, $target
      )
    );
    $data = file_get_contents($path . '.png');
    if (!$data) {
      $this->logger->warning($this->t('Could not create png from svg using GM.'));
    }
    return $data;
  }

  /**
   * Create thumbnail with ImageMagick.
   *
   * @param string $path
   *   The SVG image path.
   *
   * @return false|string
   *   The SVG as a PNG.
   */
  protected function createThumbnailImageMagick(string $path) {

    $source = escapeshellarg($path);
    $target = escapeshellarg($path . '.png');

    $width = escapeshellarg($this->width);
    $bg_colour = escapeshellarg($this->bgColor);


    if (_media_thumbnails_svg_check_binary_exists('magick') && strpos(shell_exec('magick --version'), 'ImageMagick 7') !== FALSE) {
      $binary = 'magick';
      $cmd =  sprintf(
        $binary . ' %s -background %s -density %s -thumbnail %s -quality 100 -strip %s',
        $source, $bg_colour, $width, $width, $target
      );
    }
    else {
      $binary = 'convert';
      $cmd =  sprintf(
        $binary . '  -background %s -density %s -thumbnail %s -quality 100 -strip %s %s',
        $bg_colour, $width, $width, $source, $target
      );
    }

    shell_exec($cmd);
    $data = file_get_contents($path . '.png');
    if (!$data) {
      $this->logger->warning($this->t('Could not create png from svg using IM.'));
    }
    return $data;

  }

  /**
   * Create thumbnail with GD.
   *
   * @param string $path
   *   The SVG image path.
   *
   * @return false|string|null
   *   The SVG as a PNG.
   */
  protected function createThumbnailGd(string $path) {
    $image = SVG::fromFile($path);
    if (!$image) {
      $this->logger->warning($this->t('Media entity source file (svg) not found.'));
      return NULL;
    }

    // Create a raster image using the target width, keeping the aspect ratio.
    $width = $image->getDocument()->getWidth() ?: $image->getDocument()
      ->getViewBox()[2];
    $height = $image->getDocument()->getHeight() ?: $image->getDocument()
      ->getViewBox()[3];
    $ratio = $width && $height ? $height / $width : 1;
    $height = (int) ($this->width * $ratio);
    $raster_image = $image->toRasterImage($this->width, $height, $this->bgColor);

    // Create a new image thumbnail blob.
    ob_start();
    if (!imagepng($raster_image, NULL, 9)) {
      $this->logger->warning($this->t('Could not create png from svg using GD.'));
      ob_end_clean();
      return NULL;
    }
    return ob_get_clean();

  }

}
