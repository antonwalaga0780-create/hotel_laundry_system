<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/db.php';
$id=(int)($_GET['id']??0);
if($id>0){$stmt=$conn->prepare("DELETE FROM laundry_items WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();$stmt->close();}
header('Location: view_items.php?deleted=1');exit;
?>