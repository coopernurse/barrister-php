<?php

namespace coopernurse\barrister;

interface RequestInterface {

  /**
   * Send a request to a barrister server and return the decoded results
   *
   * @param array $req
   * @return object|array
   */
  public function request($req);

}
