<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: dashboard.php");
        exit();
    }
}

function getUserInfo() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'nama' => $_SESSION['nama_lengkap'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}
?>
