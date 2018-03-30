#!/bin/bash 
set -euo pipefail

while true
do
  drupal --root=$HOME/web cron:execute all
  sleep 15m
done
