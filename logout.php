<?php
require __DIR__ . '/_layout.php';
$_SESSION = [];
session_destroy();
header('Location: login.php');
