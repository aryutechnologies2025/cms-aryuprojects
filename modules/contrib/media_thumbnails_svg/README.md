# Media Thumbnails SVG module
## Introduction

This module uses the [Media Thumbnails](https://www.drupal.org/project/media_thumbnails) framework to create media entity thumbnails for svg files. That way you can add the media entity 'thumbnail' field to Views or Media entity display modes, optionally add an image style and get png preview images for svg files.

## Installation

Install this module as usual, using Composer. Then add a media entity type supporting svg uploads, or add the 'svg' extension to the preinstalled 'document' media type's upload field. Now you'll be able to upload svg files to media entities and create thumbnails for them. The general configuration page (/admin/config/media/thumbnails) allows specifying a maximum thumbnail width and an optional background color.

## How does it work?

The module takes a svg file and converts it to a png file, using the configured width and background color. This file will be used as media thumbnail image. The module supports the mime types 'image/svg' and 'image/svg+xml'.

Converting svg files to png bitmap images requires a rasterizer. Currently the following tools are supported, in this order:

- [GraphicsMagick](http://www.graphicsmagick.org) CLI-Tool (if installed)
  This tool usually provides the best quality, but isn't preinstalled in most hosting environments. If your hosting provider or you are able to install additional packages, look for the "graphicsmagick" package. The php extension is not required.
- [ImageMagick](https://imagemagick.org) CLI-Tool (if installed)
  ImageMagick will be preinstalled in many environments. It's internal rasterizer has limited capabilities, but the quality might be better if it's compiled against [RSVG](https://gitlab.gnome.org/GNOME/librsvg), or [Inkscape](https://inkscape.org) happens to be installed (ImageMagick will use those rasterizers where possible). Try it out, it might suffice your requirements. The php extension is not required.
- PHP GD extension using [meyfa/php-svg](https://github.com/meyfa/php-svg) library
  PHP GD is a core requirement, so it's always available. But it has very limited support for rasterizing svg. Basic files will work, but there's no support for advanced features like css. That said, go for ImageMagick or GraphicsMagick!

The rasterizer giving the best results will be chosen automatically. Priority is GraphicsMagick (best quality), then ImageMagick and at last PHP GD (lowest quality).
