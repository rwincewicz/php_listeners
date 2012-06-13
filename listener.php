<?php

// Include libraries
require_once("Stomp.php");
include_once 'message.php';
include_once 'fedoraConnection.php';

// Handle interrupt signals
declare(ticks = 1);
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");

// Load config file
$config_file = file_get_contents('config.xml');
$config_xml = new SimpleXMLElement($config_file);

// Logging settings
$log_file = $config_xml->log->file;
$log_level = $config_xml->log->level;

$log = new Logging();
$log->lfile($log_file);

// Set up Fedora settings
$fedora_url = 'http://' . $config_xml->fedora->host . ':' . $config_xml->fedora->port . '/fedora';
$user = new stdClass();
$user->name = $config_xml->fedora->username;
$user->pass = $config_xml->fedora->password;

// Set up stomp settings
$stomp_url = 'tcp://' . $config_xml->stomp->host . ':' . $config_xml->stomp->port;
$channel = $config_xml->stomp->channel;

if ($log_level <= 1) {
  foreach ($config_xml->plugin as $plugin) {
    $log->lwrite('Plugin: ' . $plugin->class . ' and ' . $plugin->function . ' on ' . $plugin->dsid);
  }
}

// Make a connection
$con = new Stomp($stomp_url);
// Connect
try {
  $con->connect();
} catch (Exception $e) {
  $log->lwrite("Could not connect to Stomp server - $e", 'ERROR');
}
// Subscribe to the queue
try {
  $con->subscribe((string) $channel[0]);
} catch (Exception $e) {
  $log->lwrite("Could not subscribe to the channel $channel - $e", 'ERROR');
}
// Receive a message from the queue
while (TRUE) {
  if ($con->hasFrameToRead()) {
    $msg = $con->readFrame();

    // do what you want with the message
    if ($msg != NULL) {
      $message = new Message($msg->body);
      if ($msg->headers['methodName'] == 'ingest') {
        try {
          $fedora_object = new ListenerObject($user, $fedora_url, $msg->headers['pid']);
          $object = $fedora_object->object;
          $temp_file = $fedora_object->saveDatastream('TN');
          $extension_array = explode('.', $temp_file);
          $extension = $extension_array[1];
          $new_file = temp_filename($extension);
          exec("convert $temp_file -resize 50% $new_file", $output, $return);
          if ($log_level <= 1) {
            $log->lwrite("Pid: " . $msg->headers['pid']);
            $log->lwrite("File: $temp_file");
            $log->lwrite("New file: $new_file");
            $log->lwrite("Output: " . implode(', ', $output));
            $log->lwrite("Return: $return");
            $log->lwrite("Models: " . implode(', ', $object->models));
          }
          $new_datastream = new NewFedoraDatastream('SMALL', 'M', $object, $fedora_object->repository);
          $new_datastream->setContentFromFile($new_file);
          $new_datastream->mimetype = 'image/png';
          $object->ingestDatastream($new_datastream);
        } catch (Exception $e) {
          $log->lwrite("An error occurred creating the derivative - $e", 'ERROR');
        }
        unset($fedora_object);
        unset($object);
        unset($new_datastream);
        unset($extension);
        unset($temp_file);
        unset($extension_array);
        unset($message);
      }
//      else {
//      }
      // Mark the message as received in the queue
      $con->ack($msg);
      unset($msg);
      echo "Memory usage: " . memory_get_usage() . "\n";
      var_dump(gc_enabled()); // true
      var_dump(gc_collect_cycles()); // # of elements cleaned up
    }
  }
}

// Disconnect
$con->disconnect();

function sig_handler($signo) { // this function will process sent signals
  if ($signo == SIGTERM || $signo == SIGHUP || $signo == SIGINT || $signo == SIGUSR1) {
    print "\tGrandchild : " . getmypid() . " I got signal $signo and will exit!\n";
// If this were something important we might do data cleanup here
    exit();
  }
}

?>