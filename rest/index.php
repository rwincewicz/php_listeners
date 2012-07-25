<?php

switch ($_SERVER['REQUEST_METHOD']) {
  case 'GET':

    $request_uri = explode('?', $_SERVER['REQUEST_URI']);
    $url_params = explode('&', $_SERVER['QUERY_STRING']);
    $no_of_rows = 100;
    $pid = NULL;
    foreach ($url_params as $param) {
      if (preg_match("/^rows.*/", $param)) {
        $rows_array = explode('=', $param);
        $no_of_rows = $rows_array[1];
      }
      if (preg_match("/^pid.*/", $param)) {
        $pid_array = explode('=', $param);
        $pid = $pid_array[1];
      }
      if (preg_match("/^action.*/", $param)) {
        $action_array = explode('=', $param);
        $action = $action_array[1];
      }      
    }
    switch ($action) {
      case 'list':
        $lines = count(file('../listener.log'));
        if ($lines > $no_of_rows) {
          $content = trim(file_get_contents('../listener.log'));
          $content_array = explode("\n", $content);
          $new_content_array = array();
          foreach ($content_array as $content_line) {
            if ($content_line[0] != "#" && !preg_match("/^Stack.trace.*/", $content_line)) {
              $new_content_array[] = $content_line;
            }
          }
          $difference = count($new_content_array) - $no_of_rows;
          $new_content_array = array_slice($new_content_array, $difference, $no_of_rows);
          print json_encode($new_content_array);
        }
        else {
          $content = file_get_contents('../listener.log');
          print json_encode($content);
        }
        break;
      case 'object':
        $returned_lines = array();
        $content = trim(file_get_contents('../listener.log'));
        $content_array = explode("\n", $content);
        foreach ($content_array as $content_line) {
          if (strstr($content_line, $pid . ',')) {
            $returned_lines[] = $content_line;
          }
        }
        print json_encode($returned_lines);
        break;
      default:
        var_dump($_SERVER);
        print "Test";
        break;
    }
    break;
  case 'POST':
    print "POST method not supported!\n";
    break;
  case 'DELETE':
    print "DELETE method not supported\n";
    break;
  default:
    break;
}
?>