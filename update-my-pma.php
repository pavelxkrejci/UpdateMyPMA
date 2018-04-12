<?php
/**
 * Update My PMA (C) Pavel Krejci 2015 v1.0
 * includes PMA version checking from http://www.phpmyadmin.net/home_page/version.json
 */

error_reporting(E_ALL & ~E_NOTICE); //debug
ini_set('display_errors', 1); //debug

//---------- EDIT THESE VALUES TO CONFIGURE ---------------------------------

// PMA paths:
  $tmp_folder = 'c:\\inetpub\\wwwroot\\phpmyadmin\\upload\\'; // where the zip file will be downloaded
  $target_folder = 'c:\\inetpub\\wwwroot\\phpmyadmin'; // where is your PMA, ie. '/var/www/phpmyadmin' on Linux

// Curl SSL setup
  define("ALLOW_NON_SSL_FALLBACK", false);
  $ca_certs = ""; //path to file with SSL CA certificate(s), set if not defined globally in php.ini (curl.cainfo). You can get one here http://curl.haxx.se/docs/caextract.html

// Notification e-mail parameters:
  define("SEND_NOTIFICATION_EMAIL", false);
  $Name = "Web name"; //senders name
  $email = "noreply@yourdomain.com"; //senders e-mail adress
  $recipient = "yourmail@yourdomain.com";

//-----------------------------------------------------------------------------


function getLatestVersion() { //adapted from PMA_Util::getLatestVersion()  @Util.class.php

    // wait 3s at most for server response, it's enough to get information
    // from a working server
    $connection_timeout = 3;

    $file = 'https://www.phpmyadmin.net/home_page/version.json';

    $curl_handle = curl_init($file);
    curl_setopt($curl_handle, CURLOPT_HEADER, false);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_TIMEOUT, $connection_timeout);
    curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, false);

    $response = curl_exec($curl_handle);
    curl_close($curl_handle);

    $data = json_decode($response);
    return $data;
}

function getCurrentVersion($target_folder) {
    $ConfigClassFile = $target_folder . "/libraries/classes/Config.php";
    $VersionCode = '$this->set(\'PMA_VERSION\'';
    $ConfigClass = file_get_contents($ConfigClassFile, NULL);
    $position = strpos($ConfigClass,$VersionCode);
    if ($position === false) {
      $version = false;
    } else {
      $version = trim( substr($ConfigClass,$position + strlen($VersionCode),16) , " \t\n\r\0\x0B;,)'/*" );
    }
    unset($ConfigClass); //clear mem
    return $version;
}

function ZipStatusString( $status ) {
    switch( (int) $status )
    {
        case ZipArchive::ER_OK           : return 'N No error';
        case ZipArchive::ER_MULTIDISK    : return 'N Multi-disk zip archives not supported';
        case ZipArchive::ER_RENAME       : return 'S Renaming temporary file failed';
        case ZipArchive::ER_CLOSE        : return 'S Closing zip archive failed';
        case ZipArchive::ER_SEEK         : return 'S Seek error';
        case ZipArchive::ER_READ         : return 'S Read error';
        case ZipArchive::ER_WRITE        : return 'S Write error';
        case ZipArchive::ER_CRC          : return 'N CRC error';
        case ZipArchive::ER_ZIPCLOSED    : return 'N Containing zip archive was closed';
        case ZipArchive::ER_NOENT        : return 'N No such file';
        case ZipArchive::ER_EXISTS       : return 'N File already exists';
        case ZipArchive::ER_OPEN         : return 'S Can\'t open file';
        case ZipArchive::ER_TMPOPEN      : return 'S Failure to create temporary file';
        case ZipArchive::ER_ZLIB         : return 'Z Zlib error';
        case ZipArchive::ER_MEMORY       : return 'N Malloc failure';
        case ZipArchive::ER_CHANGED      : return 'N Entry has been changed';
        case ZipArchive::ER_COMPNOTSUPP  : return 'N Compression method not supported';
        case ZipArchive::ER_EOF          : return 'N Premature EOF';
        case ZipArchive::ER_INVAL        : return 'N Invalid argument';
        case ZipArchive::ER_NOZIP        : return 'N Not a zip archive';
        case ZipArchive::ER_INTERNAL     : return 'N Internal error';
        case ZipArchive::ER_INCONS       : return 'N Zip archive inconsistent';
        case ZipArchive::ER_REMOVE       : return 'S Can\'t remove file';
        case ZipArchive::ER_DELETED      : return 'N Entry has been deleted';

        default: return sprintf('Unknown status %s', $status );
    }
}

