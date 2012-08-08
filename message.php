<?php

/**
 * Class that parses some extra information from
 * the JMS message. May not be necessary.
 * 
 * @author Richard Wincewicz
 */

class Message {
  
  function __construct($message = NULL) {
    if ($message == NULL) {
      return;
    }
    $this->xml = new DOMDocument();
    $this->xml->loadXML($message);
    $this->author = (string) $this->xml->getElementsByTagName('name')->item(0)->nodeValue;
    $this->category = $this->xml->getElementsByTagName('category');
    foreach ($this->category as $category) {
      $scheme = explode(':', $category->getAttribute('scheme'));
      $term = $category->getAttribute('term');
      $this->{$scheme[1]} = $term;
    }
    $this->title = (string) $this->xml->getElementsByTagName('title')->item(0)->nodeValue;
  }
  
}

?>
