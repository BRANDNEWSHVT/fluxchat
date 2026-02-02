<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->sqliteOptimize();
    }

    /**
     * @return void
     */
    protected function sqliteOptimize(): void
    {
        $connections = [
            'sqlite',
            'sqlite_cache',
            'sqlite_jobs',
            'sqlite_events',
        ];

        foreach ($connections as $connection) {

            $db = DB::connection($connection);

            if ($db->getDriverName() !== 'sqlite') {
                return;
            }

            // check if file exists
            $database = $db->getDatabaseName();
            $path = database_path("database/{$database}");
            if (!file_exists($path)) {
                return;
            }

            $db->unprepared('PRAGMA synchronous = NORMAL;');
            $db->unprepared('PRAGMA foreign_keys = ON;');
            $db->unprepared('PRAGMA temp_store = MEMORY;');
            $db->unprepared('PRAGMA busy_timeout = 5000;');
            $db->unprepared('PRAGMA mmap_size = 2147483648;');
            $db->unprepared('PRAGMA cache_size = -20000;');
            $db->unprepared('PRAGMA incremental_vacuum;');
        }
    }
}
