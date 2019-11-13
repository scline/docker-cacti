<?php
//////////////////////////////////////////////////////////////////////////////
// ss_bwtest_curl.php
// @author: yreddy (melk0r101@yahoo.com)
// @version: 1.0 - 20100830
// CURL script to fetch a web page and print out statistics from the query. Can 
// be called either from command line or cacti script server.
//  IMPORTANT: NEED PHP CURL MODULE INSTALLED (aptitude install php5-curl)
//////////////////////////////////////////////////////////////////////////////



//////////////////////////////////////////////////////////////////////////////
// GLOBAL VARIABLES AND CONSTANT DEFINITIONS
//////////////////////////////////////////////////////////////////////////////

define("BWTEST_TIMEOUT", 3);



//////////////////////////////////////////////////////////////////////////////
// FUNCTIONS DEFINITIONS
//////////////////////////////////////////////////////////////////////////////

// FUNTION usage()
// Show script usage
function usage(){
  printf("Usage (command line): php ss_bwtest_curl.php -U URL [-P proxy_ip:proxy_port] [-u proxy_username] [-p proxy_password] [-t timeout] [-A useragent]\n");
  printf("Usage (Script Server): ss_bwtest_curl.php URL [proxy_ip:proxy_port] [proxy_username] [proxy_password] [timeout] [useragent]\n");
}

// FUNTION help()
// Show script usage and show details of all output fields
function help (){
  usage();
  printf("\nOutput Fields:
  CURLINFO_TOTAL_TIME - Total transaction time in seconds for last transfer 
  CURLINFO_NAMELOOKUP_TIME - Time in seconds until name resolving was complete 
  CURLINFO_CONNECT_TIME - Time in seconds it took to establish the connection
  CURLINFO_PRETRANSFER_TIME - Time in seconds from start until just before file transfer begins
  CURLINFO_STARTTRANSFER_TIME - Time in seconds until the first byte is about to be transferred 
  CURLINFO_REDIRECT_TIME - Time in seconds of all redirection steps before final transaction was started 
  CURLINFO_SIZE_UPLOAD - Total number of bytes uploaded
  CURLINFO_SIZE_DOWNLOAD - Total number of bytes downloaded 
  CURLINFO_SPEED_DOWNLOAD- Average download speed
  CURLINFO_SPEED_UPLOAD - Average upload speed
  CURLINFO_HEADER_SIZE - Total size of all headers received 
  CURLINFO_REQUEST_SIZE - Total size of issued requests, currently only for HTTP requests 
  CURLINFO_CONTENT_LENGTH_DOWNLOAD - content-length of download, read from Content-Length: field
  CURLINFO_CONTENT_LENGTH_DOWNLOAD - Specified size of upload\n");
}

// FUNTION ss_bwtest_curl()
// Main function. Create CURL object with appropriate variables, access web page and return statistics
function ss_bwtest_curl($URL, $proxy=null, $proxy_username=null, $proxy_password=null, $timeout=BWTEST_TIMEOUT, $useragent=null){
  //init CURL OBJECT
  $ch=curl_init();
  
  //set CURL options
  //curl_setopt - CURLOPT_URL - The URL to fetch. This can also be set when initializing a session with curl_init(). 
  curl_setopt($ch, CURLOPT_URL, $URL);
  if ((!is_null($proxy))&&(preg_match("/http:\/\/[^:]+:[0-9]+/", $proxy))){
    //curl_setopt - CURLOPT_PROXY - The HTTP proxy to tunnel requests through. 
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    if ((!is_null($proxy_username))&&(!is_null($proxy_username))){
      //curl_setopt - CURLOPT_PROXYUSERPWD - A username and password formatted as "[username]:[password]" to use for the connection to the proxy. 
      curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_username.":".$proxy_password);
    }
  }
  //curl_setopt - CURLOPT_DNS_USE_GLOBAL_CACHE - TRUE to use a global DNS cache. This option is not thread-safe and is enabled by default.  
  curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
  //curl_setopt - CURLOPT_FOLLOWLOCATION - TRUE to follow any "Location: " header that the server sends as part of the HTTP header (note this is recursive, PHP will follow as many "Location: " headers that it is sent, unless CURLOPT_MAXREDIRS is set). 
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);  
  //curl_setopt - CURLOPT_FORBID_REUSE - TRUE to force the connection to explicitly close when it has finished processing, and not be pooled for reuse. 
  curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
  //curl_setopt - CURLOPT_FRESH_CONNECT - TRUE to force the use of a new connection instead of a cached one. 
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
  //curl_setopt - CURLOPT_HEADER - TRUE to include the header in the output. 
  curl_setopt($ch, CURLOPT_HEADER, false);
  //curl_setopt - CURLOPT_RETURNTRANSFER - TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly. 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  //curl_setopt - CURLOPT_TIMEOUT - The maximum number of seconds to allow cURL functions to execute. 
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
  //curl_setopt - CURLOPT_USERAGENT - The contents of the "User-Agent: " header to be used in a HTTP request.
  if (!is_null($useragent)){
    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
  }
	//curl_setopt - CURLOPT_SSL_VERIFYPEER - FALSE to stop cURL from verifying the peer's certificate.
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  
  //execute HTTP GET
  $webpage = curl_exec($ch);
  
  // Fetch statistic from download through available variables
  //CURLINFO_TOTAL_TIME - Total transaction time in seconds for last transfer 
  $CURLINFO_TOTAL_TIME = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
  //CURLINFO_NAMELOOKUP_TIME - Time in seconds until name resolving was complete 
  $CURLINFO_NAMELOOKUP_TIME = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
  //CURLINFO_CONNECT_TIME - Time in seconds it took to establish the connection
  $CURLINFO_CONNECT_TIME = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
  //CURLINFO_PRETRANSFER_TIME - Time in seconds from start until just before file transfer begins
  $CURLINFO_PRETRANSFER_TIME = curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME);
  //CURLINFO_STARTTRANSFER_TIME - Time in seconds until the first byte is about to be transferred 
  $CURLINFO_STARTTRANSFER_TIME = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
  //CURLINFO_REDIRECT_TIME - Time in seconds of all redirection steps before final transaction was started 
  $CURLINFO_REDIRECT_TIME = curl_getinfo($ch, CURLINFO_REDIRECT_TIME);
  //CURLINFO_SIZE_UPLOAD - Total number of bytes uploaded
  $CURLINFO_SIZE_UPLOAD = curl_getinfo($ch, CURLINFO_SIZE_UPLOAD);
  //CURLINFO_SIZE_DOWNLOAD - Total number of bytes downloaded 
  $CURLINFO_SIZE_DOWNLOAD = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
  //CURLINFO_SPEED_DOWNLOAD - Average download speed 
  $CURLINFO_SPEED_DOWNLOAD = curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD);
  //CURLINFO_SPEED_UPLOAD - Average upload speed 
  $CURLINFO_SPEED_UPLOAD = curl_getinfo($ch, CURLINFO_SPEED_UPLOAD);
  //CURLINFO_HEADER_SIZE - Total size of all headers received 
  $CURLINFO_HEADER_SIZE = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  //CURLINFO_REQUEST_SIZE - Total size of issued requests, currently only for HTTP requests 
  $CURLINFO_REQUEST_SIZE = curl_getinfo($ch, CURLINFO_REQUEST_SIZE);
  //CURLINFO_CONTENT_LENGTH_DOWNLOAD - content-length of download, read from Content-Length: field
  $CURLINFO_CONTENT_LENGTH_DOWNLOAD = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
  //CURLINFO_CONTENT_LENGTH_UPLOAD - Specified size of upload
  $CURLINFO_CONTENT_LENGTH_UPLOAD = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_UPLOAD); 
   
  // CLOSE CURL OBJECT
  curl_close($ch);
  
  // PRINT OUT STATISTICS
  $ret=sprintf("CURLINFO_TOTAL_TIME:%0.6f CURLINFO_NAMELOOKUP_TIME:%0.6f CURLINFO_CONNECT_TIME:%0.6f CURLINFO_PRETRANSFER_TIME:%0.6f CURLINFO_STARTTRANSFER_TIME:%0.6f CURLINFO_REDIRECT_TIME:%0.6f CURLINFO_SIZE_UPLOAD:%d CURLINFO_SIZE_DOWNLOAD:%d CURLINFO_SPEED_DOWNLOAD:%d CURLINFO_SPEED_UPLOAD:%d CURLINFO_HEADER_SIZE:%d CURLINFO_REQUEST_SIZE:%d CURLINFO_CONTENT_LENGTH_DOWNLOAD:%d CURLINFO_CONTENT_LENGTH_UPLOAD:%d\n",$CURLINFO_TOTAL_TIME,$CURLINFO_NAMELOOKUP_TIME,$CURLINFO_CONNECT_TIME,$CURLINFO_PRETRANSFER_TIME,$CURLINFO_STARTTRANSFER_TIME,$CURLINFO_REDIRECT_TIME,$CURLINFO_SIZE_UPLOAD,$CURLINFO_SIZE_DOWNLOAD,$CURLINFO_SPEED_DOWNLOAD,$CURLINFO_SPEED_UPLOAD,$CURLINFO_HEADER_SIZE,$CURLINFO_REQUEST_SIZE,$CURLINFO_CONTENT_LENGTH_DOWNLOAD,$CURLINFO_CONTENT_LENGTH_UPLOAD);
  return $ret;
}



