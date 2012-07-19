<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

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
      $output_file = $this->temp_file . '_OCR';
      exec("tesseract $this->temp_file $output_file -l $language -psm 1", $ocr_output, $return);
//      $this->log->lwrite("OCR output: " . implode("\n", $ocr_output));
      $ocr_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $ocr_datastream->setContentFromFile($output_file . '.txt');
      $ocr_datastream->label = $label;
      $ocr_datastream->mimetype = 'text/plain';
      $ocr_datastream->state = 'A';
      $this->object->ingestDatastream($ocr_datastream);
      unlink($output_file . '.txt');
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file . '.txt');
    }
    return $return;
  }

  function HOCR($dsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_HOCR';
      exec("tesseract $this->temp_file $output_file -l $language -psm 1 hocr", $hocr_output, $return);
//      $this->log->lwrite("HOCR output: " . implode("\n", $hocr_output));
      $hocr_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $hocr_datastream->setContentFromFile($output_file . '.html');
      $hocr_datastream->label = $label;
      $hocr_datastream->mimetype = 'text/html';
      $hocr_datastream->state = 'A';
      $this->object->ingestDatastream($hocr_datastream);
      unlink($output_file . '.html');
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);      
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file . '.html');
    }
    return $return;
  }

  function ENCODED_OCR($dsid = 'ENCODED_OCR', $label = 'Encoded OCR', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_HOCR';
      exec("tesseract $this->temp_file $output_file -l $language -psm 1 hocr", $hocr_output, $return);
//      $this->log->lwrite("HOCR output: " . implode("\n", $hocr_output));
      $hocr_datastream = new NewFedoraDatastream("HOCR", 'M', $this->object, $this->fedora_object->repository);
      $hocr_datastream->setContentFromFile($output_file . '.html');
      $hocr_datastream->label = 'HOCR';
      $hocr_datastream->mimetype = 'text/html';
      $hocr_datastream->state = 'A';
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
      exec('kdu_compress -i ' . $this->temp_file . ' -o ' . $output_file . ' -rate 0.5 Clayers=1 Clevels=7 Cprecincts=\{256,256\},\{256,256\},\{256,256\},\{128,128\},\{128,128\},\{64,64\},\{64,64\},\{32,32\},\{16,16\} Corder=RPCL ORGgen_plt=yes ORGtparts=R Cblk=\{32,32\} Cuse_sop=yes', $jp2_output, $return);
//      $this->log->lwrite("JP2 output: " . implode("\n", $jp2_output));
      $jp2_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $jp2_datastream->setContentFromFile($output_file);
      $jp2_datastream->label = $label;
      $jp2_datastream->mimetype = 'image/jp2';
      $jp2_datastream->state = 'A';
      $this->object->ingestDatastream($jp2_datastream);
      unlink($output_file);
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
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
      exec("convert -thumbnail " . $height . "x" . $width . " $this->temp_file $output_file", $tn_output, $return);
//      $this->log->lwrite("TN output: " . implode("\n", $tn_output));
      $tn_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $tn_datastream->setContentFromFile($output_file);
      $tn_datastream->label = $label;
      $tn_datastream->mimetype = 'image/jpg';
      $tn_datastream->state = 'A';
      $this->object->ingestDatastream($tn_datastream);
      unlink($output_file);
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
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
      $tn_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $tn_datastream->setContentFromFile($tn_filename);
      $tn_datastream->label = $label;
      $tn_datastream->mimetype = 'image/png';
      $tn_datastream->state = 'A';
      $this->object->ingestDatastream($tn_datastream);
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return $return;
  }

  function TN_faculty($dsid = 'TN', $label = 'Thumbnail', $height = '200', $width = '200') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $tn_filename = 'faculty_tn.png';
      if (!file_exists($tn_filename)) {
        $this->log->lwrite("Could not find thumbnail image!", 'ERROR');
        return FALSE;
      }
      $tn_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $tn_datastream->setContentFromFile($tn_filename);
      $tn_datastream->label = $label;
      $tn_datastream->mimetype = 'image/png';
      $tn_datastream->state = 'A';
      $this->object->ingestDatastream($tn_datastream);
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return $return;
  }

  function JPG($dsid = 'JPEG', $label = 'JPEG image', $resize = '800') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_JPG.jpg';
      exec("convert -resize $resize $this->temp_file $output_file", $jpg_output, $return);
//      $this->log->lwrite("JPG output: " . implode("\n", $jpg_output));
      $jpeg_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $jpeg_datastream->setContentFromFile($output_file);
      $jpeg_datastream->label = $label;
      $jpeg_datastream->mimetype = 'image/jpg';
      $jpeg_datastream->state = 'A';
      $this->object->ingestDatastream($jpeg_datastream);
      unlink($output_file);
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
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
      exec("/opt/fits/fits.sh -i $this->temp_file -o $output_file", $techmd_output, $return);
//      $this->log->lwrite("TECHMD output: " . implode("\n", $techmd_output));
      $techmd_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $techmd_datastream->setContentFromFile($output_file);
      $techmd_datastream->label = $label;
      $techmd_datastream->mimetype = 'text/xml';
      $techmd_datastream->state = 'A';
      $this->object->ingestDatastream($techmd_datastream);
      unlink($output_file);
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
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
          exec("gs -dPDFA -dBATCH -dNOPAUSE -dUseCIEColor -sProcessColorModel=DeviceCMYK -sDEVICE=pdfwrite -sPDFACompatibilityPolicy=1 -sOutputFile=$output_file $this->temp_file", $pdfa_output, $return);
        }
        else {
          $command = "java -jar /opt/jodconverter-core-3.0-beta-4/lib/jodconverter-core-3.0-beta-4.jar $this->temp_file $output_file";
          exec($command, $pdfa_output, $return);
        }
        $pdfa_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
        $pdfa_datastream->setContentFromFile($output_file);
        $pdfa_datastream->label = $label;
        $pdfa_datastream->mimetype = 'application/pdf';
        $pdfa_datastream->state = 'A';
        $this->object->ingestDatastream($pdfa_datastream);
        unlink($output_file);
        $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
      } catch (Exception $e) {
        $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        unlink($output_file);
      }
      return $return;
    }
  }

}

?>