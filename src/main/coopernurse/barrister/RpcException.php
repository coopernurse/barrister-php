<?php

namespace coopernurse\barrister;

class RpcException extends \Exception {

  function __construct($code, $message, $data=null) {
    parent::__construct($message, $code);
    $this->data = $data;
  }

  function getData() {
    return $this->data;
  }

  function __toString() {
    $s = "RpcException code=" . $this->getCode() . " message=" . $this->getMessage();
    if (isset($this->data)) {
      $s .= " data=" . $this->data;
    }
    return $s;
  }

}
