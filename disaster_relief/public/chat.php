<?php
$page_title = "Chat | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_login();

$users = $pdo->prepare("SELECT user_id, full_name FROM users WHERE user_id != ? AND account_status='Approved' ORDER BY full_name");
$users->execute([$_SESSION['user_id']]);
$users = $users->fetchAll();

$messages = $pdo->prepare("
    SELECT cm.*, s.full_name AS sender_name, r.full_name AS receiver_name
    FROM chat_messages cm
    JOIN users s ON cm.sender_id=s.user_id
    JOIN users r ON cm.receiver_id=r.user_id
    WHERE cm.sender_id=? OR cm.receiver_id=?
    ORDER BY cm.sent_at DESC
    LIMIT 20
");
$messages->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$messages = $messages->fetchAll();

include "../includes/header.php";
?>
<main class="container">
  <section class="page-title"><span class="badge">Chat</span><h1>Internal Communication</h1></section>

  <section class="card">
    <form method="POST" action="process_chat.php">
      <label>Receiver</label>
      <select name="receiver_id"><?php foreach($users as $u): ?><option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option><?php endforeach; ?></select><br><br>
      <label>Message</label><textarea name="message" required></textarea><br>
      <button class="btn">Send Message</button>
    </form>
  </section>

  <section class="section card">
    <h2>Recent Messages</h2>
    <div class="chat-box">
      <?php foreach($messages as $m): ?>
        <div class="msg <?php echo $m['sender_id']==$_SESSION['user_id'] ? 'me' : ''; ?>">
          <strong><?php echo htmlspecialchars($m['sender_name']); ?>:</strong>
          <?php echo htmlspecialchars($m['message']); ?>
          <br><span class="small"><?php echo htmlspecialchars($m['sent_at']); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<?php include "../includes/footer.php"; ?>
