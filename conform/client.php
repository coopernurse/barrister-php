<?php
require '../vendor/autoload.php';

$inFile  = $argv[1];

$barrister = new \coopernurse\Barrister\Barrister();
$client    = $barrister->httpClient("http://localhost:9233/server.php");

$in = fopen($inFile, "r");

$batch = null;

while (($line = fgets($in)) !== false) {

  $line = trim($line);

  if ($line === "" || $line[0] === "#") {
    continue;
  }

  echo "\n -- $line\n";

  if ($line === "start_batch") {
    $batch = $client->startBatch();
  }
  elseif ($line === "end_batch") {
    $results = $batch->send();
    foreach ($results as $i => $result) {
      echo ' -> ' . json_encode($batch->getRequest($i)) . "\n";
      echo ' <- ' . json_encode($result) . "\n";
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
      echo ' -> ' . json_encode($client->createRequest($method, $paramsNative)) . "\n";
      echo ' <- ' . json_encode($client->request($method, $paramsNative)) . "\n";
    }
  }
}
fclose($in);
