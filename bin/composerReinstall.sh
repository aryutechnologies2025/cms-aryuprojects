#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
PROJECTDIR="$(dirname "$DIR")"
cd $PROJECTDIR
rm -rf vendor
composer install
composer install
