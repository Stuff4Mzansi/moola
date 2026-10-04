<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SQLite3;
use Throwable;

#[Signature('moola:backup')]
#[Description('Create a consistent SQLite backup with the persistent encryption key and uploaded files')]
class BackupContainer extends Command
{
    public function handle(): int
    {
        $directory = config('app.data_directory');
        $database = config('database.connections.sqlite.database');
        if (config('database.default') !== 'sqlite' || ! is_string($directory) || ! is_file($directory.'/app.key') || ! is_string($database) || ! is_file($database)) {
            $this->error('This command requires a container SQLite database and persistent app.key. External databases need their own backup tooling.');

            return self::FAILURE;
        }
        $backupDirectory = $directory.'/backups';
        if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0700, true)) {
            $this->error('Cannot create the backup directory.');

            return self::FAILURE;
        }
        $name = 'moola-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
        $snapshot = $backupDirectory.'/'.$name.'.sqlite';
        $temporary = $backupDirectory.'/'.$name.'.partial.tar';
        $source = null;
        $destination = null;
        try {
            $source = new SQLite3($database, SQLITE3_OPEN_READONLY);
            $source->busyTimeout(5000);
            $destination = new SQLite3($snapshot);
            $destination->busyTimeout(5000);
            if (! $source->backup($destination)) {
                throw new RuntimeException('SQLite snapshot failed.');
            }
            $source->close();
            $destination->close();
            $source = $destination = null;
            $archive = new PharData($temporary);
            $archive->addFile($snapshot, 'database.sqlite');
            $archive->addFile($directory.'/app.key', 'app.key');
            $uploads = $directory.'/storage/app';
            if (is_dir($uploads)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                    if ($file->isFile() && ! $file->isLink()) {
                        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
                        $archive->addFile($file->getPathname(), $relative);
                    }
                }
            }
            unset($archive);
            $final = $backupDirectory.'/'.$name.'.tar';
            if (! chmod($temporary, 0600) || ! rename($temporary, $final)) {
                throw new RuntimeException('Cannot finalize the backup.');
            }
            $this->info('Backup saved: '.$final);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $source?->close();
            $destination?->close();
            if (is_file($snapshot)) {
                unlink($snapshot);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
