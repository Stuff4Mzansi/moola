<?php

use App\ContainerState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/moola-hosting-'.bin2hex(random_bytes(8));
    File::makeDirectory($this->directory);
});

afterEach(function () {
    if (isset($this->directory)) {
        File::deleteDirectory($this->directory);
    }
});

test('container key is generated once persists across restarts and cannot silently rotate', function () {
    $state = new ContainerState;
    $key = $state->key($this->directory, null, false);
    expect(strlen(base64_decode(substr($key, 7))))->toBe(32)->and($state->key($this->directory, null, true))->toBe($key);
    expect(fn () => $state->key($this->directory, 'base64:'.base64_encode(random_bytes(32)), true))->toThrow(RuntimeException::class, 'differs');
    expect(trim(file_get_contents($this->directory.'/app.key')))->toBe($key);
});

test('importing a database requires its original key and malformed keys fail closed', function () {
    $state = new ContainerState;
    expect(fn () => $state->key($this->directory, null, true))->toThrow(RuntimeException::class, 'original');
    expect(fn () => $state->key($this->directory, 'not-a-key', false))->toThrow(RuntimeException::class);
    $key = 'base64:'.base64_encode(random_bytes(32));
    expect($state->key($this->directory, $key, true))->toBe($key);
});

test('backup archives a consistent SQLite database with its key and uploaded files', function () {
    $database = $this->directory.'/database.sqlite';
    $sqlite = new SQLite3($database);
    $sqlite->exec('PRAGMA journal_mode=WAL');
    $sqlite->exec('CREATE TABLE sample (name TEXT)');
    $sqlite->exec("INSERT INTO sample VALUES ('Household savings')");
    (new ContainerState)->key($this->directory, null, false);
    File::makeDirectory($this->directory.'/storage/app/private', recursive: true);
    File::put($this->directory.'/storage/app/private/receipt.txt', 'Saved receipt');
    config(['app.data_directory' => $this->directory, 'database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
    $this->artisan('moola:backup')->assertSuccessful();
    $sqlite->close();
    $archives = glob($this->directory.'/backups/*.tar');
    expect($archives)->toHaveCount(1);
    $archive = new PharData($archives[0]);
    expect($archive['app.key']->getContent())->toBe(file_get_contents($this->directory.'/app.key'));
    expect($archive['storage/app/private/receipt.txt']->getContent())->toBe('Saved receipt');
    $restore = $this->directory.'/restore';
    File::makeDirectory($restore);
    $archive->extractTo($restore);
    $restored = new SQLite3($restore.'/database.sqlite');
    expect($restored->querySingle('SELECT name FROM sample'))->toBe('Household savings')->and($restored->querySingle('PRAGMA integrity_check'))->toBe('ok');
    $restored->close();
});

test('database health endpoint is accessible before administrator setup', function () {
    $this->get('/up')->assertOk();
});

test('container initialization refuses an ephemeral database', function () {
    config(['app.data_directory' => $this->directory, 'app.key' => null]);
    $this->artisan('moola:initialize')->assertFailed();
    expect(file_exists($this->directory.'/app.key'))->toBeFalse();
});

test('fresh persistent initialization migrates without users and restarts without changing its key', function () {
    $environment = ['APP_ENV' => 'production', 'APP_KEY' => '', 'APP_DEBUG' => 'false', 'MOOLA_DATA_DIR' => $this->directory, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->directory.'/database.sqlite', 'DB_URL' => '', 'CACHE_STORE' => 'database', 'SQLITE_JOURNAL_MODE' => 'WAL'];
    $first = Process::path(base_path())->env($environment)->run([PHP_BINARY, 'artisan', 'moola:initialize', '--no-interaction']);
    expect($first->exitCode())->toBe(0, $first->output().$first->errorOutput());
    $key = file_get_contents($this->directory.'/app.key');
    $sqlite = new SQLite3($this->directory.'/database.sqlite');
    expect((int) $sqlite->querySingle('SELECT COUNT(*) FROM users'))->toBe(0);
    $sqlite->close();
    $second = Process::path(base_path())->env($environment)->run([PHP_BINARY, 'artisan', 'moola:initialize', '--no-interaction']);
    expect($second->exitCode())->toBe(0, $second->output().$second->errorOutput())->and(file_get_contents($this->directory.'/app.key'))->toBe($key);
    expect(glob($this->directory.'/backups/*.tar'))->toHaveCount(0);
});

test('only configured proxies can supply the external HTTPS scheme', function () {
    config(['trustedproxy.proxies' => '172.20.0.0/16']);
    $this->withServerVariables(['REMOTE_ADDR' => '172.20.0.2'])->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'money.example.com'])->get('/setup')->assertOk()->assertSee('https://money.example.com/setup', false);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'spoofed.example.com'])->get('/setup')->assertOk()->assertDontSee('https://spoofed.example.com/setup', false);
});

test('pending SQLite migrations produce a pre-upgrade backup before applying schema changes', function () {
    $environment = ['APP_ENV' => 'production', 'APP_KEY' => '', 'MOOLA_DATA_DIR' => $this->directory, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->directory.'/database.sqlite', 'DB_URL' => '', 'CACHE_STORE' => 'database'];
    $first = Process::path(base_path())->env($environment)->run([PHP_BINARY, 'artisan', 'moola:initialize', '--no-interaction']);
    expect($first->exitCode())->toBe(0, $first->output().$first->errorOutput());
    $sqlite = new SQLite3($this->directory.'/database.sqlite');
    $sqlite->exec('CREATE TABLE upgrade_marker (amount INTEGER)');
    $sqlite->exec('INSERT INTO upgrade_marker VALUES (12345)');
    $sqlite->exec('DROP TABLE cache');
    $sqlite->exec('DROP TABLE cache_locks');
    $sqlite->exec("DELETE FROM migrations WHERE migration LIKE '%create_cache_table'");
    $sqlite->close();
    $upgrade = Process::path(base_path())->env($environment)->run([PHP_BINARY, 'artisan', 'moola:initialize', '--no-interaction']);
    expect($upgrade->exitCode())->toBe(0, $upgrade->output().$upgrade->errorOutput());
    expect(glob($this->directory.'/backups/*.tar'))->toHaveCount(1);
    $sqlite = new SQLite3($this->directory.'/database.sqlite');
    expect((int) $sqlite->querySingle('SELECT amount FROM upgrade_marker'))->toBe(12345)->and((int) $sqlite->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE name='cache'"))->toBe(1);
    $sqlite->close();
});
