<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ListCacheKeys extends Command
{
    protected $signature = 'cache:list-keys {pattern?}';
    protected $description = 'List all cache keys';

    public function handle()
    {
        $pattern = $this->argument('pattern') ?? '*';
        
        if (config('cache.default') === 'redis') {
            try {
                $redis = Cache::getStore()->getConnection();
                $keys = $redis->keys($pattern);
                
                $this->info("Found " . count($keys) . " keys:");
                foreach ($keys as $key) {
                    if (!str_contains($key, 'queues:')) {
                        $this->line("  - {$key}");
                    }
                }
            } catch (\Exception $e) {
                $this->error("Redis error: " . $e->getMessage());
            }
        } else {
            if (Schema::hasTable('cache')) {
                $entries = DB::table('cache')
                    ->where('key', 'like', str_replace('*', '%', $pattern))
                    ->get();
                
                $this->info("Found " . $entries->count() . " keys:");
                foreach ($entries as $entry) {
                    $this->line("  - {$entry->key}");
                }
            }
        }
        
        return Command::SUCCESS;
    }
}



