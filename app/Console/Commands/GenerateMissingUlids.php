<?php

namespace App\Console\Commands;

use App\Models\FdiSg;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GenerateMissingUlids extends Command
{
    protected $signature = 'fdi:generate-missing-ulids';
    protected $description = 'Generate ULIDs for FDI records that don\'t have one';

    public function handle()
    {
        if (!Schema::hasColumn('fdi_sg', 'ulid')) {
            $this->error('The ulid column does not exist in fdi_sg table. Please run the migration first.');
            return Command::FAILURE;
        }

        $this->info('Checking for FDI records without ULIDs...');

        $recordsWithoutUlid = FdiSg::whereNull('ulid')->count();

        if ($recordsWithoutUlid === 0) {
            $this->info('All FDI records already have ULIDs.');
            return Command::SUCCESS;
        }

        $this->info("Found {$recordsWithoutUlid} records without ULIDs. Generating...");

        FdiSg::whereNull('ulid')
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    $ulid = (string) Str::ulid();
                    
                    // Ensure uniqueness
                    while (FdiSg::where('ulid', $ulid)->exists()) {
                        $ulid = (string) Str::ulid();
                    }
                    
                    $record->update(['ulid' => $ulid]);
                    $this->line("Generated ULID {$ulid} for FDI ID {$record->id} ({$record->numero_fdi})");
                }
            });

        $this->info('✓ All missing ULIDs have been generated!');

        // Show summary
        $total = FdiSg::count();
        $withUlid = FdiSg::whereNotNull('ulid')->count();
        
        $this->table(
            ['Total Records', 'With ULID', 'Without ULID'],
            [[$total, $withUlid, $total - $withUlid]]
        );

        return Command::SUCCESS;
    }
}



