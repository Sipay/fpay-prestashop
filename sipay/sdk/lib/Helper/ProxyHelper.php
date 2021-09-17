<?php

declare(strict_types=1);

namespace PWall\Helper;

class ProxyHelper{

  private $enviroment   = null;
  private $key          = null;
  private $resource     = null;
  private $secret       = null;
  private $backend_url  = null;
  private $debug_file   = null;
  private $timeout      = null;

  public function __construct(
    $enviroment,
    $key,
    $resource,
    $secret,
    $backendUrl,
    $debugFile,
    $timeout
  )
  {
    $this->enviroment   = $enviroment;
    $this->key          = $key;
    $this->resource     = $resource;
    $this->secret       = $secret;
    $this->backend_url  = $backendUrl;
    $this->debug_file   = $debugFile;
    $this->timeout      = $timeout;

    $this->logger = new \PWall\Utils\Logger($this->debug_file);
  }

  public function proxyRequest(\PWall\Request $request)
  {
    $body        = [
      "key"        => $this->key,
      "resource"   => $this->resource,
      "nonce"      => str_pad(substr($this->generateNonce(), 0, 10), 10, "0", STR_PAD_LEFT), //Make sure we only send 10 characters, Amazon seems to not like more than 10,
      "mode"       => 'sha256',
      "payload"    => $request->toArray()
    ];
    $json_body   = json_encode($body);

    $signature   = hash_hmac('sha256', $json_body, $this->secret);
    $ch          = curl_init();
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_URL, \PWall\Helper\Constants::ENVIROMENTS_URLS[$this->enviroment]); // change this to use curl_apionfig api url
    curl_setopt($ch, CURLOPT_POST, 1); // set post data to true
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);   // post data
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Signature: ' . $signature,
      'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
    $response = json_decode(curl_exec($ch));
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $this->logger->log("PROXY REQUEST: " . $json_body);
    $this->logger->log("PROXY RESPONSE CODE: " . $httpcode);
    $this->logger->log(json_encode($response));
    if ($errno = curl_errno($ch)) {
      $error_message = curl_strerror($errno);
      $this->logger->log("cURL error ({$errno}): {$error_message}");
    }
    return json_encode($response);
  }

  private static function generateNonce(){
    return sprintf(
      '%d%d',
      mt_rand(0, 0xffff),
      mt_rand(0, 0xffff)
    );
  }
}
