<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/db.php';

$from=$_GET['from']??'';$to=$_GET['to']??'';
$where='';$params=[];$types='';
if($from!==''){$where.=" AND DATE(t.transaction_date)>=?";$params[]=$from;$types.='s';}
if($to!==''){$where.=" AND DATE(t.transaction_date)<=?";$params[]=$to;$types.='s';}
$sql="SELECT t.id,i.item_name,i.item_type,t.transaction_type,t.quantity,t.notes,t.transaction_date FROM laundry_transactions t JOIN laundry_items i ON i.id=t.laundry_item_id WHERE 1=1 $where ORDER BY t.transaction_date DESC,t.id DESC";
$stmt=$conn->prepare($sql);if($params)$stmt->bind_param($types,...$params);$stmt->execute();$result=$stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="laundry_report_'.date('Y-m-d_H-i-s').'.csv"');
$out=fopen('php://output','w');fputcsv($out,['ID','Item','Type','Transaction','Quantity','Notes','Date']);
while($r=$result->fetch_assoc())fputcsv($out,[$r['id'],$r['item_name'],$r['item_type'],$r['transaction_type'],$r['quantity'],$r['notes'],$r['transaction_date']]);
fclose($out);exit;
?>