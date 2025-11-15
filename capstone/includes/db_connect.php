<?php
try {
  $dsn = "pgsql:host=gondola.proxy.rlwy.net;port=27436;dbname=railway;";
  $pdo = new PDO($dsn, "postgres", "WkzkMhBNHYDiSkYpAHbWfCMJzINdKidg", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

  echo "Connected successfully!<br>";

  $stmt = $pdo->query("SELECT * FROM users");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Name: " . $row['full_name'] . " | Email: " . $row['email'] . "<br>";
  }

} catch (PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
}
?>
