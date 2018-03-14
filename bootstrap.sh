#!/bin/bash 
set -euo pipefail

install_drupal() {
    ACCOUNT_NAME=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secrets") | .credentials.ROOT_USER_NAME')
    ACCOUNT_PASS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secrets") | .credentials.ROOT_USER_PASS')

    : "${ACCOUNT_NAME:?Need and root user name for Drupal}"
    : "${ACCOUNT_PASS:?Need and root user pass for Drupal}"

    drupal site:install \
        --root=$HOME/web \
        --no-interaction \
        --account-name="$ACCOUNT_NAME" \
        --account-pass="$ACCOUNT_PASS" \
        --langcode="en"
}

if [ "$CF_INSTANCE_INDEX" == "0" ]; then
  drupal --root=$HOME/web list | grep database > /dev/null || install_drupal
  # load configs here
  # drupal --root=$HOME/web config:import ...
fi
