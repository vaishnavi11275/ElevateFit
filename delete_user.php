<?php
if (isset($_GET['id'])) {
    $conn = new mysqli("localhost", "root", "", "elevatefit");
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
header("Location: admin.php");
exit;
?>
