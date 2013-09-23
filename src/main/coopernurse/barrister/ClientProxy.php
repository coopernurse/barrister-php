<?php

namespace coopernurse\barrister;

class ClientProxy {

  /** @var Client */
  public $client;

  /** @var string */
  public $interfaceName;

  function __construct($client, $interfaceName) {
    $this->client        = $client;
    $this->interfaceName = $interfaceName;
  }

  function __call($name, $args) {
    $method = $this->interfaceName . "." . $name;
    $resp   = $this->client->request($method, $args);
    if (isset($resp->error)) {
      throw new RpcException($resp->error->code,
                             $resp->error->message,
                             $resp->error->data);
    }
    else {
      return $resp->result;
    }
  }

}