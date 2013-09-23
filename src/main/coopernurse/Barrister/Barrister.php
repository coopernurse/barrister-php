<?php

namespace coopernurse\Barrister;

class Barrister {

  function httpClient($url) {
    return new Client(new HttpTransport($url));
  }

}
