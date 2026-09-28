<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_admin();

$pdo->exec("UPDATE transactions SET status = 'overdue' WHERE status = 'borrowed' AND due_date < NOW()");

$status = $_GET['status'] ?? 'all';
$q = clean($_GET['q'] ?? '');

$sql = "SELECT t.*, b.title, u.full_name, u.student_id FROM transactions t
        JOIN books b ON b.id=t.book_id JOIN users u ON u.id=t.user_id WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}
if ($q !== '') {
    $sql .= " AND (b.title LIKE ? OR u.full_name LIKE ? OR u.student_id LIKE ?)";
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$activePage = 'transactions';
include '../includes/admin_header.php';
?>

<div class="topbar">
  <h2>Transaction History</h2>
  <div class="user-chip"><?= clean($_SESSION['full_name']) ?> (Admin)</div>
</div>

<div class="panel">
  <form class="toolbar" method="GET">
    <input type="text" name="q" placeholder="Search book, student, or ID..." value="<?= clean($q) ?>" style="flex:1; padding:10px; border-radius:8px; border:1px solid #e1e6e3; min-width:220px;">
    <select name="status" style="padding:10px; border-radius:8px; border:1px solid #e1e6e3;">
      <?php foreach (['all','pending','borrowed','overdue','returned','rejected'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
  </form>

  <table>
    <tr><th>Book</th><th>Student</th><th>Borrow Date</th><th>Due Date</th><th>Return Date</th><th>Fine</th><th>Status</th></tr>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="text-muted">No records found.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= clean($r['title']) ?></td>
        <td><?= clean($r['full_name']) ?> (<?= clean($r['student_id']) ?>)</td>
        <td><?= $r['borrow_date'] ?: '—' ?></td>
        <td><?= $r['due_date'] ?: '—' ?></td>
        <td><?= $r['return_date'] ?: '—' ?></td>
        <td>
          <?php $out = $r['fine'] - $r['fine_paid']; ?>
          <?php if ($r['fine'] <= 0): ?>
            —
          <?php elseif ($out <= 0): ?>
            ₱<?= number_format($r['fine'], 2) ?> <span class="badge badge-returned">Paid</span>
          <?php else: ?>
            ₱<?= number_format($out, 2) ?> due
            <?php if ($r['fine_paid'] > 0): ?>
              <br><small class="text-muted">(₱<?= number_format($r['fine_paid'], 2) ?> paid of ₱<?= number_format($r['fine'], 2) ?>)</small>
            <?php else: ?>
              <br><a href="fines.php?student_id=<?= $r['user_id'] ?>" class="small">Settle payment</a>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php include '../includes/admin_footer.php'; ?>
