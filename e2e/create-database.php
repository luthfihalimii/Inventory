<?php

// Creates the E2E database if it does not exist. Used by e2e/global-setup.ts.

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$user = getenv('DB_USERNAME') ?: 'ent_inventory';
$password = getenv('DB_PASSWORD') ?: 'ent_inventory';
$database = getenv('E2E_DB') ?: 'ent_inventory_e2e';

$pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

try {
    $pdo->exec('CREATE DATABASE "'.$database.'"');
    fwrite(STDOUT, "Created database {$database}\n");
} catch (PDOException $exception) {
    if ($exception->getCode() !== '42P04') {
        throw $exception;
    }

    fwrite(STDOUT, "Database {$database} already exists\n");
}
