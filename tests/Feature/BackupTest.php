<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->backupPath = storage_path('framework/testing/backups-'.uniqid());
    config(['shop.backups.path' => $this->backupPath, 'shop.backups.offsite_disk' => null]);
});

afterEach(function (): void {
    File::deleteDirectory($this->backupPath);
});

function backupFiles(string $path, string $prefix): array
{
    return array_values(array_map('basename', glob($path.'/'.$prefix.'-*') ?: []));
}

// The suite wraps every test in a transaction and SQLite refuses to VACUUM inside one, so the
// database copy is exercised against a throwaway file connection of its own.
function throwawayConnection(): string
{
    $file = storage_path('framework/testing/backup-source-'.uniqid().'.sqlite');
    touch($file);

    config(['database.connections.backup_source' => [
        'driver' => 'sqlite',
        'database' => $file,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    DB::connection('backup_source')->statement('create table orders (id integer primary key, total integer)');
    DB::connection('backup_source')->insert('insert into orders (id, total) values (1, 4242)');

    return $file;
}

it('writes a database backup', function (): void {
    throwawayConnection();

    $this->artisan('shop:backup', ['--connection' => 'backup_source'])->assertSuccessful();

    expect(backupFiles($this->backupPath, 'database'))->toHaveCount(1);
});

it('produces a database backup that still contains the orders', function (): void {
    throwawayConnection();

    $this->artisan('shop:backup', ['--connection' => 'backup_source'])->assertSuccessful();

    $copy = glob($this->backupPath.'/database-*.sqlite')[0];
    $total = (new PDO('sqlite:'.$copy))->query('select total from orders where id = 1')->fetchColumn();

    expect((int) $total)->toBe(4242);
});

it('refuses to copy the database from inside a transaction rather than failing outright', function (): void {
    // The default connection is mid-transaction courtesy of RefreshDatabase.
    $this->artisan('shop:backup')
        ->expectsOutputToContain('a transaction is open')
        ->assertSuccessful();

    expect(backupFiles($this->backupPath, 'database'))->toBeEmpty();
});

it('archives the product files', function (): void {
    Storage::disk('shop')->put('products/script.pdf', 'the script');

    $this->artisan('shop:backup')->assertSuccessful();

    expect(backupFiles($this->backupPath, 'product-files'))->toHaveCount(1);
});

it('creates the backup directory when it does not exist yet', function (): void {
    expect(is_dir($this->backupPath))->toBeFalse();

    $this->artisan('shop:backup')->assertSuccessful();

    expect(is_dir($this->backupPath))->toBeTrue();
});

it('prunes backups older than the retention window', function (): void {
    mkdir($this->backupPath, 0750, true);

    $stale = $this->backupPath.'/database-20200101-000000.sqlite';
    touch($stale, now()->subDays(30)->getTimestamp());

    $this->artisan('shop:backup', ['--keep' => 14])->assertSuccessful();

    expect(file_exists($stale))->toBeFalse();
});

it('keeps backups inside the retention window', function (): void {
    mkdir($this->backupPath, 0750, true);

    $recent = $this->backupPath.'/database-20260101-000000.sqlite';
    touch($recent, now()->subDays(3)->getTimestamp());

    $this->artisan('shop:backup', ['--keep' => 14])->assertSuccessful();

    expect(file_exists($recent))->toBeTrue();
});

it('keeps everything when retention is disabled', function (): void {
    mkdir($this->backupPath, 0750, true);

    $ancient = $this->backupPath.'/database-19990101-000000.sqlite';
    touch($ancient, now()->subYears(5)->getTimestamp());

    $this->artisan('shop:backup', ['--keep' => 0])->assertSuccessful();

    expect(file_exists($ancient))->toBeTrue();
});

it('copies each artefact to the offsite disk when one is configured', function (): void {
    Storage::fake('offsite');
    Storage::disk('shop')->put('products/script.pdf', 'the script');
    throwawayConnection();

    config(['shop.backups.offsite_disk' => 'offsite']);

    $this->artisan('shop:backup', ['--connection' => 'backup_source'])->assertSuccessful();

    expect(Storage::disk('offsite')->files('shop'))->toHaveCount(2);
});

it('leaves nothing offsite when no disk is configured', function (): void {
    Storage::fake('offsite');

    $this->artisan('shop:backup')->assertSuccessful();

    expect(Storage::disk('offsite')->allFiles())->toBeEmpty();
});

it('is scheduled to run nightly', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'shop:backup'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('20 3 * * *');
});
