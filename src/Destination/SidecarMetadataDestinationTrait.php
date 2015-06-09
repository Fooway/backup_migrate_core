<?php
/**
 * @file
 */

namespace BackupMigrate\Core\Destination;


use BackupMigrate\Core\Plugin\FileProcessorTrait;
use BackupMigrate\Core\Util\BackupFileInterface;

/**
 * Class SidecarMetadataDestinationTrait
 * @package BackupMigrate\Core\Destination
 *
 * This trait allows destinations to store extended file metadata as a sidecar
 * file to the same destination. Sidecar files are .ini formatted. This trait
 * will work with any destination derived from DestinationBase.
 */
trait SidecarMetadataDestinationTrait {
  use FileProcessorTrait;

  /**
   * {@inheritdoc}
   */
  protected function _loadFileMetadataArray(BackupFileInterface $file) {
    $info = array();

    $id = $file->getMeta('id');
    $filename = $id . '.info';
    if ($this->fileExists($filename)) {
      $meta_file = $this->getFile($filename);
      $meta_file = $this->loadFileForReading($meta_file);
      $info = $this->_INIToArray($meta_file->readAll());
    }
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  protected function _saveFileMetadata(BackupFileInterface $file) {
    // Get the file metadata and convert to INI format
    $meta = $file->getMetaAll();
    $ini = $this->_arrayToINI($meta);

    // Create an info file
    $meta_file = $this->getTempFileManager()->pushExt($file, 'info');
    $meta_file->write($ini);

    // Save the metadata
    $this->_saveFile($meta_file);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFile($id) {
    $this->_deleteFile($id);
    $this->_deleteFile($id . '.info');
  }

  /**
   * Parse an INI file's contents.
   *
   * For simplification this function only parses the simple subset of INI
   * syntax generated by SidecarMetadataDestinationTrait::_arrayToINI();
   *
   * @param $ini
   * @return array
   */
  protected function _INIToArray($ini) {
    $out = array();
    $lines = explode("\n", $ini);
    foreach ($lines as $line) {
      $line = trim($line);
      // Skip comments (even though there probably won't be any
      if (substr($line, 0, 1) == ';') {
        continue;
      }

      // Match the key and value using a simplified syntax
      $matches = array();
      if (preg_match('/^([^=]+)\s?=\s?"(.*)"$/', $line, $matches)) {
        $key = $matches[1];
        $val = $matches[2];

        // Break up a key in the form a[b][c]
        $keys = explode('[', $key);
        $insert = &$out;
        foreach ($keys as $part) {
          $part = trim($part, ' ]');
          $insert[$part] = '';
          $insert = &$insert[$part];
        }
        $insert = $val;
      }
    }

    return $out;
  }

  /**
   * @param $data
   * @param string $prefix
   * @return string
   */
  protected function _arrayToINI($data, $prefix = '') {
    $content = "";
    foreach ($data as $key => $val) {
      if ($prefix) {
        $key = $prefix . '[' . $key .']';
      }
      if (is_array($val)) {
        $content .= $this->_arrayToINI($val, $key);
      }
      else {
        $content .= $key . " = \"". $val ."\"\n";
      }
    }
    return $content;
  }

}