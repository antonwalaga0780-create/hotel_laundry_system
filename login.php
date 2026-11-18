<?php
session_start();
require_once "config/db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($login === "" || $password === "") {
        $error = "Please enter your username/email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, full_name, username, email, password, role
             FROM users
             WHERE (username = ? OR email = ?) AND status = 'Active'
             LIMIT 1"
        );
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username/email or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Pink Laundry System</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;min-height:100vh;background:linear-gradient(135deg,#fff0f6,#fce4ec);color:#3f2631}
.container{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px}
.card{width:min(950px,100%);display:grid;grid-template-columns:45% 55%;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 20px 55px rgba(173,20,87,.18)}
.brand{background:linear-gradient(145deg,#ad1457,#ec407a);color:#fff;padding:55px 42px;display:flex;flex-direction:column;justify-content:center}
.brand h1{font-size:38px;margin-bottom:15px}
.brand p{line-height:1.7;color:#ffe8f1}
.info{margin-top:30px;padding:18px;border-radius:13px;background:rgba(255,255,255,.14);line-height:1.7}
.form-area{padding:55px 45px}
.form-area h2{font-size:31px;color:#ad1457;margin-bottom:8px}
.subtitle{color:#777;margin-bottom:25px}
.alert{padding:12px 15px;border-radius:10px;margin-bottom:18px;font-size:14px}
.error{background:#ffebee;color:#c62828;border:1px solid #ffcdd2}
label{display:block;margin:16px 0 7px;font-weight:bold;font-size:14px}
input{width:100%;padding:14px;border:1px solid #ddd;border-radius:10px;font-size:15px;outline:none}
input:focus{border-color:#ec407a;box-shadow:0 0 0 3px rgba(236,64,122,.12)}
.password-wrap{position:relative}
.password-wrap input{padding-right:80px}
.toggle{position:absolute;right:10px;top:9px;border:0;background:#fce4ec;color:#ad1457;padding:8px 10px;border-radius:7px;cursor:pointer}
button.submit{width:100%;margin-top:25px;padding:14px;border:0;border-radius:10px;background:#d81b60;color:white;font-size:16px;font-weight:bold;cursor:pointer}
button.submit:hover{background:#ad1457}
.signup{text-align:center;margin-top:22px;color:#666}
.signup a{color:#c2185b;font-weight:bold;text-decoration:none}
@media(max-width:760px){.card{grid-template-columns:1fr}.brand{padding:30px}.form-area{padding:35px 30px}.brand h1{font-size:30px}}
</style>
</head>
<body>
<div class="container">
<div class="card">
<section class="brand">
    <h1>🧺 LaundryTrack</h1>
    <p>Hotel Laundry Management System</p>
    <div class="info">
        <strong>Secure access</strong><br>
        Sign in to manage laundry items, record movements and monitor missing or returned items.
    </div>
</section>

<section class="form-area">
    <h2>Welcome Back</h2>
    <p class="subtitle">Login to your laundry management system.</p>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Username or Email</label>
        <input type="text" name="login" placeholder="Enter username or email" autocomplete="username" required>

        <label>Password</label>
        <div class="password-wrap">
            <input type="password" name="password" id="password" placeholder="Enter your password" autocomplete="current-password" required>
            <button type="button" class="toggle" onclick="togglePassword()">Show</button>
        </div>

        <button class="submit" type="submit">Login</button>
    </form>

    <div class="signup">Don't have an account? <a href="signup.php">Create Account</a></div>
</section>
</div>
</div>

<script>
function togglePassword(){
    const input=document.getElementById("password");
    const btn=document.querySelector(".toggle");
    if(input.type==="password"){input.type="text";btn.textContent="Hide";}
    else{input.type="password";btn.textContent="Show";}
}
</script>
</body>
</html>