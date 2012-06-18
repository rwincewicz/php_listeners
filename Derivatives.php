<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class Derivative {

  function __construct($fedora_object, $dsid, $log) {
    include_once 'message.php';
    include_once 'fedoraConnection.php';

    $this->log = $log;
    $this->fedora_object = $fedora_object;
    $this->object = $fedora_object->object;
    $this->dsid = $dsid;
    $this->temp_file = $fedora_object->saveDatastream('TIFF', 'tif');
    $extension_array = explode('.', $this->temp_file);
    $extension = $extension_array[1];
  }
  
  function __destruct() {
    unlink($this->temp_file);
  }

  function OCR($language = 'eng') {
    try {
      $output_file = $this->temp_file . '_OCR';
      exec("tesseract $this->temp_file $output_file -l $language", $ocr_output, $return);
      $this->log->lwrite("OCR output: " . implode("\n", $ocr_output));
      $ocr_datastream = new NewFedoraDatastream('OCR', 'M', $this->object, $this->fedora_object->repository);
      $ocr_datastream->setContentFromFile($output_file . '.txt');
      $ocr_datastream->label = 'OCR';
      $ocr_datastream->mimetype = 'text/plain';
      $this->object->ingestDatastream($ocr_datastream);
      unlink($output_file . '.txt');
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the OCR derivative! - $e", 'ERROR');
      unlink($output_file . '.txt');
    }
    return $return;
  }

  function HOCR($language = 'eng') {
    try {
      $output_file = $this->temp_file . '_HOCR';
      exec("tesseract $this->temp_file $output_file -l $language hocr", $hocr_output, $return);
      $this->log->lwrite("HOCR output: " . implode("\n", $hocr_output));
      $hocr_datastream = new NewFedoraDatastream('HOCR', 'M', $this->object, $this->fedora_object->repository);
      $hocr_datastream->setContentFromFile($output_file . '.html');
      $hocr_datastream->label = 'HOCR';
      $hocr_datastream->mimetype = 'text/html';
      $this->object->ingestDatastream($hocr_datastream);
      unlink($output_file . '.html');
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the HOCR derivative! - $e", 'ERROR');
      unlink($output_file . '.html');
    }
    return $return;
  }

  function JP2() {
    try {
      $output_file = $this->temp_file . '_JP2.jp2';
      exec("kdu_compress -i $this->temp_file -o $output_file -rate 0.5 Clayers=1 Clevels=7 Cprecincts={256,256},{256,256},{256,256},{128,128},{128,128},{64,64},{64,64},{32,32},{16,16} Corder=RPCL ORGgen_plt=yes ORGtparts=R Cblk={32,32} Cuse_sop=yes", $jp2_output, $return);
      $this->log->lwrite("JP2 output: " . implode("\n", $jp2_output));
      $jp2_datastream = new NewFedoraDatastream('JP2', 'M', $this->object, $this->fedora_object->repository);
      $jp2_datastream->setContentFromFile($output_file);
      $jp2_datastream->label = 'JP2';
      $jp2_datastream->mimetype = 'image/jp2';
      $this->object->ingestDatastream($jp2_datastream);
      unlink($output_file);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the JP2 derivative! - $e", 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

  function TN($height = '200', $width = '200') {
    try {
      $output_file = $this->temp_file . '_TN.jpg';
      exec("convert -thumbnail " . $height . "x" . $width . " $this->temp_file $output_file", $tn_output, $return);
      $this->log->lwrite("TN output: " . implode("\n", $tn_output));
      $tn_datastream = new NewFedoraDatastream('TN', 'M', $this->object, $this->fedora_object->repository);
      $tn_datastream->setContentFromFile($output_file);
      $tn_datastream->label = 'Thumbnail image';
      $tn_datastream->mimetype = 'image/jpg';
      $this->object->ingestDatastream($tn_datastream);
      unlink($output_file);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the TN derivative! - $e", 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

  function JPG($resize = '800') {
    try {
      $output_file = $this->temp_file . '_JPG.jpg';
      exec("convert -resize $resize $this->temp_file $output_file", $jpg_output, $return);
      $this->log->lwrite("JPG output: " . implode("\n", $jpg_output));
      $jpeg_datastream = new NewFedoraDatastream('JPEG', 'M', $this->object, $this->fedora_object->repository);
      $jpeg_datastream->setContentFromFile($output_file);
      $jpeg_datastream->label = 'JPEG image';
      $jpeg_datastream->mimetype = 'image/jpg';
      $this->object->ingestDatastream($jpeg_datastream);
      unlink($output_file);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the JPG derivative! - $e", 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

  function TECHMD() {
    try {
      $output_file = $this->temp_file . '_TECHMD.xml';
      exec("/opt/fits/fits.sh -i $this->temp_file -o $output_file -xc", $techmd_output, $return);
      $this->log->lwrite("TECHMD output: " . implode("\n", $techmd_output));
      $techmd_datastream = new NewFedoraDatastream('TECHMD', 'M', $this->object, $this->fedora_object->repository);
      $techmd_datastream->setContentFromFile($output_file);
      $techmd_datastream->label = 'Technical metadata';
      $techmd_datastream->mimetype = 'text/xml';
      $this->object->ingestDatastream($techmd_datastream);
      unlink($output_file);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the TECHMD derivative! - $e", 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

}

?>