<?php
/* 
 * @name: Lindsey Anderson
 * @date: 12/31/2013
 * @desc: Challenge 2: Write a script that builds anywhere from 1 to 3 512MB 
 *        cloud servers (the number is based on user input). Inject an SSH 
 *        public key into the server for login. Return the IP addresses for the
 *        server. The servers should take their name from user input, and add a
 *        numerical identifier to the name. For example, if the user inputs 
 *        "bob", the servers should be named bob1, bob2, etc... This must be 
 *        done in PHP with php-opencloud. 
 **/

  require 'vendor/autoload.php';
 
  use OpenCloud\Rackspace;
  use OpenCloud\Compute\Constants\ServerState;

  /* Parse our credentials from the desired location
   * Expected format:
   *
   * username=RS_USERNAME
   * apikey=RS_APIKEY
  **/
  $credentials_file = $_SERVER['HOME'] . "/.rackspace_cloud_credentials";
  if (!is_readable($credentials_file)) {
    print("The credentials file could not be found or the file is not readable!\n");
    exit(1);
  }
  
  /*
   * Parse our text file for credentials
   *
  **/
  $file = file_get_contents($credentials_file);
  $creds_rows = explode("\n", $file);
  foreach ($creds_rows as $value) {
    $data = explode("=", $value);
    if($data[0] == "username") $rs_username = $data[1];
    if($data[0] == "apikey") $rs_apikey = $data[1];
  }
  // Make sure we have credentials stored
  if (empty($rs_username) || empty($rs_apikey)) exit(1);
 
  // Begin authentication
  $client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
      'username' => $rs_username,
      'apiKey' => $rs_apikey
  ));


  // Gather the number of servers we're going to build
  printf("Welcome to Challenge 2!\n");
  $numServers = getInput("Please let us know how many servers you would like to create: [1-3]");
  while (!is_numeric($numServers) || (int)$numServers > 3 || (int)$numServers < 0 ) {
    printf("There was an error!  Please select a number between 1 and 3.\n");
    $numServers = getInput("Please let us know how many servers you would like to create: [1-3]");
  }
  printf("You have selected to build %s server(s).\n", $numServers);
  // Get information to build the servers
  $baseName = getInput("Please input the base name for the server(s): "); 
  printf("You have input \"%s\" as the basename.\n", $baseName);
  // Get the path to our public key 
  $publicKeyLoc = getInput("Please enter the location of your public key on your local machine:");
  while (!is_readable($publicKeyLoc)) {
    print("There was an error! Please re-enter the correct full path to your public key.\n");
    $publicKeyLoc = getInput("Please enter the location of your public key on your local machine:");
  }
  printf("Your public key exists at '%s'\n", $publicKeyLoc);
  
  
   
  // Hard coding IAD as our default datacenter for this challenge
  $compute = $client->computeService('cloudServersOpenStack', 'IAD');
  // Hard coding Wheezy image ID for this challenge
  $image = $compute->image('857d7d36-34f3-409f-8435-693e8797be8b');
  // Hard coding 512MB flavor ID for this challenge
  $serverFlavor = $compute->flavor('2');

  // Loop through servers, there's currently a listed bug for creating keypairs with php-opencloud:
  // https://github.com/rackspace/php-opencloud/blob/master/docs/changelog/1.7.0.md
  for ($i=1; $i<=$numServers; $i++) {
    $server[$i] = $compute->Server();
    $localName = $baseName . $i;
    try {
      $response = $server[$i]->Create(array(
        'name'    => $localName,
        'image'   => $image,
        'flavor'  => $serverFlavor,
        'keypair' => array(
          'name'      => 'id_rsa.pub',
          'publicKey' => file_get_contents($publicKeyLoc)
        )
      ));
    } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
      $responseBody = (string) $e->getResponse()->getBody();
      $statusCode = $e->getResponse()->getStatusCode();
      $headers = $e->getResponse()->getHeaderLines();

      echo sprintf('Status: %s\nBody: %s\nHeaders: %s', $statusCode, $responseBody, implode(', ', $headers));
    }
    $preserveServer[$i] = $server[$i]->id;
    printf("Server %s is building!\n", $localName);
  }

  // Monitor the last server to be built, most likely to finish last
  $callback = function($server) {
    if(!empty($server->error)) {
      var_dump($server->error);
      exit(1);
    } else {
      echo sprintf(
        "Waiting on %s/%-12s %4s%%\n",
        $server->name(),
        $server->status(),
        isset($server->progress) ? $server->progress : 0
      );
    }
  };
  $server[$numServers]->waitFor(ServerState::ACTIVE, 600, $callback);
  
  // Print details to the user.
  for ($i=1; $i<=$numServers; $i++) {
    $server = $compute->Server($preserveServer[$i]);
    printf(str_repeat("=", 80) . "\n");
    printf("• Server   : %s\n", $server->name);
    printf("• Username : root\n");
    printf("• IPv4 Pub : %s\n", $server->accessIPv4);
    printf("*** The current state of this server is %s ***\n", $server->status());
    if ($server->status() == "BUILD") {
      printf("*** Current PROGRESS is %s ***\n", $server->progress);
    }
    printf(str_repeat("=", 80) . "\n");
  }


  /*
   * Take input and return a value
   */
  function getInput($message) {
    printf($message . "\n");
    $input = trim(fgets(STDIN));
    return $input;
  }
  
