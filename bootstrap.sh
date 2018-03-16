#!/bin/bash 
set -euo pipefail

SECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secrets") | .credentials')

install_drupal() {
    ROOT_USER_NAME=$(echo $SECRETS | jq -r '.ROOT_USER_NAME')
    ROOT_USER_PASS=$(echo $SECRETS | jq -r '.ROOT_USER_PASS')

    : "${ACCOUNT_NAME:?Need and root user name for Drupal}"
    : "${ACCOUNT_PASS:?Need and root user pass for Drupal}"

    drupal site:install \
        --root=$HOME/web \
        --no-interaction \
        --account-name="$ROOT_USER_NAME" \
        --account-pass="$ROOT_USER_PASS" \
        --langcode="en"
    # Delete some data created in the "standard" install profile
    # See https://www.drupal.org/project/drupal/issues/2583113
    drupal --root=$HOME/web entity:delete shortcut_set default --no-interaction
    # Set site uuid to match our config
    UUID=$(grep uuid web/sites/default/config/system.site.yml | cut -d' ' -f2)
    drupal --root=$HOME/web config:override system.site uuid $UUID
}

if [ "${CF_INSTANCE_INDEX:-''}" == "0" ]; then
  drupal --root=$HOME/web list | grep database > /dev/null || install_drupal
  # Sync configs from code
  drupal --root=$HOME/web config:import --directory $HOME/web/sites/default/config

  # Secrets
  CRON_KEY=$(openssl rand -base64 32)  # Not used, so we set it to a random val
  BRIGHTCOVE_ACCOUNT=$(echo $SECRETS | jq -r '.BRIGHTCOVE_ACCOUNT')
  BRIGHTCOVE_CLIENT=$(echo $SECRETS | jq -r '.BRIGHTCOVE_CLIENT')
  BRIGHTCOVE_SECRET=$(echo $SECRETS | jq -r '.BRIGHTCOVE_SECRET')
  drupal --root=$HOME/web config:override scheduler.settings lightweight_cron_access_key $CRON_KEY > /dev/null
  drupal --root=$HOME/web config:override brightcove.brightcove_api_client.nsf_brightcove account_id $BRIGHTCOVE_ACCOUNT > /dev/null
  drupal --root=$HOME/web config:override brightcove.brightcove_api_client.nsf_brightcove client_id $BRIGHTCOVE_CLIENT > /dev/null
  drupal --root=$HOME/web config:override brightcove.brightcove_api_client.nsf_brightcove secret_key $BRIGHTCOVE_SECRET > /dev/null

  # Clear the cache
  drupal --root=$HOME/web cache:rebuild --no-interaction
fi
