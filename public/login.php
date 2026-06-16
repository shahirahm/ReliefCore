<?php
$page_title = "Login | Disaster Relief System";
include "../includes/header.php";
?>
<main class="login-shell">
  <form class="login-card" method="POST" action="process_login.php">
    <span class="badge">User Login</span>
    <h1>Sign in</h1>
    <p>Login using your approved email and password.</p>

    <label>Email</label>
    <input type="email" name="email" required placeholder="example@email.com"><br><br>

    <label>Password</label>
    <input type="password" name="password" required placeholder="Password"><br><br>

    <button class="btn" type="submit">Login</button>
    <a class="btn btn-outline" href="register.php">Register</a>
  </form>
</main>
<?php include "../includes/footer.php"; ?>
