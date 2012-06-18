<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

$connect = new Connect();
$connect->listen();
unset($connect);

class Connect {

  function __construct() {
    require_once("Stomp.php");
    include_once 'message.php';
    include_once 'fedoraConnection.php';
    include_once 'connect.php';
    include_once 'Derivatives.php';
    include_once 'Logging.php';

    // Load config file
    $config_file = file_get_contents('config.xml');
    $config_xml = new SimpleXMLElement($config_file);

// Logging settings
    $log_file = $config_xml->log->file;
    $this->log->level = $config_xml->log->level;

    $this->log = new Logging();
    $this->log->lfile($log_file);

    $this->fedora_url = 'http://' . $config_xml->fedora->host . ':' . $config_xml->fedora->port . '/fedora';
    $this->user = new stdClass();
    $this->user->name = $config_xml->fedora->username;
    $this->user->pass = $config_xml->fedora->password;

// Set up stomp settings
    $stomp_url = 'tcp://' . $config_xml->stomp->host . ':' . $config_xml->stomp->port;
    $channel = $config_xml->stomp->channel;

// Make a connection
    $this->con = new Stomp($stomp_url);
    $this->con->sync = FALSE;
    $this->con->setReadTimeout(5);
// Connect
    try {
      $this->con->connect();
    } catch (Exception $e) {
      $this->log->lwrite("Could not connect to Stomp server - $e", 'ERROR');
    }
// Subscribe to the queue
    try {
      $this->con->subscribe((string) $channel[0], array('activemq.prefetchSize' => 100));
    } catch (Exception $e) {
      $this->log->lwrite("Could not subscribe to the channel $channel - $e", 'ERROR');
    }
  }

  function listen() {

    // Receive a message from the queue
    if ($this->msg = $this->con->readFrame()) {

      // do what you want with the message
      if ($this->msg != NULL) {
        $message = new Message($this->msg->body);
        $this->log->lwrite("Pid: " . $this->msg->headers['pid']);
        $this->log->lwrite("Method: " . $this->msg->headers['methodName']);
        $this->log->lwrite("Owner: " . $message->author);
        $properties = get_object_vars($message);
        if (array_key_exists('dsID', $properties)) {
          $this->log->lwrite("DSID: " . $message->dsID);
          $this->log->lwrite("Label: " . $message->dsLabel);
          $this->log->lwrite("Control group: " . $message->controlGroup);
        }
        if ($this->msg->headers['methodName'] == 'ingest') {
          sleep(1);
          try {
            $fedora_object = new ListenerObject($this->user, $this->fedora_url, $this->msg->headers['pid']);
          } catch (Exception $e) {
            $this->log->lwrite("An error occurred creating the fedora object - $e", 'ERROR');
          }
          $this->log->lwrite("Models: " . implode(', ', $fedora_object->object->models));
          $derivatives = new Derivative($fedora_object, 'TIFF', $this->log);
          $derivatives->OCR();
          $derivatives->HOCR();
          $derivatives->TN();
          $derivatives->JPG();
          $derivatives->JP2();

          unset($fedora_object);
          unset($object);
          unset($new_datastream);
          unset($extension);
          unset($temp_file);
          unset($extension_array);
          unset($message);
        }

        // Mark the message as received in the queue
        $this->con->ack($this->msg);
        unset($this->msg);
      }
      $this->log->lwrite("Child memory usage: " . memory_get_usage());
      $this->log->lwrite("Garbage collection enabled: " . gc_enabled()); // true
      $this->log->lwrite("Garbage collected: " . gc_collect_cycles()); // # of elements cleaned up
// Disconnect
      $this->con->disconnect();
      // Close log file
      $this->log->lclose();
    }
  }

}

?>
