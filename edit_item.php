<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once "config/db.php";

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| Get Item ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
}

/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/

if ($id <= 0) {
    die("
        <div style='font-family:Arial;padding:40px;text-align:center'>
            <h2 style='color:#c2185b'>Invalid Laundry Item</h2>
            <p>No valid item ID was provided.</p>
            <a href='view_items.php'
               style='color:#fff;background:#c2185b;padding:10px 18px;
                      text-decoration:none;border-radius:8px'>
                Back to Laundry Records
            </a>
        </div>
    ");
}

/*
|--------------------------------------------------------------------------
| Get Existing Item
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        item_name,
        item_type,
        quantity,
        sent_quantity,
        returned_quantity,
        damaged_quantity,
        status
    FROM laundry_items
    WHERE id = ?
");

if ($stmt === false) {
    die("Database error while loading item: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    die("Could not load laundry item.");
}

$result = $stmt->get_result();

$item = $result->fetch_assoc();

$stmt->close();

if (!$item) {
    die("
        <div style='font-family:Arial;padding:40px;text-align:center'>
            <h2 style='color:#c2185b'>Laundry Item Not Found</h2>
            <p>The selected laundry item does not exist.</p>
            <a href='view_items.php'
               style='color:#fff;background:#c2185b;padding:10px 18px;
                      text-decoration:none;border-radius:8px'>
                Back to Laundry Records
            </a>
        </div>
    ");
}

/*
|--------------------------------------------------------------------------
| Update Item
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $item_name = trim($_POST["item_name"] ?? "");
    $item_type = trim($_POST["item_type"] ?? "");
    $quantity  = (int)($_POST["quantity"] ?? 0);

    $sent_quantity     = (int)$item["sent_quantity"];
    $returned_quantity = (int)$item["returned_quantity"];
    $damaged_quantity  = (int)$item["damaged_quantity"];

    /*
    | The total quantity cannot be smaller than items
    | already sent, returned or damaged.
    */

    $minimum_quantity =
        max(
            $sent_quantity,
            $returned_quantity + $damaged_quantity
        );

    if ($item_name === "") {

        $error = "Please enter the laundry item name.";

    } elseif ($item_type === "") {

        $error = "Please select the item type.";

    } elseif ($quantity <= 0) {

        $error = "Quantity must be greater than zero.";

    } elseif ($quantity < $minimum_quantity) {

        $error =
            "Quantity cannot be less than "
            . $minimum_quantity
            . " because some items have already been processed.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Determine Status
        |--------------------------------------------------------------------------
        */

        $outstanding =
            max(
                0,
                $sent_quantity
                - $returned_quantity
                - $damaged_quantity
            );

        if ($outstanding > 0) {

            $status = "At Laundry";

        } elseif ($damaged_quantity > 0) {

            $status = "Damaged";

        } elseif ($sent_quantity > 0) {

            $status = "Returned";

        } else {

            $status = "Available";
        }

        /*
        |--------------------------------------------------------------------------
        | Update Database
        |--------------------------------------------------------------------------
        */

        $update = $conn->prepare("
            UPDATE laundry_items
            SET
                item_name = ?,
                item_type = ?,
                quantity = ?,
                status = ?
            WHERE id = ?
        ");

        if ($update === false) {

            $error =
                "Could not prepare the update: "
                . $conn->error;

        } else {

            $update->bind_param(
                "ssisi",
                $item_name,
                $item_type,
                $quantity,
                $status,
                $id
            );

            if ($update->execute()) {

                $success =
                    "Laundry item updated successfully.";

                /*
                | Update values shown on the form
                */

                $item["item_name"] = $item_name;
                $item["item_type"] = $item_type;
                $item["quantity"] = $quantity;
                $item["status"] = $status;

            } else {

                $error =
                    "Could not update the item: "
                    . $update->error;
            }

            $update->close();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Edit Laundry Item | LaundryTrack</title>

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

/* NAVBAR */

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

.navbar a {
    color: white;
    text-decoration: none;

    background: #c2185b;

    padding: 9px 15px;

    border-radius: 8px;
}

/* CONTAINER */

.container {
    width: 92%;
    max-width: 700px;

    margin: 40px auto;
}

.card {
    background: white;

    padding: 30px;

    border-radius: 18px;

    box-shadow:
        0 10px 30px rgba(173, 20, 87, 0.10);
}

.back {
    color: #ad1457;
    text-decoration: none;
    font-weight: bold;
}

h1 {
    color: #ad1457;
    margin-top: 20px;
}

/* INFORMATION */

.info {
    background: #fff0f6;

    border-left: 5px solid #d81b60;

    padding: 15px;

    border-radius: 8px;

    margin: 20px 0;

    line-height: 1.8;
}

/* FORM */

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;

    font-weight: bold;

    margin-bottom: 7px;
}

input,
select {
    width: 100%;

    padding: 13px;

    border: 1px solid #ddd;

    border-radius: 9px;

    font-size: 15px;
}

input:focus,
select:focus {
    outline: none;

    border-color: #d81b60;

    box-shadow:
        0 0 0 3px rgba(216, 27, 96, 0.10);
}

/* BUTTON */

button {
    width: 100%;

    padding: 14px;

    border: none;

    border-radius: 9px;

    background: #d81b60;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;
}

button:hover {
    background: #ad1457;
}

/* MESSAGES */

.success {
    background: #e8f5e9;

    color: #2e7d32;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 18px;
}

.error {
    background: #ffebee;

    color: #c62828;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 18px;
}

/* MOBILE */

@media(max-width:600px) {

    .navbar {
        flex-direction: column;

        gap: 12px;
    }

    .card {
        padding: 22px;
    }
}

</style>

</head>

<body>

<nav class="navbar">

    <div class="logo">
        🧺 LaundryTrack
    </div>

    <a href="dashboard.php">
        Dashboard
    </a>

</nav>


<div class="container">

    <div class="card">

        <a
            href="view_items.php"
            class="back"
        >
            ← Back to Laundry Records
        </a>


        <h1>
            ✏️ Edit Laundry Item
        </h1>


        <?php if ($success): ?>

            <div class="success">

                ✅
                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="error">

                ⚠️
                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <div class="info">

            <strong>Current Tracking Information</strong>

            <br>

            Sent:
            <?= (int)$item["sent_quantity"] ?>

            <br>

            Returned:
            <?= (int)$item["returned_quantity"] ?>

            <br>

            Damaged:
            <?= (int)$item["damaged_quantity"] ?>

            <br>

            Status:
            <?= htmlspecialchars($item["status"]) ?>

        </div>


        <form
            method="POST"
            onsubmit="return validateForm()"
        >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$id ?>"
            >


            <div class="form-group">

                <label>
                    Laundry Item Name
                </label>

                <input
                    type="text"
                    name="item_name"
                    value="<?= htmlspecialchars($item["item_name"]) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Item Type
                </label>

                <select
                    name="item_type"
                    required
                >

                    <option value="">
                        Select Item Type
                    </option>

                    <option
                        value="Towel"
                        <?= $item["item_type"] === "Towel" ? "selected" : "" ?>
                    >
                        Towel
                    </option>

                    <option
                        value="Bed Linen"
                        <?= $item["item_type"] === "Bed Linen" ? "selected" : "" ?>
                    >
                        Bed Linen
                    </option>

                    <option
                        value="Uniform"
                        <?= $item["item_type"] === "Uniform" ? "selected" : "" ?>
                    >
                        Uniform
                    </option>

                    <option
                        value="Pillow Case"
                        <?= $item["item_type"] === "Pillow Case" ? "selected" : "" ?>
                    >
                        Pillow Case
                    </option>

                    <option
                        value="Other"
                        <?= $item["item_type"] === "Other" ? "selected" : "" ?>
                    >
                        Other
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Total Quantity
                </label>

                <input
                    id="quantity"
                    type="number"
                    name="quantity"
                    min="<?= max(
                        1,
                        (int)$item["sent_quantity"],
                        (int)$item["returned_quantity"]
                        + (int)$item["damaged_quantity"]
                    ) ?>"
                    value="<?= (int)$item["quantity"] ?>"
                    required
                >

            </div>


            <button type="submit">

                💾 Save Changes

            </button>

        </form>

    </div>

</div>


<script>

function validateForm() {

    const quantity =
        Number(
            document.getElementById("quantity").value
        );

    const minimum =
        <?= max(
            1,
            (int)$item["sent_quantity"],
            (int)$item["returned_quantity"]
            + (int)$item["damaged_quantity"]
        ) ?>;

    if (quantity < minimum) {

        alert(
            "Quantity cannot be less than "
            + minimum
            + "."
        );

        return false;
    }

    return true;
}

</script>

</body>

</html>