#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
PROJECTDIR="$(dirname "$DIR")"
cd $PROJECTDIR
composer remove \
    "drupal/anchor_link" \
    "drupal/ckeditor" \
    "drupal/ckeditor_div_manager" \
    "drupal/ckeditor_entity_link" \
    "drupal/ckeditor_liststyle" \
    "drupal/color" \
    "drupal/date_popup" \
    "drupal/entity_usage" \
    "drupal/fakeobjects" \
    "drupal/fences" \
    "drupal/honeypot" \
    "drupal/inline_block_title_automatic" \
    "drupal/layout_library" \
    "drupal/manage_display" \
    "drupal/profile" \
    "drupal/rdf" \
    "drupal/restui" \
    "drupal/show_title" \
    "drupal/structure_sync" \
    "drupal/swiftmailer"
