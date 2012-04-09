#!/usr/bin/env php
<?php

include_once(dirname(__FILE__) . "/../barrister.php");

$inFile  = $argv[1];
$outFile = $argv[2];

$out = fopen($outFile, "w");

function log_result($fh, $iface, $func, $params, $resp) {
  $status = "ok";
  $result = -1;
  if ($resp->error) {
    $status = "rpcerr";
    $result = $resp->error->code;
  }
  else {
    $result = $resp->result;
  }

  fprintf($fh, "%s|%s|%s|%s|%s\n", $iface, $func, $params, $status, json_encode($result));
}

$barrister = new Barrister();
$client    = $barrister->httpClient("http://localhost:9233/");

$in = fopen($inFile, "r");
while (($line = fgets($in)) !== false) {
  $line = trim($line);
  if ($line === "" || strpos($line, "#") === 0) {
    continue;
  }

  if ($line === "start_batch") {

  }
  elseif ($line === "end_batch") {

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
    $result = $client->request($method, $paramsNative);
    if (is_array($result)) {
      foreach ($result as $i=>$r) {
        log_result($out, $iface, $func, $params, $r);
      }
    }
    else {
      log_result($out, $iface, $func, $params, $result);
    }
  }
}
fclose($in);
fclose($out);

?>