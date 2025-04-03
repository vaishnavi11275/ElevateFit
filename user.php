<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "elevatefit";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$email = $_SESSION['email'];

// Update name from profile
if (isset($_POST['updateName']) && isset($_POST['newName'])) {
    $newName = trim($_POST['newName']);
    $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE email = ?");
    $stmt->bind_param("ss", $newName, $email);
    $stmt->execute();
    $stmt->close();
}

// Get user details
$stmt = $conn->prepare("SELECT full_name, weight, plan FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($fullName, $weight, $plan);
$stmt->fetch();
$stmt->close();

$needsSetup = empty($fullName) || empty($weight);

if (isset($_POST['submitProfile'])) {
    $newName = trim($_POST['fullName']);
    $newWeight = floatval($_POST['weight']);
    $plan = $newWeight < 45 ? "weightGain" : ($newWeight > 65 ? "weightLoss" : "normal");

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, weight = ?, plan = ? WHERE email = ?");
    $stmt->bind_param("sdss", $newName, $newWeight, $plan, $email);
    $stmt->execute();
    $stmt->close();

    $fullName = $newName;
    $weight = $newWeight;
    $needsSetup = false;
}

// Log today's weight
$today = date('Y-m-d');
$todayWeight = null;
$stmt = $conn->prepare("SELECT weight FROM weight_logs WHERE user_email = ? AND log_date = ?");
$stmt->bind_param("ss", $email, $today);
$stmt->execute();
$stmt->bind_result($todayWeight);
$stmt->fetch();
$stmt->close();

if (isset($_POST['logWeight'])) {
    $weightValue = floatval($_POST['weightInput']);
    $stmt = $conn->prepare("INSERT INTO weight_logs (user_email, log_date, weight) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE weight = ?");
    $stmt->bind_param("ssdd", $email, $today, $weightValue, $weightValue);
    $stmt->execute();
    $stmt->close();
    $todayWeight = $weightValue;
}

// Fetch all notifications sent by the admin
$notifications = [];
$stmt = $conn->prepare("SELECT message FROM notifications ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row['message'];
}
$stmt->close();

// Chart data
$weights = $dates = [];
$stmt = $conn->prepare("SELECT log_date, weight FROM weight_logs WHERE user_email = ? ORDER BY log_date ASC");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $dates[] = $row['log_date'];
    $weights[] = $row['weight'];
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>User Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f4;
      margin: 0; padding: 0;
      display: flex; justify-content: center; align-items: center;
      min-height: 100vh;
    }
    .container {
      background: #fff;
      width: 90%; max-width: 1000px;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #ccc;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }
    h1 { color: #333; }
    .icons {
      display: flex; gap: 20px; align-items: center; position: relative;
    }
    .notification-icon, .profile-icon {
      font-size: 24px; cursor: pointer;
    }
    .dropdown {
      display: none;
      position: absolute;
      top: 30px;
      right: 0;
      background: #fff;
      box-shadow: 0 0 8px rgba(0,0,0,0.15);
      padding: 10px;
      border-radius: 5px;
      z-index: 10;
      width: 200px;
    }
    .dropdown.active { display: block; }
    .edit-name {
      cursor: pointer;
      color: #007bff;
      font-size: 14px;
      margin-left: 10px;
    }
    form.inline-form {
      display: flex;
      gap: 5px;
      margin-top: 10px;
    }
    form.inline-form input {
      flex: 1;
      padding: 5px;
    }
    .input-wrapper {
      position: relative;
      width: 180px;
      margin-bottom: 10px;
    }
    .input-wrapper input {
      width: 100%;
      padding: 10px 35px 10px 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      background: #eaeaea;
    }
    .edit-icon-inside {
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      font-size: 16px;
      cursor: pointer;
      user-select: none;
    }
    .dashboard-section {
      background: #f9f9f9;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 0 5px rgba(0,0,0,0.05);
    }
    .dashboard-section.full-width {
      grid-column: span 2;
    }
    main {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    button {
      background: #2a374a;
      color: white;
      padding: 8px 12px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 10px;
    }
    .setup-form {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      width: 350px;
      text-align: center;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .hidden { display: none; }
  </style>
</head>
<body>
<?php if ($needsSetup): ?>
  <div class="setup-form">
    <h2>Set Up Your Profile</h2>
    <form method="POST">
      <input type="text" name="fullName" placeholder="Full Name" required>
      <input type="number" name="weight" placeholder="Weight in KG" required>
      <button type="submit" name="submitProfile">Save & Continue</button>
    </form>
  </div>
<?php else: ?>
  <div class="container">
    <header>
      <h1>Welcome, <?= htmlspecialchars($fullName) ?>!</h1>
      <div class="icons">
        <div class="notification-icon" onclick="toggleDropdown('notificationDropdown')">🔔</div>
        <div class="profile-icon" onclick="toggleDropdown('profileDropdown')">👤</div>

        <div id="notificationDropdown" class="dropdown">
          <?php foreach ($notifications as $notification): ?>
            <p><?= htmlspecialchars($notification) ?></p>
          <?php endforeach; ?>
        </div>

        <div id="profileDropdown" class="dropdown">
          <p>
            <?= htmlspecialchars($fullName) ?>
            <span class="edit-name" onclick="showEditName()">✏️</span>
          </p>
          <form method="POST" class="inline-form hidden" id="editNameForm">
            <input type="text" name="newName" placeholder="New name" required>
            <button type="submit" name="updateName">Save</button>
          </form>
          <form method="POST" action="logout.php" style="margin-top: 10px;">
            <button type="submit">Logout</button>
          </form>
        </div>
      </div>
    </header>

    <main>
      <section class="dashboard-section">
        <h2>Workout Schedule</h2>
        <button onclick="toggleSchedule()">View Full Schedule</button>
        <div id="workoutSchedule" class="hidden"><p id="scheduleContent"></p></div>
      </section>

      <section class="dashboard-section">
        <h2>Diet Plan</h2>
        <button onclick="togglePlan()">View Plan</button>
        <div id="dietPlan" class="hidden"><p id="dietContent"></p></div>
      </section>

      <section class="dashboard-section full-width">
        <h2>Progress Tracking</h2>
        <form method="POST">
          <div class="input-wrapper">
            <input type="number" name="weightInput" id="weightInput" step="0.1" placeholder="Today's weight"
                   value="<?= htmlspecialchars($todayWeight ?? '') ?>"
                   <?= $todayWeight ? 'readonly' : '' ?> required />
            <?php if ($todayWeight): ?>
              <span class="edit-icon-inside" id="editWeight">✏️</span>
            <?php endif; ?>
          </div>
          <button type="submit" name="logWeight">Log Weight</button>
        </form>
        <canvas id="progressChart"></canvas>
      </section>
    </main>
  </div>

  <script>
    const userPlan = "<?= $plan ?>";
    const weightLabels = <?= json_encode($dates) ?>;
    const weightData = <?= json_encode($weights) ?>;

    if (weightLabels.length) {
      new Chart(document.getElementById("progressChart").getContext("2d"), {
        type: "line",
        data: {
          labels: weightLabels,
          datasets: [{
            label: "Weight Progress",
            data: weightData,
            borderColor: "blue",
            fill: false,
            tension: 0.3
          }]
        }
      });
    }

    function toggleDropdown(id) {
      document.getElementById(id).classList.toggle("active");
    }

    function toggleSchedule() {
      document.getElementById("workoutSchedule").classList.toggle("hidden");
      const plans = {
        weightLoss: "• Monday: HIIT (20 min) + Core (Planks, Mountain Climbers, Leg Raises)\n" +
      "• Tuesday: Lower Body (Squats, Lunges, Glute Bridges)\n" +
      "• Wednesday: Cardio (Brisk Walking or Jogging - 40 mins) + Abs\n" +
      "• Thursday: Upper Body (Push-ups, Dumbbell Rows, Shoulder Press)\n" +
      "• Friday: Full Body Circuit (Burpees, Jump Squats, Push-ups, Plank)\n" +
      "• Saturday: Yoga + Mobility (30 mins)\n" +
      "• Sunday: Rest or Light Walking (20–30 mins)",
        weightGain: "• Monday: Chest & Triceps (Bench Press, Dumbbell Fly, Tricep Dips)\n" +
      "• Tuesday: Back & Biceps (Pull-Ups, Barbell Rows, Bicep Curls)\n" +
      "• Wednesday: Legs & Core (Squats, Lunges, Leg Press, Planks)\n" +
      "• Thursday: Rest or Active Recovery (Light Yoga/Stretching)\n" +
      "• Friday: Shoulders & Arms (Overhead Press, Lateral Raises, Hammer Curls)\n" +
      "• Saturday: Full Body Strength (Deadlifts, Push-ups, Dumbbell Rows)\n" +
      "• Sunday: Rest",
        normal: "• Monday: Full Body Strength (Squats, Push-ups, Rows, Plank)\n" +
      "• Tuesday: Light Cardio + Core (Cycling or Walking + Crunches)\n" +
      "• Wednesday: Flexibility/Yoga (Stretching, Breathing Exercises)\n" +
      "• Thursday: Upper Body (Push-ups, Shoulder Press, Curls)\n" +
      "• Friday: Legs & Core (Squats, Glute Bridges, Russian Twists)\n" +
      "• Saturday: Outdoor Fun Activity (Hike, Swim, Dance, or Long Walk)\n" +
      "• Sunday: Rest"
      };
      document.getElementById("scheduleContent").innerText = plans[userPlan];
    }

    function togglePlan() {
      document.getElementById("dietPlan").classList.toggle("hidden");
      const plans = {
        weightLoss:  "• Breakfast: Oats with almond milk, berries, chia seeds\n" +
      "• Snack: 1 boiled egg + green tea\n" +
      "• Lunch: Grilled chicken breast, quinoa, steamed broccoli\n" +
      "• Snack: Greek yogurt (low fat)\n" +
      "• Dinner: Mixed vegetable soup, tofu salad, herbal tea",
        weightGain:  "• Breakfast: 3 eggs, whole wheat toast, peanut butter, banana, full cream milk\n" +
      "• Snack: Protein shake + mixed nuts\n" +
      "• Lunch: Chicken breast or paneer, white rice, dal, ghee-roasted veggies\n" +
      "• Snack: Cheese sandwich + fruit juice\n" +
      "• Dinner: Steak or tofu, mashed potatoes, sautéed vegetables, milkshake before bed",
        normal:  "• Breakfast: Smoothie (banana, spinach, peanut butter, oats)\n" +
      "• Snack: Fruit salad or trail mix\n" +
      "• Lunch: Grilled paneer/chicken wrap, vegetable soup\n" +
      "• Snack: Buttermilk or boiled corn\n" +
      "• Dinner: Mixed veggie bowl, roti or brown rice, dal, light dessert like dark chocolate square"
      };
      document.getElementById("dietContent").innerText = plans[userPlan];
    }

    function showEditName() {
      document.getElementById("editNameForm").classList.toggle("hidden");
    }

    const editWeight = document.getElementById("editWeight");
    const weightInput = document.getElementById("weightInput");
    if (editWeight && weightInput) {
      editWeight.addEventListener("click", () => {
        weightInput.readOnly = false;
        weightInput.focus();
      });
    }
  </script>
<?php endif; ?>
</body>
</html>