//////////////////////////////////////////////////////////////////////////////
// MAIN PROGRAM
//////////////////////////////////////////////////////////////////////////////

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die("<br><strong>This script is only meant to run at the command line.</strong>");
}
$no_http_headers = true;

/* display No errors */
error_reporting(0);

include_once(dirname(__FILE__) . "/../include/global.php");

// If called directly from command line
if (!isset($called_by_script_server)) {
  // read command line argumet with getopt
  $arrArgv = getopt("hU:P:u:p:A:t:c:");
  if (isset($arrArgv["h"])){
    help();
    return 1;
  }
  if (isset($arrArgv["U"])){
    $URL=$arrArgv["U"];
  }else{
    usage();
    return 1;
  }
  if (isset($arrArgv["P"])){
    $proxy=$arrArgv["P"];
  }else{
    $proxy=null;
  }
  if (isset($arrArgv["u"])){
    $proxy_username=$arrArgv["u"];
  }else{
    $proxy_username=null;
  }
  if (isset($arrArgv["p"])){
    $proxy_password=$arrArgv["p"];
  }else{
    $proxy_password=null;
  }
  if (isset($arrArgv["t"])){
    $timeout=$arrArgv["t"];
  }else{
    $timeout=BWTEST_TIMEOUT;
  }
  if (isset($arrArgv["A"])){
    $useragent=$arrArgv["A"];
  }else{
    $useragent=null;
  }
  // call appropriate function
  print call_user_func_array("ss_bwtest_curl", array ($URL,$proxy,$proxy_username,$proxy_password,$timeout,$useragent));
}
?>
