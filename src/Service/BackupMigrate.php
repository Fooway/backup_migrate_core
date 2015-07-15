<?php

/**
 * @file
 * Contains \BackupMigrate\Core\Services\BackupMigrate.
 */

namespace BackupMigrate\Core\Service;

use \BackupMigrate\Core\Config\ConfigInterface;
use BackupMigrate\Core\Environment\EnvironmentInterface;
use BackupMigrate\Core\Exception\BackupMigrateException;
use \BackupMigrate\Core\Plugin\PluginCallerInterface;
use \BackupMigrate\Core\Plugin\PluginCallerTrait;
use \BackupMigrate\Core\Plugin\PluginManager;

/**
 * The core Backup and Migrate service.
 *
 * Usage:
 *   // (Optional) Instantiate an application container
 *   // to provide access to the file system etc.
 *   $env = new EnvironmentBase();
 *   // (Optional) Pass in the configuration
 *   $config = new ConfigBase(...);
 *   $bam = new BackupMigrate($env, $config);
 *   $bam->plugins()->add(new MySQLSource(...), 'db');
 *   $bam->plugins()->add(new MySQLSource(...), 'another');
 *   $bam->plugins()->add(new DirectoryDestination(...), 'manual');
 *   $bam->plugins()->add(new CompressionPlugin(), 'encryption');
 *   $bam->backup($from, to);
 */
class BackupMigrate implements BackupMigrateInterface, PluginCallerInterface
{
  use PluginCallerTrait;

  /**
   * {@inheritdoc}
   */
  function __construct(EnvironmentInterface $env = NULL, ConfigInterface $config = NULL) {
    $this->setPluginManager(new PluginManager($env, $config));
  }


  /**
   * {@inheritdoc}
   */
  public function backup($source_id, $destination_id) {
    try {
      // Get the source and the destination to use.
      $source = $this->plugins()->get($source_id);
      $destinations = array();

      // Allow a single destination or multiple destinations.
      foreach ((array)$destination_id as $id) {
        $destinations[$id] = $this->plugins()->get($id);

        // Check that the destination is valid.
        if (!$destinations[$id]) {
          throw new BackupMigrateException('The destination !id does not exist.', array('!id' => $destination_id));
        }
      }

      // Check that the source is valid.
      if (!$source) {
        throw new BackupMigrateException('The source !id does not exist.', array('!id' => $source_id));
      }

      // Run each of the installed plugins which implements the 'beforeBackup' operation.
      // $this->plugins()->call('beforeBackup');

      // Do the actual backup.
      $file = $source->exportToFile();

      // Run each of the installed plugins which implements the 'afterBackup' operation.
      $file = $this->plugins()->call('afterBackup', $file);

      // Save the file to each destination.
      foreach ($destinations as $destination) {
        $destination->saveFile($file);
      }

      // Let plugins react to a successful operation.
      $this->plugins()->call('backupSucceed', $file);
    }
    catch (\Exception $e) {
      // Let plugins react to a failed operation.
      $this->plugins()->call('backupFail', $e);

      // The consuming software needs to deal with this.
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function restore($source_id, $destination_id, $file_id = NULL) {
    try {
      // Get the source and the destination to use.
      $source = $this->plugins()->get($source_id);
      $destination = $this->plugins()->get($destination_id);

      if (!$source) {
        throw new BackupMigrateException('The source !id does not exist.', array('!id' => $source_id));
      }
      if (!$destination) {
        throw new BackupMigrateException('The destination !id does not exist.', array('!id' => $destination_id));
      }

      // Load the file from the destination.
      $file = $destination->getFile($file_id);

      if (!$file) {
        throw new BackupMigrateException('The file !id does not exist.', array('!id' => $file_id));
      }

      // Run each of the installed plugins which implements the 'backup' operation.
      $file = $this->plugins()->call('beforeRestore', $file);

      // Do the actual source restore.
      $source->importFromFile($file);

      // Let plugins react to a successful operation.
      $this->plugins()->call('backupSucceed', $file);
    }
    catch (\Exception $e) {
      // Let plugins react to a failed operation.
      $this->plugins()->call('backupFail', $e);

      // The consuming software needs to deal with this.
      throw $e;
    }
  }

}
