<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class DatabaseManagerService
{
    /**
     * Get all available databases
     */
    public function getAvailableDatabases(): array
    {
        try {
            $pattern = env('DB_PATTERN', 'sm_db_%');
            
            // Use the default connection first to query available databases
            $databases = DB::connection($this->getDefaultConnection())
                ->select("
                    SELECT SCHEMA_NAME as database_name 
                    FROM INFORMATION_SCHEMA.SCHEMATA 
                    WHERE SCHEMA_NAME LIKE ? 
                    AND SCHEMA_NAME NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
                    ORDER BY SCHEMA_NAME
                ", [$pattern]);

            return array_map(function ($db) {
                return [
                    'name' => $db->database_name,
                    'connection' => $this->generateConnectionName($db->database_name)
                ];
            }, $databases);

        } catch (\Exception $e) {
            Log::error('Failed to get available databases: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get the default database connection name
     */
    protected function getDefaultConnection(): string
    {
        // Try school_manager_connect first, fall back to default
        $connections = Config::get('database.connections', []);
        
        if (array_key_exists('school_manager_connect', $connections)) {
            return 'school_manager_connect';
        }
        
        return Config::get('database.default', 'mysql');
    }

    /**
     * Refresh database connections cache
     */
    public function refreshDatabases(): void
    {
        Cache::forget('dynamic_databases');
        
        $databases = $this->getAvailableDatabases();
        
        foreach ($databases as $database) {
            $this->registerConnection($database['name']);
        }
    }

    /**
     * Register a new database connection dynamically
     */
    public function registerConnection(string $databaseName): void
    {
        $connectionName = $this->generateConnectionName($databaseName);

        config(["database.connections.{$connectionName}" => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'database' => $databaseName,
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            // Removed PDO options to avoid the import issue
        ]]);
    }

    /**
     * Check if a database exists
     */
    public function databaseExists(string $databaseName): bool
    {
        try {
            $result = DB::connection($this->getDefaultConnection())
                ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            
            return !empty($result);
        } catch (\Exception $e) {
            Log::error("Failed to check if database exists: {$databaseName} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a connection is configured
     */
    public function connectionExists(string $connectionName): bool
    {
        return array_key_exists($connectionName, Config::get('database.connections', []));
    }

    /**
     * Generate connection name from database name
     */
    protected function generateConnectionName(string $databaseName): string
    {
        return str_replace(['-', ' '], '_', $databaseName);
    }

    /**
     * Get connection for a specific database
     */
    public function getConnectionForDatabase(string $databaseName): string
    {
        return $this->generateConnectionName($databaseName);
    }

    /**
     * Get all registered connections
     */
    public function getRegisteredConnections(): array
    {
        return array_keys(Config::get('database.connections', []));
    }

    /**
     * Test database connection
     */
    public function testConnection(string $connectionName): bool
    {
        try {
            DB::connection($connectionName)->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::error("Database connection test failed for: {$connectionName} - " . $e->getMessage());
            return false;
        }
    }
}