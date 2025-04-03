<?php
session_start();

// Only allow access if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "elevatefit";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle user deletion
if (isset($_POST['deleteUser']) && isset($_POST['uid'])) {
    $uid = intval($_POST['uid']);
    $stmt = $conn->prepare("DELETE FROM users WHERE UID = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php");
    exit;
}

// Fetch users
$users = [];
$result = $conn->query("SELECT UID, full_name, email, weight, plan FROM users");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Update plan
if (isset($_POST['updatePlan'])) {
    $uid = $_POST['uid'];
    $plan = $_POST['plan'];
    $conn->query("UPDATE users SET plan = '$plan' WHERE UID = $uid");
    header("Location: admin.php");
    exit;
}

// Send notification
if (isset($_POST['sendNotification'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO notifications (message) VALUES (?)");
        $stmt->bind_param("s", $message);
        $stmt->execute();
        $stmt->close();
        $notificationSent = true;
    }
}

// Add new user
if (isset($_POST['addUser'])) {
    $name = trim($_POST['new_name']);
    $email = trim($_POST['new_email']);
    $pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $weight = floatval($_POST['new_weight']);

    // Assign plan
    if ($weight < 45) $plan = "weightGain";
    elseif ($weight > 65) $plan = "weightLoss";
    else $plan = "normal";

    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, weight, plan) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssds", $name, $email, $pass, $weight, $plan);
    $stmt->execute();
    $stmt->close();

    header("Location: admin.php");
    exit;
}

// Fetch all notifications
$notifications = [];
$stmt = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC");
while ($row = $stmt->fetch_assoc()) {
    $notifications[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial;
            background-color: #f2f2f2;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 95%;
            max-width: 1100px;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.1);
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #ccc;
            padding-bottom: 10px;
        }
        h1 {
            color: #2a374a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background: #2a374a;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        input[type=text], input[type=email], input[type=password], input[type=number], select, textarea {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            width: 100%;
            margin-top: 6px;
        }
        .btn {
            background-color: #2a374a;
            color: white;
            padding: 7px 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn:hover {
            background-color: #1d2737;
        }
        .delete-btn {
            background-color: #e74c3c;
        }
        .delete-btn:hover {
            background-color: #c0392b;
        }
        .logout-btn {
            background-color: #e74c3c;
        }
        .logout-btn:hover {
            background-color: #c0392b;
        }
        .section {
            margin-top: 30px;
        }
        .notification {
            color: green;
            font-weight: bold;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .notification-history {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>Admin Dashboard</h1>
        <form method="post" action="logout.php">
            <button class="btn logout-btn">Logout</button>
        </form>
    </header>

    <!-- USERS TABLE -->
    <div class="section">
        <h2>All Users</h2>
        <table>
            <tr>
                <th>UID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Weight</th>
                <th>Plan</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['UID'] ?></td>
                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                    <td><?= $user['email'] ?></td>
                    <td><?= $user['weight'] ?></td>
                    <td><?= $user['plan'] ?></td>
                    <td>
                        <div class="action-buttons">
                            <!-- Update Plan Form -->
                            <form method="POST">
                                <input type="hidden" name="uid" value="<?= $user['UID'] ?>">
                                <select name="plan">
                                    <option value="weightLoss" <?= $user['plan'] === 'weightLoss' ? 'selected' : '' ?>>Weight Loss</option>
                                    <option value="weightGain" <?= $user['plan'] === 'weightGain' ? 'selected' : '' ?>>Weight Gain</option>
                                    <option value="normal" <?= $user['plan'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                </select>
                                <button class="btn" name="updatePlan">Update</button>
                            </form>

                            <!-- Delete User Form -->
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="uid" value="<?= $user['UID'] ?>">
                                <button class="btn delete-btn" name="deleteUser">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- NOTIFICATIONS -->
    <div class="section">
        <h2>Send Notification</h2>
        <?php if (!empty($notificationSent)) echo "<p class='notification'>Notification sent successfully!</p>"; ?>
        <form method="POST">
            <textarea name="message" rows="3" placeholder="Enter notification..."></textarea>
            <button class="btn" name="sendNotification">Send</button>
        </form>
    </div>

    <!-- NOTIFICATION HISTORY -->
    <div class="notification-history">
        <h3>Notification History</h3>
        <?php foreach ($notifications as $notification): ?>
            <p><?= htmlspecialchars($notification['message']) ?> - <?= $notification['created_at'] ?></p>
        <?php endforeach; ?>
    </div>

    <!-- ADD USER FORM -->
    <div class="section">
        <h2>Add New User</h2>
        <form method="POST">
            <label>Name</label>
            <input type="text" name="new_name" required>

            <label>Email</label>
            <input type="email" name="new_email" required>

            <label>Password</label>
            <input type="password" name="new_password" required>

            <label>Weight (kg)</label>
            <input type="number" name="new_weight" step="0.1" required>

            <button class="btn" name="addUser">Add User</button>
        </form>
    </div>
</div>
</body>
</html>
