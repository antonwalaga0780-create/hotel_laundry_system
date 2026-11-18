<?php
session_start();

require_once "config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

/*
|--------------------------------------------------------------------------
| PROCESS TRANSACTION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $item_id = intval($_POST['item_id'] ?? 0);
    $transaction_type = trim($_POST['transaction_type'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($item_id <= 0) {

        $message = "Please select a laundry item.";
        $message_type = "error";

    } elseif (!in_array($transaction_type, ['Sent', 'Returned', 'Damaged'])) {

        $message = "Invalid transaction type.";
        $message_type = "error";

    } elseif ($quantity <= 0) {

        $message = "Quantity must be greater than zero.";
        $message_type = "error";

    } else {

        /*
        Get current laundry item
        */

        $sql = "SELECT
                    id,
                    item_name,
                    quantity,
                    sent_quantity,
                    returned_quantity,
                    damaged_quantity
                FROM laundry_items
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $message = "Database error: " . $conn->error;
            $message_type = "error";

        } else {

            $stmt->bind_param("i", $item_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {

                $message = "Laundry item not found.";
                $message_type = "error";

            } else {

                $item = $result->fetch_assoc();

                $total = (int)$item['quantity'];
                $sent = (int)$item['sent_quantity'];
                $returned = (int)$item['returned_quantity'];
                $damaged = (int)$item['damaged_quantity'];

                $valid = true;

                /*
                ----------------------------------------------------------
                Validate transaction
                ----------------------------------------------------------
                */

                if ($transaction_type === "Sent") {

                    /*
                    Available quantity =
                    Total - Sent + Returned + Damaged

                    For sending, we don't allow more than available stock.
                    */

                    $available = $total - $sent + $returned;

                    if ($quantity > $available) {

                        $message = "Cannot send $quantity items. Only $available items are currently available.";
                        $message_type = "error";
                        $valid = false;

                    }

                } elseif ($transaction_type === "Returned") {

                    $outstanding = $sent - $returned - $damaged;

                    if ($quantity > $outstanding) {

                        $message = "Cannot return $quantity items. Only $outstanding items are currently outstanding.";
                        $message_type = "error";
                        $valid = false;

                    }

                } elseif ($transaction_type === "Damaged") {

                    $outstanding = $sent - $returned - $damaged;

                    if ($quantity > $outstanding) {

                        $message = "Cannot record $quantity damaged items. Only $outstanding items are currently outstanding.";
                        $message_type = "error";
                        $valid = false;

                    }
                }

                /*
                ----------------------------------------------------------
                Save transaction
                ----------------------------------------------------------
                */

                if ($valid) {

                    $conn->begin_transaction();

                    try {

                        /*
                        Generate transaction code
                        */

                        $transaction_code =
                            "TRX-" .
                            date("YmdHis") .
                            "-" .
                            rand(100, 999);

                        /*
                        Insert transaction
                        */

                        $transaction_sql = "INSERT INTO laundry_transactions
                            (
                                transaction_code,
                                transaction_type,
                                transaction_date,
                                notes
                            )
                            VALUES (?, ?, NOW(), ?)";

                        $transaction_stmt =
                            $conn->prepare($transaction_sql);

                        if (!$transaction_stmt) {
                            throw new Exception($conn->error);
                        }

                        $transaction_stmt->bind_param(
                            "sss",
                            $transaction_code,
                            $transaction_type,
                            $notes
                        );

                        if (!$transaction_stmt->execute()) {
                            throw new Exception(
                                $transaction_stmt->error
                            );
                        }

                        $transaction_id =
                            $transaction_stmt->insert_id;

                        $transaction_stmt->close();

                        /*
                        Insert transaction item
                        */

                        $item_sql = "INSERT INTO transaction_items
                            (
                                transaction_id,
                                item_id,
                                quantity
                            )
                            VALUES (?, ?, ?)";

                        $item_stmt =
                            $conn->prepare($item_sql);

                        if (!$item_stmt) {
                            throw new Exception($conn->error);
                        }

                        $item_stmt->bind_param(
                            "iii",
                            $transaction_id,
                            $item_id,
                            $quantity
                        );

                        if (!$item_stmt->execute()) {
                            throw new Exception(
                                $item_stmt->error
                            );
                        }

                        $item_stmt->close();

                        /*
                        --------------------------------------------------
                        Update laundry item
                        --------------------------------------------------
                        */

                        $new_sent = $sent;
                        $new_returned = $returned;
                        $new_damaged = $damaged;

                        if ($transaction_type === "Sent") {
                            $new_sent += $quantity;
                        }

                        if ($transaction_type === "Returned") {
                            $new_returned += $quantity;
                        }

                        if ($transaction_type === "Damaged") {
                            $new_damaged += $quantity;
                        }

                        /*
                        Calculate outstanding
                        */

                        $outstanding =
                            $new_sent
                            - $new_returned
                            - $new_damaged;

                        /*
                        Determine status
                        */

                        if ($outstanding > 0) {
                            $new_status = "At Laundry";
                        } else {
                            $new_status = "Available";
                        }

                        /*
                        Update item
                        */

                        $update_sql = "UPDATE laundry_items
                            SET
                                sent_quantity = ?,
                                returned_quantity = ?,
                                damaged_quantity = ?,
                                status = ?
                            WHERE id = ?";

                        $update_stmt =
                            $conn->prepare($update_sql);

                        if (!$update_stmt) {
                            throw new Exception($conn->error);
                        }

                        $update_stmt->bind_param(
                            "iiisi",
                            $new_sent,
                            $new_returned,
                            $new_damaged,
                            $new_status,
                            $item_id
                        );

                        if (!$update_stmt->execute()) {
                            throw new Exception(
                                $update_stmt->error
                            );
                        }

                        $update_stmt->close();

                        /*
                        Everything successful
                        */

                        $conn->commit();

                        $message =
                            "Transaction recorded successfully! Transaction Code: "
                            . $transaction_code;

                        $message_type = "success";

                    } catch (Exception $e) {

                        $conn->rollback();

                        $message =
                            "Transaction failed: "
                            . $e->getMessage();

                        $message_type = "error";
                    }
                }
            }

            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET LAUNDRY ITEMS
|--------------------------------------------------------------------------
*/

$items = [];

$sql = "SELECT
            id,
            item_name,
            item_type,
            quantity,
            sent_quantity,
            returned_quantity,
            damaged_quantity
        FROM laundry_items
        ORDER BY item_name ASC";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| GET TRANSACTION HISTORY
|--------------------------------------------------------------------------
*/

$history = [];

$sql = "SELECT
            lt.id,
            lt.transaction_code,
            lt.transaction_type,
            lt.transaction_date,
            lt.notes,
            ti.quantity,
            li.item_name,
            li.item_type
        FROM laundry_transactions lt
        INNER JOIN transaction_items ti
            ON lt.id = ti.transaction_id
        INNER JOIN laundry_items li
            ON ti.item_id = li.id
        ORDER BY lt.transaction_date DESC
        LIMIT 100";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Transactions | LaundryTrack</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #fff4f8;
    color: #3d2630;
}

/* HEADER */

.topbar {

    background: #b70955;

    color: white;

    padding: 18px 6%;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.logo {

    font-size: 22px;

    font-weight: bold;
}

.dashboard {

    background: #d91b68;

    color: white;

    text-decoration: none;

    padding: 11px 20px;

    border-radius: 8px;
}

.dashboard:hover {

    background: #960743;
}

/* PAGE */

.container {

    max-width: 1200px;

    margin: 40px auto;

    padding: 0 20px;
}

h1 {

    color: #b70955;

    font-size: 32px;
}

.subtitle {

    color: #6d5360;

    margin-bottom: 30px;
}

/* CARDS */

.card {

    background: white;

    padding: 30px;

    border-radius: 18px;

    margin-bottom: 30px;

    box-shadow:
        0 8px 30px rgba(183, 9, 85, 0.10);
}

.card h2 {

    color: #b70955;

    margin-top: 0;
}

/* FORM */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;
}

.form-group {

    margin-bottom: 5px;
}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;
}

