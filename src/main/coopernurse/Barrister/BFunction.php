<?php

namespace coopernurse\Barrister;

class BFunction {

  function __construct($func) {
    $this->returns = $func->returns;
    $this->params  = $func->params;
  }

  function validateParams(Contract $contract, $reqParams) {
    $len = count($this->params);
    if ($len != count($reqParams)) {
      return "Param length: " . count($reqParams) . " != expected length: $len";
    }

    for ($i = 0; $i < $len; $i++) {
      $p = $this->params[$i];
      $invalid = $contract->validate($p->name, $p, $p->is_array, $reqParams[$i]);
      if ($invalid !== null) {
        return "Invalid request param[$i]: $invalid";
      }
    }

    return null;
  }

  function validateResult(Contract $contract, $result) {
    return $contract->validate("", $this->returns, $this->returns->is_array, $result);
  }

}