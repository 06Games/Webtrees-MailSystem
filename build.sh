#!/bin/bash

for poFile in resources/lang/*.po; do
  moFile="${poFile%.po}.mo"
  echo "$poFile -> $moFile"
  msgfmt -o "$moFile" "$poFile"
done
