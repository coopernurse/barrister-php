#!/usr/bin/env php
<?php

include_once(dirname(__FILE__) . "/../../barrister.php");

$idlDir = '../../barrister/conform';
if (getenv('WORKSPACE')) {
  $idlDir = getenv('WORKSPACE') . "/conform";
}
elseif (getenv('PYTHONPATH')) {
  $idlDir = getenv('PYTHONPATH') . "/conform";
}
elseif (getenv('BARRISTER')) {
  $idlDir = getenv('BARRISTER') . "/conform";
}

class A {

  function add($a, $b) {
    return $a+$b;
  }

  function calc($nums, $op) {
    $total = 0;
    if ($op === "multiply") {
      $total = 1;
    }

    foreach ($nums as $i=>$num) {
      if ($op === "add") {
        $total += $num;
      }
      elseif ($op === "multiply") {
        $total = $total * $num;
      }
      else {
        throw new Exception("Unknown op: $op");
      }
    }

    return $total;
  }

  function sqrt($a) {
    return sqrt($a);
  }

  function repeat($req) {
    $resp = array("status"=>"ok", "count"=> $req->count, "items"=> array() );
    $s = $req->to_repeat;
    if ($req->force_uppercase) {
      $s = strtoupper($s);
    }

    for ($i = 0; $i < $req->count; $i++) {
      array_push($resp["items"], $s);
    }

    return $resp;
  }

  function repeat_num($num, $count) {
    $arr = array();
    for ($i = 0; $i < $count; $i++) {
      array_push($arr, $num);
    }
    return $arr;
  }

  function say_hi() {
    return array("hi"=>"hi");
  }

  function putPerson($person) {
    return $person->personId;
  }

}

class B {

  function echo_($s) {
    if ($s === "return-null") {
      return null;
    }
    else {
      return $s;
    }
  }

}

$server = new BarristerServer($idlDir . "/conform.json");
$server->addHandler("A", new A());
$server->addHandler("B", new B());
$server->handleHTTP();

?>
