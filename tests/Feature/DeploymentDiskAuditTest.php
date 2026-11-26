<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeploymentDiskAuditTest extends TestCase
{
    public function test_audit_distinguishes_copies_and_counts_only_old_backup_archives(): void
    {
        $disk = Storage::fake('deployment-audit');
        $disk->makeDirectory('repo/.git');
        $disk->put('repo/artisan', '');
        $disk->put('repo/deploy_result_new.html', 'old report');
        $disk->put('live/deploy_result_new.html', 'old report');
        $disk->put('repo/error_deploy.html', 'old error report');
        $disk->put('live/error_deploy.html', 'updated on live');
        $oldArchive = 'live/storage/app/YEDEKLEMELER/firma_1_test/araclar/yedek_2026-01-01.zip';
        $disk->put($oldArchive, 'archive');
        touch($disk->path($oldArchive), now()->subDays(11)->timestamp);
        $disk->put('live/storage/app/YEDEKLEMELER/firma_1_test/araclar/yedek_2026-01-02.zip', 'recent archive');

        $base = base_path();
        $storage = storage_path();
        try {
            $this->app->setBasePath(realpath($disk->path('live')));
            $this->app->useStoragePath(base_path('storage'));
            $this->artisan('filo:disk-kontrol', ['--repository' => $disk->path('repo')])
                ->expectsOutputToContain('1 eşleşen dosya')
                ->expectsOutputToContain('10 günü dolduran uygulama yedekleri: 1 ZIP')
                ->assertSuccessful();
        } finally {
            $this->app->setBasePath($base);
            $this->app->useStoragePath($storage);
        }
        $disk->assertExists([$oldArchive, 'live/deploy_result_new.html', 'live/error_deploy.html']);
    }
}
