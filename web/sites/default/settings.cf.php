<?php
/** 
 * Collect external service information from environment. 
 * Cloud Foundry places all service credentials in VCAP_SERVICES
 */

$cf_service_data = json_decode($_ENV['VCAP_SERVICES'] ?? '{}', true);

foreach ($cf_service_data as $service_provider => $service_list) {
  foreach ($service_list as $service) {
    if ($service['name'] === 'database') {
      $databases['default']['default'] = array (
        'database' => $service['credentials']['db_name'],
        'username' => $service['credentials']['username'],
        'password' => $service['credentials']['password'],
        'prefix' => '',
        'host' => $service['credentials']['host'],
        'port' => $service['credentials']['port'],
        'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
        'driver' => 'mysql',
      );
    }
    if ($service['name'] === 'secrets') {
      $settings['hash_salt'] = $service['credentials']['HASH_SALT'];
    }
    if ($service['name'] === 'storage') {
      $settings['flysystem']['s3'] = array(
        'driver' => 's3',
        'config' => array(
          'key'    => $service['credentials']['access_key_id'],
          'secret' => $service['credentials']['secret_access_key'],
          'region' => $service['credentials']['region'],
          'bucket' => $service['credentials']['bucket'],
          // Optional configuration settings.
          'options' => array(
            'ACL' => 'public-read',
            'ServerSideEncryption' => 'AES256',
          ),
          'protocol' => 'https',      // Will be autodetected based on the current request.
          'prefix' => 'flysystem-s3', // Directory prefix for all uploaded/viewed files.
        ),
        'cache' => TRUE, // Creates a metadata cache to speed up lookups.
      );
    }
  }
}

// CSS and JS aggregation need per dyno/container cache.
// This is from https://www.fomfus.com/articles/how-to-create-a-drupal-8-project-for-heroku-part-1
// included here without fully understanding implications:
$settings['cache']['bins']['data'] = 'cache.backend.php';
