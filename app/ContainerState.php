<?php

namespace App;

use RuntimeException;

class ContainerState
{
    public function key(string $directory, ?string $provided, bool $hasDatabase): string
    {
        $path = $directory.'/app.key';
        $provided = $provided ?: null;
        $saved = is_file($path) ? trim(file_get_contents($path)) : null;
        foreach ([$provided, $saved] as $key) {
            if ($key !== null && (! str_starts_with($key, 'base64:') || strlen(base64_decode(substr($key, 7), true) ?: '') !== 32)) {
                throw new RuntimeException('APP_KEY must be a base64-encoded 32-byte key.');
            }
        }
        if ($saved !== null && $provided !== null && ! hash_equals($saved, $provided)) {
            throw new RuntimeException('APP_KEY differs from the persistent key. Restore the original key; automatic rotation is refused.');
        }
        if ($saved !== null) {
            return $saved;
        }
        if ($hasDatabase && $provided === null) {
            throw new RuntimeException('An existing database has no persistent key. Supply its original APP_KEY before starting.');
        }
        $key = $provided ?? 'base64:'.base64_encode(random_bytes(32));
        $temporary = $path.'.tmp';
        if (file_put_contents($temporary, $key."\n", LOCK_EX) === false || ! chmod($temporary, 0600) || ! rename($temporary, $path)) {
            throw new RuntimeException('Cannot persist APP_KEY in the data directory.');
        }

        return $key;
    }
}