function download_pma($url,$certs="") {
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_TIMEOUT, 50);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
  curl_setopt($curl, CURLOPT_HEADER, false);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
  if (file_exists($certs)) {
    curl_setopt($curl, CURLOPT_CAINFO, $certs);
  }
  $rawout = curl_exec($curl);
  if ($rawout === false) {
      echo 'Curl error: ' . curl_error($curl)."\n";
  }
  curl_close($curl);
  return $rawout;
}

//main
$version = getLatestVersion();
$currentversion = getCurrentVersion($target_folder);

echo("<pre>\n"); //for readable browser output
print_r(
    array(
        'new_version' => $version->version,
        'date' => $version->date,
        'current' => $currentversion,
        'update' => ($version->version <> $currentversion) ? true : false
    )
);

if (!empty($version->version) && ($version->version <> $currentversion)) {
  echo ("New version detected - starting Update\n");

  $newpmaname = 'phpMyAdmin-'.$version->version.'-all-languages'; //multilingual version
  $newpmazipfilename = $newpmaname.'.zip'; //only ZIP version supported
  $newpmazipurl = 'https://files.phpmyadmin.net/phpMyAdmin/'.$version->version.'/'.$newpmazipfilename;  //URL since 2015-07

  //download start
  $dl_file = download_pma($newpmazipurl,$ca_certs);
  if ($dl_file === false) {
    echo ("Warning: Error downloading PMA\n");
    if (strpos($newpmazipurl,"https://") !== false && ALLOW_NON_SSL_FALLBACK) {
      echo ("Warning: Falling back to non-SSL URL...\n");
      $dl_file = download_pma(str_replace("https://","http://",$newpmazipurl));
    }
    if ( $dl_file === false) {
       die("Fatal Error: Download Failed.");
    }
  }
  if (strlen($dl_file) > 1000000) { //size sanity check
    $newpmazip = fopen ($tmp_folder.$newpmazipfilename, 'w+'); //target file for download
    fwrite($newpmazip, $dl_file);
    fclose($newpmazip);
    echo("Download OK.\n");
  } else {
    die("Fatal Error: Downloaded file too small!\n");
  }
  unset($dl_file); //clear mem
  //download end

  //process ZIP file
  $zip = new ZipArchive();
  $res = $zip->open($tmp_folder.$newpmazipfilename, ZipArchive::CHECKCONS);  // ZipArchive::CHECKCONS will enforce additional consistency checks
  if ($res === true) {
    echo ( "ZIP OK\n");

    $listzip = "";
    for($i = 0; $i < $zip->numFiles; $i++) {
       $listzip = $listzip . ($zip->getNameIndex($i) . "\n");
    }
    file_put_contents($tmp_folder.$newpmazipfilename.".filelist.log", $listzip); //log filelist
    unset($listzip); //clear mem

    //rename folder
    $i=0;
    while($item_name = $zip->getNameIndex($i)){
      $zip->renameIndex( $i, str_replace( $newpmaname."/", "", $item_name ) );
      $i++;
    }
    $zip->deleteName($newpmaname."/"); //delete last empty dir
    $zip->close(); //synch updated folder structure

    //extract ZIP
    $res = $zip->open($tmp_folder.$newpmazipfilename);
    echo 'Extraction ' . ($zip->extractTo($target_folder) ? "OK\n" : "Error\n"); //modifies archive file modification times
    $zip->close();

    //send mail
    $mail_body = "PMA auto update executed to target ver ".$version->version." released on ".$version->date; //mail body
    $subject = "PMA Update executed to ".$version->version; //subject
    $header = "From: ". $Name . " <" . $email . ">\r\n"; //optional headerfields
    if (SEND_NOTIFICATION_EMAIL) mail($recipient, $subject, $mail_body, $header);

  } else {
       echo ( ZipStatusString( $res ) ."\n");
       die("Error opening ZIP file.");
  }

} else { //do update
  echo ("PMA Update not detected. Nothing to do.");
}

?>