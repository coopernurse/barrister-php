#!/usr/bin/env php
<?php

include_once(dirname(__FILE__) . "/../barrister.php");

function log_result($fh, $iface, $func, $params, $resp) {
  $status = "ok";
  $result = -1;
  if (isset($resp->error)) {
    $status = "rpcerr";
    print "$iface.$func " . $resp->error->message . "\n";
    $result = $resp->error->code;
  }
  else {
    $result = $resp->result;
  }

  fprintf($fh, "%s|%s|%s|%s|%s\n", $iface, $func, $params, $status, json_encode($result));
}


$inFile  = $argv[1];
$outFile = $argv[2];

$out = fopen($outFile, "w");

$barrister = new Barrister();
$client    = $barrister->httpClient("http://localhost:9233/");

$in = fopen($inFile, "r");

$batch = null;

while (($line = fgets($in)) !== false) {
  $line = trim($line);
  if ($line === "" || strpos($line, "#") === 0) {
    continue;
  }

  if ($line === "start_batch") {
    $batch = $client->startBatch();
  }
  elseif ($line === "end_batch") {
    $results = $batch->send();
    foreach ($results as $i=>$res) {
      $req = $batch->getRequest($i);
      $parts = preg_split("/\\./", $req["method"]);
      log_result($out, $parts[0], $parts[1], $req["params"], $res);
    }
    $batch = null;
  }
  else {
    $cols = preg_split("/\\|/", $line);
    $iface     = $cols[0];
    $func      = $cols[1];
    $params    = $cols[2];
    $expStatus = $cols[3];
    $expResult = $cols[4];

    $paramsNative = json_decode($params);
    $method = $iface . "." . $func;
    if ($batch) {
      $batch->request($method, $paramsNative);
    }
    else {
      $result = $client->request($method, $paramsNative);
      log_result($out, $iface, $func, $params, $result);
    }
  }
}
fclose($in);
fclose($out);

?>