input,
select,
textarea {

    width: 100%;

    padding: 13px;

    border: 1px solid #e4bdcd;

    border-radius: 9px;

    font-size: 15px;

    outline: none;
}

input:focus,
select:focus,
textarea:focus {

    border-color: #d91b68;

    box-shadow:
        0 0 0 3px rgba(217,27,104,.10);
}

textarea {

    min-height: 100px;

    resize: vertical;
}

.full {

    grid-column: 1 / -1;
}

.btn {

    border: none;

    background: #e01661;

    color: white;

    padding: 14px 25px;

    border-radius: 9px;

    font-weight: bold;

    cursor: pointer;

    font-size: 15px;
}

.btn:hover {

    background: #b70955;
}

/* MESSAGES */

.message {

    padding: 15px;

    border-radius: 9px;

    margin-bottom: 25px;
}

.success {

    background: #e8f8ee;

    border: 1px solid #b7e4c7;

    color: #176b3a;
}

.error {

    background: #ffe8ed;

    border: 1px solid #f2b6c5;

    color: #a50035;
}

/* TABLE */

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 800px;
}

th {

    background: #f8dce7;

    color: #a8004b;

    padding: 14px;

    text-align: left;
}

td {

    padding: 14px;

    border-bottom: 1px solid #f0d9e2;
}

