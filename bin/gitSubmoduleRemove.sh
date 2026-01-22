#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
project=$(dirname "$DIR")
for dir in $project/modules/contrib/*; do
  if [ -d "$dir/.git" ]; then
    echo "removing git submodule from $dir"
    rm -rf "$dir/.git";
  fi
done

for dir in $project/themes/contrib/*; do
  if [ -d "$dir/.git" ]; then
    echo "removing git submodule from $dir"
    rm -rf "$dir/.git";
  fi
done

for dir in $project/libraries/contrib/*; do
  if [ -d "$dir/.git" ]; then
    echo "removing git submodule from $dir"
    rm -rf "$dir/.git";
  fi
done
