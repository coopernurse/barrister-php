<?php

namespace coopernurse\Barrister;

interface RequestInterface {

  /**
   * Send a request to a Barrister server and return the decoded results
   *
   * @param array $req
   * @return object|array
   */
  public function request($req);

}
