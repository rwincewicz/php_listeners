<?php

//do this until we expost these in a module or library
@include_once 'tuque/Datastream.php';
@include_once 'tuque/FedoraApi.php';
@include_once 'tuque/FedoraApiSerializer.php';
@include_once 'tuque/Object.php';
@include_once 'tuque/RepositoryConnection.php';
@include_once 'tuque/Cache.php';
@include_once 'tuque/RepositoryException.php';
@include_once 'tuque/Repository.php';
@include_once 'tuque/FedoraRelationships.php';

class ListenerObject {

  /**
   * Connection to the repository
   *
   * @var RepositoryConnection
   */
  public $connection = NULL;

  /**
   * The Fedora API we are using
   *
   * @var FedoraAPI
   */
  public $api = NULL;

  /**
   * The cache we use to connect.
   *
   * @var SimpleCache
   */
  public $cache = NULL;

  /**
   * The repository object.
   *
   * @var FedoraRepository
   */
  public $repository = NULL;

  function __construct($user = NULL, $url = NULL, $pid = NULL) {

    $user_string = $user->name;
    $pass_string = $user->pass;

    if (!isset($url)) {
      $url = 'http://localhost:8080/fedora';
    }

    if (self::exists()) {
      $this->connection = new RepositoryConnection($url, $user_string, $pass_string);
      $this->connection->reuseConnection = TRUE;
      $this->api = new FedoraApi($this->connection);
      $this->cache = new SimpleCache();
      $this->repository = new FedoraRepository($this->api, $this->cache);
      $this->object = new FedoraObject($pid, $this->repository);
    }
  }

  function saveDatastream($dsid = NULL, $extension = NULL) {
    if (!isset($dsid)) {
      return;
    }
    $datastream_array = array();
    foreach ($this->object as $datastream) {
      $datastream_array[] = $datastream->id;
    }
    if (!in_array($dsid, $datastream_array)) {
      print "Could not find the $dsid datastream!";
    }
    try {
      $datastream = $this->object->getDatastream($dsid);
      $mime_type = $datastream->mimetype;
      if (!$extension) {
        $extension = system_mime_type_extension($mime_type);
      }
      $tempfile = temp_filename($extension);
      $file_handle = fopen($tempfile, 'w');
      fwrite($file_handle, $datastream->content);
      fclose($file_handle);
    } catch (Exception $e) {
      print "Could not save datastream - $e";
    }

    return $tempfile;
  }

  static function exists() {
    return class_exists('RepositoryConnection');
  }

}

function system_extension_mime_types() {
  # Returns the system MIME type mapping of extensions to MIME types, as defined in /etc/mime.types.
  $out = array();
  $file = fopen('/etc/mime.types', 'r');
  while (($line = fgets($file)) !== false) {
    $line = trim(preg_replace('/#.*/', '', $line));
    if (!$line)
      continue;
    $parts = preg_split('/\s+/', $line);
    if (count($parts) == 1)
      continue;
    $type = array_shift($parts);
    foreach ($parts as $part)
      $out[$part] = $type;
  }
  fclose($file);
  return $out;
}

function system_extension_mime_type($file) {
  # Returns the system MIME type (as defined in /etc/mime.types) for the filename specified.
  #
    # $file - the filename to examine
  static $types;
  if (!isset($types))
    $types = system_extension_mime_types();
  $ext = pathinfo($file, PATHINFO_EXTENSION);
  if (!$ext)
    $ext = $file;
  $ext = strtolower($ext);
  return isset($types[$ext]) ? $types[$ext] : null;
}

function system_mime_type_extensions() {
  # Returns the system MIME type mapping of MIME types to extensions, as defined in /etc/mime.types (considering the first
  # extension listed to be canonical).
  $out = array();
  $file = fopen('/etc/mime.types', 'r');
  while (($line = fgets($file)) !== false) {
    $line = trim(preg_replace('/#.*/', '', $line));
    if (!$line)
      continue;
    $parts = preg_split('/\s+/', $line);
    if (count($parts) == 1)
      continue;
    $type = array_shift($parts);
    if (!isset($out[$type]))
      $out[$type] = array_shift($parts);
  }
  fclose($file);
  return $out;
}

function system_mime_type_extension($type) {
  # Returns the canonical file extension for the MIME type specified, as defined in /etc/mime.types (considering the first
  # extension listed to be canonical).
  #
    # $type - the MIME type
  static $exts;
  if (!isset($exts))
    $exts = system_mime_type_extensions();
  return isset($exts[$type]) ? $exts[$type] : null;
}

function temp_filename($extension = NULL) {
  while (true) {
    $filename = sys_get_temp_dir() . '/' . uniqid(rand()) . '.' . $extension;
    if (!file_exists($filename))
      break;
  }
  return $filename;
}

function fedora_object_exists($fedora_url = 'http://localhost:8080/fedora', $user = NULL, $pid = NULL) {
  if (!isset($pid)) {
    return;
  }

  $fedora_user = $user->name;
  $fedora_pass = $user->pass;

  $url = $fedora_url . '/objects/' . $pid;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERPWD, "$fedora_user:$fedora_pass");
  $content = curl_exec($ch);

  if ($content == $pid) {
    return FALSE;
  }
  else {
    return TRUE;
  }
}

?>
