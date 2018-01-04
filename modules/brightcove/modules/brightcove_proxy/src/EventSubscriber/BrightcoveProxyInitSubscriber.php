<?php

namespace Drupal\brightcove_proxy\EventSubscriber;

use Brightcove\API\Client;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class BrightcoveProxyInitSubscriber
 * @package Drupal\brightcove_proxy\EventSubscriber
 */
class BrightcoveProxyInitSubscriber implements EventSubscriberInterface {
  /**
   * Brightcove proxy configuration.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * BrightcoveProxyInitSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Configuration object factory.
   */
  public function __construct(ConfigFactory $config) {
    $this->config = $config->get('brightcove_proxy.config');
  }

  /**
   * Initialize Brightcove client proxy.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function initializeBrightcoveClientProxy(GetResponseEvent $event) {
    // Initialize proxy config for Brightcove client if enabled.
    if ($this->config->get('use_proxy')) {
      Client::$proxyUserPassword = "{$this->config->get('proxy_username')}:{$this->config->get('proxy_password')}";
      Client::$httpProxyTunnel = $this->config->get('http_proxy_tunnel');
      Client::$proxyAuth = $this->config->get('proxy_auth');
      Client::$proxyPort = $this->config->get('proxy_port');
      Client::$proxyType = $this->config->get('proxy_type');
      Client::$proxy = $this->config->get('proxy');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['initializeBrightcoveClientProxy'];
    return $events;
  }
}