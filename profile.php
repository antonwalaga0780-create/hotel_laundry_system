```php
<?php
session_start();

require_once "config/db.php";

/* =====================================================
   CHECK LOGIN
===================================================== */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$message = "";
$error = "";

/* =====================================================
   GET CURRENT USER
===================================================== */
$stmt = $conn->prepare("
    SELECT id, full_name, username, email, role, status, created_at
    FROM users
    WHERE id = ?
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();


/* =====================================================
   UPDATE PROFILE
===================================================== */
if (isset($_POST['update_profile'])) {

    $full_name = trim($_POST['full_name'] ?? "");
    $username  = trim($_POST['username'] ?? "");
    $email     = trim($_POST['email'] ?? "");

    if ($full_name === "" || $username === "" || $email === "") {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /* Check username */
        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            AND id != ?
        ");

        if (!$check) {

            $error = "Database error: " . $conn->error;

        } else {

            $check->bind_param("si", $username, $user_id);
            $check->execute();

            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {

                $error = "That username is already being used.";

            } else {

                /* Check email */
                $check_email = $conn->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                    AND id != ?
                ");

                if (!$check_email) {

                    $error = "Database error: " . $conn->error;

                } else {

                    $check_email->bind_param("si", $email, $user_id);
                    $check_email->execute();

                    $email_result = $check_email->get_result();

                    if ($email_result->num_rows > 0) {

                        $error = "That email address is already being used.";

                    } else {

                        /* Update profile */
                        $update = $conn->prepare("
                            UPDATE users
                            SET full_name = ?,
                                username = ?,
                                email = ?
                            WHERE id = ?
                        ");

                        if (!$update) {

                            $error = "Database error: " . $conn->error;

                        } else {

                            $update->bind_param(
                                "sssi",
                                $full_name,
                                $username,
                                $email,
                                $user_id
                            );

                            if ($update->execute()) {

                                $_SESSION['full_name'] = $full_name;
                                $_SESSION['username'] = $username;

                                $message = "Profile updated successfully.";

                                /* Update displayed information */
                                $user['full_name'] = $full_name;
                                $user['username'] = $username;
                                $user['email'] = $email;

                            } else {

                                $error = "Could not update profile: " . $update->error;
                            }

                            $update->close();
                        }
                    }

                    $check_email->close();
                }
            }

            $check->close();
        }
    }
}


/* =====================================================
   CHANGE PASSWORD
===================================================== */
if (isset($_POST['change_password'])) {

    $current_password = $_POST['current_password'] ?? "";
    $new_password     = $_POST['new_password'] ?? "";
    $confirm_password = $_POST['confirm_password'] ?? "";

    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {

        $error = "Please fill in all password fields.";

    } elseif (strlen($new_password) < 8) {

        $error = "New password must contain at least 8 characters.";

    } elseif ($new_password !== $confirm_password) {

        $error = "The new passwords do not match.";

    } else {

        /* Get current password */
        $password_stmt = $conn->prepare("
            SELECT password
            FROM users
            WHERE id = ?
        ");

        if (!$password_stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $password_stmt->bind_param("i", $user_id);
            $password_stmt->execute();

            $password_result = $password_stmt->get_result();

            if ($password_result->num_rows !== 1) {

                $error = "User account could not be found.";

            } else {

                $password_data = $password_result->fetch_assoc();

                /* Verify old password */
                if (
                    !password_verify(
                        $current_password,
                        $password_data['password']
                    )
                ) {

                    $error = "Current password is incorrect.";

                } else {

                    /* Create new password hash */
                    $new_hash = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    $change_stmt = $conn->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                    ");

                    if (!$change_stmt) {

                        $error = "Database error: " . $conn->error;

                    } else {

                        $change_stmt->bind_param(
                            "si",
                            $new_hash,
                            $user_id
                        );

                        if ($change_stmt->execute()) {

                            $message = "Password changed successfully.";

                        } else {

                            $error = "Could not change password.";
                        }

                        $change_stmt->close();
                    }
                }
            }

            $password_stmt->close();
        }
    }
}


/* =====================================================
   CREATE PROFILE INITIAL
===================================================== */
$initial = "U";

if (!empty($user['full_name'])) {
    $initial = strtoupper(substr(trim($user['full_name']), 0, 1));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | LaundryTrack</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #fff4f8;
            color: #3d2631;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            background: linear-gradient(
                135deg,
                #b20f5c,
                #d41465
            );

            color: white;
            padding: 18px 6%;

            display: flex;
            justify-content: space-between;
            align-items: center;

            box-shadow: 0 3px 12px rgba(178, 15, 92, 0.25);
        }

        .logo {
            font-size: 23px;
            font-weight: bold;
        }

        .logo span {
            margin-right: 8px;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .header-btn {
            text-decoration: none;
            color: white;
            background: rgba(255,255,255,0.16);

            padding: 10px 18px;
            border-radius: 8px;

            font-weight: bold;
        }

        .header-btn:hover {
            background: rgba(255,255,255,0.28);
        }

        /* =========================
           MAIN
        ========================= */

        .container {
            width: 92%;
            max-width: 1100px;
            margin: 45px auto;
        }

        .page-title {
            color: #b20f5c;
            font-size: 34px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #765865;
            margin-bottom: 30px;
        }

        /* =========================
           ALERTS
        ========================= */

        .success {
            background: #e7f8ed;
            color: #176b39;
            border-left: 5px solid #22a653;

            padding: 14px 18px;
            border-radius: 8px;

            margin-bottom: 20px;
        }

        .error {
            background: #ffe6ed;
            color: #b0003a;
            border-left: 5px solid #d41465;

            padding: 14px 18px;
            border-radius: 8px;

            margin-bottom: 20px;
        }

        /* =========================
           PROFILE LAYOUT
        ========================= */

        .profile-grid {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 25px;
        }

        .card {
            background: white;

            border: 1px solid #f3c8d9;

            border-radius: 15px;

            padding: 30px;

            box-shadow: 0 7px 25px rgba(178, 15, 92, 0.08);
        }

        .card h2 {
            color: #b20f5c;

            margin-top: 0;
            margin-bottom: 8px;
        }

        .card-description {
            color: #765865;

            margin-bottom: 25px;
        }

        /* =========================
           AVATAR
        ========================= */

        .profile-avatar {
            width: 90px;
            height: 90px;

            border-radius: 50%;

            background: linear-gradient(
                135deg,
                #d41465,
                #b20f5c
            );

            color: white;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 38px;
            font-weight: bold;

            margin-bottom: 20px;
        }

        /* =========================
           INFORMATION
        ========================= */

        .info-row {
            border-bottom: 1px solid #f2d9e3;

            padding: 14px 0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            display: block;

            font-size: 13px;

            color: #8b6875;

            margin-bottom: 5px;
        }

        .info-value {
            font-weight: bold;

            color: #38232c;
        }

        .badge {
            display: inline-block;

            padding: 5px 12px;

            background: #ffe1ec;

            color: #b20f5c;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;
        }

        /* =========================
           FORM
        ========================= */

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;

            margin-bottom: 7px;

            font-weight: bold;
        }

        input {
            width: 100%;

            padding: 13px 14px;

            border: 1px solid #e3b7c9;

            border-radius: 9px;

            font-size: 15px;

            outline: none;

            transition: 0.2s;
        }

        input:focus {
            border-color: #d41465;

            box-shadow:
                0 0 0 3px
                rgba(212,20,101,0.10);
        }

        /* =========================
           PASSWORD
        ========================= */

        .password-box {
            position: relative;
        }

        .password-box input {
            padding-right: 85px;
        }

        .show-password {
            position: absolute;

            right: 10px;
            top: 9px;

            border: none;

            background: #ffe3ed;

            color: #b20f5c;

            padding: 7px 10px;

            border-radius: 6px;

            cursor: pointer;
        }

        /* =========================
           BUTTON
        ========================= */

        .btn {
            border: none;

            background: linear-gradient(
                135deg,
                #d41465,
                #b20f5c
            );

            color: white;

            padding: 13px 22px;

            border-radius: 9px;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;
        }

        .btn:hover {
            opacity: 0.9;

            transform: translateY(-1px);
        }

        /* =========================
           SECURITY NOTE
        ========================= */

        .security-note {
            background: #fff1f6;

            border-left: 4px solid #d41465;

            padding: 13px;

            margin-top: 20px;

            border-radius: 6px;

            font-size: 14px;

            color: #684b58;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 800px) {

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .header {
                padding: 15px 4%;
            }

            .container {
                width: 94%;
                margin: 30px auto;
            }

            .page-title {
                font-size: 28px;
            }

        }

    </style>

</head>

<body>

<header class="header">

    <div class="logo">
        <span>🧺</span>
        LaundryTrack
    </div>

    <div class="header-buttons">

        <a href="dashboard.php" class="header-btn">
            Dashboard
        </a>

        <a href="logout.php" class="header-btn">
            Logout
        </a>

    </div>

</header>


<div class="container">

    <h1 class="page-title">
        My Profile
    </h1>

    <p class="subtitle">
        View and manage your LaundryTrack account information.
    </p>


    <?php if ($message !== ""): ?>

        <div class="success">
            ✅ <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <div class="profile-grid">


        <!-- ==========================================
             ACCOUNT INFORMATION
        =========================================== -->

        <div class="card">

            <h2>👤 Account Information</h2>

            <p class="card-description">
                Your current LaundryTrack account details.
            </p>


            <div class="profile-avatar">

                <?php
                echo htmlspecialchars($initial);
                ?>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Full Name
                </span>

                <span class="info-value">

                    <?php
                    echo htmlspecialchars($user['full_name']);
                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Username
                </span>

                <span class="info-value">

                    <?php
                    echo htmlspecialchars($user['username']);
                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Email Address
                </span>

                <span class="info-value">

                    <?php
                    echo htmlspecialchars($user['email']);
                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Role
                </span>

                <span class="badge">

                    <?php
                    echo htmlspecialchars($user['role']);
                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Account Status
                </span>

                <span class="badge">

                    <?php
                    echo htmlspecialchars($user['status']);
                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Account Created
                </span>

                <span class="info-value">

                    <?php

                    if (!empty($user['created_at'])) {

                        echo date(
                            "d M Y",
                            strtotime($user['created_at'])
                        );

                    } else {

                        echo "Not available";

                    }

                    ?>

                </span>

            </div>

        </div>


        <!-- ==========================================
             EDIT PROFILE
        =========================================== -->

        <div class="card">

            <h2>✏️ Edit Profile</h2>

            <p class="card-description">
                Update your name, username and email address.
            </p>


            <form method="POST" action="">


                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?php
                        echo htmlspecialchars($user['full_name']);
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?php
                        echo htmlspecialchars($user['username']);
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php
                        echo htmlspecialchars($user['email']);
                        ?>"
                        required
                    >

                </div>


                <button
                    type="submit"
                    name="update_profile"
                    class="btn"
                >
                    💾 Save Changes
                </button>

            </form>

        </div>


        <!-- ==========================================
             CHANGE PASSWORD
        =========================================== -->

        <div class="card">

            <h2>🔐 Change Password</h2>

            <p class="card-description">
                Change your account password.
            </p>


            <form
                method="POST"
                action=""
                name="change_password"
                id="change_password_form"
            >


                <div class="form-group">

                    <label for="current_password">
                        Current Password
                    </label>

                    <div class="password-box">

                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            required
                        >

                        <button
                            type="button"
                            class="show-password"
                            onclick="togglePassword(
                                'current_password',
                                this
                            )"
                        >
                            Show
                        </button>

                    </div>

                </div>


                <div class="form-group">

                    <label for="new_password">
                        New Password
                    </label>

                    <div class="password-box">

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            minlength="8"
                            required
                        >

                        <button
                            type="button"
                            class="show-password"
                            onclick="togglePassword(
                                'new_password',
                                this
                            )"
                        >
                            Show
                        </button>

                    </div>

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <div class="password-box">

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            required
                        >

                        <button
                            type="button"
                            class="show-password"
                            onclick="togglePassword(
                                'confirm_password',
                                this
                            )"
                        >
                            Show
                        </button>

                    </div>

                </div>


                <button
                    type="submit"
                    name="change_password"
                    class="btn"
                >
                    🔑 Change Password
                </button>


            </form>


            <div class="security-note">

                🔒 Your password is stored securely using
                password hashing. Never share your password
                with other people.

            </div>

        </div>


        <!-- ==========================================
             SYSTEM ACCOUNT
        =========================================== -->

        <div class="card">

            <h2>🧺 LaundryTrack Account</h2>

            <p class="card-description">
                Your account gives you access to the laundry
                management system.
            </p>


            <div class="info-row">

                <span class="info-label">
                    System
                </span>

                <span class="info-value">
                    Hotel Laundry Management System
                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Account Type
                </span>

                <span class="badge">

                    <?php
                    echo htmlspecialchars($user['role']);
                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Status
                </span>

                <span class="badge">

                    <?php
                    echo htmlspecialchars($user['status']);
                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Main Functions
                </span>

                <span class="info-value">
                    Laundry Items, Sending, Returns,
                    Missing Items and Reports
                </span>

            </div>


            <div style="margin-top:25px;">

                <a
                    href="dashboard.php"
                    class="btn"
                    style="
                        display:inline-block;
                        text-decoration:none;
                    "
                >
                    ← Back to Dashboard
                </a>

            </div>

        </div>


    </div>

</div>


<script>

/* =====================================================
   SHOW / HIDE PASSWORD
===================================================== */

function togglePassword(id, button) {

    const input = document.getElementById(id);

    if (!input) {
        return;
    }

    if (input.type === "password") {

        input.type = "text";

        button.textContent = "Hide";

    } else {

        input.type = "password";

        button.textContent = "Show";

    }
}


/* =====================================================
   CONFIRM PASSWORDS
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById(
        "change_password_form"
    );

    const newPassword = document.getElementById(
        "new_password"
    );

    const confirmPassword = document.getElementById(
        "confirm_password"
    );


    if (
        form &&
        newPassword &&
        confirmPassword
    ) {

        form.addEventListener(
            "submit",
            function (event) {

                if (
                    newPassword.value !==
                    confirmPassword.value
                ) {

                    event.preventDefault();

                    confirmPassword.setCustomValidity(
                        "Passwords do not match."
                    );

                    confirmPassword.reportValidity();

                } else {

                    confirmPassword.setCustomValidity("");

                }

            }
        );


        confirmPassword.addEventListener(
            "input",
            function () {

                if (
                    confirmPassword.value !== "" &&
                    newPassword.value !==
                    confirmPassword.value
                ) {

                    confirmPassword.setCustomValidity(
                        "Passwords do not match."
                    );

                } else {

                    confirmPassword.setCustomValidity("");

                }

            }
        );

    }

});

</script>


</body>

</html>
```
