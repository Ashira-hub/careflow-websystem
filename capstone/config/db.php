<?php
// Ensure PHP uses Malaysia time for any date/time operations
date_default_timezone_set('Asia/Kuala_Lumpur');
// PostgreSQL connection via PDO
// Edit these values to match your local setup.
$PG_HOST = getenv('PGHOST') ?: 'gondola.proxy.rlwy.net';
$PG_PORT = getenv('PGPORT') ?: '27436';
$PG_DB   = getenv('PGDATABASE') ?: 'railway';
$PG_USER = getenv('PGUSER') ?: 'postgres';
$PG_PASS = getenv('PGPASSWORD') ?: 'WkzkMhBNHYDiSkYpAHbWfCMJzINdKidg';

function get_pdo(): PDO {
  global $PG_HOST, $PG_PORT, $PG_DB, $PG_USER, $PG_PASS;
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  $dsn = "pgsql:host={$PG_HOST};port={$PG_PORT};dbname={$PG_DB}";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];
  $pdo = new PDO($dsn, $PG_USER, $PG_PASS, $options);
  // Ensure this connection/session uses Malaysia time zone for now(), timestamptz, etc.
  try { $pdo->exec("SET TIME ZONE 'Asia/Kuala_Lumpur'"); } catch (Throwable $_) {}
  return $pdo;
}
