<?php
require_once __DIR__ . "/auth.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title ?? "Disaster Relief System"; ?></title>
  <link rel="stylesheet" href="css/camp-manager.css">
</head>
<body>
  <div id="appAlert" class="alert"></div>

  <?php if (isset($_SESSION['success'])): ?>
    <script>
      window.addEventListener("DOMContentLoaded", function(){
        showAlert("<?php echo addslashes($_SESSION['success']); ?>", "success");
      });
    </script>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <script>
      window.addEventListener("DOMContentLoaded", function(){
        showAlert("<?php echo addslashes($_SESSION['error']); ?>", "error");
      });
    </script>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <script>
      window.addEventListener("DOMContentLoaded", function(){
        showAlert("<?php echo addslashes($_GET['success']); ?>", "success");
      });
    </script>
  <?php endif; ?>

  <?php if (isset($_GET['error'])): ?>
    <script>
      window.addEventListener("DOMContentLoaded", function(){
        showAlert("<?php echo addslashes($_GET['error']); ?>", "error");
      });
    </script>
  <?php endif; ?>

  <header class="navbar">
    <div class="container nav-inner">
      <a href="index.php" class="brand">
        <span class="logo">✚</span>
        <span>Disaster<br><small>RELIEF NETWORK</small></span>
      </a>

      <nav class="nav-links">
        <a href="index.php#home">Home</a>
        <a href="index.php#about">About</a>
        <a href="index.php#features">Features</a>
        <a href="index.php#active-camps">Active Camps</a>
        <a href="index.php#donate">Donate</a>
        <a href="guest.php#contact">Contact</a>

        <?php if (is_logged_in()): ?>
          <?php
            $role = current_user_role();
            $dashboard = "index.php";
            if ($role === "Admin") $dashboard = "admin.php";
            if ($role === "Camp Manager") $dashboard = "camp-manager.php";
            if ($role === "Volunteer") $dashboard = "volunteer.php";
            if ($role === "Donor") $dashboard = "donor.php";
            if ($role === "Affected Person") $dashboard = "affected-person.php";
          ?>
          <a href="<?php echo $dashboard; ?>">Dashboard</a>
          <a href="logout.php">Logout</a>
        <?php else: ?>
          <a href="login.php">Login</a>
          <a class="signup-link" href="register.php">Sign Up</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
