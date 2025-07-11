<?php
function getDbConnection() {
    $host = 'localhost';
    $dbname = 'mengbao';
    $user = 'mengbao';
    $pass = 'shXmfJzEMCWjdDRT'; // ⚠️ 修改为你自己的密码

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        die("数据库连接失败：" . $e->getMessage());
    }
}
