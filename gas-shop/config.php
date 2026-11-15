<?php
// Change these 3 values to match your MySQL setup
$DB_HOST='localhost'; $DB_USER='root'; $DB_PASS='';
date_default_timezone_set('Africa/Kampala');
$pdo=new PDO("mysql:host=$DB_HOST;dbname=gas_shop;charset=utf8mb4",$DB_USER,$DB_PASS,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("SET time_zone='+03:00'");
session_start();
$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('admin','staff') NOT NULL DEFAULT 'staff')");
if(!$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn())
  $pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES ('admin',?,'admin')")->execute([password_hash('admin123',PASSWORD_DEFAULT)]);
