<?php
/*
 * @name: Lindsey Anderson
 * @date: 12/31/2013
 * @desc:Challenge 3: Write a script that prints a list of all of the DNS 
 * domains on an account. Let the user select a domain from the list and add an
 * "A" record to that domain by entering an IP Address TTL, and requested "A" 
 * record text. This must be done in PHP with php-opencloud.
**/

  require 'vendor/autoload.php';

  use OpenCloud\Rackspace;

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

  $dns = $client->dnsService();
  $domains = $dns->domainList();

  // Get user input
  foreach($domains as $key => $ldomain) {
    $domainName[$key]['name'] = $ldomain->name();
    $domainName[$key]['id']   = $ldomain->id();
    printf("%s) %s\n", $key, $domainName[$key]['name']);
  }
  printf("Please select a domain from the list:\n");
  $input = trim(fgets(STDIN));
  printf("You have selected %s!\n", $domainName[$input]['name']);
  printf("ID: %s\n", $domainName[$input]['id']);
  $serverID = $domainName[$input]['id'];
  $serverName = $domainName[$input]['name'];
  printf("Please enter the A record data (ex: www.):\n");
  $a_data = trim(fgets(STDIN));
  printf("Please enter the TTL for your object (in seconds):\n");
  $a_ttl = trim(fgets(STDIN));
  printf("Please enter the IP address for your new A record:\n");
  $a_ip = trim(fgets(STDIN));
  $a_name = $a_data . $domainName[$input]['name'];

  $dns = $client->dnsService();
  $dlist = $dns->DomainList();
  while ($domain = $dlist->next()) {
    if ($domain->name() == $serverName) {
      $record = $domain->record();
      $response = $record->create(array(
        'type'  => 'A',
        'ttl'   => $a_ttl,
        'name'  => $a_name,
        'data'  => $a_ip
      ));

      $func = function($object) {
        if(!empty($object->error)) {
          var_dump($object->error);
          exit(1);
        } else {
          echo sprintf(
            "Waiting on %s/%-12s %4s\n",
            $object->name(),
            $object->status(),
            isset($object->progress) ? $object->progress . '%' : 0
          );
        }
      };
      $response->waitFor("COMPLETED", 300, $func, 1);
    }
  } 
