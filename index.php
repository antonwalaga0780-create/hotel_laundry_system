<?php
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

require_once "config/db.php";

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
$row = $result ? $result->fetch_assoc() : ["total" => 0];

if ((int)$row["total"] === 0) {
    header("Location: signup.php");
} else {
    header("Location: login.php");
}
exit;
?>