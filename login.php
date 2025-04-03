<?php
session_start();
$signupError = "";
$loginError = "";
$showSignup = false;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "elevatefit";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// SIGNUP
if (isset($_POST['signupBtn'])) {
  $signupEmail = trim($_POST['signupEmail']);
  $signupPassword = trim($_POST['signupPassword']);
  $hashedPassword = password_hash($signupPassword, PASSWORD_DEFAULT);

  $check = $conn->prepare("SELECT email FROM users WHERE email = ?");
  $check->bind_param("s", $signupEmail);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    $signupError = "User already exists!";
    $showSignup = true;
  } else {
    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $signupEmail, $hashedPassword);
    if ($stmt->execute()) {
      echo "<script>alert('Signup successful! You can now log in.');</script>";
    } else {
      $signupError = "Signup error: " . $stmt->error;
      $showSignup = true;
    }
    $stmt->close();
  }
  $check->close();
}

// LOGIN
if (isset($_POST['loginBtn'])) {
  $loginEmail = trim($_POST['loginEmail']);
  $loginPassword = trim($_POST['loginPassword']);

  // Check for admin login (Admin has a specific email)
  if ($loginEmail == 'vaishnavipandit79@gmail.com') {
    // Admin login
    $stmt = $conn->prepare("SELECT APass FROM admin WHERE AdminName = ?");
    $stmt->bind_param("s", $loginEmail);
    $stmt->execute();
    $stmt->bind_result($adminPassword);
    $stmt->fetch();

    if (password_verify($loginPassword, $adminPassword)) {
      $_SESSION['admin'] = $loginEmail;
      header("Location: admin.php");
      exit();
    } else {
      $loginError = "Invalid admin password.";
    }
  } else {
    // Regular user login
    $stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->bind_param("s", $loginEmail);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $stmt->bind_result($storedPassword);
      $stmt->fetch();

      if (password_verify($loginPassword, $storedPassword)) {
        $_SESSION['email'] = $loginEmail;
        header("Location: user.php");
        exit();
      } else {
        $loginError = "Invalid password.";
      }
    } else {
      $loginError = "No user found with that email.";
    }
  }
  $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login & Signup</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
    body { display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f7fc; }
    .container {
      width: 350px; background: #fff; padding: 30px;
      border-radius: 15px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1); text-align: center;
    }
    h2 { margin-bottom: 10px; color: #333; }
    p { font-size: 14px; color: #666; margin-bottom: 20px; }
    input {
      width: 100%; padding: 10px 40px 10px 10px; margin: 10px 0;
      border: 1px solid #ccc; border-radius: 25px; outline: none;
    }
    .password-wrapper {
      position: relative;
    }
    .toggle-password {
      position: absolute;
      top: 50%;
      right: 15px;
      transform: translateY(-50%);
      font-size: 14px;
      color: #2a374a;
      cursor: pointer;
      user-select: none;
      font-weight: 600;
      background: none;
      border: none;
      padding: 0;
    }
    button {
      width: 100%; padding: 12px; border: none; background: #2a374a;
      color: white; border-radius: 25px; font-size: 16px; cursor: pointer; margin-top: 10px;
    }
    button:hover { background: #1d2737; }
    .toggle { margin-top: 15px; font-size: 14px; color: #333; }
    .toggle a { color: #2a374a; text-decoration: none; font-weight: bold; }
  </style>
</head>
<body>

<!-- LOGIN FORM -->
<div class="container" id="loginBox" style="display: <?= $showSignup ? 'none' : 'block' ?>;">
  <h2>Login</h2>
  <p>Please sign in to continue</p>
  <form method="POST">
    <input type="email" name="loginEmail" placeholder="Email" required>
    <div class="password-wrapper">
      <input type="password" name="loginPassword" id="loginPassword" placeholder="Password" required>
      <span class="toggle-password" onclick="togglePassword('loginPassword', this)">Show</span>
    </div>
    <p style="color: red;"><?= $loginError ?></p>
    <button type="submit" name="loginBtn">Sign In</button>
  </form>
  <div class="toggle">
    Don't have an account? <a href="#" id="toggleSignup">Sign Up</a>
  </div>
</div>

<!-- SIGNUP FORM -->
<div class="container" id="signupBox" style="display: <?= $showSignup ? 'block' : 'none' ?>;">
  <h2>Register</h2>
  <p>Please register to login</p>
  <form method="POST">
    <input type="email" name="signupEmail" placeholder="Email" required>
    <div class="password-wrapper">
      <input type="password" name="signupPassword" id="signupPassword" placeholder="Password" required>
      <span class="toggle-password" onclick="togglePassword('signupPassword', this)">Show</span>
    </div>
    <p style="color: red;"><?= $signupError ?></p>
    <button type="submit" name="signupBtn">Sign Up</button>
  </form>
  <div class="toggle">
    Already have an account? <a href="#" id="toggleLogin">Sign In</a>
  </div>
</div>

<script>
  function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const isHidden = input.type === "password";
    input.type = isHidden ? "text" : "password";
    btn.textContent = isHidden ? "Hide" : "Show";
  }

  document.getElementById("toggleSignup").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById('loginBox').style.display = 'none';
    document.getElementById('signupBox').style.display = 'block';
  });

  document.getElementById("toggleLogin").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById('signupBox').style.display = 'none';
    document.getElementById('loginBox').style.display = 'block';
  });
</script>

</body>
</html>
