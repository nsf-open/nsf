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
    # Delete some data created in the "standard" install profile
    # See https://www.drupal.org/project/drupal/issues/2583113
    drupal --root=$HOME/web entity:delete shortcut_set default --no-interaction
    # Set site uuid to match our config
    UUID=$(grep uuid web/sites/default/config/system.site.yml | cut -d' ' -f2)
    drupal --root=$HOME/web config:override system.site uuid $UUID
}

if [ "$CF_INSTANCE_INDEX" == "0" ]; then
  drupal --root=$HOME/web list | grep database > /dev/null || install_drupal
  # load configs here
  # drupal --root=$HOME/web config:import ...
fi
