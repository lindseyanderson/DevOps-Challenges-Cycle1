<?php
/* 
 * @name: Lindsey Anderson
 * @date: 12/31/2013
 * @desc: Write a script that builds a 512MB Cloud Server and returns the root
 *        password and IP address for the server. This must be done in PHP with
 *        php-opencloud 
 **/

  require 'vendor/autoload.php';

  use OpenCloud\Rackspace;
  use OpenCloud\Compute\Constants\Network;
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
      'apiKey'   => $rs_apikey
  ));

  // Hard coding IAD as our default datacenter for this challenge
  $compute = $client->computeService('cloudServersOpenStack', 'IAD');
  $images = $compute->imageList();
  // Hard coding Wheezy image ID for this challenge
  $wheezy = $compute->image('857d7d36-34f3-409f-8435-693e8797be8b');
  // Hard coding 512MB flavor ID for this challenge
  $serverFlavor = $compute->flavor('2');

  // Create our server!
  $server = $compute->Server();
  try {
    $response = $server->Create(array(
      'name'     => 'Challenge 1 Server',
      'image'    => $wheezy,
      'flavor'   => $serverFlavor,
      'networks' => array(
        $compute->network(Network::RAX_PUBLIC),
        $compute->network(Network::RAX_PRIVATE)
      )
    ));
  } catch (\Guzzle\Http\Exception\BadResponseException $e) {
    $responseBody = (string) $e->getResponse()->getBody();
    $statusCode = $e->getResponse()->getStatusCode();
    $headers = $e->getResponse()->getHeaderLines();

    echo sprintf('Status: %s\nBody: %s\nHeaders: %s', $statusCode, $responseBody, implode(', ', $headers));
  }
  // Store our admin password, only available on creation
  $adminPassword = $server->adminPass; 

  // Wait for our server to complete 
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
  $server->waitFor(ServerState::ACTIVE, 600, $callback);
 
  printf("Server %s build complete!\n", $server->name);
  printf(str_repeat("=", 80) . "\n");
  printf("• Server   : %s\n", $server->name);
  printf("• Username : root\n");
  printf("• Password : %s\n", $adminPassword);
  printf("• IPv4 Pub : %s\n", $server->accessIPv4);
  printf(str_repeat("=", 80). "\n");
?>
