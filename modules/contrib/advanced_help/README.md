# Advanced Help

The Advanced Help module allows module developers to store their help
outside the module system, in .html or .md (MarkDown) files. It
provides a framework that allows module and theme developers to
integrate help texts in a Drupal site, as well as exposing help to
site administrators through the site's administrative interface.

- For a full description of the module visit the
  [project page](https://www.drupal.org/project/advanced_help).

- To submit bug reports and feature suggestions, or to track changes
  visit the project's
  [issue queue](https://www.drupal.org/project/issues/advanced_help).


## Table of contents

- Requirements
- Installation
- Configuration


## Requirements

This projects requires the following core module:
[Help](https://www.drupal.org/docs/8/core/modules/help)

This projects requires the following third party library:
[PHP Markdown](https://github.com/michelf/php-markdown)

The PHP Markdown library is installed automatically by
*composer*. Please see the project's `composer.json` for the exact
requirements.


## Installation

You must use *composer* to install the project. The exact command line
is displayed on the project page, along with the release.


## Configuration

By itself, this module doesn't do much. It assists other modules and
themes in showing help texts. Nothing will show up until you enable at
least one other module that makes use of the Advanced Help framework
or comes with a file named README.md or README.txt.
