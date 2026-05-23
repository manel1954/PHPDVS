<?php
// auth.php — incluir al inicio de cada página protegida
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}
