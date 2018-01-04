<?php

namespace Drupal\brightcove\Entity;

use Brightcove\API\CMS;
use Brightcove\API\Exception\APIException;
use Brightcove\API\Exception\AuthenticationException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\brightcove\BrightcoveAPIClientInterface;
use Brightcove\API\Client;

/**
 * Defines the Brightcove API Client entity.
 *
 * @ConfigEntityType(
 *   id = "brightcove_api_client",
 *   label = @Translation("Brightcove API Client"),
 *   handlers = {
 *     "list_builder" = "Drupal\brightcove\BrightcoveAPIClientListBuilder",
 *     "form" = {
 *       "add" = "Drupal\brightcove\Form\BrightcoveAPIClientForm",
 *       "edit" = "Drupal\brightcove\Form\BrightcoveAPIClientForm",
 *       "delete" = "Drupal\brightcove\Form\BrightcoveAPIClientDeleteForm"
 *     },
 *   },
 *   config_prefix = "brightcove_api_client",
 *   admin_permission = "administer brightcove configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/media/brightcove_api_client/{brightcove_api_client}",
 *     "add-form" = "/admin/config/media/brightcove_api_client/add",
 *     "edit-form" = "/admin/config/media/brightcove_api_client/{brightcove_api_client}/edit",
 *     "delete-form" = "/admin/config/media/brightcove_api_client/{brightcove_api_client}/delete",
 *     "collection" = "/admin/config/media/brightcove_api_client"
 *   }
 * )
 */
class BrightcoveAPIClient extends ConfigEntityBase implements BrightcoveAPIClientInterface {
  /**
   * The Brightcove API Client ID (Drupal-internal).
   *
   * @var string
   */
  protected $id;

  /**
   * The Brightcove API Client label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Brightcove API Client account ID.
   *
   * @var string
   */
  protected $account_id;

  /**
   * The Brightcove API Client ID.
   *
   * @var string
   */
  protected $client_id;

  /**
   * The Brightcove API Client default player.
   *
   * @var string
   */
  protected $default_player;

  /**
   * The Brightcove API Client secret key.
   *
   * @var string
   */
  protected $secret_key;

  /**
   * The loaded API client.
   *
   * @var \Brightcove\API\Client
   */
  protected $client;

  /**
   * Client connection status.
   *
   * @var int
   */
  protected $client_status;

  /**
   * Client connection status message.
   *
   * If the connection status is OK, then it's an empty string.
   *
   * @var string
   */
  protected $client_status_message;

  /**
   * Indicate if there was an re-authorization attempt or not.
   *
   * @var bool
   */
  private $re_authorization_tried = FALSE;

  /**
   * Maximum number of Custom fields.
   *
   * @var array
   */
  protected $max_custom_fields;

  /**
   * Expirable key/value store for the client.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $key_value_expirable_store;

  /**
   * @inheritdoc
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * @inheritdoc
   */
  public function getAccountID() {
    return $this->account_id;
  }

  /**
   * @inheritdoc
   */
  public function getClientID() {
    return $this->client_id;
  }

  /**
   * @inheritdoc
   */
  public function getDefaultPlayer() {
    return $this->default_player;
  }

  /**
   * @inheritdoc
   */
  public function getSecretKey() {
    return $this->secret_key;
  }

  /**
   * @inheritdoc
   */
  public function getClient() {
    $this->authorizeClient();
    return $this->client;
  }

  /**
   * @inheritdoc
   */
  public function getClientStatus() {
    return $this->client_status;
  }

  /**
   * @inheritdoc
   */
  public function getClientStatusMessage() {
    return $this->client_status_message;
  }

  /**
   * @inheritdoc
   */
  public function getAccessToken() {
    return $this->key_value_expirable_store->get($this->client_id, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxCustomFields() {
    return $this->max_custom_fields;
  }

  /**
   * @inheritdoc
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function setAccountID($account_id) {
    $this->account_id = $account_id;
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function setClientID($client_id) {
    $this->client_id = $client_id;
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function setDefaultPlayer($default_player) {
    $this->default_player = $default_player;
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function setSecretKey($secret_key) {
    $this->secret_key = $secret_key;
    return $this;
  }

  /**
   * Set Brightcove API client.
   *
   * @param \Brightcove\API\Client $client
   *   The initialized Brightcove API Client.
   *
   * @return $this
   */
  public function setClient(Client $client) {
    $this->client = $client;
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function setAccessToken($access_token, $expire) {
    $this->key_value_expirable_store->setWithExpire($this->client_id, $access_token, $expire);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setMaxCustomFields($max_custom_fields) {
    $this->max_custom_fields = $max_custom_fields;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    $this->key_value_expirable_store = \Drupal::keyValueExpirable('brightcove_access_token');
  }

  /**
   * Authorize client with Brightcove API and store client on the entity.
   *
   * @return $this
   *
   * @throws AuthenticationException|\Exception
   *   Re-throw any exception to be able to handle them nicely.
   */
  public function authorizeClient() {
    try {
      // Use the got access token while it is not expired.
      if ($this->key_value_expirable_store->has($this->client_id)) {
        // Create new client.
        $this->setClient(new Client($this->getAccessToken()));
      }
      // Otherwise get a new access token.
      else {
        $client = Client::authorize($this->client_id, $this->secret_key);

        // Update access information. This will ensure that in the current
        // session we will get the correct access data.
        // Set token expire time in seconds and subtract the default php
        // max_execution_time from it.
        // We have to use the default php max_execution_time because if we
        // would get the value from ini_get('max_execution_time'), then it
        // could be larger than the Brightcove's expire date causing to always
        // get a new access token.
        $this->setAccessToken($client->getAccessToken(), intval($client->getExpiresIn()) - 30);
        $this->save();

        // Create new client.
        $this->setClient(new Client($this->getAccessToken()));
      }

      // Test account ID.
      $cms = new CMS($this->client, $this->account_id);
      $cms->countVideos();

      // If client authentication was successful store it's state on the
      // entity.
      $this->client_status = self::CLIENT_OK;
    }
    catch (\Exception $e) {
      if ($e instanceof APIException) {
        // If we got an unauthorized error, try to re-authorize the client
        // only once.
        if ($e->getCode() == 401 && !$this->re_authorization_tried) {
          $this->re_authorization_tried = TRUE;
          $this->authorizeClient();
        }
      }

      // Store an error status and message on the entity if there was an
      // error.
      $this->client_status = self::CLIENT_ERROR;
      $this->client_status_message = $e->getMessage();

      // If we have already tried to re-authorize the client, throw the
      // exception outside of this scope, to be able to catch this Exception
      // for better error handling.
      if (($e->getCode() != 401 && !$this->re_authorization_tried) || ($e->getCode() == 401 && $this->re_authorization_tried)) {
        watchdog_exception('brightcove', $e, $e->getMessage());
        throw $e;
      }
    }

    return $this;
  }
}
