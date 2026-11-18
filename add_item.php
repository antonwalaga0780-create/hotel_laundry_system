<?php
session_start();

require_once "config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $item_name = trim($_POST['item_name'] ?? '');
    $item_type = trim($_POST['item_type'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if ($item_name === '') {
        $message = "Please enter the item name.";
        $message_type = "error";
    } elseif ($item_type === '') {
        $message = "Please select the item type.";
        $message_type = "error";
    } elseif ($quantity <= 0) {
        $message = "Quantity must be greater than zero.";
        $message_type = "error";
    } else {

        // Generate a unique item code
        $item_code = "LND-" . date("YmdHis") . "-" . rand(100, 999);

        // New item starts with zero sent, returned and damaged
        $sent_quantity = 0;
        $returned_quantity = 0;
        $damaged_quantity = 0;
        $status = "Available";
        $condition_status = "Good";

        /*
         * category_id is intentionally NULL.
         * The system now uses item_type instead of categories.
         */
        $category_id = null;

        $sql = "INSERT INTO laundry_items
                (
                    item_code,
                    item_name,
                    category_id,
                    quantity,
                    condition_status,
                    description,
                    item_type,
                    sent_quantity,
                    returned_quantity,
                    damaged_quantity,
                    status,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $message = "Database error: " . $conn->error;
            $message_type = "error";
        } else {

            $description = $notes;

            $stmt->bind_param(
                "ssisssiiiiss",
                $item_code,
                $item_name,
                $category_id,
                $quantity,
                $condition_status,
                $description,
                $item_type,
                $sent_quantity,
                $returned_quantity,
                $damaged_quantity,
                $status,
                $notes
            );

            if ($stmt->execute()) {
                $message = "Laundry item added successfully!";
                $message_type = "success";

                // Clear form
                $item_name = "";
                $item_type = "";
                $quantity = "";
                $notes = "";
            } else {
                $message = "Could not add item: " . $stmt->error;
                $message_type = "error";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Laundry Item | LaundryTrack</title>

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

/* TOP BAR */

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

.logo span {
    margin-right: 8px;
}

.dashboard-btn {
    background: #d91b68;
    color: white;
    text-decoration: none;
    padding: 11px 20px;
    border-radius: 8px;
    font-weight: bold;
}

.dashboard-btn:hover {
    background: #9d0749;
}

/* PAGE */

.page {
    max-width: 900px;
    margin: 45px auto;
    padding: 0 20px;
}

.card {
    background: white;
    padding: 35px;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(183, 9, 85, 0.10);
}

h1 {
    color: #b70955;
    margin-top: 0;
    font-size: 32px;
}

.subtitle {
    color: #6d5360;
    margin-bottom: 30px;
}

/* FORM */

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

input,
select,
textarea {
    width: 100%;
    padding: 14px;
    border: 1px solid #e5b9cb;
    border-radius: 10px;
    font-size: 15px;
    outline: none;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #d91b68;
    box-shadow: 0 0 0 3px rgba(217, 27, 104, 0.10);
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.save-btn {
    width: 100%;
    border: none;
    background: #e01661;
    color: white;
    padding: 15px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.save-btn:hover {
    background: #b70955;
}

/* MESSAGES */

.message {
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.success {
    background: #e7f8ee;
    color: #176b3a;
    border: 1px solid #b8e6ca;
}

.error {
    background: #ffe8ee;
    color: #b0003a;
    border: 1px solid #f5b5c7;
}

/* INFO */

.info-box {
    margin-top: 25px;
    background: #fff0f6;
    border-left: 5px solid #e01661;
    padding: 15px;
    border-radius: 8px;
    line-height: 1.6;
}

/* MOBILE */

@media (max-width: 600px) {

    .topbar {
        padding: 15px 20px;
    }

    .logo {
        font-size: 18px;
    }

    .page {
        margin: 25px auto;
    }

    .card {
        padding: 25px 20px;
    }

    h1 {
        font-size: 27px;
    }
}

</style>
</head>

<body>

<div class="topbar">

    <div class="logo">
        <span>🧺</span> LaundryTrack
    </div>

    <a href="dashboard.php" class="dashboard-btn">
        Dashboard
    </a>

</div>

<div class="page">

    <div class="card">

        <h1>＋ Add Laundry Item</h1>

        <p class="subtitle">
            Register towels, uniforms, bed linen and other hotel laundry.
        </p>

        <?php if ($message !== ""): ?>

            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <!-- ITEM NAME -->

            <div class="form-group">

                <label for="item_name">
                    Item Name
                </label>

                <input
                    type="text"
                    id="item_name"
                    name="item_name"
                    placeholder="e.g. Bath Towel"
                    value="<?php echo htmlspecialchars($item_name ?? ''); ?>"
                    required
                >

            </div>

            <!-- ITEM TYPE -->

            <div class="form-group">

                <label for="item_type">
                    Item Type
                </label>

                <select
                    id="item_type"
                    name="item_type"
                    required
                >

                    <option value="">Select type</option>

                    <option value="Towel"
                        <?php echo (($item_type ?? '') === 'Towel') ? 'selected' : ''; ?>>
                        Towel
                    </option>

                    <option value="Bed Linen"
                        <?php echo (($item_type ?? '') === 'Bed Linen') ? 'selected' : ''; ?>>
                        Bed Linen
                    </option>

                    <option value="Uniform"
                        <?php echo (($item_type ?? '') === 'Uniform') ? 'selected' : ''; ?>>
                        Uniform
                    </option>

                    <option value="Pillow Case"
                        <?php echo (($item_type ?? '') === 'Pillow Case') ? 'selected' : ''; ?>>
                        Pillow Case
                    </option>

                    <option value="Curtain"
                        <?php echo (($item_type ?? '') === 'Curtain') ? 'selected' : ''; ?>>
                        Curtain
                    </option>

                    <option value="Other"
                        <?php echo (($item_type ?? '') === 'Other') ? 'selected' : ''; ?>>
                        Other
                    </option>

                </select>

            </div>

            <!-- QUANTITY -->

            <div class="form-group">

                <label for="quantity">
                    Total Quantity
                </label>

                <input
                    type="number"
                    id="quantity"
                    name="quantity"
                    min="1"
                    placeholder="e.g. 50"
                    value="<?php echo htmlspecialchars($quantity ?? ''); ?>"
                    required
                >

            </div>

            <!-- NOTES -->

            <div class="form-group">

                <label for="notes">
                    Notes
                </label>

                <textarea
                    id="notes"
                    name="notes"
                    placeholder="Optional notes"
                ><?php echo htmlspecialchars($notes ?? ''); ?></textarea>

            </div>

            <button type="submit" class="save-btn">
                Save Laundry Item
            </button>

        </form>

        <div class="info-box">

            <strong>How it works:</strong><br>

            New laundry items start with:
            <strong>0 Sent</strong>,
            <strong>0 Returned</strong>,
            and
            <strong>0 Damaged</strong>.

            You can update these quantities later using the
            Send, Return and Damage modules.

        </div>

    </div>

</div>

<script>

/* Quantity validation */

document.querySelector("form").addEventListener("submit", function(event) {

    const quantity = document.getElementById("quantity").value;

    if (quantity <= 0) {
        alert("Please enter a quantity greater than zero.");
        event.preventDefault();
    }

});

</script>

</body>
</html>

