<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use App\Support\CacheTagger;

class CacheController extends Controller
{
    /**
     * Liste tous les caches avec leurs tags et clés
     */
    public function index(Request $request): JsonResponse
    {
        $tag = $request->query('tag');
        $search = $request->query('search');
        
        $cacheData = [];
        
        // Si Redis est utilisé, essayer de lister les clés
        if (config('cache.default') === 'redis') {
            try {
                // Utiliser la connexion Redis configurée pour le cache
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                $cachePrefix = config('cache.prefix', '');
                
                // Récupérer toutes les clés - essayer avec et sans préfixe
                $keys = [];
                if ($cachePrefix) {
                    $pattern = $cachePrefix . '*';
                $keys = $redis->keys($pattern);
                }
                
                // Si aucune clé trouvée avec le préfixe, essayer toutes les clés
                if (empty($keys)) {
                    $allKeys = $redis->keys('*');
                    // Filtrer pour ne garder que les clés de cache (pas queues, etc.)
                    $keys = array_filter($allKeys, function($key) {
                        return !str_contains($key, 'queues:') 
                            && !str_contains($key, 'horizon:')
                            && !str_contains($key, 'spatie.permission.cache');
                    });
                }
                
                foreach ($keys as $fullKey) {
                    // Ignorer les clés de queue Laravel
                    if (str_contains($fullKey, 'queues:')) {
                        continue;
                    }
                    
                    // Retirer le préfixe pour obtenir la clé réelle
                    $actualKey = $fullKey;
                    if ($cachePrefix && str_starts_with($fullKey, $cachePrefix)) {
                        $actualKey = substr($fullKey, strlen($cachePrefix));
                    }
                    
                    // Filtrer par tag si spécifié
                    if ($tag && !str_contains($actualKey, $tag)) {
                        continue;
                    }
                    
                    // Filtrer par recherche si spécifié
                    if ($search && !str_contains($actualKey, $search)) {
                        continue;
                    }
                    
                    // Récupérer la valeur - gérer les clés taguées
                    try {
                        $value = null;
                        $detectedTags = [];
                        
                        // Essayer d'abord avec la clé telle quelle
                        $value = Cache::get($actualKey);
                        
                        // Si null, essayer avec CacheTagger (format: tag|tag:key)
                        if ($value === null && str_contains($actualKey, '|') && str_contains($actualKey, ':')) {
                            // Format: tag|tag:key ou tag1|tag2:key
                            $parts = explode(':', $actualKey, 2);
                            if (count($parts) === 2) {
                                $tagPart = $parts[0];
                                $key = $parts[1];
                                
                                // Extraire les tags (séparés par |)
                                if (str_contains($tagPart, '|')) {
                                    $tags = explode('|', $tagPart);
                                    $detectedTags = $tags;
                                    try {
                                        $value = \App\Support\CacheTagger::tags($tags)->get($key);
                                    } catch (\Exception $e) {
                                        // Ignorer
                                    }
                                } else {
                                    // Format simple: tag:key
                                    $detectedTags = [$tagPart];
                                    try {
                                        $value = Cache::tags([$tagPart])->get($key);
                                    } catch (\Exception $e) {
                                        // Ignorer
                                    }
                                }
                            }
                        } elseif ($value === null && str_contains($actualKey, ':')) {
                            // Format simple: tag:key
                            $parts = explode(':', $actualKey, 2);
                            if (count($parts) === 2) {
                                $tag = $parts[0];
                                $key = $parts[1];
                                $detectedTags = [$tag];
                                try {
                                    $value = Cache::tags([$tag])->get($key);
                                } catch (\Exception $e) {
                                    // Ignorer
                                }
                            }
                        }
                        
                        // Si toujours null, essayer de récupérer directement depuis Redis
                        if ($value === null) {
                            try {
                                $rawValue = $redis->get($fullKey);
                                if ($rawValue) {
                                    $value = unserialize($rawValue);
                                }
                            } catch (\Exception $e) {
                                // Ignorer
                            }
                        }
                        
                        // Ne pas ajouter si la valeur est toujours null (clé invalide ou expirée)
                        if ($value === null) {
                            continue;
                        }
                        
                        $ttl = $redis->ttl($fullKey);
                        
                        $cacheData[] = [
                            'key' => $fullKey,
                            'actual_key' => $actualKey,
                            'tags' => $detectedTags ?: null,
                            'value' => $this->formatValue($value),
                            'ttl' => $ttl > 0 ? $ttl : null,
                            'ttl_formatted' => $ttl > 0 ? now()->addSeconds($ttl)->diffForHumans() : 'Permanent',
                            'size' => strlen(serialize($value)),
                            'size_formatted' => $this->formatBytes(strlen(serialize($value))),
                        ];
                    } catch (\Exception $e) {
                        // Si erreur, ignorer cette clé et continuer
                        Log::debug('Erreur lors de la récupération du cache', [
                            'key' => $fullKey,
                            'actual_key' => $actualKey,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }
            } catch (\Exception $e) {
                // Si on ne peut pas accéder à Redis directement, utiliser la base de données
                Log::warning('Impossible d\'accéder à Redis, utilisation de la base de données', [
                    'error' => $e->getMessage(),
                ]);
                $cacheData = $this->getCacheFromDatabase($tag, $search);
            }
        } else {
            // Si database cache est utilisé
            $cacheData = $this->getCacheFromDatabase($tag, $search);
        }
        
        return response()->json([
            'status' => 200,
            'message' => 'Caches récupérés avec succès',
            'data' => $cacheData,
            'meta' => [
                'total' => count($cacheData),
                'cache_driver' => config('cache.default'),
                'tags_available' => $this->getAvailableTags(),
            ],
        ]);
    }
    
    /**
     * Afficher un cache spécifique par clé
     */
    public function show(string $key): JsonResponse
    {
        $decodedKey = urldecode($key);
        $cachePrefix = config('cache.prefix', '');
        $redisPrefix = config('database.redis.options.prefix', '');
        $fullPrefix = $redisPrefix . $cachePrefix;
        
        // Essayer avec la clé telle quelle d'abord
        $foundKey = $decodedKey;
        $value = null;
        $ttl = null;
        
        // Essayer de trouver le cache avec différentes variations de la clé
        $keyVariations = [
            $decodedKey,  // Clé complète telle quelle (avec préfixe complet)
        ];
        
        // Si la clé contient déjà le préfixe complet, l'utiliser directement
        if ($fullPrefix && str_starts_with($decodedKey, $fullPrefix)) {
            // La clé contient déjà le préfixe complet, essayer directement avec Redis
            if (config('cache.default') === 'redis') {
                try {
                    $redisConnection = config('cache.stores.redis.connection', 'cache');
                    $redis = Redis::connection($redisConnection);
                    
                    // Vérifier d'abord si c'est un set (tag:entries)
                    if (str_contains($decodedKey, 'tag:') && str_ends_with($decodedKey, ':entries')) {
                        // Type Redis: 1=STRING, 2=SET, 3=LIST, 4=ZSET, 5=HASH
                        $type = $redis->type($decodedKey);
                        if ($type === 2) { // REDIS_SET = 2
                            $members = $redis->sMembers($decodedKey);
                            $cardinality = $redis->sCard($decodedKey);
                            
                            return response()->json([
                                'status' => 200,
                                'message' => 'Set Redis détecté (tag Laravel)',
                                'data' => [
                                    'key' => $decodedKey,
                                    'type' => 'set',
                                    'type_label' => 'Set Redis (Tag Laravel)',
                                    'cardinality' => $cardinality,
                                    'members' => $members,
                                    'members_count' => count($members),
                                    'note' => 'Ce set contient les références aux clés de cache taguées. Utilisez GET /api/cache/tag/{tag} pour voir les caches réels.',
                                ],
                            ]);
                        }
                    }
                    
                    // Sinon, vérifier si c'est une valeur de cache normale
                    if ($redis->exists($decodedKey)) {
                        $type = $redis->type($decodedKey);
                        if ($type === 1) { // STRING
                            $rawValue = $redis->get($decodedKey);
                            if ($rawValue) {
                                $value = unserialize($rawValue);
                                $foundKey = $decodedKey;
                                $ttl = $redis->ttl($decodedKey);
                            }
                        } elseif ($type === 2) { // SET
                            $members = $redis->sMembers($decodedKey);
                            $cardinality = $redis->sCard($decodedKey);
                            
                            return response()->json([
                                'status' => 200,
                                'message' => 'Set Redis détecté',
                                'data' => [
                                    'key' => $decodedKey,
                                    'type' => 'set',
                                    'type_label' => 'Set Redis',
                                    'cardinality' => $cardinality,
                                    'members' => $members,
                                    'members_count' => count($members),
                                ],
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorer
                }
            }
        }
        
        // Si pas trouvé, essayer avec Cache::get()
        if ($value === null) {
        foreach ($keyVariations as $keyVariation) {
            if (Cache::has($keyVariation)) {
                $value = Cache::get($keyVariation);
                $foundKey = $keyVariation;
                break;
                }
            }
        }
        
        // Si toujours pas trouvé, essayer avec Redis directement avec différentes variations
        if ($value === null && config('cache.default') === 'redis') {
            try {
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                
                // Vérifier si c'est un set Redis (tag:entries)
                if (str_contains($decodedKey, 'tag:') && str_ends_with($decodedKey, ':entries')) {
                    // C'est un set de tags Laravel
                    // Type Redis: 1=STRING, 2=SET, 3=LIST, 4=ZSET, 5=HASH
                    $type = $redis->type($decodedKey);
                    if ($type === 2) { // REDIS_SET = 2
                        $members = $redis->sMembers($decodedKey);
                        $cardinality = $redis->sCard($decodedKey);
                        
                        return response()->json([
                            'status' => 200,
                            'message' => 'Set Redis détecté (tag Laravel)',
                            'data' => [
                                'key' => $decodedKey,
                                'type' => 'set',
                                'type_label' => 'Set Redis (Tag Laravel)',
                                'cardinality' => $cardinality,
                                'members' => $members,
                                'members_count' => count($members),
                                'note' => 'Ce set contient les références aux clés de cache taguées. Utilisez GET /api/cache/tag/{tag} pour voir les caches réels.',
                            ],
                        ]);
                    }
                }
                
                // Essayer avec toutes les variations possibles
                $redisKeyVariations = [
                    $decodedKey,  // Clé complète telle quelle
                    $fullPrefix . $decodedKey,  // Préfixe complet + clé
                    $cachePrefix . $decodedKey,  // Préfixe cache + clé
                ];
                
                // Si la clé contient déjà un préfixe, essayer de le retirer
                if ($fullPrefix && str_starts_with($decodedKey, $fullPrefix)) {
                    $keyWithoutPrefix = substr($decodedKey, strlen($fullPrefix));
                    $redisKeyVariations[] = $keyWithoutPrefix;
                    $redisKeyVariations[] = $cachePrefix . $keyWithoutPrefix;
                } elseif ($cachePrefix && str_starts_with($decodedKey, $cachePrefix)) {
                    $keyWithoutPrefix = substr($decodedKey, strlen($cachePrefix));
                    $redisKeyVariations[] = $keyWithoutPrefix;
                }
                
                foreach ($redisKeyVariations as $redisKey) {
                    if ($redis->exists($redisKey)) {
                        // Vérifier le type de la clé
                        // Type Redis: 1=STRING, 2=SET, 3=LIST, 4=ZSET, 5=HASH
                        $type = $redis->type($redisKey);
                        
                        if ($type === 1) { // REDIS_STRING = 1
                            // C'est une valeur de cache normale
                            $rawValue = $redis->get($redisKey);
                            if ($rawValue) {
                                $value = unserialize($rawValue);
                                $foundKey = $redisKey;
                                $ttl = $redis->ttl($redisKey);
                        break;
                            }
                        } elseif ($type === 2) { // REDIS_SET = 2
                            // C'est un set (probablement un tag)
                            $members = $redis->sMembers($redisKey);
                            $cardinality = $redis->sCard($redisKey);
                            
                            return response()->json([
                                'status' => 200,
                                'message' => 'Set Redis détecté',
                                'data' => [
                                    'key' => $redisKey,
                                    'type' => 'set',
                                    'type_label' => 'Set Redis',
                                    'cardinality' => $cardinality,
                                    'members' => $members,
                                    'members_count' => count($members),
                                ],
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignorer l'erreur
            }
        }
        
        if ($value !== null) {
            // Le TTL a déjà été récupéré si la clé contenait le préfixe complet
            // Sinon, essayer de récupérer le TTL si Redis est utilisé
            if ($ttl === null && config('cache.default') === 'redis') {
                try {
                    $redisConnection = config('cache.stores.redis.connection', 'cache');
                    $redis = Redis::connection($redisConnection);
                    $testTtl = $redis->ttl($foundKey);
                        if ($testTtl >= -1) {
                            $ttl = $testTtl > 0 ? $testTtl : null;
                    }
                } catch (\Exception $e) {
                    // Ignorer l'erreur
                }
            }
            
            return response()->json([
                'status' => 200,
                'message' => 'Cache récupéré avec succès',
                'data' => [
                    'key' => $decodedKey,
                    'actual_key' => $foundKey,
                    'value' => $this->formatValue($value),
                    'ttl' => $ttl > 0 ? $ttl : null,
                    'ttl_formatted' => $ttl > 0 ? now()->addSeconds($ttl)->diffForHumans() : 'Permanent',
                    'size' => strlen(serialize($value)),
                    'size_formatted' => $this->formatBytes(strlen(serialize($value))),
                    'cached_at' => $ttl > 0 ? now()->subSeconds($ttl)->toDateTimeString() : now()->toDateTimeString(),
                    'expires_at' => $ttl > 0 ? now()->addSeconds($ttl)->toDateTimeString() : null,
                ],
            ]);
        }
        
        // Si le cache n'est pas trouvé, vérifier une dernière fois si c'est un set
        if ($value === null && config('cache.default') === 'redis') {
            try {
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                
                // Essayer directement avec la clé telle quelle
                if ($redis->exists($decodedKey)) {
                    $type = $redis->type($decodedKey);
                    if ($type === 2) { // SET
                        $members = $redis->sMembers($decodedKey);
                        $cardinality = $redis->sCard($decodedKey);
                        
                        return response()->json([
                            'status' => 200,
                            'message' => 'Set Redis détecté',
                            'data' => [
                                'key' => $decodedKey,
                                'type' => 'set',
                                'type_label' => 'Set Redis',
                                'cardinality' => $cardinality,
                                'members' => $members,
                                'members_count' => count($members),
                            ],
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }
        
        // Si le cache n'est pas trouvé, chercher des clés similaires pour suggérer
        $similarKeys = $this->findSimilarKeys($decodedKey, $fullPrefix ?: $cachePrefix);
        
        // Vérifier si la clé existe vraiment dans Redis (pour diagnostic)
        $keyExists = false;
        $keyType = null;
        $diagnosticInfo = [];
        
        if (config('cache.default') === 'redis') {
            try {
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                
                // Essayer avec la clé telle quelle
                $type = $redis->type($decodedKey);
                if ($type > 0) {
                    $keyExists = true;
                    $keyType = $type;
                    $diagnosticInfo['found_with'] = 'exact_key';
                } else {
                    // Essayer avec différentes variations pour diagnostic
                    $testKeys = [
                        $fullPrefix . $decodedKey,
                        $cachePrefix . $decodedKey,
                    ];
                    
                    // Si la clé contient déjà le préfixe, essayer sans
                    if ($fullPrefix && str_starts_with($decodedKey, $fullPrefix)) {
                        $testKeys[] = substr($decodedKey, strlen($fullPrefix));
                    }
                    
                    foreach ($testKeys as $testKey) {
                        $testType = $redis->type($testKey);
                        if ($testType > 0) {
                            $keyExists = true;
                            $keyType = $testType;
                            $diagnosticInfo['found_with'] = $testKey;
                            break;
                        }
                    }
                }
                
                // Vérifier aussi avec keys() pour voir si une clé similaire existe
                $pattern = '*' . str_replace(['s360-database-', 's360-cache-'], '', $decodedKey);
                $similarRedisKeys = $redis->keys($pattern);
                if (!empty($similarRedisKeys)) {
                    $diagnosticInfo['similar_redis_keys'] = array_slice($similarRedisKeys, 0, 5);
                }
            } catch (\Exception $e) {
                $diagnosticInfo['error'] = $e->getMessage();
            }
        }
        
        $typeLabels = ['1' => 'STRING', '2' => 'SET', '3' => 'LIST', '4' => 'ZSET', '5' => 'HASH'];
        
        // Si la clé existe mais n'a pas pu être récupérée, essayer de la récupérer maintenant
        if ($keyExists && $keyType === 2) {
            try {
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                $actualKey = $diagnosticInfo['found_with'] ?? $decodedKey;
                $members = $redis->sMembers($actualKey);
                $cardinality = $redis->sCard($actualKey);
                
                return response()->json([
                    'status' => 200,
                    'message' => 'Set Redis détecté',
                    'data' => [
                        'key' => $decodedKey,
                        'actual_key' => $actualKey,
                        'type' => 'set',
                        'type_label' => 'Set Redis',
                        'cardinality' => $cardinality,
                        'members' => $members,
                        'members_count' => count($members),
                    ],
                ]);
            } catch (\Exception $e) {
                // Continuer avec l'erreur 404
            }
        }
        
        return response()->json([
            'status' => 404,
            'message' => 'Cache introuvable',
            'data' => [
                'requested_key' => $decodedKey,
                'cache_prefix' => $cachePrefix,
                'full_prefix' => $fullPrefix,
                'key_exists_in_redis' => $keyExists,
                'key_type' => $keyType ? ($typeLabels[(string)$keyType] ?? 'UNKNOWN') : null,
                'diagnostic' => $diagnosticInfo,
                'similar_keys' => $similarKeys,
                'hint' => $keyExists 
                    ? "La clé existe dans Redis (type: " . ($typeLabels[(string)$keyType] ?? 'UNKNOWN') . ") mais n'a pas pu être récupérée. Essayez GET /api/cache pour voir toutes les clés disponibles."
                    : ($similarKeys ? 'Clés similaires trouvées. Essayez d\'utiliser une de ces clés.' : 'La clé n\'existe pas dans Redis. Essayez GET /api/cache/debug/keys pour voir toutes les clés disponibles.'),
            ],
        ], 404);
    }
    
    /**
     * Debug: Afficher toutes les clés Redis (pour diagnostic)
     */
    public function debugKeys(): JsonResponse
    {
        $debug = [
            'cache_driver' => config('cache.default'),
            'cache_prefix' => config('cache.prefix', ''),
            'redis_connection' => config('cache.stores.redis.connection', 'cache'),
            'all_keys' => [],
            'keys_count' => 0,
        ];
        
        if (config('cache.default') === 'redis') {
            try {
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                $cachePrefix = config('cache.prefix', '');
                
                // Récupérer toutes les clés
                $allKeys = $redis->keys('*');
                $debug['keys_count'] = count($allKeys);
                
                // Afficher les 50 premières clés pour diagnostic
                $debug['all_keys'] = array_slice($allKeys, 0, 50);
                $debug['sample_keys'] = array_filter($allKeys, function($key) {
                    return !str_contains($key, 'queues:') 
                        && !str_contains($key, 'horizon:')
                        && !str_contains($key, 'spatie.permission.cache');
                });
                $debug['sample_keys'] = array_slice($debug['sample_keys'], 0, 20);
            } catch (\Exception $e) {
                $debug['error'] = $e->getMessage();
            }
        }
        
        return response()->json([
            'status' => 200,
            'message' => 'Clés Redis pour diagnostic',
            'data' => $debug,
        ]);
    }
    
    /**
     * Afficher les caches par tag
     */
    public function byTag(string $tag): JsonResponse
    {
        $cacheData = [];
        
        if (config('cache.default') === 'redis') {
            try {
                // Utiliser la connexion Redis configurée pour le cache
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                $cachePrefix = config('cache.prefix', '');
                
                // Le préfixe Redis peut être ajouté automatiquement
                // Format réel observé: s360-database-s360-cache-*
                $redisPrefix = config('database.redis.options.prefix', '');
                $fullPrefix = $redisPrefix . $cachePrefix;
                
                // Chercher toutes les clés (avec le préfixe complet)
                $allKeys = [];
                if ($fullPrefix) {
                    $pattern = $fullPrefix . '*';
                    $allKeys = $redis->keys($pattern);
                }
                
                // Si aucune clé trouvée avec le préfixe complet, essayer avec juste cache prefix
                if (empty($allKeys) && $cachePrefix) {
                    $pattern = $cachePrefix . '*';
                    $allKeys = $redis->keys($pattern);
                }
                
                // Si toujours vide, essayer toutes les clés
                if (empty($allKeys)) {
                    $allKeys = $redis->keys('*');
                }
                
                // Filtrer les clés qui contiennent le tag
                // Format Laravel Redis avec tags:
                // - Sets: s360-database-s360-cache-tag:users:entries
                // - Clés: s360-database-s360-cache-HASH:users:options:managers
                $keys = [];
                
                // Méthode 1: Chercher les clés dans le set du tag (Laravel tags natifs)
                try {
                    // Chercher le set du tag avec le préfixe complet
                    $tagSetKey = $fullPrefix . 'tag:' . $tag . ':entries';
                    
                    // Si pas trouvé, essayer avec juste cache prefix
                    if (!$redis->exists($tagSetKey) && $cachePrefix) {
                        $tagSetKey = $cachePrefix . 'tag:' . $tag . ':entries';
                    }
                    
                    // Vérifier si le set existe
                    if ($redis->exists($tagSetKey)) {
                        // Récupérer toutes les références de clés dans ce set
                        $keyRefs = $redis->sMembers($tagSetKey);
                        
                        foreach ($keyRefs as $keyRef) {
                            // keyRef est le nom de la clé sans préfixe ni hash
                            // Format réel: users:options:managers
                            // Chercher toutes les clés qui correspondent: PREFIX-HASH:keyRef
                            $patterns = [
                                $fullPrefix . '*:' . $keyRef,
                            ];
                            
                            if ($cachePrefix) {
                                $patterns[] = $cachePrefix . '*:' . $keyRef;
                            }
                            
                            foreach ($patterns as $pattern) {
                                $matchingKeys = $redis->keys($pattern);
                                foreach ($matchingKeys as $matchingKey) {
                                    if ($redis->exists($matchingKey) && !in_array($matchingKey, $keys)) {
                                        $keys[] = $matchingKey;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug('Erreur lors de la récupération du set de tag', [
                        'tag' => $tag,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Méthode 2: Filtrer par pattern direct (fallback)
                // Format réel: s360-database-s360-cache-HASH:users:options:managers
                if (empty($keys)) {
                    $keys = array_filter($allKeys, function($key) use ($tag, $fullPrefix, $cachePrefix) {
                        // Ignorer les clés de queue et autres systèmes
                        if (str_contains($key, 'queues:') || 
                            str_contains($key, 'horizon:') || 
                            str_contains($key, 'spatie.permission.cache') ||
                            str_contains($key, 'tag:')) { // Ignorer les sets de tags
                            return false;
                        }
                        
                        // Retirer le préfixe pour vérifier
                        $actualKey = $key;
                        if ($fullPrefix && str_starts_with($key, $fullPrefix)) {
                            $actualKey = substr($key, strlen($fullPrefix));
                        } elseif ($cachePrefix && str_starts_with($key, $cachePrefix)) {
                            $actualKey = substr($key, strlen($cachePrefix));
                        }
                        
                        // Vérifier si la clé contient le tag
                        // Format: HASH:users:options:managers -> chercher :users: ou users:
                        return str_contains($actualKey, ':' . $tag . ':') || str_contains($actualKey, $tag . ':');
                    });
                }
                
                // Éliminer les doublons
                $keys = array_unique($keys);
                
                foreach ($keys as $fullKey) {
                    // Retirer le préfixe pour obtenir la clé réelle
                    $actualKey = $fullKey;
                    if ($fullPrefix && str_starts_with($fullKey, $fullPrefix)) {
                        $actualKey = substr($fullKey, strlen($fullPrefix));
                    } elseif ($cachePrefix && str_starts_with($fullKey, $cachePrefix)) {
                        $actualKey = substr($fullKey, strlen($cachePrefix));
                    }
                    
                    // Format réel: HASH:users:options:managers ou HASH:audit-logs:index:hash
                    // Extraire la clé réelle (sans le hash)
                    $keyParts = explode(':', $actualKey, 2);
                    $realKey = count($keyParts) === 2 ? $keyParts[1] : $actualKey;
                    
                    // Récupérer la valeur - utiliser Cache::tags() directement
                    try {
                        $value = null;
                        $detectedTags = [$tag];
                        
                        // Méthode 1: Utiliser Cache::tags() avec la clé réelle
                        try {
                            $value = Cache::tags([$tag])->get($realKey);
                        } catch (\Exception $e) {
                            // Ignorer
                        }
                        
                        // Méthode 2: Utiliser CacheTagger
                        if ($value === null) {
                            try {
                                $value = \App\Support\CacheTagger::tags([$tag])->get($realKey);
                            } catch (\Exception $e) {
                                // Ignorer
                            }
                        }
                        
                        // Méthode 3: Récupérer directement depuis Redis
                        if ($value === null) {
                            try {
                                $rawValue = $redis->get($fullKey);
                                if ($rawValue) {
                                    $value = unserialize($rawValue);
                                }
                            } catch (\Exception $e) {
                                // Ignorer
                            }
                        }
                        
                        // Ne pas ajouter si la valeur est null
                        if ($value === null) {
                            continue;
                        }
                        
                        $ttl = $redis->ttl($fullKey);
                        
                        $cacheData[] = [
                            'key' => $fullKey,
                            'actual_key' => $actualKey,
                            'tags' => $detectedTags ?: null,
                            'value' => $this->formatValue($value),
                            'ttl' => $ttl > 0 ? $ttl : null,
                            'ttl_formatted' => $ttl > 0 ? now()->addSeconds($ttl)->diffForHumans() : 'Permanent',
                            'size' => strlen(serialize($value)),
                            'size_formatted' => $this->formatBytes(strlen(serialize($value))),
                        ];
                    } catch (\Exception $e) {
                        // Si erreur, ignorer cette clé et continuer
                        Log::debug('Erreur lors de la récupération du cache par tag', [
                            'key' => $fullKey,
                            'actual_key' => $actualKey,
                            'tag' => $tag,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur lors de la récupération du cache par tag', [
                    'tag' => $tag,
                    'error' => $e->getMessage(),
                ]);
                $cacheData = $this->getCacheFromDatabase($tag);
            }
        } else {
            $cacheData = $this->getCacheFromDatabase($tag);
        }
        
        // Détecter les tags disponibles depuis Redis
        $availableTags = [];
        if (config('cache.default') === 'redis') {
            try {
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                $redisPrefix = config('database.redis.options.prefix', '');
                $cachePrefix = config('cache.prefix', '');
                $fullPrefix = $redisPrefix . $cachePrefix;
                
                // Chercher tous les sets de tags
                $tagSetPattern = $fullPrefix . 'tag:*:entries';
                $tagSets = $redis->keys($tagSetPattern);
                
                foreach ($tagSets as $tagSet) {
                    // Extraire le nom du tag depuis: PREFIX-tag:TAG_NAME:entries
                    $parts = explode(':', $tagSet);
                    if (count($parts) >= 2) {
                        // Retirer le préfixe
                        $tagPart = $parts[count($parts) - 2]; // Avant "entries"
                        if ($tagPart && $tagPart !== 'entries') {
                            $availableTags[] = $tagPart;
                        }
                    }
                }
                
                // Si pas trouvé avec fullPrefix, essayer avec cachePrefix
                if (empty($availableTags) && $cachePrefix) {
                    $tagSetPattern = $cachePrefix . 'tag:*:entries';
                    $tagSets = $redis->keys($tagSetPattern);
                    foreach ($tagSets as $tagSet) {
                        $parts = explode(':', $tagSet);
                        if (count($parts) >= 2) {
                            $tagPart = $parts[count($parts) - 2];
                            if ($tagPart && $tagPart !== 'entries') {
                                $availableTags[] = $tagPart;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs
            }
        }
        
        // Si aucun tag détecté, utiliser la liste par défaut
        if (empty($availableTags)) {
            $availableTags = [
                'users', 'roles', 'permissions', 'audit-logs',
                'fdi_sg', 'fdi_articles', 'fdi_rech_comp', 'fdi_validation',
                'fcvr_sg', 'fcvr_article',
                'manifestes', 'manifeste_sg', 'manifeste_tt', 'manifeste_tc',
                'controles', 's360v02_users', 's360v02_uggroups',
                'bons-provisoires', 'declarations',
                'banque', 'banque-tvf', 'banque-sad', 'banque-tvf-comp-1', 'banque-tvf-comp-2'
            ];
        }
        
        $message = count($cacheData) > 0
            ? count($cacheData) . " cache(s) trouvé(s) pour le tag '{$tag}'"
            : "Aucun cache trouvé pour le tag '{$tag}'. Tags disponibles: " . implode(', ', array_unique($availableTags));
        
        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $cacheData,
            'meta' => [
                'tag' => $tag,
                'total' => count($cacheData),
                'tags_available' => array_unique($availableTags),
            ],
        ]);
    }
    
    /**
     * Statistiques du cache
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'cache_driver' => config('cache.default'),
            'total_keys' => 0,
            'total_size' => 0,
            'total_size_formatted' => '0 B',
            'by_tag' => [],
            'note' => 'Le cache est créé uniquement lors des requêtes GET. Faites des requêtes GET pour voir les statistiques.',
        ];
        
        if (config('cache.default') === 'redis') {
            try {
                // Utiliser la connexion Redis configurée pour le cache
                $redisConnection = config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($redisConnection);
                
                // Récupérer le préfixe de cache depuis la config
                $cachePrefix = config('cache.prefix', '');
                
                // Chercher toutes les clés - essayer avec et sans préfixe
                $keys = [];
                if ($cachePrefix) {
                    $pattern = $cachePrefix . '*';
                    $keys = $redis->keys($pattern);
                }
                
                // Si aucune clé trouvée avec le préfixe, essayer toutes les clés
                if (empty($keys)) {
                    $allKeys = $redis->keys('*');
                    // Filtrer pour ne garder que les clés de cache (pas queues, etc.)
                    $keys = array_filter($allKeys, function($key) {
                        return !str_contains($key, 'queues:') 
                            && !str_contains($key, 'horizon:')
                            && !str_contains($key, 'spatie.permission.cache');
                    });
                }
                
                // Filtrer les clés de queue
                $cacheKeys = array_filter($keys, fn($k) => !str_contains($k, 'queues:'));
                $stats['total_keys'] = count($cacheKeys);
                
                $allTags = [
                    'fdi_sg', 'fdi_articles', 'fdi_rech_comp', 'fdi_validation',
                    'fcvr_sg', 'fcvr_article',
                    'manifestes', 'manifeste_sg', 'manifeste_tt', 'manifeste_tc',
                    'users', 'admins', 'roles', 'permissions', 'controles',
                    's360v02_users', 's360v02_uggroups', 'audit-logs'
                ];
                
                foreach ($cacheKeys as $fullKey) {
                    // Retirer le préfixe
                    $actualKey = $fullKey;
                    if ($cachePrefix && str_starts_with($fullKey, $cachePrefix)) {
                        $actualKey = substr($fullKey, strlen($cachePrefix));
                    }
                    
                    // Essayer de récupérer la valeur
                    $value = null;
                    try {
                        $value = Cache::get($actualKey);
                        
                        // Si null, essayer avec tags (format: tag|tag:key)
                        if ($value === null && str_contains($actualKey, '|') && str_contains($actualKey, ':')) {
                            $parts = explode(':', $actualKey, 2);
                            if (count($parts) === 2) {
                                $tagPart = $parts[0];
                                $key = $parts[1];
                                if (str_contains($tagPart, '|')) {
                                    $tags = explode('|', $tagPart);
                                    try {
                                        $value = \App\Support\CacheTagger::tags($tags)->get($key);
                                    } catch (\Exception $e) {
                                        // Ignorer
                                    }
                                }
                            }
                        }
                        
                        // Si toujours null, essayer directement depuis Redis
                        if ($value === null) {
                            $rawValue = $redis->get($fullKey);
                            if ($rawValue) {
                                $value = unserialize($rawValue);
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignorer les erreurs
                        continue;
                    }
                    
                    if ($value !== null) {
                    $size = strlen(serialize($value));
                    $stats['total_size'] += $size;
                    
                    // Extraire le tag de la clé
                        foreach ($allTags as $tag) {
                            if (str_contains($actualKey, $tag)) {
                            $stats['by_tag'][$tag] = ($stats['by_tag'][$tag] ?? 0) + 1;
                            break;
                            }
                        }
                    }
                }
                
                $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
            } catch (\Exception $e) {
                $stats['error'] = 'Impossible de récupérer les statistiques: ' . $e->getMessage();
            }
        } else {
            // Database cache
            try {
            $cacheEntries = DB::table('cache')->get();
            $stats['total_keys'] = $cacheEntries->count();
            
            foreach ($cacheEntries as $entry) {
                $stats['total_size'] += strlen($entry->value ?? '');
            }
            
            $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
            } catch (\Exception $e) {
                $stats['error'] = 'Impossible de récupérer les statistiques: ' . $e->getMessage();
            }
        }
        
        return response()->json([
            'status' => 200,
            'message' => 'Statistiques du cache récupérées avec succès',
            'data' => $stats,
        ]);
    }
    
    /**
     * Effacer un cache spécifique
     */
    public function destroy(string $key): JsonResponse
    {
        $decodedKey = urldecode($key);
        $cachePrefix = config('cache.prefix', '');
        
        // Retirer le préfixe si présent dans la clé
        $actualKey = $decodedKey;
        if ($cachePrefix && str_starts_with($decodedKey, $cachePrefix)) {
            $actualKey = substr($decodedKey, strlen($cachePrefix));
        }
        
        // Essayer de supprimer avec différentes variations de la clé
        $keyVariations = [
            $decodedKey,  // Clé complète telle quelle
            $actualKey,   // Clé sans préfixe
        ];
        
        $deleted = false;
        $deletedKey = null;
        
        foreach ($keyVariations as $keyVariation) {
            if (Cache::forget($keyVariation)) {
                $deleted = true;
                $deletedKey = $keyVariation;
                break;
            }
        }
        
        if ($deleted) {
            return response()->json([
                'status' => 200,
                'message' => 'Cache supprimé avec succès',
                'data' => [
                    'requested_key' => $decodedKey,
                    'deleted_key' => $deletedKey,
                ],
            ]);
        }
        
        return response()->json([
            'status' => 404,
            'message' => 'Cache introuvable',
            'data' => [
                'requested_key' => $decodedKey,
                'cache_prefix' => $cachePrefix,
                'hint' => 'Assurez-vous que la clé existe et n\'est pas expirée.',
            ],
        ], 404);
    }
    
    /**
     * Effacer tous les caches d'un tag
     */
    public function flushTag(string $tag): JsonResponse
    {
        CacheTagger::tags([$tag])->flush();
        
        return response()->json([
            'status' => 200,
            'message' => "Tous les caches du tag '{$tag}' ont été supprimés",
            'data' => ['tag' => $tag],
        ]);
    }
    
    /**
     * Effacer tous les caches
     */
    public function flush(): JsonResponse
    {
        Cache::flush();
        
        return response()->json([
            'status' => 200,
            'message' => 'Tous les caches ont été supprimés',
            'data' => null,
        ]);
    }
    
    /**
     * Récupérer les caches depuis la base de données
     */
    private function getCacheFromDatabase(?string $tag = null, ?string $search = null): array
    {
        $cacheData = [];
        
        if (!Schema::hasTable('cache')) {
            return $cacheData;
        }
        
        $query = DB::table('cache');
        
        if ($tag) {
            $query->where('key', 'like', "%{$tag}%");
        }
        
        if ($search) {
            $query->where('key', 'like', "%{$search}%");
        }
        
        $entries = $query->get();
        
        foreach ($entries as $entry) {
            try {
                $rawValue = $entry->value ?? '';
                
                // Essayer de désérialiser les données
                $value = null;
                if (!empty($rawValue)) {
                    // Laravel stocke les données sérialisées, essayer de désérialiser
                    try {
                        // Utiliser @ pour supprimer les warnings
                        $testUnserialize = @unserialize($rawValue);
                        
                        // Vérifier si la désérialisation a réussi
                        if ($testUnserialize !== false || $rawValue === serialize(false)) {
                            $value = $testUnserialize;
                        } else {
                            // Si la désérialisation échoue, essayer JSON
                            $jsonValue = @json_decode($rawValue, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $value = $jsonValue;
                            } else {
                                // Si ni serialization ni JSON, garder la valeur brute (mais tronquer si trop longue)
                                $value = strlen($rawValue) > 500 ? substr($rawValue, 0, 500) . '... (valeur brute)' : $rawValue;
                            }
                        }
                    } catch (\Error $e) {
                        // Erreur de désérialisation, essayer JSON
                        try {
                            $jsonValue = @json_decode($rawValue, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $value = $jsonValue;
                            } else {
                                // Valeur brute
                                $value = strlen($rawValue) > 500 ? substr($rawValue, 0, 500) . '... (valeur brute)' : $rawValue;
                            }
                        } catch (\Exception $jsonException) {
                            // En dernier recours, garder une valeur brute tronquée
                            $value = strlen($rawValue) > 500 ? substr($rawValue, 0, 500) . '... (valeur brute, format non reconnu)' : $rawValue;
                        }
                    }
                }
                
                // Calculer l'expiration
                $expiration = null;
                if (isset($entry->expiration) && $entry->expiration) {
                    $expiration = max(0, $entry->expiration - time());
                }
                
                $cacheData[] = [
                    'key' => $entry->key,
                    'value' => $this->formatValue($value),
                    'ttl' => $expiration > 0 ? $expiration : null,
                    'ttl_formatted' => $expiration > 0 ? now()->addSeconds($expiration)->diffForHumans() : 'Permanent',
                    'size' => strlen($rawValue),
                    'size_formatted' => $this->formatBytes(strlen($rawValue)),
                ];
            } catch (\Exception $e) {
                // Si une erreur survient, ignorer cette entrée et continuer
                // Loguer l'erreur pour le débogage
                Log::warning('Erreur lors de la récupération du cache', [
                    'key' => $entry->key ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                continue;
            }
        }
        
        return $cacheData;
    }
    
    /**
     * Formater la valeur pour l'affichage
     */
    private function formatValue($value): mixed
    {
        // Limiter la taille pour l'affichage
        if (is_string($value) && strlen($value) > 500) {
            return substr($value, 0, 500) . '... (tronqué)';
        }
        
        if (is_array($value) && count($value) > 50) {
            return array_slice($value, 0, 50) + ['...' => 'tronqué'];
        }
        
        return $value;
    }
    
    /**
     * Formater les octets
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Obtenir les tags disponibles
     */
    private function getAvailableTags(): array
    {
        return [
            'fdi_sg',
            'fdi_articles',
            'fdi_rech_comp',
            'fdi_validation',
            'fcvr_sg',
            'fcvr_article',
            'manifestes',
            'manifeste_sg',
            'manifeste_tt',
            'manifeste_tc',
            'users',
            'admins',
            'roles',
            'permissions',
            'controles',
            's360v02_users',
            's360v02_uggroups',
            'audit-logs',
            'bons-provisoires',
            'declarations',
            'banque',
            'banque-tvf',
            'banque-sad',
            'banque-tvf-comp-1',
            'banque-tvf-comp-2',
        ];
    }
    
    /**
     * Trouver des clés similaires à la clé demandée
     */
    private function findSimilarKeys(string $requestedKey, string $cachePrefix): array
    {
        $similarKeys = [];
        $maxResults = 10;
        
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                
                // Extraire les parties de la clé demandée pour la recherche
                $searchParts = [];
                if (str_contains($requestedKey, ':')) {
                    $parts = explode(':', $requestedKey);
                    $searchParts = array_filter($parts);
                } else {
                    $searchParts[] = $requestedKey;
                }
                
                // Retirer le préfixe si présent
                $keyWithoutPrefix = $requestedKey;
                if ($cachePrefix && str_starts_with($requestedKey, $cachePrefix)) {
                    $keyWithoutPrefix = substr($requestedKey, strlen($cachePrefix));
                }
                
                // Rechercher des clés similaires
                $pattern = $cachePrefix ? $cachePrefix . '*fdi_sg*' : '*fdi_sg*';
                $allKeys = $redis->keys($pattern);
                
                foreach ($allKeys as $key) {
                    if (str_contains($key, 'queues:')) {
                        continue;
                    }
                    
                    // Vérifier si la clé contient des parties similaires
                    foreach ($searchParts as $part) {
                        if (str_contains($key, $part)) {
                            $similarKeys[] = $key;
                            if (count($similarKeys) >= $maxResults) {
                                break 2;
                            }
                        }
                    }
                }
            } else {
                // Pour database cache
                if (Schema::hasTable('cache')) {
                    $entries = DB::table('cache')
                        ->where('key', 'like', '%fdi_sg%')
                        ->limit($maxResults)
                        ->get();
                    
                    $similarKeys = $entries->pluck('key')->toArray();
                }
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs
        }
        
        return array_unique($similarKeys);
    }
}

