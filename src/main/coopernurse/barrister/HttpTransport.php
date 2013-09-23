<?php

namespace coopernurse\barrister;

class HttpTransport implements RequestInterface {

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
      $err = curl_error($ch);
      throw new RpcException(-32603, "HTTP POST to " . $this->url . " failed: " . $err);
    }
    else {
      //print "result: $result\n";
      $decoder = new Decoder();
      $resp = $decoder->json_decode($result);
      return $resp;
    }
  }

}
