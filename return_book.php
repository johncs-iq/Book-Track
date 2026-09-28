<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_admin();

$pdo->exec("UPDATE transactions SET status = 'overdue' WHERE status = 'borrowed' AND due_date < NOW()");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_return') {
    $trans_id = (int)$_POST['trans_id'];

    $t = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $t->execute([$trans_id]);
    $t = $t->fetch();

    if ($t && in_array($t['status'], ['borrowed', 'overdue'])) {
        $today = date('Y-m-d H:i:s');
        $fine = calculate_fine($t['due_date'], $today);

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE transactions SET status='returned', return_date=?, fine=? WHERE id=?")
            ->execute([$today, $fine, $trans_id]);
        $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?")->execute([$t['book_id']]);
        $pdo->commit();

        $msg = 'Book marked as returned.';
        if ($fine > 0) $msg .= " Fine due: ₱" . number_format($fine, 2);
        flash('success', $msg);
    } else {
        flash('error', 'Transaction not found or already returned.');
    }
    redirect('return_book.php');
}

$activeLoansQ = clean($_GET['loanq'] ?? '');
if ($activeLoansQ !== '') {
    $stmt = $pdo->prepare("
        SELECT t.*, b.title, u.full_name FROM transactions t
        JOIN books b ON b.id=t.book_id JOIN users u ON u.id=t.user_id
        WHERE t.status IN ('borrowed','overdue') AND (b.title LIKE ? OR u.full_name LIKE ? OR u.student_id LIKE ?)
        ORDER BY t.due_date ASC
    ");
    $like = "%$activeLoansQ%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("
        SELECT t.*, b.title, u.full_name FROM transactions t
        JOIN books b ON b.id=t.book_id JOIN users u ON u.id=t.user_id
        WHERE t.status IN ('borrowed','overdue')
        ORDER BY t.due_date ASC
    ");
}
$activeLoans = $stmt->fetchAll();

$activePage = 'return_book';
include '../includes/admin_header.php';
?>

<div class="topbar">
  <h2>Return a Book</h2>
  <div class="user-chip"><?= clean($_SESSION['full_name']) ?> (Admin)</div>
</div>

<?php if ($m = flash('success')): ?><div class="alert alert-success"><?= clean($m) ?></div><?php endif; ?>
<?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= clean($m) ?></div><?php endif; ?>

<div class="panel">
  <form class="search-bar" method="GET">
    <input type="text" name="loanq" placeholder="Search active loans by book, student name, or ID..." value="<?= clean($activeLoansQ) ?>">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <table>
    <tr><th>Book</th><th>Borrower</th><th>Due Date</th><th>Status</th><th></th></tr>
    <?php if (!$activeLoans): ?>
      <tr><td colspan="5" class="text-muted">No active loans<?= $activeLoansQ !== '' ? ' matched your search' : '' ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($activeLoans as $t): ?>
      <tr>
        <td><?= clean($t['title']) ?></td>
        <td><?= clean($t['full_name']) ?></td>
        <td><?= $t['due_date'] ?></td>
        <td><span class="badge badge-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
        <td>
          <form method="POST" onsubmit="return confirm('Mark this book as returned?');">
            <input type="hidden" name="action" value="quick_return">
            <input type="hidden" name="trans_id" value="<?= $t['id'] ?>">
            <button class="btn btn-sm btn-accent" type="submit">Return Now</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php include '../includes/admin_footer.php'; ?>
