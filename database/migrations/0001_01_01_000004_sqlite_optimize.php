<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * @return void
     */
    public function up(): void
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

            $db->unprepared('PRAGMA journal_mode = WAL;');
            $db->unprepared('PRAGMA page_size = 32768;');
            $db->unprepared('PRAGMA auto_vacuum = INCREMENTAL;');
        }
    }
};
