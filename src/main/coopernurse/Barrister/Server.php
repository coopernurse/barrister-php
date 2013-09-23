<?php

namespace coopernurse\Barrister;

class Server {

  /** @var Contract */
  public $contract;

  function __construct($idlFile) {
    if (file_exists($idlFile)) {
      $data = file_get_contents($idlFile);
      $decoder = new Decoder();
      $this->contract = new Contract($decoder->json_decode($data));
      $this->handlers = array();
    }
    else {
      throw new \Exception("File not found: $idlFile");
    }
  }

  function addHandler($interfaceName, $handler) {
    $this->contract->checkInterface($interfaceName);
    $this->handlers[$interfaceName] = $handler;
  }

  function handleHTTP() {
    $isCGI = stripos(PHP_SAPI, "cgi") !== false;
    if ($isCGI) {
      $reqJson = file_get_contents('php://stdin', NULL, NULL, 0, $_ENV['CONTENT_LENGTH']);
    }
    else {
      $reqJson = file_get_contents('php://input');
    }

    $resp = null;
    $req  = null;
    try {
      $decoder = new Decoder();
      $req = $decoder->json_decode($reqJson);
    }
    catch (RpcException $e) {
      $resp = $this->errResp($req, $e->getCode(), $e->getMessage());
    }

    if ($resp === null) {
      $resp = $this->handle($req);
    }

    $respJson = json_encode($resp);
    $len      = strlen($respJson);

    if ($isCGI) {
      print "Content-Type: application/json\r\n";
      print "Content-Length: $len\r\n\r\n";
      print $respJson;
    }
    else {
      header("Content-Type: application/json");
      header("Content-Length: $len");
      print $respJson;
    }
  }

  function handle($req) {
    if (is_array($req)) {
      $retList = array();
      foreach ($req as $i=>$r) {
        array_push($retList, $this->handleSingle($r));
      }
      return $retList;
    }
    else {
      return $this->handleSingle($req);
    }
  }

  function handleSingle($req) {
    $method = $req->method;
    if (!$method) {
      return $this->errResp($req, -32600, "No method specified on request");
    }

    if ($method === "barrister-idl") {
      return $this->okResp($req, $this->contract->idl);
    }

    $pos = strpos($method, ".");
    if ($pos > 0) {
      $iface = substr($method, 0, $pos);
      $func  = substr($method, $pos+1);
    }
    else {
      return $this->errResp($req, -32600, "Invalid request method: $method");
    }

    $ifaceInst = $this->contract->getInterface($iface);
    $funcInst  = null;
    if ($ifaceInst) {
      $funcInst = $ifaceInst->getFunction($func);
    }
    if (!$ifaceInst || !$funcInst) {
      return $this->errResp($req, -32601, "Method not found on IDL: $method");
    }

//    error_log(json_encode($req));
    $params = isset($req->params) ? $req->params : array();

    $invalid = $funcInst->validateParams($this->contract, $params);
    if ($invalid !== null) {
      return $this->errResp($req, -32602, $invalid);
    }

    $handler = $this->handlers[$iface];
    if (!$handler) {
      return $this->errResp($req, -32601, "Interface not found: $iface");
    }

    $reflectMethod = null;
    try {
      $reflectMethod = new \ReflectionMethod(get_class($handler), $func);
    }
    catch (\Exception $e) { }

    if (!$reflectMethod) {
      try {
        $reflectMethod = new \ReflectionMethod(get_class($handler), $func . "_");
      }
      catch (\Exception $e) { }
    }

    if (!$reflectMethod) {
      return $this->errResp($req, -32601, "Method not found: $method");
    }

    try {
      $result = $reflectMethod->invokeArgs($handler, $params);

      $invalid = $funcInst->validateResult($this->contract, $result);
      if ($invalid !== null) {
        return $this->errResp($req, -32001, $invalid);
      }
      else {
        return $this->okResp($req, $result);
      }
    }
    catch (RpcException $e) {
      return $this->errResp($req, $e->getCode(), $e->getMessage(), $e->getData());
    }
    catch (\Exception $e) {
      return $this->errResp($req, -32000, "Unknown error: " . $e->getMessage());
    }
  }

  function okResp($req, $result) {
    $resp = array("jsonrpc"=>"2.0", "result"=>$result);
    if (isset($req->id)) {
      $resp["id"] = $req->id;
    }
    return $resp;
  }

  function errResp($req, $code, $message, $data=null) {
    $err = array("code"=>$code, "message"=>$message);
    if ($data) {
      $err["data"] = $data;
    }
    $resp = array("jsonrpc"=>"2.0", "error"=>$err);
    if (isset($req->id)) {
      $resp["id"] = $req->id;
    }
    return $resp;
  }

}
