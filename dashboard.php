<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/db.php';
function val($c,$q){$r=$c->query($q);return $r?(int)$r->fetch_assoc()['v']:0;}
$total=val($conn,"SELECT COUNT(*) v FROM laundry_items");
$registered=val($conn,"SELECT COALESCE(SUM(quantity),0) v FROM laundry_items");
$sent=val($conn,"SELECT COALESCE(SUM(sent_quantity),0) v FROM laundry_items");
$returned=val($conn,"SELECT COALESCE(SUM(returned_quantity),0) v FROM laundry_items");
$damaged=val($conn,"SELECT COALESCE(SUM(damaged_quantity),0) v FROM laundry_items");
$outstanding=max(0,$sent-$returned-$damaged);
$tx=0;$x=$conn->query("SHOW TABLES LIKE 'laundry_transactions'");if($x&&$x->num_rows)$tx=val($conn,"SELECT COUNT(*) v FROM laundry_transactions");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | LaundryTrack</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Arial;background:#fff5f8;color:#402631}.nav{background:#ad1457;color:#fff;padding:17px 5%;display:flex;justify-content:space-between;align-items:center}.logo{font-size:22px;font-weight:bold}.nav a{color:#fff;text-decoration:none;background:#c2185b;padding:9px 14px;border-radius:8px}.container{width:min(1200px,92%);margin:35px auto}.welcome h1{color:#ad1457;margin-bottom:8px}.welcome p{color:#777}.cards{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-top:28px}.card{background:#fff;padding:19px;border-radius:15px;box-shadow:0 7px 25px #ad14570d;border:1px solid #f8d8e4}.card h3{margin-top:9px;font-size:26px;color:#ad1457}.card p{color:#777;margin-top:5px;font-size:13px}.actions{margin-top:30px;background:#fff;border-radius:15px;padding:25px;box-shadow:0 7px 25px #ad14570d}.actions h2{color:#ad1457;margin-bottom:18px}.buttons{display:flex;gap:10px;flex-wrap:wrap}.btn{background:#d81b60;color:#fff;text-decoration:none;padding:12px 16px;border-radius:9px;font-weight:bold}.btn.secondary{background:#fce4ec;color:#ad1457}.notice{margin-top:20px;padding:15px;background:#fff0f6;border-left:4px solid #d81b60;border-radius:8px;line-height:1.6}.notice a{color:#ad1457;font-weight:bold}
@media(max-width:1000px){.cards{grid-template-columns:repeat(3,1fr)}}@media(max-width:600px){.cards{grid-template-columns:repeat(2,1fr)}}@media(max-width:420px){.cards{grid-template-columns:1fr}}
</style></head><body><nav class="nav"><div class="logo">🧺 LaundryTrack</div><a href="logout.php">Logout</a></nav>
<main class="container"><div class="welcome"><h1>Welcome, <?=htmlspecialchars($_SESSION['full_name'])?> 👋</h1><p>Hotel Laundry Management Dashboard</p></div>
<section class="cards"><div class="card">📦<h3><?=$total?></h3><p>Laundry Records</p></div><div class="card">🧺<h3><?=$registered?></h3><p>Registered Items</p></div><div class="card">📤<h3><?=$sent?></h3><p>Items Sent</p></div><div class="card">📥<h3><?=$returned?></h3><p>Items Returned</p></div><div class="card">⚠️<h3><?=$outstanding?></h3><p>Outstanding</p></div><div class="card">🧾<h3><?=$tx?></h3><p>Transactions</p></div></section>
<section class="actions"><h2>System Modules</h2><div class="buttons"><a class="btn" href="add_item.php">＋ Add Laundry Item</a><a class="btn secondary" href="view_items.php">View Records</a><a class="btn secondary" href="transactions.php">Transaction History</a><a class="btn secondary" href="missing_items.php">⚠️ Outstanding</a><a class="btn secondary" href="reports.php">📊 Reports</a></div>
<div class="notice"><strong>Stage 5:</strong> The system now tracks <b>sent, returned, damaged and outstanding</b> quantities. Reports can be filtered by date and exported to CSV.</div></section></main></body></html>