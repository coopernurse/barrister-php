<?php

namespace coopernurse\Barrister;

class Decoder {

  function json_decode($jsonStr) {
    if ($jsonStr === null || $jsonStr === "null") {
      return null;
    }

    $ok  = true;
    $val = json_decode($jsonStr);
    if (function_exists('json_last_error')) {
      if (json_last_error() !== JSON_ERROR_NONE) {
        $ok = false;
      }
    }
    else if ($val === null) {
      $ok = false;
    }

    if ($ok) {
      return $val;
    }
    else {
      $s = substr($jsonStr, 0, 100);
      throw new RpcException(-32700, "Unable to decode JSON. First 100 chars: $s");
    }
  }

}
