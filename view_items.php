<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/db.php';
$search=trim($_GET['search']??'');
if($search!==''){
 $stmt=$conn->prepare("SELECT * FROM laundry_items WHERE item_name LIKE ? OR item_type LIKE ? ORDER BY id DESC");
 $like='%'.$search.'%'; $stmt->bind_param('ss',$like,$like); $stmt->execute(); $items=$stmt->get_result();
}else $items=$conn->query("SELECT * FROM laundry_items ORDER BY id DESC");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Laundry Records | LaundryTrack</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial;background:#fff5f8;color:#402631}.nav{background:#ad1457;color:#fff;padding:16px 5%;display:flex;justify-content:space-between}.nav a{color:#fff;text-decoration:none;background:#c2185b;padding:9px 14px;border-radius:8px}.wrap{width:96%;max-width:1350px;margin:30px auto}.top{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}.title{color:#ad1457}.search{display:flex;gap:8px}.search input{padding:11px;border:1px solid #ddd;border-radius:8px}.search button{padding:11px 15px;background:#d81b60;color:#fff;border:0;border-radius:8px}.box{background:#fff;border-radius:15px;padding:20px;overflow:auto;box-shadow:0 7px 25px #ad14570d}table{width:100%;border-collapse:collapse;min-width:1150px}th,td{padding:11px;text-align:left;border-bottom:1px solid #f2dbe4}th{background:#fce4ec;color:#ad1457}.num{font-weight:bold}.danger{color:#c62828}.ok{color:#2e7d32}.badge{padding:5px 9px;border-radius:20px;background:#fce4ec;color:#ad1457;font-size:12px;font-weight:bold}.actions a{display:inline-block;margin:2px;padding:7px 8px;border-radius:7px;text-decoration:none;background:#d81b60;color:#fff;font-size:11px}.actions .return{background:#8e24aa}.actions .damage{background:#c2185b}.actions .edit{background:#6a1b9a}.actions .delete{background:#c62828}
</style></head><body><nav class="nav"><strong>🧺 LaundryTrack</strong><a href="dashboard.php">Dashboard</a></nav>
<div class="wrap"><div class="top"><div><h1 class="title">Laundry Records</h1><p>Complete item status, including returned, damaged and outstanding quantities.</p></div>
<form class="search"><input name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search item/type"><button>Search</button></form></div>
<div class="box"><table><tr><th>ID</th><th>Item</th><th>Type</th><th>Total</th><th>Sent</th><th>Returned</th><th>Damaged</th><th>Outstanding</th><th>Status</th><th>Actions</th></tr>
<?php if($items&&$items->num_rows):while($r=$items->fetch_assoc()):
 $damaged=(int)($r['damaged_quantity']??0); $out=max(0,(int)$r['sent_quantity']-(int)$r['returned_quantity']-$damaged); ?>
<tr><td><?=$r['id']?></td><td><?=htmlspecialchars($r['item_name'])?></td><td><?=htmlspecialchars($r['item_type'])?></td><td class="num"><?=$r['quantity']?></td><td><?=$r['sent_quantity']?></td><td><?=$r['returned_quantity']?></td><td><?=$damaged?></td><td class="<?=$out?'danger':'ok'?>"><?=$out?></td><td><span class="badge"><?=htmlspecialchars($r['status'])?></span></td>
<td class="actions"><a href="send_item.php?id=<?=$r['id']?>">Send</a><a class="return" href="return_item.php?id=<?=$r['id']?>">Return</a><a class="damage" href="damaged_item.php?id=<?=$r['id']?>">Damage</a><a class="edit" href="edit_item.php?id=<?=$r['id']?>">Edit</a><a class="delete" href="delete_item.php?id=<?=$r['id']?>" onclick="return confirm('Delete this item and all its transaction history?')">Delete</a></td></tr>
<?php endwhile;else:?><tr><td colspan="10">No laundry records found.</td></tr><?php endif;?></table></div></div></body></html>