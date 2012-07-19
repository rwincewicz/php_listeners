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

    set_time_limit(5);
    
    // Load config file
    $config_file = file_get_contents('config.xml');
    $this->config_xml = new SimpleXMLElement($config_file);

    // Logging settings
    $log_file = $this->config_xml->log->file;
    $this->log->level = $this->config_xml->log->level;

    $this->log = new Logging();
    $this->log->lfile($log_file);

    $this->fedora_url = 'http://' . $this->config_xml->fedora->host . ':' . $this->config_xml->fedora->port . '/fedora';
    $this->user = new stdClass();
    $this->user->name = $this->config_xml->fedora->username;
    $this->user->pass = $this->config_xml->fedora->password;

    // Set up stomp settings
    $stomp_url = 'tcp://' . $this->config_xml->stomp->host . ':' . $this->config_xml->stomp->port;
    $channel = $this->config_xml->stomp->channel;

    // Make a connection
    $this->con = new Stomp($stomp_url);
    $this->con->sync = FALSE;
    $this->con->setReadTimeout(1);
    // Connect
    try {
      $this->con->connect();
    } catch (Exception $e) {
      $this->log->lwrite("Could not connect to Stomp server - $e", 'SERVER', NULL, NULL, NULL, 'ERROR');
    }
    // Subscribe to the queue
    try {
      $this->con->subscribe((string) $channel[0], array('activemq.prefetchSize' => 100));
    } catch (Exception $e) {
      $this->log->lwrite("Could not subscribe to the channel $channel - $e", 'SERVER', NULL, NULL, NULL, 'ERROR');
    }
  }

  function listen() {
    
    // Receive a message from the queue
    if ($this->msg = $this->con->readFrame()) {

      // do what you want with the message
      if ($this->msg != NULL) {
//        sleep(1);
        $message = new Message($this->msg->body);
        $pid = $this->msg->headers['pid'];
        if (!$message->dsID) {
          $message->dsID = NULL;
        }
        $this->log->lwrite("Method: " . $this->msg->headers['methodName'], 'MODIFY_OBJECT', $pid, $message->dsID, $message->author);
        try {
          if (fedora_object_exists($this->fedora_url, $this->user, $pid) === FALSE) {
            $this->log->lwrite("Could not find object", 'DELETED_OBJECT', $pid, NULL, $message->author, 'ERROR');
            $this->con->ack($this->msg);
            unset($this->msg);
            return;
          }
          $fedora_object = new ListenerObject($this->user, $this->fedora_url, $pid);
        } catch (Exception $e) {
          $this->log->lwrite("An error occurred creating the fedora object", 'FAIL_OBJECT', $pid, NULL, $message->author, 'ERROR');
        }
        $properties = get_object_vars($message);
        $object_namespace_array = explode(':', $pid);
        $object_namespace = $object_namespace_array[0];
        $objects = $this->config_xml->xpath('//object');

        foreach ($objects as $object) {
          $namespaces = $object->nameSpace;
          $content_models = $object->contentModel;
          $xml_methods = $object->method;
          $methods = array();
          foreach ($xml_methods as $xml_method) {
            $methods[] = (string) $xml_method[0];
          }
          $datastream = $object->datastream;
          $datastream = (string) $datastream[0];
          $new_datastreams = $object->derivative;
          $extension = $object->extension;
          $extension = (string) $extension[0];
          foreach ($content_models as $content_model) {
//            $this->log->lwrite('Config content model: ' . $content_model);
//            $this->log->lwrite('Object content model: ' . implode(', ', $fedora_object->object->models));
            if (in_array($content_model, $fedora_object->object->models)) {
              foreach ($namespaces as $namespace) {
                if ((string) $namespace == (string) $object_namespace) {
                  if (in_array($this->msg->headers['methodName'], $methods)) {
                    $derivative = new Derivative($fedora_object, $datastream, $extension, $this->log, $message->dsID);
                    foreach ($new_datastreams as $new_datastream) {   
//                      $this->log->lwrite("Adding datastream '$new_datastream->dsid' with label '$new_datastream->label' using function '$new_datastream->function'", 'START_DATASTREAM', $pid, $new_datastream->dsid, $message->author);
                      $function = (string) $new_datastream->function;
                      $derivative->{$function}((string) $new_datastream->dsid, (string) $new_datastream->label);
                    }
                  }
                }
              }
            }
          }
          unset($namespaces);
          unset($namespace);
          unset($content_models);
          unset($content_model);
          unset($methods);
          unset($datastream);
          unset($new_datastreams);
          unset($new_datastream);
          unset($derivative);
        }
        
        // Mark the message as received in the queue
        $this->con->ack($this->msg);
        unset($this->msg);
      }
      
      // Disconnect
      $this->con->disconnect();
      
      // Close log file
      $this->log->lclose();
    }
  }
}

?>
