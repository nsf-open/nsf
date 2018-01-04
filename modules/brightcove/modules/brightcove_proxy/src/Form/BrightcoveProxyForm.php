<?php

namespace Drupal\brightcove_proxy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class BrightcoveProxyForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'brightcove_proxy_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'brightcove_proxy.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get config.
    $config = $this->config('brightcove_proxy.config');

    $form['use_proxy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use proxy'),
      '#default_value' => $config->get('use_proxy'),
      '#description' => 'Enable proxy connection.',
    ];

    // Proxy config.
    $form['proxy_config'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Proxy configuration'),
      '#states' => [
        'visible' => [
          ':input[name="use_proxy"]' => ['checked' => TRUE],
        ],
      ],
      'proxy_username' => [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#default_value' => $config->get('proxy_username'),
        '#description' => $this->t('The username to use for the connection to the proxy.')
      ],
      'proxy_password' => [
        '#type' => 'textfield',
        '#title' => $this->t('Password'),
        '#default_value' => $config->get('proxy_password'),
        '#description' => $this->t('The password to use for the connection to the proxy.')
      ],
      'proxy_auth' => [
        '#type' => 'select',
        '#title' => $this->t('Auth'),
        '#options' => [
          CURLAUTH_ANY => $this->t('Any'),
          CURLAUTH_ANYSAFE => $this->t('Any safe'),
          CURLAUTH_BASIC => $this->t('Basic'),
          CURLAUTH_DIGEST => $this->t('Digest'),
          CURLAUTH_GSSNEGOTIATE => $this->t('GSS Negotiation'),
          CURLAUTH_NTLM => $this->t('NTLM'),
        ],
        '#default_value' => $config->get('proxy_auth'),
        '#description' => $this->t('The HTTP authentication method(s) to use for the proxy connection.<br>For proxy authentication, only <em>Basic</em> and <em>NTLM</em> are currently supported.<p><em>Any</em> means either <em>Basic</em>, <em>Digest</em>, <em>GSS Negotiation</em> or <em>NTLM</em></p><p><em>Any safe</em> means either <em>Digest</em>, <em>GSS Negotiation</em> or <em>NTLM</em>.</p>'),
      ],
      'proxy_type' => [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#options' => [
          CURLPROXY_HTTP => 'HTTP',
          CURLPROXY_SOCKS4 => 'SOCKS4',
          CURLPROXY_SOCKS5 => 'SOCKS5',
        ],
        '#default_value' => $config->get('proxy_type'),
      ],
      'proxy' => [
        '#type' => 'textfield',
        '#title' => $this->t('Proxy'),
        '#default_value' => $config->get('proxy'),
        '#description' => $this->t('The HTTP proxy to tunnel requests through.'),
      ],
      'proxy_port' => [
        '#type' => 'number',
        '#title' => $this->t('Port'),
        '#default_value' => $config->get('proxy_port'),
        '#min' => 1,
        '#max' => 65535,
        '#size' => 5,
        '#description' => $this->t('The port number of the proxy to connect to.'),
      ],
      'http_proxy_tunnel' => [
        '#type' => 'checkbox',
        '#title' => $this->t('HTTP tunnel proxy'),
        '#default_value' => $config->get('http_proxy_tunnel'),
        '#description' => $this->t('Enable to tunnel through a given HTTP proxy.'),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate proxy config.
    if ($form_state->getValue('use_proxy')) {
      $ch = curl_init("{$_SERVER['HTTP_HOST']}:{$_SERVER['SERVER_PORT']}/brightcove-proxy/test");
      curl_setopt_array($ch, [
        CURLOPT_PROXYUSERPWD => "{$form_state->getValue('proxy_username')}:{$form_state->getValue('proxy_password')}",
        CURLOPT_PROXYAUTH => $form_state->getValue('proxy_auth'),
        CURLOPT_PROXYTYPE => $form_state->getValue('proxy_type'),
        CURLOPT_PROXY => $form_state->getValue('proxy'),
        CURLOPT_PROXYPORT => $form_state->getValue('proxy_port'),
        CURLOPT_HTTPPROXYTUNNEL => $form_state->getValue('http_proxy_tunnel'),
        CURLOPT_RETURNTRANSFER => FALSE,
        CURLOPT_AUTOREFERER => TRUE,
        CURLOPT_FOLLOWLOCATION => TRUE,
        CURLOPT_MAXREDIRS => 5,
      ]);
      curl_exec($ch);
      $info = curl_getinfo($ch);

      if ($info['http_code'] != 200) {
        $form_state->setErrorByName('', curl_error($ch));
      }

      curl_close($ch);
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('brightcove_proxy.config')
      ->set('use_proxy', $form_state->getValue('use_proxy'));

    // Set proxy values.
    if ($form_state->getValue('use_proxy')) {
      foreach (array_keys($form['proxy_config']) as $config_name) {
        if (strpos($config_name, '#') === 0) {
          continue;
        }
        $config->set($config_name, $form_state->getValue($config_name));
      }
    }
    // Remove proxy values if disabled.
    else {
      foreach (array_keys($form['proxy_config']) as $config_name) {
        if (strpos($config_name, '#') === 0) {
          continue;
        }
        $config->set($config_name, NULL);
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }
}
