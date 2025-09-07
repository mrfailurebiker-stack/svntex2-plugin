
<?php
// Simple KYC testing code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = htmlspecialchars($_POST['name'] ?? '');
	$id = htmlspecialchars($_POST['id'] ?? '');
	echo "<h2>KYC Submitted</h2>";
	echo "<p>Name: $name</p>";
	echo "<p>ID: $id</p>";
} else {
	echo '<form method="post">';
	echo '<label>Name: <input type="text" name="name" required></label><br>';
	echo '<label>ID: <input type="text" name="id" required></label><br>';
	echo '<button type="submit">Submit KYC</button>';
	echo '</form>';
}
?>
