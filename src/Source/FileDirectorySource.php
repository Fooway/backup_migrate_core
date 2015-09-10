<?php
/**
 * @file
 * Contains BackupMigrate\Core\Source\FileDirectorySource
 */


namespace BackupMigrate\Core\Source;


use Archive_Tar;
use BackupMigrate\Core\Config\Config;
use BackupMigrate\Core\Service\ArchiverInterface;
use BackupMigrate\Core\Exception\BackupMigrateException;
use BackupMigrate\Core\Exception\IgnorableException;
use BackupMigrate\Core\Plugin\FileProcessorInterface;
use BackupMigrate\Core\Plugin\FileProcessorTrait;
use BackupMigrate\Core\Plugin\PluginBase;
use BackupMigrate\Core\File\BackupFileReadableInterface;

/**
 * Class FileDirectorySource
 * @package BackupMigrate\Core\Source
 */
class FileDirectorySource extends PluginBase
  implements SourceInterface, FileProcessorInterface
{
  use FileProcessorTrait;

  /**
   * @var \BackupMigrate\Core\Service\ArchiverInterface
   */
  private $archiver;


  /**
   * {@inheritdoc}
   */
  public function supportedOps() {
    return [
      'exportToFile' => [],
      'importFromFile' => []
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function exportToFile() {
    if ($directory = $this->confGet('directory')) {
      // Make sure the directory ends in exactly 1 slash:
      if (substr($directory, -1) !== '/') {
        $directory = $directory . '/';
      }

      if (!$writer = $this->getArchiver()) {
        throw new BackupMigrateException('A file directory source requires an archive writer object.');
      }
      $ext = $writer->getFileExt();
      $file = $this->getTempFileManager()->create($ext);

      if ($files = $this->getFilesToBackup($directory)) {
        $writer->setArchive($file);
        foreach ($files as $path) {
          $writer->addFile($path, $directory);
        }
        $writer->closeArchive();
        return $file;
      }
      throw new BackupMigrateException('The directory %dir does not not have any files to be backed up.',
        array('%dir' => $directory));
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function importFromFile(BackupFileReadableInterface $file) {
    if ($directory = $this->confGet('directory')) {
      // Make sure the directory ends in exactly 1 slash:
      if (substr($directory, -1) !== '/') {
        $directory = $directory . '/';
      }

      if (!file_exists($directory)) {
        throw new BackupMigrateException('The directory %dir does not exist to restore to.',
          array('%dir' => $directory));
      }
      if (!is_writable($directory)) {
        throw new BackupMigrateException('The directory %dir cannot be written to because of the operating system file permissions.',
          array('%dir' => $directory));
      }

      if (!$archiver= $this->getArchiver()) {
        throw new BackupMigrateException('A file directory source requires an archive writer object.');
      }
      // Check that the file endings match.
      if ($archiver->getFileExt() !== $file->getExtLast()) {
        throw new BackupMigrateException('This source expects a .%ext file.', array('%ext' => $archiver->getFileExt()));
      }

      $archiver->setArchive($file);
      $archiver->extractTo($directory);

      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get a list if files to be backed up from the given directory. Do not
   * include files that match the 'exclude_filepaths' setting.
   *
   * @param string $dir The name of the directory to list.
   * @return array
   * @throws \BackupMigrate\Core\Exception\BackupMigrateException
   * @throws \BackupMigrate\Core\Exception\IgnorableException
   * @internal param $directory
   */
  protected function getFilesToBackup($dir) {
    $exclude = $this->confGet('exclude_filepaths');
    $exclude = $this->compileExcludePatterns($exclude);

    // Remove any trailing slashes.
    $dir = rtrim($dir, '/');

    if (!file_exists($dir)) {
      throw new BackupMigrateException('Directory %dir does not exist.',
        array('%dir' => $dir));
    }
    if (!is_dir($dir)) {
      throw new BackupMigrateException('The file %dir is not a directory.',
        array('%dir' => $dir));
    }
    if (!is_readable($dir)) {
      throw new BackupMigrateException('Directory %dir could not be read from.',
        array('%dir' => $dir));
    }

    // Get a filtered list if files from the directory.
    list($out, $errors) = $this->_getFilesFromDirectory($dir, $exclude, $dir . '/');

    // Alert the user to any errors there might have been.
    if ($errors) {
      $count = count($errors);
      $file_list = implode(', ', array_slice($errors, 0, 5));
      if ($count > 5) {
        $file_list .= ', ...';
      }

      if (!$this->confGet('ignore_errors')) {
        throw new IgnorableException('The backup could not be completed because !count files could not be read: (!files).',
          array('!count' => $count, '!files' => $file_list));
      }
      else {
        // throw new IgnorableException('!count files could not be read: (!files).', array('!files' => $filesmsg));
        // @TODO: Log the ignored files.
      }
    }

    return $out;
  }

  /**
   * @param string $dir The name of the directory to list.
   * @param array $exclude An array of exclude rules.
   * @return array
   */
  protected function _getFilesFromDirectory($dir, $exclude = array(), $base_path = '') {
    $out = $errors = array();

    // Open the directory.
    if (!$handle = opendir($dir)) {
      $errors[] = $dir;
    }
    else {
      while (($file = readdir($handle)) !== FALSE) {
        // If not a dot file and the file name isn't excluded.
        if ($file != '.' && $file != '..') {

          // Get the full path of the file.
          $path = $dir . '/' . $file;

          // Make sure this path is not excluded.
          if (!$this->matchPath($path, $exclude, $base_path)) {
            if (is_dir($path)) {
              list($sub_files, $sub_errors) =
                $this->_getFilesFromDirectory($path, $exclude, $base_path);

              // Add the directory if it is empty.
              if (empty($sub_files)) {
                $out[] = $path;
              }

              // Add the sub-files to the output
              $out = array_merge($out, $sub_files);
              $errors = array_merge($errors, $sub_errors);
            }
            else {
              if (is_readable($path)) {
                $out[] = $path;
              }
              else {
                $errors[] = $path;
              }
            }
          }
        }
      }
      closedir($handle);
    }

    return array($out, $errors);
  }


  /**
   * @param \BackupMigrate\Core\Service\ArchiverInterface $writer
   */
  public function setArchiver(ArchiverInterface $writer) {
    $this->archiver  = $writer;
  }

  /**
   * @return ArchiverInterface
   */
  public function getArchiver() {
    return $this->archiver;
  }

  /**
   * Get the default values for the plugin.
   *
   * @return \BackupMigrate\Core\Config\Config
   */
  public function configDefaults() {
    return new Config([
      'exclude_filepaths' => [],
      'directory' => '',
    ]);
  }


  /**
   * Convert an array of glob patterns to an array of regex patterns for file name exclusion.
   *
   * @param array $exclude
   *    A list of patterns with glob wildcards
   * @return array
   *    A list of patterns as regular expressions
   *
   */
  private function compileExcludePatterns($exclude) {
    $out = array();
    foreach ($exclude as $pattern) {
      // Convert Glob wildcards to a regex per http://php.net/manual/en/function.fnmatch.php#71725
      $out[] = "#^". strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.', '\[' => '[', '\]' => ']'))."$#i";
    }
    return $out;
  }

  /**
   * Match a path to the list of exclude patterns.
   *
   * @param string $path
   *    The path to match.
   * @param array $exclude
   *    An array of regular expressions to match against.
   * @param string $base_path
   * @return bool
   */
  private function matchPath($path, $exclude, $base_path = '') {
    $path = substr($path, strlen($base_path));

    foreach ($exclude as $pattern) {
      if (preg_match($pattern, $path)) {
        return true;
      }
    }
    return false;
  }

}