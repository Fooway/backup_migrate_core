<?php

/**
 * @file
 * Contains \BackupMigrate\Core\Services\TempFileManager.
 */

namespace BackupMigrate\Core\Services;

/**
 * Provides a very basic temp file manager which assumes read/write access to a
 * local temp directory.
 */
class TempFileAdapter implements TempFileAdapterInterface
{
  /**
   * The path to the temp directory.
   *
   * @var string
   */
  protected $dir;

  /**
   * A prefix to add to all temp files.
   *
   * @var string
   */
  protected $prefix;

  /**
   * The list of files created by this manager
   * 
   * @var array
   */
  protected $tempfiles;

  /**
   * Construct a manager
   * 
   * @param string $dir A file path or stream URL for the temp directory
   * @param string $prefix A string prefix to add to each created file.
   */
  public function __construct($dir, $prefix = 'bam') {
    $this->dir = $dir;
    $this->prefix = $prefix;
    // @TODO: check that temp direcory is writeable or throw an exception.
  }

  /**
   * Destruct the manager. Delete all the temporary files when this manager is destroyed.
   */
  public function __destruct() {
    $this->deleteAllTempFiles();
  }

  /**
   * {@inheritdoc}
   */
  public function createTempFile() {
    $out = tempnam($this->dir, $this->prefix);
    $this->tempfiles[] = $out;
    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTempFile($filename) {
    // Only delete files that were created by this manager.
    if (in_array($filename, $this->tempfiles)) {
      if (file_exists($filename)) {
        if (is_writable($filename)) {
          unlink($filename);
        }
        else {
          // @TODO: Throw exception. Cannot delete temp file.
          throw new \Exception('Could not delete the temp file because it is not writable');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllTempFiles() {
    foreach ($this->tempfiles as $file) {
      $this->deleteTempFile($file);
    }
  }

}
