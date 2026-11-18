<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/db.php';
$items=$conn->query("SELECT id,item_name,item_type,quantity,sent_quantity,returned_quantity,status FROM laundry_items WHERE sent_quantity > returned_quantity ORDER BY (sent_quantity-returned_quantity) DESC,item_name ASC");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Missing Items | LaundryTrack</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial;background:#fff5f8;color:#402631}.nav{background:#ad1457;color:#fff;padding:16px 5%;display:flex;justify-content:space-between}.nav a{color:#fff;text-decoration:none;background:#c2185b;padding:9px 14px;border-radius:8px}.wrap{width:94%;max-width:1100px;margin:30px auto}.title{color:#ad1457}.alert{background:#fff0f6;border-left:4px solid #1bd834;padding:15px;border-radius:8px;margin:18px 0}.box{background:#fff;padding:20px;border-radius:15px;overflow:auto;box-shadow:0 7px 25px #ad14570d}table{width:100%;border-collapse:collapse;min-width:750px}th,td{padding:13px;text-align:left;border-bottom:1px solid #f1dbe4}th{background:#fce4ec;color:#ad1457}.num{font-weight:bold;color:#c62828}
</style></head><body><nav class="nav"><strong>🧺 LaundryTrack</strong><a href="dashboard.php">Dashboard</a></nav>
<div class="wrap"><h1 class="title">⚠️ Missing / Outstanding Items</h1><p>These are items sent to laundry that have not yet been fully returned.</p>
<div class="alert"><strong>How it works:</strong> Missing is calculated as <b>Items Sent − Items Returned</b>.</div>
<div class="box"><table><tr><th>Item</th><th>Type</th><th>Total</th><th>Sent</th><th>Returned</th><th>Outstanding</th><th>Action</th></tr>
<?php if($items&&$items->num_rows):while($r=$items->fetch_assoc()):$m=(int)$r['sent_quantity']-(int)$r['returned_quantity'];?>
<tr><td><?=htmlspecialchars($r['item_name'])?></td><td><?=htmlspecialchars($r['item_type'])?></td><td><?=$r['quantity']?></td><td><?=$r['sent_quantity']?></td><td><?=$r['returned_quantity']?></td><td class="num"><?=$m?></td><td><a href="return_item.php?id=<?=$r['id']?>" style="background:#8e24aa;color:#fff;padding:8px 11px;border-radius:7px;text-decoration:none">Record Return</a></td></tr>
<?php endwhile;else:?><tr><td colspan="7">🎉 No outstanding laundry items.</td></tr><?php endif;?></table></div></div></body></html>