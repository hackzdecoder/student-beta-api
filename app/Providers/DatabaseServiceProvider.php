<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Services\DatabaseManagerService;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DatabaseManagerService::class, function ($app) {
            return new DatabaseManagerService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // First, ensure the main school_manager_connect connection exists
        $this->ensureMainConnection();

        // Then register dynamic databases
        $this->registerDynamicDatabases();
    }

    /**
     * Ensure the main connection is configured
     */
    protected function ensureMainConnection(): void
    {
        if (!Config::has('database.connections.school_manager_connect')) {
            config(['database.connections.school_manager_connect' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'database' => env('DB_DATABASE', 'sm_db_users_main'),
                'charset' => env('DB_CHARSET', 'utf8mb4'),
                'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ]]);
        }
    }

    /**
     * Register dynamic database connections
     */
    protected function registerDynamicDatabases(): void
    {
        try {
            // Register dynamic database connections
            $databases = Cache::remember('dynamic_databases', 300, function () {
                return $this->getAvailableDatabases();
            });

            foreach ($databases as $database) {
                $this->registerDatabaseConnection($database);
            }

            Log::info('Dynamic databases registered successfully: ' . count($databases) . ' databases found');

        } catch (\Exception $e) {
            Log::error('Failed to register dynamic databases: ' . $e->getMessage());
        }
    }

    /**
     * Get list of available databases from MySQL server
     */
    protected function getAvailableDatabases(): array
    {
        $pattern = env('DB_PATTERN', 'sm_db_%');
        
        try {
            // Try school_manager_connect first
            $databases = DB::connection('school_manager_connect')
                ->select("
                    SELECT SCHEMA_NAME as database_name 
                    FROM INFORMATION_SCHEMA.SCHEMATA 
                    WHERE SCHEMA_NAME LIKE ? 
                    AND SCHEMA_NAME NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
                    ORDER BY SCHEMA_NAME
                ", [$pattern]);

            return array_map(function ($db) {
                return $db->database_name;
            }, $databases);

        } catch (\Exception $e) {
            Log::warning('school_manager_connect failed, falling back to default connection: ' . $e->getMessage());
            
            // Fallback to default connection
            $databases = DB::connection()
                ->select("
                    SELECT SCHEMA_NAME as database_name 
                    FROM INFORMATION_SCHEMA.SCHEMATA 
                    WHERE SCHEMA_NAME LIKE ? 
                    AND SCHEMA_NAME NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
                    ORDER BY SCHEMA_NAME
                ", [$pattern]);

            return array_map(function ($db) {
                return $db->database_name;
            }, $databases);
        }
    }

    /**
     * Register a dynamic database connection
     */
    protected function registerDatabaseConnection(string $databaseName): void
    {
        $connectionName = $this->generateConnectionName($databaseName);

        // Skip if connection already exists
        if (Config::has("database.connections.{$connectionName}")) {
            return;
        }

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
        ]]);

        Log::debug("Registered dynamic database connection: {$connectionName}");
    }

    /**
     * Generate a connection name from database name
     */
    protected function generateConnectionName(string $databaseName): string
    {
        return str_replace(['-', ' '], '_', $databaseName);
    }
}