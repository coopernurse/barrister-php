<?php

namespace coopernurse\Barrister;

class Client {

  /** @var Contract */
  public $contract;

  /** @var RequestInterface */
  public $trans;

  function __construct(RequestInterface $trans) {
    $this->trans = $trans;
    $this->loadContract();
  }

  function proxy($interfaceName) {
    $this->contract->checkInterface($interfaceName);
    return new ClientProxy($this, $interfaceName);
  }

  function getMeta() {
    return $this->contract->getMeta();
  }

  function loadContract() {
    $req = array("jsonrpc"=>"2.0", "id"=>"1", "method"=>"barrister-idl");
    $resp = $this->trans->request($req);
    $this->contract = new Contract($resp->result);
  }

  function startBatch() {
    return new Batch($this);
  }

  function request($method, $params) {
    $req = $this->createRequest($method, $params);
    return $this->trans->request($req);
  }

  function createRequest($method, $params) {
    $req = array("jsonrpc"=>"2.0", "id"=>uniqid("", true), "method"=>$method);
    if ($params && is_array($params) && count($params) > 0) {
      $req["params"] = $params;
    }
    return $req;
  }

}