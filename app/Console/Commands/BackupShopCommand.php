<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupShopCommand extends Command
{
    protected $signature = 'shop:backup
        {--keep= : Override how many days of backups to keep}
        {--connection= : Database connection to copy, defaults to the app connection}';

    protected $description = 'Back up the order database and the sellable product files';

    public function handle(): int
    {
        $path = (string) config('shop.backups.path');

        if (! is_dir($path) && ! mkdir($path, 0750, true) && ! is_dir($path)) {
            $this->error("Cannot create backup directory {$path}");

            return self::FAILURE;
        }

        $stamp = now()->format('Ymd-His');

        try {
            $database = $this->backupDatabase($path, $stamp);
            $files = $this->archive($path, 'product-files', $stamp, storage_path('app/shop'));
            // Kept as its own artefact rather than folded in with the sellable files. Restoring a
            // public cover onto the private disk, or the reverse, is exactly the mistake the two
            // disk split exists to prevent (SHOP-31).
            $public = $this->archive($path, 'public-files', $stamp, storage_path('app/public'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (array_filter([$database, $files, $public]) as $artefact) {
            $this->info(basename($artefact).' ('.$this->humanSize($artefact).')');
            $this->copyOffsite($artefact);
        }

        $this->prune($path);

        return self::SUCCESS;
    }

    /**
     * VACUUM INTO rather than a file copy: the database runs in WAL mode, so copying the file on
     * its own can capture a torn state that is missing whatever is still in the -wal sidecar.
     */
    private function backupDatabase(string $path, string $stamp): ?string
    {
        $connection = DB::connection($this->option('connection'));

        if ($connection->getDriverName() !== 'sqlite') {
            $this->warn('Database is not SQLite, skipping the database backup.');

            return null;
        }

        // SQLite refuses to VACUUM inside a transaction. Nothing in production runs this from
        // one, so bail loudly rather than half backing up.
        if ($connection->transactionLevel() > 0) {
            $this->warn('Skipping the database backup: a transaction is open.');

            return null;
        }

        $target = $path.'/database-'.$stamp.'.sqlite';

        $connection->getPdo()->exec('VACUUM INTO '.$connection->getPdo()->quote($target, PDO::PARAM_STR));

        return $target;
    }

    private function archive(string $path, string $name, string $stamp, string $source): ?string
    {
        if (! is_dir($source)) {
            $this->warn('Nothing at '.$source.' to back up.');

            return null;
        }

        $target = $path.'/'.$name.'-'.$stamp.'.tar.gz';

        $process = new Process(['tar', '-czf', $target, '-C', dirname($source), basename($source)]);
        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($name.' archive failed: '.$process->getErrorOutput());
        }

        return $target;
    }

    private function copyOffsite(string $artefact): void
    {
        $disk = config('shop.backups.offsite_disk');

        if (! is_string($disk) || $disk === '') {
            return;
        }

        $stream = fopen($artefact, 'rb');

        if ($stream === false) {
            $this->warn('Could not read '.basename($artefact).' for the offsite copy.');

            return;
        }

        Storage::disk($disk)->put('shop/'.basename($artefact), $stream);
        fclose($stream);

        $this->line('  copied to '.$disk);
    }

    private function prune(string $path): void
    {
        $keepDays = (int) ($this->option('keep') ?? config('shop.backups.keep_days'));

        if ($keepDays <= 0) {
            return;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (glob($path.'/{database,product-files,public-files}-*', GLOB_BRACE) ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->line("Pruned {$removed} backup(s) older than {$keepDays} days.");
        }
    }

    private function humanSize(string $file): string
    {
        $bytes = filesize($file) ?: 0;

        return $bytes > 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
