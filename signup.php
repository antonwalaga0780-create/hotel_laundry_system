<?php
session_start();
require_once "config/db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$success = "";

$countResult = $conn->query("SELECT COUNT(*) AS total FROM users");
$countRow = $countResult->fetch_assoc();
$accountExists = ((int)$countRow["total"] > 0);

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$accountExists) {
    $full_name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($full_name === "" || $username === "" || $email === "" || $password === "" || $confirm_password === "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($username) < 3) {
        $error = "Username must contain at least 3 characters.";
    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $existing = $check->get_result();

        if ($existing->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (full_name, username, email, password, role, status)
                 VALUES (?, ?, ?, ?, 'Admin', 'Active')"
            );
            $stmt->bind_param("ssss", $full_name, $username, $email, $hashedPassword);

            if ($stmt->execute()) {
                $success = "Account created successfully. You can now log in.";
                $accountExists = true;
            } else {
                $error = "Could not create the account. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up | Pink Laundry System</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;min-height:100vh;background:linear-gradient(135deg,#fff0f6,#fce4ec);color:#3f2631}
.container{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px}
.card{width:min(1000px,100%);display:grid;grid-template-columns:42% 58%;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 20px 55px rgba(173,20,87,.18)}
.brand{background:linear-gradient(145deg,#d81b60,#ec407a);color:#fff;padding:55px 42px;display:flex;flex-direction:column;justify-content:center}
.brand h1{font-size:38px;margin-bottom:15px}
.brand p{line-height:1.7;color:#ffe8f1}
.features{margin-top:30px;display:grid;gap:15px}
.feature{background:rgba(255,255,255,.13);padding:14px;border-radius:12px}
.form-area{padding:45px}
.form-area h2{font-size:30px;color:#ad1457;margin-bottom:8px}
.subtitle{color:#777;margin-bottom:25px}
.alert{padding:12px 15px;border-radius:10px;margin-bottom:18px;font-size:14px}
.error{background:#ffebee;color:#c62828;border:1px solid #ffcdd2}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9}
label{display:block;margin:15px 0 7px;font-weight:bold;font-size:14px}
input{width:100%;padding:13px 14px;border:1px solid #ddd;border-radius:10px;font-size:15px;outline:none;transition:.2s}
input:focus{border-color:#ec407a;box-shadow:0 0 0 3px rgba(236,64,122,.12)}
.password-wrap{position:relative}
.password-wrap input{padding-right:80px}
.toggle{position:absolute;right:10px;top:9px;border:0;background:#fce4ec;color:#ad1457;padding:8px 10px;border-radius:7px;cursor:pointer}
button.submit{width:100%;margin-top:24px;padding:14px;border:0;border-radius:10px;background:#d81b60;color:white;font-size:16px;font-weight:bold;cursor:pointer}
button.submit:hover{background:#ad1457}
.login{text-align:center;margin-top:20px;color:#666}
.login a{color:#c2185b;font-weight:bold;text-decoration:none}
.hint{font-size:12px;color:#888;margin-top:7px}
@media(max-width:760px){.card{grid-template-columns:1fr}.brand{padding:30px}.form-area{padding:30px}.brand h1{font-size:30px}}
</style>
</head>
<body>
<div class="container">
<div class="card">
    <section class="brand">
        <h1>🧺 LaundryTrack</h1>
        <p>A simple hotel laundry management system for tracking towels, uniforms, bed sheets and other laundry items.</p>
        <div class="features">
            <div class="feature">📦 Track laundry items</div>
            <div class="feature">🔄 Record sent and returned items</div>
            <div class="feature">🔍 Reduce misplaced items</div>
            <div class="feature">📊 View laundry records and reports</div>
        </div>
    </section>

    <section class="form-area">
        <h2>Create Account</h2>
        <p class="subtitle">Create the administrator account to start using the system.</p>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($accountExists && !$success): ?>
            <div class="alert error">An account already exists. This system is configured for one administrator account.</div>
        <?php endif; ?>

        <?php if (!$accountExists): ?>
        <form method="POST" onsubmit="return validateForm()">
            <label>Full Name</label>
            <input type="text" name="full_name" id="full_name" placeholder="Enter your full name" required>

            <label>Username</label>
            <input type="text" name="username" id="username" placeholder="Choose a username" required>

            <label>Email Address</label>
            <input type="email" name="email" id="email" placeholder="Enter your email" required>

            <label>Password</label>
            <div class="password-wrap">
                <input type="password" name="password" id="password" placeholder="Minimum 8 characters" required>
                <button type="button" class="toggle" onclick="togglePassword('password',this)">Show</button>
            </div>
            <div class="hint" id="strength">Password strength: —</div>

            <label>Confirm Password</label>
            <div class="password-wrap">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat your password" required>
                <button type="button" class="toggle" onclick="togglePassword('confirm_password',this)">Show</button>
            </div>

            <button class="submit" type="submit">Create Account</button>
        </form>
        <?php endif; ?>

        <div class="login">Already have an account? <a href="login.php">Login here</a></div>
    </section>
</div>
</div>

<script>
function togglePassword(id, btn){
    const input=document.getElementById(id);
    if(input.type==="password"){input.type="text";btn.textContent="Hide";}
    else{input.type="password";btn.textContent="Show";}
}
document.getElementById("password")?.addEventListener("input",function(){
    const s=document.getElementById("strength");
    const p=this.value;
    if(!p){s.textContent="Password strength: —";return;}
    let score=0;
    if(p.length>=8)score++;
    if(/[A-Z]/.test(p))score++;
    if(/[0-9]/.test(p))score++;
    if(/[^A-Za-z0-9]/.test(p))score++;
    s.textContent="Password strength: "+(score<=1?"Weak":score<=3?"Good":"Strong");
});
function validateForm(){
    const p=document.getElementById("password").value;
    const c=document.getElementById("confirm_password").value;
    if(p.length<8){alert("Password must contain at least 8 characters.");return false;}
    if(p!==c){alert("Passwords do not match.");return false;}
    return true;
}
</script>
</body>
</html>