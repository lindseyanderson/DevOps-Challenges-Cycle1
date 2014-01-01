<?php
/*
 * @name: Lindsey Anderson
 * @date: 12/31/2013
 * @desc: Challenge 4: Write a script that creates a Cloud Files Container. If 
 *        the container already exists, exit and let the user know. The script 
 *        should also upload a directory from the local filesystem to the new 
 *        container, and enable CDN for the new container.  The script must 
 *        return the CDN URL. This must be done in PHP with php-opencloud. 
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


  $ourContainer = "devops-challenge4";
  $service = $client->objectStoreService('cloudFiles');
  $container = $service->createContainer($ourContainer);
  if (!$container) {
    printf("This container, %s, exists.  Exiting.\n", $ourContainer);
    exit(1);
  }
  printf("This container has been created!\n");
  
  // hard coding upload directory
  $uploadDir = "/tmp/deCFUpload";


  // Known bug with the uploadDirectory that forces you to modify the resourceIterator
  // https://github.com/rackspace/php-opencloud/issues/249
  $container->uploadDirectory($uploadDir);
  printf("The contents of %s have been uploaded to the container.\n", $uploadDir);
  
  // enable the CDN
  $container->enableCdn();
  $cdn = $container->getCdn();
  printf("The CDN has been abled for your new container.\n");
  // return the CDN url
  $cdnURL = $cdn->getCdnUri();
  printf("Your container's new URL is:\n");
  printf("  %s\n", $cdnURL);
