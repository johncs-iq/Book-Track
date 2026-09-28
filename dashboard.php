<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_admin();

$pdo->exec("UPDATE transactions SET status = 'overdue' WHERE status = 'borrowed' AND due_date < NOW()");

$totalBooks = $pdo->query("SELECT COALESCE(SUM(total_copies),0) FROM books")->fetchColumn();
$availableBooks = $pdo->query("SELECT COALESCE(SUM(available_copies),0) FROM books")->fetchColumn();
$borrowedCount = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status IN ('borrowed','overdue')")->fetchColumn();
$overdueCount = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'overdue'")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();
$studentCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalOutstandingFines = $pdo->query("SELECT COALESCE(SUM(fine - fine_paid),0) FROM transactions WHERE status = 'returned' AND fine > fine_paid")->fetchColumn();

$recent = $pdo->query("
    SELECT t.*, b.title, u.full_name
    FROM transactions t
    JOIN books b ON b.id = t.book_id
    JOIN users u ON u.id = t.user_id
    ORDER BY t.id DESC LIMIT 8
")->fetchAll();

$activePage = 'dashboard';
include '../includes/admin_header.php';
?>

<div class="topbar">
  <h2>Dashboard</h2>
  <div class="user-chip"><?= clean($_SESSION['full_name']) ?> (Admin)</div>
</div>

<div class="cards-row">
  <div class="stat-card">
    <div class="num"><?= $totalBooks ?></div>
    <div class="label">Total Book Copies</div>
  </div>
  <div class="stat-card accent">
    <div class="num"><?= $availableBooks ?></div>
    <div class="label">Available Copies</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= $borrowedCount ?></div>
    <div class="label">Currently Borrowed</div>
  </div>
  <div class="stat-card danger">
    <div class="num"><?= $overdueCount ?></div>
    <div class="label">Overdue</div>
  </div>
  <div class="stat-card warn">
    <div class="num"><?= $pendingCount ?></div>
    <div class="label">Pending Requests</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= $studentCount ?></div>
    <div class="label">Registered Students</div>
  </div>
  <div class="stat-card danger">
    <div class="num">₱<?= number_format($totalOutstandingFines, 2) ?></div>
    <div class="label">Outstanding Fines</div>
  </div>
</div>

<div class="panel">
  <h3>Quick Actions</h3>
  <a href="return_book.php" class="btn btn-primary"><i class="fa-solid fa-rotate-left"></i> Process a Return</a>
  <a href="requests.php" class="btn btn-accent"><i class="fa-solid fa-inbox"></i> View Pending Requests (<?= $pendingCount ?>)</a>
  <a href="fines.php" class="btn btn-danger"><i class="fa-solid fa-peso-sign"></i> Manage Fines</a>
  <a href="books.php" class="btn btn-success"><i class="fa-solid fa-plus"></i> Add New Book</a>
</div>

<div class="panel">
  <h3>Recent Activity</h3>
  <table>
    <tr><th>Book</th><th>Borrower</th><th>Borrow Date</th><th>Due Date</th><th>Status</th></tr>
    <?php if (!$recent): ?>
      <tr><td colspan="5" class="text-muted">No transactions yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= clean($r['title']) ?></td>
        <td><?= clean($r['full_name']) ?></td>
        <td><?= $r['borrow_date'] ?: '—' ?></td>
        <td><?= $r['due_date'] ?: '—' ?></td>
        <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php include '../includes/admin_footer.php'; ?>
