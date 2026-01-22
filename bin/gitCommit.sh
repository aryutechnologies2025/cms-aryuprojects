#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
path="$DIR/../$1";
cd $path;
if [[ -n $(git status --porcelain) ]]; then
  git add .
  git commit -m "$2";
fi
