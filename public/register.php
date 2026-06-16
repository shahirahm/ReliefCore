<?php
$page_title = "Register | Disaster Relief System";
require_once "../config/database.php";
$roles = $pdo->query("SELECT * FROM roles WHERE role_name != 'Admin' ORDER BY role_name")->fetchAll();
include "../includes/header.php";
?>
<main class="login-shell">
  <form class="login-card" method="POST" action="process_register.php">
    <span class="badge">Registration</span>
    <h1>Create Account</h1>
    <p>New volunteer, donor and affected person accounts need admin approval.</p>

    <label>Full Name</label>
    <input type="text" name="full_name" required><br><br>

    <label>Email</label>
    <input type="email" name="email" required><br><br>

    <label>Phone</label>
    <input type="text" name="phone"><br><br>

    <label>Role</label>
    <select name="role_id" required>
      <option value="">Select role</option>
      <?php foreach($roles as $role): ?>
        <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
      <?php endforeach; ?>
    </select><br><br>

    <label>Password</label>
    <input type="password" name="password" required><br><br>

    <button class="btn" type="submit">Submit Registration</button>
  </form>
</main>
<?php include "../includes/footer.php"; ?>
