<?php

function cgidebug($s) {
  print "Content-Type: application/json\r\n";
  print "\r\n";
  print $s;
  exit;
}

class Barrister {

  function httpClient($url) {
    return new BarristerClient(new BarristerHttpTransport($url));
  }

}

class BarristerRpcException extends Exception {

  function __construct($code, $message, $data=None) {
    parent::__construct($message, $code);
    $this->data = $error["data"];
  }

  function getData() {
    return $this->data;
  }

  function __toString() {
    $s = "BarristerRpcException code=" . $this->getCode() . " message=" . $this->getMessage();
    if ($this->data) {
      $s .= " data=" . $this->data;
    }
    return $s;
  }

}

class BarristerServer {

  function __construct($idlFile) {
    if (file_exists($idlFile)) {
      $fh = fopen($idlFile, 'r');
      $data = fread($fh, filesize($idlFile));
      fclose($fh);

      $this->contract = new BarristerContract(json_decode($data));
      $this->handlers = array();
    }
    else {
      throw new Exception("File not found: $idlFile");
    }
  }

  function addHandler($interfaceName, $handler) {
    $this->contract->checkInterface($interfaceName);
    $this->handlers[$interfaceName] = $handler;
  }

  function handleHTTP() {
    $isCGI = strpos($_ENV['GATEWAY_INTERFACE'], "CGI") === 0;
    if ($isCGI) {
      $reqJson = file_get_contents('php://stdin', NULL, NULL, 0, $_ENV['CONTENT_LENGTH']);
    }
    else {
      $reqJson = file_get_contents('php://input');
    }

    $req      = json_decode($reqJson);
    $resp     = $this->handle($req);
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

    $params = $req->params;

    $handler = $this->handlers[$iface];
    if (!$handler) {
      return $this->errResp($req, -32601, "Interface not found: $iface");
    }

    $reflectMethod = null;
    try {
      $reflectMethod = new ReflectionMethod(get_class($handler), $func);
    }
    catch (Exception $e) { }

    if (!$reflectMethod) {
      try {
        $reflectMethod = new ReflectionMethod(get_class($handler), $func . "_");
      }
      catch (Exception $e) { }
    }

    if (!$reflectMethod) {
      return $this->errResp($req, -32601, "Method not found: $method");
    }

    try {
      $result = $reflectMethod->invokeArgs($handler, $params);
      return $this->okResp($req, $result);
    }
    catch (BarristerRpcException $e) {
      return $this->errResp($req, $e->getCode(), $e->getMessage(), $e->getData());
    }
    catch (Exception $e) {
      return $this->errResp($req, -32000, "Unknown error: " . $e->getMessage());
    }
  }

  function okResp($req, $result) {
    $resp = array("jsonrpc"=>"2.0", "result"=>$result);
    if ($req->id) {
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
    if ($req->id) {
      $resp["id"] = $req->id;
    }
    return $resp;
  }

}

class BarristerClient {

  function __construct($trans) {
    $this->trans = $trans;
    $this->loadContract();
  }

  function proxy($interfaceName) {
    $this->contract->checkInterface($interfaceName);
    return new BarristerClientProxy($this, $interfaceName);
  }

  function loadContract() {
    $req = array("jsonrpc"=>"2.0", "id"=>"1", "method"=>"barrister-idl");
    $resp = $this->trans->request($req);
    $this->contract = new BarristerContract($resp->result);
  }

  function startBatch() {
    return new BarristerBatch($this);
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

class BarristerBatch {

  function __construct($client) {
    $this->client = $client;
    $this->requests = array();
    $this->sent = false;
  }

  function proxy($interfaceName) {
    $this->client->contract->checkInterface($interfaceName);
    return new BarristerClientProxy($this, $interfaceName);
  }

  function request($method, $params) {
    if ($this->sent) {
      throw new Exception("Batch has already been sent!");
    }
    array_push($this->requests, $req = $this->client->createRequest($method, $params));
  }

  function send() {
    if ($this->sent) {
      throw new Exception("Batch has already been sent!");
    }
    $this->sent = true;

    $results = $this->client->trans->request($this->requests);

    $resultsSorted = array();
    $resultsById   = array();

    foreach ($results as $i=>$res) {
      if ($res->id) {
        $resultsById[$res->id] = $res;
      }
    }

    foreach ($this->requests as $i=>$req) {
      $res = $resultsById[$req["id"]];
      if (!$res) {
        $err = array("code"=>-32603, "message"=>"No result for request id: " . $req["id"]);
        $res = array("jsonrpc"=>"2.0", "id"=>$req["id"], "error"=>$err);
      }
      array_push($resultsSorted, $res);
    }

    return $resultsSorted;
  }

}

class BarristerClientProxy {

  function __construct($client, $interfaceName) {
    $this->client        = $client;
    $this->interfaceName = $interfaceName;
  }

  function __call($name, $args) {
    $method = $this->interfaceName . "." . $name;
    $resp   = $this->client->request($method, $args);
    if ($resp->error) {
      throw new BarristerRpcException($resp->error->code, 
                                      $resp->error->message,
                                      $resp->error->data);
    }
    else {
      return $resp->result;
    }
  }

}

class BarristerHttpTransport {

  function __construct($url) {
    $this->url = $url;
  }

  function request($req) {
    $post_data = json_encode($req);
    //print "request: $post_data\n";
    $headers = array('Content-Type: application/json', 'Content-Length: ' . strlen($post_data));
    $ch = curl_init($this->url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers);
    $result = curl_exec($ch);
    if ($result === false) {
      throw new BarristerRpcException(-32603, "HTTP POST to " . $this->url . " failed");
    }
    else {
      //print "result: $result\n";
      $resp = json_decode($result);
      if ($resp === NULL) {
        throw new BarristerRpcException(-32603, "Could not parse response. Method: " . 
                                        $req->method . " JSON: $result");
      }
      return $resp;
    }
  }

}

class BarristerContract {

  function __construct($idl) {
    $this->idl        = $idl;
    $this->interfaces = array();
    $this->structs    = array();
    $this->enums      = array();

    foreach ($idl as $i=>$val) {
      $type = $val->type;
      if ($type === "interface") {
        $this->interfaces[$val->name] = $val;
      }
      elseif ($type === "struct") {
        $this->structs[$val->name] = $val;
      }
      elseif ($type === "enum") {
        $this->enums[$val->name] = $val;
      }
    }
  }

  function getInterface($name) {
    return $this->interfaces[$name];
  }

  function checkInterface($interfaceName) {
    $iface = $this->getInterface($interfaceName);
    if (!$iface) {
      throw new Exception("No interface found with name: $interfaceName");
    }
  }

}

?>