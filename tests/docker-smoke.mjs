import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';

const origin = 'http://127.0.0.1:8080';
const compose = (...args) => execFileSync('docker', ['compose', ...args], { stdio: 'pipe' }).toString();
async function healthy() {
    for (let attempt = 0; attempt < 90; attempt++) {
        try { if ((await fetch(`${origin}/up`, { signal: AbortSignal.timeout(5000) })).ok) return; } catch {}
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
    throw new Error('Container did not become healthy');
}
const cookies = new Map();
async function request(path, options = {}) {
    const response = await fetch(`${origin}${path}`, { ...options, redirect: 'manual', headers: { ...options.headers, Cookie: [...cookies].map(([key, value]) => `${key}=${value}`).join('; ') } });
    response.headers.getSetCookie().forEach((cookie) => {
        const pair = cookie.split(';')[0];
        const split = pair.indexOf('=');
        cookies.set(pair.slice(0, split), pair.slice(split + 1));
    });
    return response;
}
await healthy();
const page = await request('/setup');
assert.equal(page.status, 200);
const html = await page.text();
const token = html.match(/name="_token" value="([^"]+)"/)[1];
const asset = html.match(/(?:href|src)="([^"]*\/build\/assets\/[^"]+)"/)[1];
assert.equal((await fetch(new URL(asset, origin))).status, 200);
assert.equal((await request('/.env')).status, 404);
const setup = await request('/setup', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, name: 'Docker Test Owner', email: 'docker-test@example.com', password: 'only-for-container-tests-12345', password_confirmation: 'only-for-container-tests-12345' }) });
assert.equal(setup.status, 302);
assert.equal((await request('/')).status, 200);
const fingerprint = compose('exec', '-T', 'app', 'sha256sum', '/data/app.key').trim();
compose('up', '-d', '--force-recreate', '--no-build');
await healthy();
assert.equal(compose('exec', '-T', 'app', 'sha256sum', '/data/app.key').trim(), fingerprint);
assert.equal((await request('/')).status, 200, 'Existing encrypted session survives container recreation');
assert.equal((await request('/setup')).status, 302, 'Setup remains closed after restart');
compose('exec', '-T', '--user', 'www-data', 'app', 'php', '-r', 'file_put_contents("/data/storage/app/private/docker-test.txt", "Original receipt");');
const backupOutput = compose('exec', '-T', '--user', 'www-data', 'app', 'php', 'artisan', 'moola:backup', '--no-interaction');
const archive = backupOutput.match(/Backup saved: (\/data\/backups\/moola-[a-zA-Z0-9.-]+\.tar)/)?.[1];
assert.ok(archive, 'Backup command returns a persistent archive path');
compose('exec', '-T', '--user', 'www-data', 'app', 'php', '-r', 'file_put_contents("/data/storage/app/private/docker-test.txt", "Changed after backup");');
compose('stop', 'app');
compose('run', '--rm', '--no-deps', '--entrypoint', 'sh', 'app', '-c', `rm -f /data/database.sqlite-wal /data/database.sqlite-shm; tar -xf ${archive} -C /data`);
compose('up', '-d', '--no-build');
await healthy();
assert.equal(compose('exec', '-T', 'app', 'sha256sum', '/data/app.key').trim(), fingerprint, 'Restored key matches the original');
assert.equal(compose('exec', '-T', '--user', 'www-data', 'app', 'cat', '/data/storage/app/private/docker-test.txt'), 'Original receipt', 'Stored files recover from the backup');
assert.equal((await request('/')).status, 200, 'Restored administrator and encrypted session still work');
assert.equal((await request('/setup')).status, 302, 'Restored installation remains configured');
console.log('Docker setup, assets, persistence, encrypted sessions, backup, and recovery checks passed.');
