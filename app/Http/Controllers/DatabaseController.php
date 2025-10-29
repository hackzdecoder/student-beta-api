<?php

namespace App\Http\Controllers;

use App\Services\DatabaseManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatabaseController extends Controller
{
    protected $dbManager;

    public function __construct(DatabaseManagerService $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * List all available databases
     */
    public function index()
    {
        $databases = $this->dbManager->getAvailableDatabases();
        
        return response()->json([
            'success' => true,
            'databases' => $databases,
            'total' => count($databases)
        ]);
    }

    /**
     * Refresh database list
     */
    public function refresh()
    {
        $this->dbManager->refreshDatabases();
        
        return response()->json([
            'success' => true,
            'message' => 'Database list refreshed successfully'
        ]);
    }

    /**
     * Query a specific database
     */
    public function queryDatabase($databaseName)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            // Test connection first
            if (!$this->dbManager->testConnection($connectionName)) {
                return response()->json([
                    'success' => false,
                    'message' => "Unable to connect to database '{$databaseName}'"
                ], 500);
            }
            
            // Get all tables from the database
            $tables = DB::connection($connectionName)
                ->select("SHOW TABLES");
                
            $tableNames = array_map(function($table) {
                return array_values((array)$table)[0];
            }, $tables);
            
            return response()->json([
                'success' => true,
                'database' => $databaseName,
                'tables' => $tableNames,
                'table_count' => count($tableNames)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to query database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get data from a specific table
     */
    public function getTableData($databaseName, $tableName, Request $request)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            // Validate table exists
            $tableExists = DB::connection($connectionName)
                ->select("SHOW TABLES LIKE ?", [$tableName]);
                
            if (empty($tableExists)) {
                return response()->json([
                    'success' => false,
                    'message' => "Table '{$tableName}' not found in database '{$databaseName}'"
                ], 404);
            }
            
            // Get paginated data
            $perPage = $request->get('per_page', 50);
            $page = $request->get('page', 1);
            
            $data = DB::connection($connectionName)
                ->table($tableName)
                ->paginate($perPage, ['*'], 'page', $page);
                
            // Get table structure
            $columns = DB::connection($connectionName)
                ->select("DESCRIBE `{$tableName}`");
                
            return response()->json([
                'success' => true,
                'database' => $databaseName,
                'table' => $tableName,
                'columns' => $columns,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get table data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats($databaseName)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            // Get database statistics
            $stats = DB::connection($connectionName)
                ->select("
                    SELECT 
                        COUNT(*) as table_count,
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as total_size_mb,
                        ROUND(SUM(data_length) / 1024 / 1024, 2) as data_size_mb,
                        ROUND(SUM(index_length) / 1024 / 1024, 2) as index_size_mb,
                        ROUND(SUM(data_free) / 1024 / 1024, 2) as free_size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ", [$databaseName]);
                
            // Get table details
            $tables = DB::connection($connectionName)
                ->select("
                    SELECT 
                        table_name,
                        table_rows,
                        ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb,
                        ROUND(data_length / 1024 / 1024, 2) as data_size_mb,
                        ROUND(index_length / 1024 / 1024, 2) as index_size_mb,
                        table_comment
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                    ORDER BY (data_length + index_length) DESC
                ", [$databaseName]);
            
            return response()->json([
                'success' => true,
                'database' => $databaseName,
                'stats' => $stats[0] ?? null,
                'tables' => $tables
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get database statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute custom query on a database
     */
    public function executeQuery($databaseName, Request $request)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        $query = $request->get('query');
        
        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required'
            ], 400);
        }

        // Basic security check - prevent destructive operations
        $forbiddenKeywords = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE', 'INSERT', 'UPDATE', 'GRANT', 'REVOKE'];
        $upperQuery = strtoupper($query);
        
        foreach ($forbiddenKeywords as $keyword) {
            if (str_contains($upperQuery, $keyword)) {
                return response()->json([
                    'success' => false,
                    'message' => "Query type '{$keyword}' is not allowed for security reasons"
                ], 403);
            }
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            $result = DB::connection($connectionName)->select($query);
            
            return response()->json([
                'success' => true,
                'database' => $databaseName,
                'query' => $query,
                'result' => $result,
                'row_count' => count($result)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Query execution failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance data from specific database
     */
    public function getAttendanceData($databaseName, Request $request)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            $query = DB::connection($connectionName)->table('attendance');
            
            // Apply filters
            if ($request->has('startDate')) {
                $query->where('date', '>=', $request->startDate);
            }
            
            if ($request->has('endDate')) {
                $query->where('date', '<=', $request->endDate);
            }
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('user_id', 'like', "%{$search}%")
                      ->orWhere('kiosk_terminal_in', 'like', "%{$search}%")
                      ->orWhere('kiosk_terminal_out', 'like', "%{$search}%");
                });
            }
            
            // Filter by specific user_id if provided
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }
            
            $data = $query->orderBy('date', 'desc')
                         ->orderBy('created_at', 'desc')
                         ->get();
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark attendance record as read
     */
    public function markAttendanceAsRead($databaseName, $recordId)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            $updated = DB::connection($connectionName)
                ->table('attendance')
                ->where('id', $recordId)
                ->update([
                    'status' => 'read',
                    'updated_at' => now()
                ]);
            
            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Record marked as read'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark record as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all attendance records as read
     */
    public function markAllAttendanceAsRead($databaseName)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            $updated = DB::connection($connectionName)
                ->table('attendance')
                ->where('status', 'unread')
                ->update([
                    'status' => 'read',
                    'updated_at' => now()
                ]);
            
            return response()->json([
                'success' => true,
                'message' => "{$updated} records marked as read"
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark records as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages data from specific database
     */
    public function getMessagesData($databaseName, Request $request)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            $query = DB::connection($connectionName)->table('messages');
            
            // Apply filters
            if ($request->has('startDate')) {
                $query->where('date', '>=', $request->startDate);
            }
            
            if ($request->has('endDate')) {
                $query->where('date', '<=', $request->endDate);
            }
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('user_id', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
                });
            }
            
            // Filter by specific user_id if provided
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }
            
            $data = $query->orderBy('date', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->get();
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark message record as read
     */
    public function markMessageAsRead($databaseName, $recordId)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            $updated = DB::connection($connectionName)
                ->table('messages')
                ->where('id', $recordId)
                ->update([
                    'status' => 'read',
                    'updated_at' => now()
                ]);
            
            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message marked as read'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark message as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all message records as read
     */
    public function markAllMessagesAsRead($databaseName)
    {
        // Validate database exists
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json([
                'success' => false,
                'message' => "Database '{$databaseName}' not found"
            ], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            
            $updated = DB::connection($connectionName)
                ->table('messages')
                ->where('status', 'unread')
                ->update([
                    'status' => 'read',
                    'updated_at' => now()
                ]);
            
            return response()->json([
                'success' => true,
                'message' => "{$updated} messages marked as read"
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read: ' . $e->getMessage()
            ], 500);
        }
    }
}