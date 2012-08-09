<?php

/**
 * Class to create derivatives and ingets them into Fedora
 * 
 * @author Richard Wincewicz
 */
include_once 'PREMIS.php';

class Derivative {

  function __construct($fedora_object, $incoming_dsid, $extension = NULL, $log, $created_datastream) {
    include_once 'message.php';
    include_once 'fedoraConnection.php';

    $this->log = $log;
    $this->fedora_object = $fedora_object;
    $this->object = $fedora_object->object;
    $this->pid = $fedora_object->object->id;
    $this->created_datastream = $created_datastream;
    $this->incoming_dsid = $incoming_dsid;
    $this->incoming_datastream = new FedoraDatastream($this->incoming_dsid, $this->fedora_object->object, $this->fedora_object->repository);
    $this->mimetype = $this->incoming_datastream->mimetype;
    $this->log->lwrite('Mimetype: ' . $this->mimetype, 'SERVER_INFO');
    $this->extension = $extension;
    if ($this->incoming_dsid != NULL) {
      $this->temp_file = $fedora_object->saveDatastream($incoming_dsid, $extension);
    }
    $extension_array = explode('.', $this->temp_file);
    $extension = $extension_array[1];
  }

  function __destruct() {
    unlink($this->temp_file);
  }

  function OCR($dsid = 'OCR', $label = 'Scanned text', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      if (file_exists($this->temp_file)) {
        $output_file = $this->temp_file . '_OCR';
        $command = "tesseract $this->temp_file $output_file -l $language -psm 1";
        exec($command, $ocr_output, $return);
        if (file_exists($output_file . '.txt')) {
          $log_message = "$dsid derivative created by tesseract v3.0.1 using command - $command || SUCCESS";
          $ingest = $this->add_derivative($dsid, $label, $output_file . '.txt', 'text/plain', $log_message);
        }
        else {
          $convert_command = "convert -quality 99" . $this->temp_file . " " . $this->temp_file . "_JPG2.jpg";
          exec($convert_command, $convert_output, $return);
          print $convert_command;
          print implode(', ', $convert_output);
          $command = "tesseract " . $this->temp_file . "_JPG2.jpg" . $output_file . " -l $language -psm 1";
          exec($command, $ocr2_output, $return);
          print $command;
          print implode(', ', $ocr2_output);
          if (file_exists($output_file . '.txt')) {
            $log_message = "$dsid derivative created by using ImageMagick to convert to jpg and tesseract v3.0.1 using command - $command || SUCCESS";
            $ingest = $this->add_derivative($dsid, $label, $output_file . '.txt', 'text/plain', $log_message);
          }
          else {
            $this->log->lwrite("Could not find the file '$output_file.txt' for the $dsid derivative!\nTesseract output: " . implode(', ', $ocr_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          }
        }
      }
      else {
        $this->log->lwrite("Could not find the input file '$this->temp_file' for the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      }
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      if ($ingest) {
        unlink($output_file . '.txt');
      }
    }
    return $return;
  }

  function HOCR($dsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      if (file_exists($this->temp_file)) {
        $output_file = $this->temp_file . '_HOCR';
        $command = "tesseract $this->temp_file $output_file -l $language -psm 1 hocr";
        exec($command, $hocr_output, $return);
        if (file_exists($output_file . '.html')) {
          $log_message = "$dsid derivative created by tesseract v3.0.1 using command - $command || SUCCESS";
          $ingest = $this->add_derivative($dsid, $label, $output_file . '.html', 'text/html', $log_message);
        }
        else {
          exec("convert -quality 99" . $this->temp_file . " " . $this->temp_file . "_JPG2.jpg");
          $command = "tesseract " . $this->temp_file . "_JPG2.jpg" . $output_file . " -l $language -psm 1 hocr";
          exec($command, $hocr2_output, $return);
          if (file_exists($output_file . '.html')) {
            $log_message = "$dsid derivative created by using ImageMagick to convert to jpg and tesseract v3.0.1 using command - $command || SUCCESS";
            $ingest = $this->add_derivative($dsid, $label, $output_file . '.html', 'text/plain', $log_message);
          }
          else {
            $this->log->lwrite("Could not find the file '$output_file.html' for the $dsid derivative!\nTesseract output: " . implode(', ', $ocr_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          }
        }
      }
      else {
        $this->log->lwrite("Could not find the input file '$this->temp_file' for the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      }
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      if ($ingest) {
        unlink($output_file . '.html');
      }
    }
    return $return;
  }

  function ENCODED_OCR($dsid = 'ENCODED_OCR', $label = 'Encoded OCR', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_HOCR';
      $command = "tesseract $this->temp_file $output_file -l $language -psm 1 hocr";
      exec($command, $hocr_output, $return);
      if (!file_exists($output_file . '.html')) {
        exec("convert -quality 99" . $this->temp_file . " " . $this->temp_file . "_JPG2.jpg");
        $command = "tesseract " . $this->temp_file . "_JPG2.jpg" . $output_file . " -l $language -psm 1 hocr";
        exec($command, $hocr2_output, $return);
        if (file_exists($output_file . '.html')) {
          $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          return $return;
        }
      }
//      $this->log->lwrite("HOCR output: " . implode("\n", $hocr_output));
      $hocr_datastream = new NewFedoraDatastream("HOCR", 'M', $this->object, $this->fedora_object->repository);
      $hocr_datastream->setContentFromFile($output_file . '.html');
      $hocr_datastream->label = 'HOCR';
      $hocr_datastream->mimetype = 'text/html';
      $hocr_datastream->state = 'A';
      $hocr_datastream->checksum = TRUE;
      $hocr_datastream->checksumType = 'MD5';
      $hocr_datastream->logMessage = "HOCR derivative created by tesseract v3.0.1 using command - $command || SUCCESS";
      $this->object->ingestDatastream($hocr_datastream);
      $hocr_xml = new DOMDocument();
      $hocr_xml->load($output_file . '.html');
      $xsl = new DOMDocument();
      $xsl->load('hocr_to_lower.xslt');
      $proc = new XSLTProcessor();
      $proc->importStylesheet($xsl);
      $encoded_xml = $proc->transformToXml($hocr_xml);
      $encoded_xml = str_replace('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">', '<?xml version="1.0" encoding="UTF-8"?>', $encoded_xml);
      $encoded_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $encoded_datastream->setContentFromString($encoded_xml);
      $encoded_datastream->label = $label;
      $encoded_datastream->mimetype = 'text/xml';
      $encoded_datastream->state = 'A';
      $encoded_datastream->checksum = TRUE;
      $encoded_datastream->checksumType = 'MD5';
      $encoded_datastream->logMessage = "$dsid derivative created by tesseract v3.0.1 and transformed using hocr_to_lower.xslt || SUCCESS";
      $this->object->ingestDatastream($encoded_datastream);
      unlink($output_file . '.html');
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file . '.html');
    }
    return $return;
  }

  function JP2($dsid = 'JP2', $label = 'Compressed jp2') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_JP2.jp2';
      $command = 'kdu_compress -i ' . $this->temp_file . ' -o ' . $output_file . ' -rate 0.5 Clayers=1 Clevels=7 Cprecincts=\{256,256\},\{256,256\},\{256,256\},\{128,128\},\{128,128\},\{64,64\},\{64,64\},\{32,32\},\{16,16\} Corder=RPCL ORGgen_plt=yes ORGtparts=R Cblk=\{32,32\} Cuse_sop=yes';
      exec($command, $jp2_output, $return);
      $log_message = "$dsid derivative created using kdu_compress with command - $command || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'image/jp2', $log_message);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

  function TN($dsid = 'TN', $label = 'Thumbnail', $height = '200', $width = '200') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_TN.jpg';
      $command = "convert -thumbnail " . $height . "x" . $width . " $this->temp_file $output_file";
      exec($command, $tn_output, $return);
      $log_message = "$dsid derivative created using ImageMagick with command - $command || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'image/jpeg', $log_message);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

  function TN_department($dsid = 'TN', $label = 'Thumbnail', $height = '200', $width = '200') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $tn_filename = 'department_tn.png';
      if (!file_exists($tn_filename)) {
        $this->log->lwrite("Could not find thumbnail image!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        return FALSE;
      }
      $log_message = "$dsid derivative uploaded from file system || SUCCESS";
      $this->add_derivative($dsid, $label, $tn_filename, 'image/png', $log_message);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return TRUE;
  }

  function TN_faculty($dsid = 'TN', $label = 'Thumbnail', $height = '200', $width = '200') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $tn_filename = 'faculty_tn.png';
      if (!file_exists($tn_filename)) {
        $this->log->lwrite("Could not find thumbnail image!", 'ERROR');
        return FALSE;
      }
      $log_message = "$dsid derivative uploaded from file system || SUCCESS";
      $this->add_derivative($dsid, $label, $tn_filename, 'image/png', $log_message);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return TRUE;
  }

  function JPG($dsid = 'JPEG', $label = 'JPEG image', $resize = '800') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_JPG.jpg';
      $command = "convert -resize $resize $this->temp_file $output_file";
      exec($command, $jpg_output, $return);
      $log_message = "$dsid derivative created using ImageMagick with command - $command || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'image/jpeg', $log_message);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

  function TECHMD($dsid = 'TECHMD', $label = 'Technical metadata') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_TECHMD.xml';
      $command = "/opt/fits/fits.sh -i $this->temp_file -o $output_file -xc";
      exec($command, $techmd_output, $return);
      $log_message = "$dsid derivative created using FITS with command - $command || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

  function Scholar_PDFA($dsid = 'PDF', $label = 'PDF') {
    if ($this->created_datastream == 'OBJ') {
      $this->log->lwrite('Starting processing because the ' . $this->created_datastream . ' datastream was added', 'PROCESS_DATASTREAM', $this->pid, $dsid);
      try {
        $output_file = $this->temp_file . '_Scholar_PDFA.pdf';
        if ($this->mimetype == 'application/pdf') {
          $command = "gs -dPDFA -dBATCH -dNOPAUSE -dUseCIEColor -sProcessColorModel=DeviceCMYK -sDEVICE=pdfwrite -sPDFACompatibilityPolicy=1 -sOutputFile=$output_file $this->temp_file";
          exec($command, $pdfa_output, $return);
          $log_message = "$dsid derivative created using GhostScript with command - $command || SUCCESS";
        }
        else {
          $command = "java -jar /opt/jodconverter-core-3.0-beta-4/lib/jodconverter-core-3.0-beta-4.jar $this->temp_file $output_file";
          exec($command, $pdfa_output, $return);
          $log_message = "$dsid derivative created using JODConverted and OpenOffice with command - $command || SUCCESS";
        }
        $this->add_derivative($dsid, $label, $output_file, 'application/pdf', $log_message);
      } catch (Exception $e) {
        $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        unlink($output_file);
      }
      return $return;
    }
  }

  function Scholar_Policy($dsid = 'POLICY', $label = "Embargo policy - Both") {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = '/opt/php_listeners/document-embargo.xml';
      $log_message = "$dsid derivative uploaded from the file system || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message, FALSE);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return TRUE;
  }

  private function add_derivative($dsid, $label, $output_file, $mimetype, $log_message = NULL, $delete = TRUE) {
    $datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
    $datastream->setContentFromFile($output_file);
    $datastream->label = $label;
    $datastream->mimetype = $mimetype;
    $datastream->state = 'A';
    $datastream->checksum = TRUE;
    $datastream->checksumType = 'MD5';
    if ($log_message) {
      $datastream->logMessage = $log_message;
    }
    $return = $this->object->ingestDatastream($datastream);
    if ($delete) {
      unlink($output_file);
    }
    $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    return $return;
  }

}

?>