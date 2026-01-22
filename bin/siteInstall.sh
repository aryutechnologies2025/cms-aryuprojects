#!/usr/bin/env bash
set -e
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
SITE="${DIR}/..";

cd $SITE && drush si --existing-config --account-name=coalmarch --account-mail=domains@coalmarch.com --account-pass=tapir_shalt_fourteen_tertiary
