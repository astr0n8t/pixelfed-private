<?php

use App\Services\ConfigCacheService;
use App\Util\Database\DatabaseDriver;
use Illuminate\Support\Facades\URL;

if (! function_exists('config_cache')) {
    function config_cache($key)
    {
        return ConfigCacheService::get($key);
    }
}

if (! function_exists('db_is_mysql_maria')) {
    /**
     * True when the active (or given) connection uses MySQL or MariaDB.
     *
     * Use instead of config('database.default') === 'mysql', which misses
     * MariaDB (Laravel exposes it as a distinct 'mariadb' driver).
     */
    function db_is_mysql_maria(?string $connection = null): bool
    {
        return DatabaseDriver::isMysqlMaria($connection);
    }
}

if (! function_exists('db_is_mysql')) {
    /**
     * True when the connection uses MySQL specifically (not MariaDB).
     *
     * Only use when behaviour must differ between MySQL and MariaDB;
     * otherwise prefer db_is_mysql_maria().
     */
    function db_is_mysql(?string $connection = null): bool
    {
        return DatabaseDriver::isMysql($connection);
    }
}

if (! function_exists('db_is_mariadb')) {
    /**
     * True when the connection uses MariaDB specifically (not MySQL).
     *
     * Only use when behaviour must differ between MySQL and MariaDB;
     * otherwise prefer db_is_mysql_maria().
     */
    function db_is_mariadb(?string $connection = null): bool
    {
        return DatabaseDriver::isMariadb($connection);
    }
}

if (! function_exists('db_is_pgsql')) {
    /**
     * True when the active (or given) connection uses PostgreSQL.
     */
    function db_is_pgsql(?string $connection = null): bool
    {
        return DatabaseDriver::isPgsql($connection);
    }
}

if (! function_exists('db_is_sqlite')) {
    /**
     * True when the active (or given) connection uses SQLite (usually tests).
     */
    function db_is_sqlite(?string $connection = null): bool
    {
        return DatabaseDriver::isSqlite($connection);
    }
}

if (! function_exists('signed_storage_url')) {
    /**
     * Temporary signed URL for a file on the local disk.
     *
     * Files are served by the `storage.file` route instead of the public
     * storage symlink, so media can only be fetched through links the app
     * hands out to signed-in users. Links expire after 60 minutes, which is
     * why cached payloads containing them must not outlive that either.
     */
    function signed_storage_url(string $path, array $query = []): string
    {
        return URL::temporarySignedRoute(
            'storage.file',
            now()->addMinutes(60),
            ['file' => $path, 'user_id' => auth()->id()] + $query
        );
    }
}
