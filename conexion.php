<?php
$host = getenv('SMOKEJEANS_DB_HOST') ?: 'localhost';
$db = getenv('SMOKEJEANS_DB_NAME') ?: 'viernes';
$user = getenv('SMOKEJEANS_DB_USER') ?: 'root';
$pass = getenv('SMOKEJEANS_DB_PASS') ?: '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Set up the Data Source Name
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Set parameters for safe execution and error handling

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     error_log($e->getMessage());
     http_response_code(503);
     exit('El servicio no está disponible en este momento.');
}

?>