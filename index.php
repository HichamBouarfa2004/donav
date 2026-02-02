<?php
session_start();


require_once 'config/database.php';
require_once 'router.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Handle routing
$router = new Router();
$router->handleRequest();
?>