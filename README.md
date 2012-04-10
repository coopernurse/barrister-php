
## Installation

Download from GitHub:

    curl -o barrister.php https://raw.github.com/coopernurse/barrister-php/master/barrister.php

To write a client:

    <?php
        include_once("barrister.php");
        
        $barrister = new Barrister();
        $client = $barrister->httpClient("http://example.org/service");
    ?>
    
To expose a service:

    <?php
        include_once("barrister.php");
        
        // substitute your interface .json file here
        // use the 'barrister' command line tool to produce the json file
        // from your IDL
        $server = new BarristerServer("calc.json");
        
        // bind your implementation classes to interface names
        $server->addHandler("Calculator", new Calculator());
        
        // will parse the raw POST data, invoke the correct
        // handler function, and send the result as JSON
        $server->handleHTTP();
    ?>

## Dependencies

This library was developed using PHP 5.3.8 (MacOS) and tested on PHP 5.2.4 (Linux).  It uses the
`json_decode` and `json_encode` functions added in PHP 5.2.  

The client code uses the PHP `curl` functions. Run: `php -m | grep curl` from the command line to 
conform that your PHP install has curl availble.  Alternately you can run: `phpinfo()` from a script.

If you are using a version of PHP older than 5.2, please download a JSON library that provides
`json_decode` and `json_encode` functions.  Facebook put the PEAR JSON library on github here:

https://github.com/facebook/platform/tree/master/clients/php/trunk/jsonwrapper

## More information

* [Barrister site](http://barrister.bitmechanic.com/) - Includes examples
* [IDL docs](http://barrister.bitmechanic.com/docs.html) - How to write an IDL and convert to JSON