tr:hover {

    background: #fff8fa;
}

/* BADGES */

.badge {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.sent {

    background: #fbe0e9;

    color: #a8004b;
}

.returned {

    background: #e6f6ed;

    color: #176b3a;
}

.damaged {

    background: #ffe7d6;

    color: #a14b00;
}

/* MOBILE */

@media(max-width:700px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .full {

        grid-column: auto;
    }

    .card {

        padding: 20px;
    }

    h1 {

        font-size: 27px;
    }

}

</style>

</head>

<body>

<header class="topbar">

    <div class="logo">
        🧺 LaundryTrack
    </div>

    <a href="dashboard.php"
       class="dashboard">

        Dashboard

    </a>

</header>


<div class="container">

    <h1>🧾 Laundry Transactions</h1>

    <p class="subtitle">

        Record laundry sent out, returned and damaged items.

    </p>


    <?php if ($message !== ""): ?>

        <div class="message <?php echo $message_type; ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <!-- TRANSACTION FORM -->

    <div class="card">

        <h2>Record New Transaction</h2>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Laundry Item
                    </label>

                    <select
                        name="item_id"
                        id="item_id"
                        required
                    >

                        <option value="">
                            Select laundry item
                        </option>

                        <?php foreach ($items as $item): ?>

                            <option
                                value="<?php echo $item['id']; ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $item['item_name']
                                );
                                ?>

                                -
                                <?php
                                echo htmlspecialchars(
                                    $item['item_type']
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Transaction Type
                    </label>

                    <select
                        name="transaction_type"
                        required
                    >

                        <option value="">
                            Select transaction
                        </option>

                        <option value="Sent">
                            📤 Sent to Laundry
                        </option>

                        <option value="Returned">
                            📥 Returned
                        </option>

                        <option value="Damaged">
                            ⚠️ Damaged
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Quantity
                    </label>

                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        required
                        placeholder="e.g. 20"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        placeholder="Optional transaction notes"
                    ></textarea>

                </div>


                <div class="form-group full">

                    <button
                        type="submit"
                        class="btn"
                    >

                        Save Transaction

                    </button>

                </div>

            </div>

        </form>

    </div>


    <!-- HISTORY -->

    <div class="card">

        <h2>Transaction History</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Code</th>

                        <th>Item</th>

                        <th>Type</th>

                        <th>Quantity</th>

                        <th>Date</th>

                        <th>Notes</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($history) > 0): ?>

                    <?php foreach ($history as $row): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $row['transaction_code']
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $row['item_name']
                                );
                                ?>

                                <br>

                                <small>
                                    <?php
                                    echo htmlspecialchars(
                                        $row['item_type']
                                    );
                                    ?>
                                </small>

                            </td>

                            <td>

                                <?php

                                $class = strtolower(
                                    $row['transaction_type']
                                );

                                ?>

                                <span
                                    class="badge <?php echo $class; ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $row['transaction_type']
                                    );
                                    ?>

                                </span>

                            </td>

                            <td>

                                <?php
                                echo (int)$row['quantity'];
                                ?>

                            </td>

                            <td>

                                <?php
                                echo date(
                                    "d M Y, H:i",
                                    strtotime(
                                        $row['transaction_date']
                                    )
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $row['notes'] ?? ''
                                );
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6"
                            style="text-align:center;">

                            No transactions recorded yet.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Transaction form JavaScript
|--------------------------------------------------------------------------
*/

const form = document.querySelector("form");

form.addEventListener("submit", function(event) {

    const quantity =
        parseInt(
            document.querySelector(
                "input[name='quantity']"
            ).value
        );

    if (quantity <= 0) {

        alert(
            "Quantity must be greater than zero."
        );

        event.preventDefault();
    }

});

</script>

</body>

</html>