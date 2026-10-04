<?php

namespace App\Console\Commands;

use App\ContainerState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('moola:initialize')]
#[Description('Initialize persistent container state, back up pending upgrades, and migrate without seeding users')]
class InitializeContainer extends Command
{
    public function handle(ContainerState $state): int
    {
        $directory = config('app.data_directory');
        if (! is_string($directory) || ! is_dir($directory) || ! is_writable($directory)) {
            $this->error('MOOLA_DATA_DIR must point to a writable persistent directory.');

            return self::FAILURE;
        }
        $lock = fopen($directory.'/initialize.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            $this->error('Cannot lock the persistent data directory.');

            return self::FAILURE;
        }
        try {
            $connection = config('database.default');
            $sqlite = $connection === 'sqlite';
            $database = config('database.connections.sqlite.database');
            if ($sqlite && (! is_string($database) || $database === ':memory:' || ! is_dir(dirname($database)))) {
                throw new RuntimeException('SQLite needs a persistent file path whose parent directory exists.');
            }
            $hasDatabase = $sqlite ? is_file($database) && filesize($database) > 0 : true;
            config(['app.key' => $state->key($directory, config('app.key'), $hasDatabase)]);
            if ($sqlite && ! is_file($database) && ! touch($database)) {
                throw new RuntimeException('Cannot create the SQLite database.');
            }
            if ($sqlite && DB::getSchemaBuilder()->hasTable('migrations')) {
                $applied = DB::table('migrations')->pluck('migration')->all();
                $pending = collect(glob(database_path('migrations/*.php')))->map(fn (string $file): string => basename($file, '.php'))->diff($applied);
                if ($pending->isNotEmpty() && $this->call('moola:backup') !== self::SUCCESS) {
                    throw new RuntimeException('Pre-upgrade backup failed; migration was not started.');
                }
            }

            return $this->call('migrate', ['--force' => true, '--no-interaction' => true]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
