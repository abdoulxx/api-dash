<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasPublicUlid
{
    /**
     * Cache for column existence checks to avoid repeated schema queries.
     */
    protected static array $ulidColumnCache = [];

    /**
     * Boot the trait.
     */
    protected static function bootHasPublicUlid(): void
    {
        static::creating(function ($model) {
            // Generate ULID only if column exists and value is empty
            if (static::hasUlidColumn($model) && empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });

        static::saving(function ($model) {
            // Remove ulid from attributes if column doesn't exist
            if (!static::hasUlidColumn($model) && isset($model->attributes['ulid'])) {
                unset($model->attributes['ulid']);
            }
        });
    }

    /**
     * Check if the ulid column exists in the table.
     */
    protected static function hasUlidColumn($model): bool
    {
        $table = $model->getTable();
        
        if (!isset(static::$ulidColumnCache[$table])) {
            static::$ulidColumnCache[$table] = Schema::hasColumn($table, 'ulid');
        }
        
        return static::$ulidColumnCache[$table];
    }

    /**
     * Use the ULID as the default route key if column exists, otherwise use primary key.
     */
    public function getRouteKeyName(): string
    {
        // Check if ulid column exists in the table
        if (static::hasUlidColumn($this)) {
            return 'ulid';
        }
        
        return $this->getKeyName();
    }

    /**
     * Allow lookups by either ULID (preferred) or numeric primary key.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // If field is explicitly set, use it
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $table = $this->getTable();
        
        // Check if value is a ULID
        if (Str::isUlid($value)) {
            // Clear cache to ensure fresh check
            unset(static::$ulidColumnCache[$table]);
            
            // Check if ulid column exists in the table
            if (Schema::hasColumn($table, 'ulid')) {
                // Try to find by ULID directly
                $model = $this->newQuery()->where('ulid', $value)->first();
                
                if ($model) {
                    return $model;
                }
                
                // Model not found with this ULID - throw clear exception
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())
                    ->setModel($this->getMorphClass(), $value);
            }
        }

        // If value is numeric, try to find by primary key
        if (is_numeric($value)) {
            return parent::resolveRouteBinding($value, $field);
        }

        // If value is ULID format but column doesn't exist, try numeric ID as fallback
        // (in case someone is using ULID format but column wasn't migrated yet)
        if (Str::isUlid($value)) {
            // Don't try numeric lookup for ULID - it will fail
            // Instead, provide a clear error message
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "No query results for model [{$this->getMorphClass()}] {$value}. ULID column exists but no record found with this ULID."
            );
        }

        // Default behavior - try parent's resolveRouteBinding
        return parent::resolveRouteBinding($value, $field);
    }
}

