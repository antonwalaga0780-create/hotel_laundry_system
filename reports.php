<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once "config/db.php";

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$error = "";
$transactions = [];

$totalTransactions = 0;
$totalSent = 0;
$totalReturned = 0;
$totalDamaged = 0;
$totalOutstanding = 0;

/*
|--------------------------------------------------------------------------
| Check whether laundry_transactions table exists
|--------------------------------------------------------------------------
*/

$tableCheck = $conn->query("SHOW TABLES LIKE 'laundry_transactions'");

if ($tableCheck && $tableCheck->num_rows > 0) {

    /*
    |--------------------------------------------------------------------------
    | Build query safely
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            t.id,
            t.transaction_type,
            t.quantity,
            t.notes,
            t.transaction_date,
            i.item_name,
            i.item_type
        FROM laundry_transactions t
        LEFT JOIN laundry_items i
            ON t.laundry_item_id = i.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    if (!empty($from)) {
        $sql .= " AND DATE(t.transaction_date) >= ?";
        $params[] = $from;
        $types .= "s";
    }

    if (!empty($to)) {
        $sql .= " AND DATE(t.transaction_date) <= ?";
        $params[] = $to;
        $types .= "s";
    }

    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {

        $error = "The transaction report could not be loaded. "
               . "Please check the laundry_transactions table.";

    } else {

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {

            $error = "Could not load transaction records: "
                   . $stmt->error;

        } else {

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {

                $transactions[] = $row;

                $totalTransactions++;

                $type = strtoupper($row['transaction_type']);
                $quantity = (int)$row['quantity'];

                if ($type === "SENT") {
                    $totalSent += $quantity;
                }

                if ($type === "RETURNED") {
                    $totalReturned += $quantity;
                }

                if ($type === "DAMAGED") {
                    $totalDamaged += $quantity;
                }
            }

            $totalOutstanding =
                max(
                    0,
                    $totalSent -
                    $totalReturned -
                    $totalDamaged
                );
        }

        $stmt->close();
    }

} else {

    $error = "The laundry_transactions table does not exist yet. "
           . "The report page can still be used after the table is created.";
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Reports | LaundryTrack</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #fff5f8;
    color: #402631;
}

/* NAVIGATION */

.navbar {
    background: #ad1457;
    color: white;
    padding: 16px 5%;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-size: 22px;
    font-weight: bold;
}

.nav-buttons {
    display: flex;
    gap: 10px;
}

.nav-buttons a {
    text-decoration: none;
    color: white;
    background: #c2185b;
    padding: 9px 14px;
    border-radius: 8px;
}

/* MAIN */

.container {
    width: 95%;
    max-width: 1250px;
    margin: 30px auto;
}

.title {
    color: #ad1457;
    margin-bottom: 5px;
}

.subtitle {
    color: #777;
}

/* FILTER */

.filter-box {
    background: white;
    padding: 20px;
    border-radius: 15px;

    margin: 25px 0;

    box-shadow: 0 7px 25px rgba(173, 20, 87, 0.08);
}

.filter-form {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 6px;
}

.filter-group input {
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 8px;
}

/* BUTTONS */

.btn {
    border: none;
    padding: 11px 16px;
    border-radius: 8px;

    text-decoration: none;

    font-weight: bold;
    cursor: pointer;
}

.btn-primary {
    background: #d81b60;
    color: white;
}

.btn-secondary {
    background: #fce4ec;
    color: #ad1457;
}

.btn-print {
    background: #8e24aa;
    color: white;
}

/* ERROR */

.error {
    background: #ffebee;
    color: #c62828;

    padding: 15px;

    border-left: 5px solid #c62828;

    border-radius: 8px;

    margin-bottom: 20px;
}

/* SUMMARY CARDS */

.cards {
    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.card {
    background: white;

    padding: 20px;

    border-radius: 15px;

    border: 1px solid #f6d8e3;

    box-shadow:
        0 7px 25px rgba(173, 20, 87, 0.08);
}

.card-icon {
    font-size: 24px;
}

.card-number {
    display: block;

    color: #ad1457;

    font-size: 27px;

    font-weight: bold;

    margin-top: 8px;
}

.card-label {
    color: #777;

    font-size: 13px;

    margin-top: 5px;
}

/* TABLE */

.table-box {
    background: white;

    padding: 20px;

    border-radius: 15px;

    overflow-x: auto;

    box-shadow:
        0 7px 25px rgba(173, 20, 87, 0.08);
}

.table-box h2 {
    color: #ad1457;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}

th,
td {
    padding: 12px;

    text-align: left;

    border-bottom: 1px solid #f1dbe4;
}

th {
    background: #fce4ec;

    color: #ad1457;
}

.transaction-sent {
    color: #d81b60;

    font-weight: bold;
}

.transaction-returned {
    color: #8e24aa;

    font-weight: bold;
}

.transaction-damaged {
    color: #c2185b;

    font-weight: bold;
}

.no-records {
    text-align: center;

    padding: 30px;

    color: #777;
}

/* RESPONSIVE */

@media(max-width: 1000px) {

    .cards {
        grid-template-columns:
            repeat(3, 1fr);
    }

}

@media(max-width: 650px) {

    .cards {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media(max-width: 450px) {

    .cards {
        grid-template-columns: 1fr;
    }

    .navbar {
        flex-direction: column;

        gap: 12px;
    }

}

/* PRINT */

@media print {

    .navbar,
    .filter-box,
    .btn-print {
        display: none;
    }

    body {
        background: white;
    }

    .container {
        width: 100%;
        margin: 0;
    }

}

</style>

</head>

<body>

<nav class="navbar">

    <div class="logo">
        🧺 LaundryTrack
    </div>

    <div class="nav-buttons">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="logout.php">
            Logout
        </a>

    </div>

</nav>


<div class="container">

    <h1 class="title">
        📊 Laundry Reports
    </h1>

    <p class="subtitle">
        View and filter laundry transaction records.
    </p>


    <!-- FILTER -->

    <div class="filter-box">

        <form
            method="GET"
            class="filter-form"
        >

            <div class="filter-group">

                <label>
                    From Date
                </label>

                <input
                    type="date"
                    name="from"
                    value="<?= htmlspecialchars($from) ?>"
                >

            </div>


            <div class="filter-group">

                <label>
                    To Date
                </label>

                <input
                    type="date"
                    name="to"
                    value="<?= htmlspecialchars($to) ?>"
                >

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                🔍 Apply Filter
            </button>


            <a
                href="reports.php"
                class="btn btn-secondary"
            >
                Clear
            </a>


            <button
                type="button"
                onclick="window.print()"
                class="btn btn-print"
            >
                🖨️ Print
            </button>

        </form>

    </div>


    <?php if (!empty($error)): ?>

        <div class="error">

            ⚠️

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- SUMMARY -->

    <div class="cards">

        <div class="card">

            <div class="card-icon">
                🧾
            </div>

            <span class="card-number">
                <?= $totalTransactions ?>
            </span>

            <span class="card-label">
                Transactions
            </span>

        </div>


        <div class="card">

            <div class="card-icon">
                📤
            </div>

            <span class="card-number">
                <?= $totalSent ?>
            </span>

            <span class="card-label">
                Items Sent
            </span>

        </div>


        <div class="card">

            <div class="card-icon">
                📥
            </div>

            <span class="card-number">
                <?= $totalReturned ?>
            </span>

            <span class="card-label">
                Items Returned
            </span>

        </div>


        <div class="card">

            <div class="card-icon">
                ⚠️
            </div>

            <span class="card-number">
                <?= $totalDamaged ?>
            </span>

            <span class="card-label">
                Items Damaged
            </span>

        </div>


        <div class="card">

            <div class="card-icon">
                🔴
            </div>

            <span class="card-number">
                <?= $totalOutstanding ?>
            </span>

            <span class="card-label">
                Outstanding
            </span>

        </div>

    </div>


    <!-- TRANSACTIONS -->

    <div class="table-box">

        <h2>
            Transaction History
        </h2>

        <br>

        <table>

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Item</th>

                    <th>Type</th>

                    <th>Transaction</th>

                    <th>Quantity</th>

                    <th>Notes</th>

                    <th>Date</th>

                </tr>

            </thead>


            <tbody>

            <?php if (!empty($transactions)): ?>

                <?php foreach ($transactions as $row): ?>

                    <?php

                    $transactionType =
                        strtoupper(
                            $row['transaction_type']
                        );

                    $class = "";

                    if ($transactionType === "SENT") {
                        $class =
                            "transaction-sent";
                    }

                    elseif (
                        $transactionType === "RETURNED"
                    ) {
                        $class =
                            "transaction-returned";
                    }

                    elseif (
                        $transactionType === "DAMAGED"
                    ) {
                        $class =
                            "transaction-damaged";
                    }

                    ?>

                    <tr>

                        <td>
                            <?= (int)$row['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $row['item_name'] ?? 'Unknown'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $row['item_type'] ?? '-'
                            ) ?>
                        </td>

                        <td class="<?= $class ?>">

                            <?= htmlspecialchars(
                                $transactionType
                            ) ?>

                        </td>

                        <td>

                            <?= (int)$row['quantity'] ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $row['notes'] ?? ''
                            ) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $row['transaction_date']
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="7"
                        class="no-records"
                    >

                        📭 No transaction records found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


</body>

</html>