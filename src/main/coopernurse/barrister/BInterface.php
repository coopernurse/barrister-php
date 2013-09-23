<?php

namespace coopernurse\barrister;

class BInterface {

  /** @var BFunction[] */
  public $functions;

  function __construct($iface) {
    $this->functions = array();
    foreach ($iface->functions as $i=>$func) {
      $this->functions[$func->name] = new BFunction($func);
    }
  }

  /**
   * @param string $name
   * @return BFunction
   */
  function getFunction($name) {
    return $this->functions[$name];
  }

}