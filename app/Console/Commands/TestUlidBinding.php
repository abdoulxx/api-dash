<?php

namespace App\Console\Commands;

use App\Models\FdiSg;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestUlidBinding extends Command
{
    protected $signature = 'test:ulid-binding {ulid?}';
    protected $description = 'Test ULID route model binding';

    public function handle()
    {
        // Get existing FDI or create a test one
        $fdi = FdiSg::first();
        
        if (!$fdi) {
            $this->error('No FDI found in database. Please create one first.');
            return Command::FAILURE;
        }

        $testUlid = $this->argument('ulid') ?? $fdi->ulid;

        $this->info("Testing ULID: {$testUlid}");
        $this->info("FDI exists in DB: " . ($fdi ? 'YES' : 'NO'));

        // Test direct query
        $directQuery = FdiSg::where('ulid', $testUlid)->first();
        $this->info("Direct query result: " . ($directQuery ? "Found ID {$directQuery->id}" : "NOT FOUND"));

        // Test resolveRouteBinding
        try {
            $model = new FdiSg();
            $resolved = $model->resolveRouteBinding($testUlid);
            $this->info("resolveRouteBinding result: Found ID {$resolved->id}");
            $this->info("Route key name: " . $resolved->getRouteKeyName());
        } catch (\Exception $e) {
            $this->error("resolveRouteBinding failed: " . $e->getMessage());
        }

        // Show all existing ULIDs
        $this->info("\nExisting ULIDs in database:");
        $allFdis = FdiSg::select('id', 'ulid', 'numero_fdi')->get();
        foreach ($allFdis as $f) {
            $this->line("  ID {$f->id}: {$f->ulid} ({$f->numero_fdi})");
        }

        return Command::SUCCESS;
    }
}


