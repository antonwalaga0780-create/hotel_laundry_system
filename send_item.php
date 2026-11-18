<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM laundry_items WHERE id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$item = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$item) die("Laundry item not found.");

$error=""; $message="";
$available=max(0,(int)$item['quantity']-(int)$item['sent_quantity']+(int)$item['returned_quantity']);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $qty=(int)($_POST['sent_quantity']??0); $notes=trim($_POST['notes']??'');
    if($qty<1) $error="Enter a quantity of at least 1.";
    elseif($qty>$available) $error="You cannot send more than the available quantity ($available).";
    else {
        $newSent=(int)$item['sent_quantity']+$qty;
        $u=$conn->prepare("UPDATE laundry_items SET sent_quantity=?, status='Sent to Laundry' WHERE id=?");
        $u->bind_param("ii",$newSent,$id);
        if($u->execute()) {
            $log=$conn->prepare("INSERT INTO laundry_transactions (laundry_item_id,transaction_type,quantity,notes) VALUES (?, 'SENT', ?, ?)");
            $log->bind_param("iis",$id,$qty,$notes);
            if($log->execute()) {
                $message="Successfully recorded $qty item(s) sent to laundry.";
                $item['sent_quantity']=$newSent;
                $available=max(0,(int)$item['quantity']-$newSent+(int)$item['returned_quantity']);
            } else $error="Item updated, but transaction history could not be saved: ".$log->error;
            $log->close();
        } else $error="Could not update the item: ".$u->error;
        $u->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Send Items | LaundryTrack</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#fff5f8;color:#402631}
.nav{background:#ad1457;color:#fff;padding:16px 5%;display:flex;justify-content:space-between}
.nav a{color:#fff;text-decoration:none;background:#c2185b;padding:9px 14px;border-radius:8px}
.wrap{width:min(700px,92%);margin:35px auto}.box{background:#fff;padding:30px;border-radius:18px;box-shadow:0 10px 30px #ad145714}
.back{color:#ad1457;text-decoration:none;font-weight:bold}.title{color:#ad1457}
.info{background:#fff0f6;border-left:4px solid #ec407a;padding:15px;border-radius:8px;line-height:1.8;margin:18px 0}
label{display:block;font-weight:bold;margin:15px 0 7px}input,textarea{width:100%;padding:13px;border:1px solid #ddd;border-radius:9px;font-size:15px}
textarea{min-height:90px}button{margin-top:22px;background:#d81b60;color:#fff;border:0;padding:13px 20px;border-radius:9px;font-weight:bold;font-size:15px;cursor:pointer}
.alert{padding:13px;border-radius:9px;margin:16px 0}.ok{background:#e8f5e9;color:#2e7d32}.err{background:#ffebee;color:#c62828}
</style></head><body>
<nav class="nav"><strong>🧺 LaundryTrack</strong><a href="dashboard.php">Dashboard</a></nav>
<div class="wrap"><div class="box"><a class="back" href="view_items.php">← Laundry Records</a>
<h1 class="title">Send Items to Laundry</h1><h3><?=htmlspecialchars($item['item_name'])?></h3>
<div class="info">Registered quantity: <b><?=$item['quantity']?></b><br>Sent so far: <b><?=$item['sent_quantity']?></b><br>Returned so far: <b><?=$item['returned_quantity']?></b><br>Available to send: <b><?=$available?></b></div>
<?php if($message):?><div class="alert ok"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="alert err"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="POST" onsubmit="return checkForm()"><input type="hidden" name="id" value="<?=$id?>">
<label>Quantity to Send</label><input id="qty" type="number" name="sent_quantity" min="1" max="<?=$available?>" required>
<label>Notes</label><textarea name="notes" placeholder="e.g. Sent to external laundry for washing"></textarea>
<button>📤 Record Items Sent</button></form></div></div>
<script>function checkForm(){const q=Number(document.getElementById('qty').value),max=<?=$available?>;if(q<1||q>max){alert('Quantity must be between 1 and '+max+'.');return false}return true}</script>
</body></html>