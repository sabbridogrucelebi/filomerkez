<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditDeploymentCopies extends Command
{
    protected $signature = 'filo:disk-kontrol {--repository= : cPanel Git deposunun tam yolu}';
    protected $description = 'Kopya dağıtım dosyalarını ve on günü dolduran uygulama yedeklerini salt okunur olarak raporlar';

    public function handle(): int
    {
        $path = $this->option('repository');
        $repo = $path ? realpath($path) : false;
        $live = realpath(base_path());
        if (!$repo || $repo === $live || !is_dir($repo.'/.git') || !is_file($repo.'/artisan')) {
            $this->error('Canlı uygulamadan ayrı bir Filo Merkez Git deposu belirtin.');
            return self::FAILURE;
        }

        $files = ['deploy_result_new.html', 'error_deploy.html', 'rendered_dashboard.html'];
        foreach (['ios_screenshots', 'google_play_resimleri'] as $dir) {
            foreach (glob($repo.'/'.$dir.'/*.png') ?: [] as $image) {
                $files[] = $dir.'/'.basename($image);
            }
        }

        $total = 0;
        $count = 0;
        foreach ($files as $file) {
            $source = $repo.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);
            $copy = $live.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (!is_file($copy)) {
                continue;
            }
            if (realpath($source) !== $source || realpath($copy) !== $copy || !is_readable($source) || !is_readable($copy)) {
                $this->warn("Doğrulanamadı · $file");
                continue;
            }
            $digest = hash_file('sha256', $source);
            if ($digest === false || $digest !== hash_file('sha256', $copy)) {
                $this->warn("İçerik eşleşmedi · $file");
                continue;
            }
            $count++;
            $total += filesize($copy);
            $this->line("Birebir kopya · $file");
        }

        $this->info(sprintf('%d eşleşen dosya, %.2f MiB', $count, $total / 1048576));

        $backupDir = storage_path('app/YEDEKLEMELER');
        $oldCount = 0;
        $oldBytes = 0;
        $cutoff = now()->subDays(10)->getTimestamp();
        foreach (glob($backupDir.'/{firma_*,superadmin}/*/yedek_*.zip', GLOB_BRACE) ?: [] as $zip) {
            if (realpath($zip) !== str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $zip)
                || !is_file($zip) || !preg_match('/^yedek_\d{4}-\d{2}-\d{2}\.zip$/D', basename($zip))) {
                continue;
            }
            $modified = filemtime($zip);
            if ($modified !== false && $modified <= $cutoff) {
                $oldCount++;
                $oldBytes += filesize($zip);
            }
        }
        $this->line(sprintf('10 günü dolduran uygulama yedekleri: %d ZIP, %.2f MiB', $oldCount, $oldBytes / 1048576));
        return self::SUCCESS;
    }
}
