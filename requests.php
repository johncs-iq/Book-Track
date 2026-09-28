<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)$_POST['id'];

    $t = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status='pending'");
    $t->execute([$id]);
    $t = $t->fetch();

    if ($t) {
        if ($action === 'approve') {
            $book = $pdo->prepare("SELECT * FROM books WHERE id = ?");
            $book->execute([$t['book_id']]);
            $book = $book->fetch();

            if ($book['available_copies'] < 1) {
                flash('error', 'Cannot approve: no available copies left.');
            } else {
                $borrow_date = date('Y-m-d H:i:s');
                $due_date = (defined('TEST_MODE_MINUTES') && TEST_MODE_MINUTES)
                    ? date('Y-m-d H:i:s', strtotime("+" . LOAN_DAYS . " minutes"))
                    : date('Y-m-d H:i:s', strtotime("+" . LOAN_DAYS . " days"));
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE transactions SET status='borrowed', borrow_date=?, due_date=?, processed_by=? WHERE id=?")
                    ->execute([$borrow_date, $due_date, $_SESSION['user_id'], $id]);
                $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?")->execute([$t['book_id']]);
                $pdo->commit();
                flash('success', "Request approved. Due on $due_date.");
            }
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE transactions SET status='rejected', processed_by=? WHERE id=?")->execute([$_SESSION['user_id'], $id]);
            flash('success', 'Request rejected.');
        }
    }
    redirect('requests.php');
}

$pending = $pdo->query("
    SELECT t.*, b.title, b.available_copies, u.full_name, u.student_id
    FROM transactions t
    JOIN books b ON b.id = t.book_id
    JOIN users u ON u.id = t.user_id
    WHERE t.status = 'pending'
    ORDER BY t.created_at ASC
")->fetchAll();

$activePage = 'requests';
include '../includes/admin_header.php';
?>

<div class="topbar">
  <h2>Pending Borrow Requests</h2>
  <div class="user-chip"><?= clean($_SESSION['full_name']) ?> (Admin)</div>
</div>

<?php if ($m = flash('success')): ?><div class="alert alert-success"><?= clean($m) ?></div><?php endif; ?>
<?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= clean($m) ?></div><?php endif; ?>

<div class="panel">
  <p class="text-muted">These are borrow requests submitted by students from their portal. Approve to hand out the book, or reject if unavailable.</p>
  <table>
    <tr><th>Book</th><th>Student</th><th>Requested On</th><th>Available Copies</th><th>Actions</th></tr>
    <?php if (!$pending): ?>
      <tr><td colspan="5" class="text-muted">No pending requests.</td></tr>
    <?php endif; ?>
    <?php foreach ($pending as $p): ?>
      <tr>
        <td><?= clean($p['title']) ?></td>
        <td><?= clean($p['full_name']) ?> (<?= clean($p['student_id']) ?>)</td>
        <td><?= date('M d, Y g:i A', strtotime($p['created_at'])) ?></td>
        <td><?= $p['available_copies'] ?></td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-success" type="submit" <?= $p['available_copies'] < 1 ? 'disabled' : '' ?>>Approve</button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Reject this request?');">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php include '../includes/admin_footer.php'; ?>
