<?php

$monitor = new Monitor();
$monitor->listen();
unset($monitor);

class Monitor {

  function __construct() {
    require_once("Stomp.php");
    include_once 'message.php';
    include_once 'Logging.php';

// Load config file
    $config_file = file_get_contents('config.xml');
    $this->config_xml = new SimpleXMLElement($config_file);

// Logging settings
//    $log_file = $this->config_xml->log->file;
    $log_file = 'monitor.log';
    $this->log->level = $this->config_xml->log->level;

    $this->log = new Logging();
    $this->log->lfile($log_file);

// Set up stomp settings
    $stomp_url = 'tcp://' . $this->config_xml->stomp->host . ':' . $this->config_xml->stomp->port;
    $channel = $this->config_xml->stomp->channel;

// Make a connection
    $this->con = new Stomp($stomp_url);
    $this->con->sync = FALSE;
    $this->con->setReadTimeout(5);
// Connect
    $this->con->connect();
    print "Running...";
  }

  function listen() {
    while (TRUE) {
      print "Still running...";
// Subscribe to the queue
      try {
        $this->con->subscribe('/queue/listener.monitor');
      } catch (Exception $e) {
        $this->log->lwrite("Could not subscribe to the channel $channel - $e", 'ERROR');
      }

      // Receive a message from the queue
      if ($this->msg = $this->con->readFrame()) {
        // do what you want with the message
        if ($this->msg != NULL) {
          sleep(1);
          print $this->msg;
          $pid = $this->msg->headers['pid'];
          $this->log->lwrite("Pid: " . $pid);
          $this->log->lwrite("Method: " . $this->msg->headers['methodName']);
        }
        // Mark the message as received in the queue
        $this->con->ack($this->msg);
        unset($this->msg);
      }
    }
  }
}

?>
