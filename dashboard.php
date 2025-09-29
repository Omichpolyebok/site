<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
echo "<h1>Dashboard</h1><p>User id: ".htmlspecialchars((int)$_SESSION['user_id'])."</p>";
echo '<p><a href="logout.php">Logout</a></p>';
