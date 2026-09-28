<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_admin();

$pdo->exec("UPDATE transactions SET status = 'overdue' WHERE status = 'borrowed' AND due_date < NOW()");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settle_payment') {
    $student_id = (int)$_POST['student_id'];
    $trans_id = (int)$_POST['transaction_id'];
    $amount = (float)($_POST['amount'] ?? 0);
    $notes = clean($_POST['notes'] ?? '');

    $t = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ? AND status = 'returned'");
    $t->execute([$trans_id, $student_id]);
    $t = $t->fetch();

    $outstanding = $t ? ($t['fine'] - $t['fine_paid']) : 0;

    if (!$t || $outstanding <= 0) {
        flash('error', 'That book has no outstanding fine to settle.');
    } elseif ($amount <= 0) {
        flash('error', 'Enter a valid amount greater than zero.');
    } else {
        $applied = min($amount, $outstanding);

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE transactions SET fine_paid = fine_paid + ? WHERE id = ?")->execute([$applied, $trans_id]);
        $pdo->prepare("INSERT INTO fine_payments (user_id, transaction_id, amount, notes, processed_by) VALUES (?,?,?,?,?)")
            ->execute([$student_id, $trans_id, $applied, $notes ?: null, $_SESSION['user_id']]);
        $pdo->commit();

        $bookTitle = $pdo->prepare("SELECT title FROM books WHERE id = ?");
        $bookTitle->execute([$t['book_id']]);
        $bookTitle = $bookTitle->fetchColumn();
        $msg = 'Payment of ₱' . number_format($applied, 2) . ' recorded for "' . $bookTitle . '".';
        if ($amount > $applied) {
            $msg .= ' Note: only ₱' . number_format($outstanding, 2) . ' was owed for that book, so only that much was applied.';
        }
        flash('success', $msg);
    }
    redirect('fines.php?student_id=' . $student_id);
}

$q = clean($_GET['q'] ?? '');
$listSql = "
    SELECT u.id, u.full_name, u.student_id, COALESCE(SUM(t.fine - t.fine_paid),0) AS balance
    FROM users u
    JOIN transactions t ON t.user_id = u.id AND t.status = 'returned' AND t.fine > t.fine_paid
    WHERE u.role = 'student'
