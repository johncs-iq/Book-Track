<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ? AND role='student'");
        $stmt->execute([$id]);
        flash('success', 'Student status updated.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $active = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status IN ('borrowed','overdue','pending')");
        $active->execute([$id]);
        if ($active->fetchColumn() > 0) {
            flash('error', 'Cannot delete: student has active loans or requests.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role='student'")->execute([$id]);
            flash('success', 'Student removed.');
        }
    }
    redirect('students.php');
}

$q = clean($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role='student' AND (full_name LIKE ? OR username LIKE ? OR student_id LIKE ?) ORDER BY full_name");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM users WHERE role='student' ORDER BY full_name");
}
$students = $stmt->fetchAll();

$activePage = 'students';
include '../includes/admin_header.php';
?>

<div class="topbar">
  <h2>Manage Students</h2>
  <div class="user-chip"><?= clean($_SESSION['full_name']) ?> (Admin)</div>
</div>

<?php if ($m = flash('success')): ?><div class="alert alert-success"><?= clean($m) ?></div><?php endif; ?>
<?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= clean($m) ?></div><?php endif; ?>

<div class="toolbar">
  <form class="search-bar" method="GET" style="margin:0; flex:1; max-width:400px;">
    <input type="text" name="q" placeholder="Search by name, username, or student ID..." value="<?= clean($q) ?>">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>
</div>

<div class="panel">
  <table>
    <tr><th>Full Name</th><th>Student ID</th><th>Username</th><th>Email</th><th>Status</th><th>Actions</th></tr>
    <?php if (!$students): ?>
      <tr><td colspan="6" class="text-muted">No students found.</td></tr>
    <?php endif; ?>
    <?php foreach ($students as $s): ?>
      <tr>
        <td><?= clean($s['full_name']) ?></td>
        <td><?= clean($s['student_id']) ?></td>
        <td><?= clean($s['username']) ?></td>
        <td><?= clean($s['email']) ?></td>
        <td><span class="badge <?= $s['status']==='active' ? 'badge-returned' : 'badge-rejected' ?>"><?= ucfirst($s['status']) ?></span></td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-sm btn-primary" type="submit"><?= $s['status']==='active' ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Remove this student?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php include '../includes/admin_footer.php'; ?>
