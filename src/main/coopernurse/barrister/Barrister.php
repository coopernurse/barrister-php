<?php

namespace coopernurse\barrister;

class Barrister {

  function httpClient($url) {
    return new Client(new HttpTransport($url));
  }

}
