<?php

/**
 * @file
 * Contains \BackupMigrate\Core\Util\BackupFile.
 */

namespace BackupMigrate\Core\Util;

use \BackupMigrate\Core\Util\BackupFileInterface;

/**
 * Class BackupFile
 * @package BackupMigrate\Core\Util
 */
class BackupFile implements BackupFileInterface {
  /**
   * The file info (size, timestamp, etc.).
   *
   * @var array
   */
  protected $file_info;

  /**
   * The file path.
   *
   * @var string
   */
  protected $path;

  /**
   * The file name without extension.
   *
   * @var string
   */
  protected $name;

  /**
   * The file extension(s)
   *
   * @var array
   */
  protected $ext;

  /**
   * The file's metadata
   * 
   * @var array A key/value associative array of metadata.
   */
  protected $metadata;


  /**
   * Get a metadata value
   *
   * @param string $key The key for the metadata item.
   * @return mixed The value of the metadata for this file.
   */
  public function getMeta($key) {
    return isset($this->metadata[$key]) ? $this->metadata[$key] : NULL;
  }

  /**
   * Set a metadata value
   *
   * @param string $key The key for the metadata item.
   * @param mixed $value The value for the metadata item.
   */
  public function setMeta($key, $value) {
    $this->metadata[$key] = $value;
  }

  /**
   * Set a metadata value
   *
   * @param array $values An array of key-value pairs for the file metadata.
   */
  public function setMetaMultiple($values) {
    foreach ((array)$values as $key => $value) {
      $this->setMeta($key, $value);
    }
  }

  /**
   * Get all metadata
   *
   * @param array $values An array of key-value pairs for the file metadata.
   * @return array
   */
  public function getMetaAll() {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName() {
    return rtrim($this->name . '.' . implode($this->getExtList(), '.'));
  }

  /**
   * {@inheritdoc}
   */
  public function setFullName($fullname) {
    // Break the file name into name and extension array.
    $parts = explode('.', $fullname);
    $this->setName(array_shift($parts));
    $this->ext = $parts;
  }


  /**
   * {@inheritdoc}
   */
  public function getExtList() {
    return $this->ext;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtLast() {
    return end($this->ext);
  }

  /**
   * {@inheritdoc}
   */
  public function getExt() {
    return implode($this->getExtList(), '.');
  }

}
