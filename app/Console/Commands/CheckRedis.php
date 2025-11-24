<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;

class CheckRedis extends Command
{
    protected $signature = 'redis:check';
    protected $description = 'Vérifier l\'installation et la configuration de Redis/Memurai';

    public function handle()
    {
        $this->info('=== Vérification Redis/Memurai ===');
        $this->newLine();

        // 1. Vérifier si Memurai/Redis CLI est accessible
        $this->info('1. Vérification de Memurai/Redis CLI...');
        $cliAvailable = $this->checkRedisCli();
        if ($cliAvailable) {
            $this->line('   ✅ Memurai/Redis CLI est accessible');
        } else {
            $this->line('   ❌ Memurai/Redis CLI n\'est pas accessible');
            $this->line('   💡 Essayez: memurai-cli ping ou redis-cli ping');
        }
        $this->newLine();

        // 2. Vérifier la configuration .env
        $this->info('2. Configuration du projet...');
        $this->displayConfig();
        $this->newLine();

        // 3. Vérifier l'extension PHP Redis
        $this->info('3. Extension PHP Redis...');
        $this->checkPhpExtension();
        $this->newLine();

        // 4. Tester la connexion depuis Laravel
        $this->info('4. Test de connexion depuis Laravel...');
        $this->testLaravelConnection();
        $this->newLine();

        // 5. État actuel du cache et queue
        $this->info('5. État actuel...');
        $this->displayCurrentState();
        $this->newLine();

        // 6. Recommandations
        $this->info('6. Recommandations...');
        $this->displayRecommendations();

        return Command::SUCCESS;
    }

    private function checkRedisCli(): bool
    {
        // Essayer memurai-cli
        $memuraiCommand = 'memurai-cli ping 2>&1';
        $output = shell_exec($memuraiCommand);
        
        if ($output && (str_contains($output, 'PONG') || str_contains($output, 'pong'))) {
            return true;
        }

        // Essayer redis-cli
        $redisCommand = 'redis-cli ping 2>&1';
        $output = shell_exec($redisCommand);
        
        if ($output && (str_contains($output, 'PONG') || str_contains($output, 'pong'))) {
            return true;
        }

        return false;
    }

    private function displayConfig(): void
    {
        $cacheStore = config('cache.default');
        $queueConnection = config('queue.default');
        $redisHost = config('database.redis.default.host', '127.0.0.1');
        $redisPort = config('database.redis.default.port', '6379');
        $redisClient = config('database.redis.client', 'predis');

        $this->line("   Cache Store: <fg=cyan>{$cacheStore}</>");
        $this->line("   Queue Connection: <fg=cyan>{$queueConnection}</>");
        $this->line("   Redis Host: <fg=cyan>{$redisHost}</>");
        $this->line("   Redis Port: <fg=cyan>{$redisPort}</>");
        $this->line("   Redis Client: <fg=cyan>{$redisClient}</>");

        if ($cacheStore === 'redis') {
            $this->line('   ✅ Cache configuré pour utiliser Redis');
        } else {
            $this->line("   ⚠️  Cache utilise: <fg=yellow>{$cacheStore}</>");
        }

        if ($queueConnection === 'redis') {
            $this->line('   ✅ Queue configurée pour utiliser Redis');
        } else {
            $this->line("   ⚠️  Queue utilise: <fg=yellow>{$queueConnection}</>");
        }
    }

    private function checkPhpExtension(): void
    {
        $phpredisAvailable = extension_loaded('redis');
        $predisAvailable = class_exists('Predis\Client');

        if ($phpredisAvailable) {
            $this->line('   ✅ Extension PHP Redis (phpredis) est installée');
        } else {
            $this->line('   ❌ Extension PHP Redis (phpredis) n\'est pas installée');
        }

        if ($predisAvailable) {
            $this->line('   ✅ Predis (client PHP pur) est disponible');
        } else {
            $this->line('   ❌ Predis n\'est pas disponible');
        }

        if (!$phpredisAvailable && !$predisAvailable) {
            $this->line('   ⚠️  Aucun client Redis disponible !');
        }
    }

    private function testLaravelConnection(): void
    {
        try {
            // Tester avec Redis facade
            if (class_exists('Illuminate\Support\Facades\Redis')) {
                $ping = Redis::connection()->ping();
                if ($ping) {
                    $this->line('   ✅ Connexion Redis réussie via Redis::ping()');
                } else {
                    $this->line('   ❌ Redis::ping() a échoué');
                }
            }

            // Tester avec Cache store redis
            try {
                Cache::store('redis')->put('test_connection', 'ok', 10);
                $value = Cache::store('redis')->get('test_connection');
                if ($value === 'ok') {
                    $this->line('   ✅ Connexion Redis réussie via Cache::store(\'redis\')');
                    Cache::store('redis')->forget('test_connection');
                } else {
                    $this->line('   ❌ Cache::store(\'redis\') ne fonctionne pas correctement');
                }
            } catch (\Exception $e) {
                $this->line('   ❌ Erreur lors du test Cache::store(\'redis\'): ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->line('   ❌ Erreur de connexion: ' . $e->getMessage());
        }
    }

    private function displayCurrentState(): void
    {
        $cacheStore = config('cache.default');
        $queueConnection = config('queue.default');

        $this->line("   Cache actuel: <fg=cyan>{$cacheStore}</>");
        $this->line("   Queue actuelle: <fg=cyan>{$queueConnection}</>");

        if ($cacheStore === 'database') {
            $this->line('   ℹ️  Le cache utilise la base de données (fallback automatique)');
        }

        if ($queueConnection === 'database') {
            $this->line('   ℹ️  La queue utilise la base de données (fallback automatique)');
        }
    }

    private function displayRecommendations(): void
    {
        $cacheStore = config('cache.default');
        $queueConnection = config('queue.default');
        $cliAvailable = $this->checkRedisCli();

        if (!$cliAvailable) {
            $this->line('   ⚠️  Memurai/Redis ne semble pas être démarré');
            $this->line('   💡 Pour démarrer Memurai:');
            $this->line('      - Vérifiez que Memurai est installé');
            $this->line('      - Testez: memurai-cli ping');
        }

        if ($cacheStore !== 'redis' && $queueConnection !== 'redis') {
            $this->line('   💡 Pour utiliser Redis, modifiez votre fichier .env:');
            $this->line('      CACHE_STORE=redis');
            $this->line('      QUEUE_CONNECTION=redis');
            $this->line('      REDIS_HOST=127.0.0.1');
            $this->line('      REDIS_PORT=6379');
        }

        if ($cacheStore === 'redis' || $queueConnection === 'redis') {
            if (!$cliAvailable) {
                $this->line('   ⚠️  Votre .env est configuré pour Redis mais Redis n\'est pas accessible');
                $this->line('   ℹ️  L\'application utilise automatiquement la base de données (fallback)');
            } else {
                $this->line('   ✅ Redis est configuré et accessible !');
            }
        }
    }
}

