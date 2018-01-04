<?php

namespace Drupal\brightcove_proxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class BrightcoveProxyTest
 *
 * Dummy page for proxy testing.
 *
 * @package Drupal\brightcove_proxy\Controller
 */
class BrightcoveProxyTest extends ControllerBase {
  public function testPage() {
    return new Response();
  }
}