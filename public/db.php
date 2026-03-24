<?php
// ============================================================
//  db.php  ·  Conexión MySQL – Karen & Sergio
//  Pon tus datos de Hostinger aquí
// ============================================================
 
define('DB_HOST', 'localhost');          // siempre localhost en Hostinger shared
define('DB_NAME', 'u548874252_boda');   // nombre de tu BD en hPanel
define('DB_USER', 'u548874252_admin');  // usuario de la BD
define('DB_PASS', '^iimGco!Z0I');  // contraseña de la BD
define('DB_CHARSET', 'utf8mb4');
 
function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
 
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("SET time_zone = '-06:00'");  // Date Time MX

    return $pdo;
}