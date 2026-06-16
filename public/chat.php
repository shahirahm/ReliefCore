<?php
$page_title = "Chat | Disaster Relief System";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";
require_login();

$user_id = $_SESSION['user_id'];
$role = current_user_role();


$pdo->exec("
    CREATE TABLE IF NOT EXISTS affected_user_links (
        link_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        family_id INT,
        chat_allowed TINYINT(1) DEFAULT 0,
        support_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (family_id) REFERENCES affected_families(family_id) ON DELETE SET NULL
    )
");


if ($role === 'Affected Person') {
    $stmt = $pdo->prepare("SELECT phone FROM users WHERE user_id=?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch();

    if (!empty($me['phone'])) {
        $stmt = $pdo->prepare("SELECT link_id FROM affected_user_links WHERE user_id=?");
        $stmt->execute([$user_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("SELECT family_id FROM affected_families WHERE phone=? ORDER BY family_id DESC LIMIT 1");
            $stmt->execute([$me['phone']]);
            $fam = $stmt->fetch();
            if ($fam) {
                $stmt = $pdo->prepare("INSERT INTO affected_user_links (user_id, family_id, chat_allowed, support_note) VALUES (?, ?, 0, 'Auto-linked by matching phone number. Admin can allow camp support chat.')");
                $stmt->execute([$user_id, $fam['family_id']]);
            }
        }
    }
}

function get_allowed_receivers($pdo, $user_id, $role) {
    if ($role === 'Admin') {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.full_name, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.user_id != ?
            AND u.account_status='Approved'
            ORDER BY r.role_name, u.full_name
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    if ($role === 'Camp Manager') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.user_id, u.full_name, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.account_status='Approved'
            AND u.user_id != ?
            AND (
                r.role_name='Admin'
                OR (
                    r.role_name='Volunteer'
                    AND u.user_id IN (
                        SELECT vt.volunteer_id
                        FROM volunteer_tasks vt
                        JOIN relief_camps rc ON vt.camp_id = rc.camp_id
                        WHERE rc.manager_id = ?
                        AND vt.volunteer_id IS NOT NULL
                    )
                )
            )
            ORDER BY r.role_name, u.full_name
        ");
        $stmt->execute([$user_id, $user_id]);
        return $stmt->fetchAll();
    }

    if ($role === 'Volunteer') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT m.user_id, m.full_name, r.role_name
            FROM volunteer_tasks vt
            JOIN relief_camps rc ON vt.camp_id = rc.camp_id
            JOIN users m ON rc.manager_id = m.user_id
            JOIN roles r ON m.role_id = r.role_id
            WHERE vt.volunteer_id = ?
            AND m.account_status='Approved'
            ORDER BY m.full_name
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    if ($role === 'Donor') {
        $stmt = $pdo->query("
            SELECT u.user_id, u.full_name, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE r.role_name='Admin'
            AND u.account_status='Approved'
            ORDER BY u.full_name
        ");
        return $stmt->fetchAll();
    }

    if ($role === 'Affected Person') {
        $stmt = $pdo->prepare("
            SELECT aul.chat_allowed, rc.manager_id
            FROM affected_user_links aul
            LEFT JOIN affected_families af ON aul.family_id = af.family_id
            LEFT JOIN relief_camps rc ON af.camp_id = rc.camp_id
            WHERE aul.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $link = $stmt->fetch();

        if (!$link || (int)$link['chat_allowed'] !== 1) {
            return [];
        }

        $params = [];
        $sql = "
            SELECT DISTINCT u.user_id, u.full_name, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.account_status='Approved'
            AND (
                r.role_name='Admin'
        ";

        if (!empty($link['manager_id'])) {
            $sql .= " OR u.user_id = ? ";
            $params[] = $link['manager_id'];
        }

        $sql .= ") ORDER BY r.role_name, u.full_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    return [];
}

$users = get_allowed_receivers($pdo, $user_id, $role);
$allowed_ids = array_map(fn($u) => (int)$u['user_id'], $users);

if (count($allowed_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
    $params = array_merge([$user_id, $user_id], $allowed_ids, $allowed_ids);

    $stmt = $pdo->prepare("
        SELECT cm.*, s.full_name AS sender_name, sr.role_name AS sender_role,
               r.full_name AS receiver_name, rr.role_name AS receiver_role
        FROM chat_messages cm
        JOIN users s ON cm.sender_id=s.user_id
        JOIN roles sr ON s.role_id=sr.role_id
        JOIN users r ON cm.receiver_id=r.user_id
        JOIN roles rr ON r.role_id=rr.role_id
        WHERE (cm.sender_id=? OR cm.receiver_id=?)
        AND (
            cm.sender_id IN ($placeholders)
            OR cm.receiver_id IN ($placeholders)
            OR cm.sender_id=?
            OR cm.receiver_id=?
        )
        ORDER BY cm.sent_at DESC
        LIMIT 50
    ");
    $stmt->execute(array_merge($params, [$user_id, $user_id]));
    $messages = $stmt->fetchAll();
} else {
    $messages = [];
}

include "../includes/header.php";
?>

<main class="container">
  <section class="page-title">
    <span class="badge">Role-based Chat</span>
    <h1>Internal Communication</h1>
    <p>
      <?php if($role === 'Camp Manager'): ?>
        You can chat with volunteers assigned to your camp and admins.
      <?php elseif($role === 'Volunteer'): ?>
        You can chat with the camp manager of your assigned task camp.
      <?php elseif($role === 'Donor'): ?>
        Donors can chat with admins only.
      <?php elseif($role === 'Affected Person'): ?>
        Affected people can chat with camp support only after admin allows support chat.
      <?php else: ?>
        Admin can communicate with approved users.
      <?php endif; ?>
    </p>
  </section>

  <section class="card">
    <h2>Send Message</h2>

    <?php if(count($users) > 0): ?>
      <form method="POST" action="process_chat.php">
        <label>Receiver</label>
        <select name="receiver_id" required>
          <?php foreach($users as $u): ?>
            <option value="<?php echo $u['user_id']; ?>">
              <?php echo htmlspecialchars($u['full_name']); ?> — <?php echo htmlspecialchars($u['role_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <br><br>

        <label>Message</label>
        <textarea name="message" required></textarea>
        <br>

        <button class="btn">Send Message</button>
      </form>
    <?php else: ?>
      <p>No chat receiver is currently available for your role.</p>
      <?php if($role === 'Affected Person'): ?>
        <p class="anchor-note">Your camp support chat may not be allowed yet. Submit a help request or contact support.</p>
      <?php elseif($role === 'Volunteer'): ?>
        <p class="anchor-note">You need an assigned task linked to a camp manager before chat is available.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="section card">
    <h2>Recent Messages</h2>
    <div class="chat-box">
      <?php foreach($messages as $m): ?>
        <div class="msg <?php echo $m['sender_id']==$user_id ? 'me' : ''; ?>">
          <strong>
            <?php echo htmlspecialchars($m['sender_name']); ?>
            <span class="small">(<?php echo htmlspecialchars($m['sender_role']); ?>)</span>
            →
            <?php echo htmlspecialchars($m['receiver_name']); ?>
            <span class="small">(<?php echo htmlspecialchars($m['receiver_role']); ?>)</span>
          </strong>
          <br>
          <?php echo htmlspecialchars($m['message']); ?>
          <br>
          <span class="small"><?php echo htmlspecialchars($m['sent_at']); ?></span>
        </div>
      <?php endforeach; ?>

      <?php if(count($messages) === 0): ?>
        <p>No messages found.</p>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include "../includes/footer.php"; ?>
