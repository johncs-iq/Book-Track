<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_admin();

$pdo->exec("UPDATE transactions SET status = 'overdue' WHERE status = 'borrowed' AND due_date < CURDATE()");


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_borrow') {
    $book_id = (int)$_POST['book_id'];
    $user_id = (int)$_POST['user_id'];

    $book = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $book->execute([$book_id]);
    $book = $book->fetch();

    if (!$book || $book['available_copies'] < 1) {
        flash('error', 'This book has no available copies right now.');
    } else {
        $borrow_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime("+" . LOAN_DAYS . " days"));

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO transactions (book_id, user_id, borrow_date, due_date, status, processed_by) VALUES (?,?,?,?,'borrowed',?)")
            ->execute([$book_id, $user_id, $borrow_date, $due_date, $_SESSION['user_id']]);
        $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?")->execute([$book_id]);
        $pdo->commit();

        flash('success', "Book borrowed successfully. Due on $due_date.");
    }
    redirect('borrow_return.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_return') {
    $trans_id = (int)$_POST['trans_id'];

    $t = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $t->execute([$trans_id]);
    $t = $t->fetch();

    if ($t && in_array($t['status'], ['borrowed', 'overdue'])) {
        $today = date('Y-m-d');
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
    redirect('borrow_return.php');
}

$bookQ = clean($_GET['bookq'] ?? '');
$books = [];
if ($bookQ !== '') {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE (title LIKE ? OR isbn LIKE ? OR author LIKE ?) AND available_copies > 0 ORDER BY title LIMIT 10");
    $like = "%$bookQ%";
    $stmt->execute([$like, $like, $like]);
    $books = $stmt->fetchAll();
}

$students = $pdo->query("SELECT id, full_name, student_id, username FROM users WHERE role='student' AND status='active' ORDER BY full_name")->fetchAll();

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

$activePage = 'borrow_return';
include '../includes/admin_header.php';
?>

<div class="topbar">
  <h2>⚡ Borrow / Return Counter</h2>
  <div class="user-chip">👤 <?= clean($_SESSION['full_name']) ?> (Admin)</div>
</div>

<?php if ($m = flash('success')): ?><div class="alert alert-success"><?= clean($m) ?></div><?php endif; ?>
<?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= clean($m) ?></div><?php endif; ?>

<div class="panel">
  <h3 class="mt-0">1. Borrow a Book</h3>
  <form class="search-bar" method="GET">
    <input type="hidden" name="loanq" value="<?= clean($activeLoansQ) ?>">
    <input type="text" name="bookq" placeholder="Search available book by title, author, or ISBN..." value="<?= clean($bookQ) ?>">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <?php if ($bookQ !== ''): ?>
    <?php if (!$books): ?>
      <p class="text-muted">No available books matched your search.</p>
    <?php else: ?>
      <table>
        <tr><th>Title</th><th>Author</th><th>Available</th><th>Assign to student &amp; borrow</th></tr>
        <?php foreach ($books as $b): ?>
          <tr>
            <td><?= clean($b['title']) ?></td>
            <td><?= clean($b['author']) ?></td>
            <td><?= $b['available_copies'] ?></td>
            <td>
              <form method="POST" style="display:flex; gap:6px;">
                <input type="hidden" name="action" value="quick_borrow">
                <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                <select name="user_id" required style="flex:1; padding:6px; border-radius:6px; border:1px solid #e1e6e3;">
                  <option value="">Select student...</option>
                  <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= clean($s['full_name']) ?> (<?= clean($s['student_id']) ?>)</option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-success" type="submit">Borrow Now</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  <?php else: ?>
    <p class="text-muted small">Type in the search box to find a book instantly.</p>
  <?php endif; ?>
</div>

<div class="panel">
  <h3 class="mt-0">2. Return a Book</h3>
  <form class="search-bar" method="GET">
    <input type="hidden" name="bookq" value="<?= clean($bookQ) ?>">
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