";
$params = [];
if ($q !== '') {
    $listSql .= " AND (u.full_name LIKE ? OR u.student_id LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$listSql .= " GROUP BY u.id, u.full_name, u.student_id HAVING balance > 0 ORDER BY balance DESC";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$owingStudents = $stmt->fetchAll();

$selected = null;
$selectedLoans = [];
$paymentHistory = [];
if (!empty($_GET['student_id'])) {
    $sid = (int)$_GET['student_id'];
    $s = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $s->execute([$sid]);
    $selected = $s->fetch();

    if ($selected) {
        $l = $pdo->prepare("
            SELECT t.*, b.title FROM transactions t
            JOIN books b ON b.id = t.book_id
            WHERE t.user_id = ? AND t.status = 'returned' AND t.fine > 0
            ORDER BY t.due_date ASC
        ");
        $l->execute([$sid]);
        $selectedLoans = $l->fetchAll();

        $p = $pdo->prepare("
            SELECT fp.*, a.full_name AS admin_name, b.title AS book_title FROM fine_payments fp
            LEFT JOIN users a ON a.id = fp.processed_by
            LEFT JOIN transactions t ON t.id = fp.transaction_id
            LEFT JOIN books b ON b.id = t.book_id
            WHERE fp.user_id = ? ORDER BY fp.id DESC LIMIT 10
        ");
        $p->execute([$sid]);
        $paymentHistory = $p->fetchAll();
    }
}

$activePage = 'fines';
include '../includes/admin_header.php';
?>

<div class="topbar">
  <h2>Manage Fines</h2>
  <div class="user-chip"><?= clean($_SESSION['full_name']) ?> (Admin)</div>
</div>

<?php if ($m = flash('success')): ?><div class="alert alert-success"><?= clean($m) ?></div><?php endif; ?>
<?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= clean($m) ?></div><?php endif; ?>

<div class="panel">
  <h3 class="mt-0">Students with Outstanding Fines</h3>
  <form class="search-bar" method="GET">
    <input type="text" name="q" placeholder="Search by student name or ID..." value="<?= clean($q) ?>">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <table>
    <tr><th>Student</th><th>Student ID</th><th>Outstanding Balance</th><th></th></tr>
    <?php if (!$owingStudents): ?>
      <tr><td colspan="4" class="text-muted">No outstanding fines<?= $q !== '' ? ' matched your search' : '' ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($owingStudents as $s): ?>
      <tr>
        <td><?= clean($s['full_name']) ?></td>
        <td><?= clean($s['student_id'] ?: '—') ?></td>
        <td>₱<?= number_format($s['balance'], 2) ?></td>
        <td><a class="btn btn-sm btn-accent" href="fines.php?student_id=<?= $s['id'] ?>">Settle Payment</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($selected): ?>
<div class="panel">
  <h3 class="mt-0">Settle Payment — <?= clean($selected['full_name']) ?> (<?= clean($selected['student_id'] ?: '—') ?>)</h3>
  <p class="text-muted small">Choose which borrowed book the student wants to pay off. Each book's fine is settled separately.</p>

  <table>
    <tr><th>Book</th><th>Due Date</th><th>Returned</th><th>Fine</th><th>Paid</th><th>Outstanding</th><th></th></tr>
    <?php $totalOutstanding = 0; ?>
    <?php foreach ($selectedLoans as $t): $out = $t['fine'] - $t['fine_paid']; if ($out <= 0) continue; $totalOutstanding += $out; ?>
      <tr>
        <td><?= clean($t['title']) ?></td>
        <td><?= $t['due_date'] ?></td>
        <td><?= $t['return_date'] ?></td>
        <td>₱<?= number_format($t['fine'], 2) ?></td>
        <td>₱<?= number_format($t['fine_paid'], 2) ?></td>
        <td>₱<?= number_format($out, 2) ?></td>
        <td>
          <form method="POST" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;" onsubmit="return confirm('Record this payment for <?= clean(addslashes($t['title'])) ?>?');">
            <input type="hidden" name="action" value="settle_payment">
            <input type="hidden" name="student_id" value="<?= $selected['id'] ?>">
            <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
            <input type="number" name="amount" step="0.01" min="0.01" max="<?= $out ?>" value="<?= $out ?>" required style="width:90px; padding:6px; border-radius:6px; border:1px solid #e1e6e3;">
            <input type="text" name="notes" placeholder="Notes (optional)" style="width:140px; padding:6px; border-radius:6px; border:1px solid #e1e6e3;">
            <button type="submit" class="btn btn-sm btn-success">Pay</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($totalOutstanding <= 0): ?>
      <tr><td colspan="7" class="text-muted">This student has no outstanding fines.</td></tr>
    <?php endif; ?>
  </table>

  <?php if ($totalOutstanding > 0): ?>
    <p><strong>Total Outstanding (all books): ₱<?= number_format($totalOutstanding, 2) ?></strong></p>
  <?php endif; ?>

  <?php if ($paymentHistory): ?>
    <h4>Recent Payments</h4>
    <table>
      <tr><th>Date</th><th>Book</th><th>Amount</th><th>Received By</th><th>Notes</th></tr>
      <?php foreach ($paymentHistory as $p): ?>
        <tr>
          <td><?= $p['created_at'] ?></td>
          <td><?= clean($p['book_title'] ?: '—') ?></td>
          <td>₱<?= number_format($p['amount'], 2) ?></td>
          <td><?= clean($p['admin_name'] ?: '—') ?></td>
          <td><?= clean($p['notes'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include '../includes/admin_footer.php'; ?>
