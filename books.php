<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $isbn = clean($_POST['isbn']);
        $title = clean($_POST['title']);
        $author = clean($_POST['author']);
        $category = clean($_POST['category']);
        $shelf = clean($_POST['shelf_location']);
        $total = max(1, (int)$_POST['total_copies']);

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO books (isbn, title, author, category, shelf_location, total_copies, available_copies) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$isbn, $title, $author, $category, $shelf, $total, $total]);
            flash('success', 'Book added successfully.');
        } else {
            $id = (int)$_POST['id'];
            $current = $pdo->prepare("SELECT total_copies, available_copies FROM books WHERE id = ?");
            $current->execute([$id]);
            $row = $current->fetch();
            $borrowedOut = $row['total_copies'] - $row['available_copies'];
            $newAvailable = max(0, $total - $borrowedOut);

            $stmt = $pdo->prepare("UPDATE books SET isbn=?, title=?, author=?, category=?, shelf_location=?, total_copies=?, available_copies=? WHERE id=?");
            $stmt->execute([$isbn, $title, $author, $category, $shelf, $total, $newAvailable, $id]);
            flash('success', 'Book updated successfully.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $active = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE book_id = ? AND status IN ('borrowed','overdue','pending')");
        $active->execute([$id]);
        if ($active->fetchColumn() > 0) {
            flash('error', 'Cannot delete: this book has active loans or requests.');
        } else {
            $pdo->prepare("DELETE FROM books WHERE id = ?")->execute([$id]);
            flash('success', 'Book deleted.');
        }
    }
    redirect('books.php');
}

$q = clean($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? ORDER BY title");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM books ORDER BY title");
}
$books = $stmt->fetchAll();

$activePage = 'books';
include '../includes/admin_header.php';
?>

<div class="topbar">
  <h2>Manage Books</h2>
  <div class="user-chip"><?= clean($_SESSION['full_name']) ?> (Admin)</div>
</div>

<?php if ($m = flash('success')): ?><div class="alert alert-success"><?= clean($m) ?></div><?php endif; ?>
<?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= clean($m) ?></div><?php endif; ?>

<div class="toolbar">
  <form class="search-bar" method="GET" style="margin:0; flex:1; max-width:400px;">
    <input type="text" name="q" placeholder="Search by title, author, or ISBN..." value="<?= clean($q) ?>">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>
  <button class="btn btn-success" onclick="openModal('addModal')">+ Add New Book</button>
</div>

<div class="panel">
  <table>
    <tr><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Shelf</th><th>Copies (Avail/Total)</th><th>Actions</th></tr>
    <?php if (!$books): ?>
      <tr><td colspan="7" class="text-muted">No books found.</td></tr>
    <?php endif; ?>
    <?php foreach ($books as $b): ?>
      <tr>
        <td><?= clean($b['title']) ?></td>
        <td><?= clean($b['author']) ?></td>
        <td><?= clean($b['isbn']) ?></td>
        <td><?= clean($b['category']) ?></td>
        <td><?= clean($b['shelf_location']) ?></td>
        <td><?= $b['available_copies'] ?> / <?= $b['total_copies'] ?></td>
        <td>
          <button class="btn btn-sm btn-primary"
            onclick='openEdit(<?= json_encode($b) ?>)'>Edit</button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this book?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<!-- Add Modal -->
<div class="modal-bg" id="addModal">
  <div class="modal-box">
    <h3 class="mt-0">Add New Book</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
      <div class="form-group"><label>Author</label><input type="text" name="author" required></div>
      <div class="form-group"><label>ISBN</label><input type="text" name="isbn" required></div>
      <div class="form-group"><label>Category</label><input type="text" name="category" value="General"></div>
      <div class="form-group"><label>Shelf Location</label><input type="text" name="shelf_location"></div>
      <div class="form-group"><label>Total Copies</label><input type="number" name="total_copies" min="1" value="1" required></div>
      <button type="submit" class="btn btn-primary btn-block">Save Book</button>
      <button type="button" class="btn btn-block" style="margin-top:8px;" onclick="closeModal('addModal')">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-bg" id="editModal">
  <div class="modal-box">
    <h3 class="mt-0">Edit Book</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-group"><label>Title</label><input type="text" name="title" id="edit_title" required></div>
      <div class="form-group"><label>Author</label><input type="text" name="author" id="edit_author" required></div>
      <div class="form-group"><label>ISBN</label><input type="text" name="isbn" id="edit_isbn" required></div>
      <div class="form-group"><label>Category</label><input type="text" name="category" id="edit_category"></div>
      <div class="form-group"><label>Shelf Location</label><input type="text" name="shelf_location" id="edit_shelf"></div>
      <div class="form-group"><label>Total Copies</label><input type="number" name="total_copies" id="edit_total" min="1" required></div>
      <button type="submit" class="btn btn-primary btn-block">Update Book</button>
      <button type="button" class="btn btn-block" style="margin-top:8px;" onclick="closeModal('editModal')">Cancel</button>
    </form>
  </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function openEdit(b) {
  document.getElementById('edit_id').value = b.id;
  document.getElementById('edit_title').value = b.title;
  document.getElementById('edit_author').value = b.author;
  document.getElementById('edit_isbn').value = b.isbn;
  document.getElementById('edit_category').value = b.category;
  document.getElementById('edit_shelf').value = b.shelf_location;
  document.getElementById('edit_total').value = b.total_copies;
  openModal('editModal');
}
</script>

<?php include '../includes/admin_footer.php'; ?>
