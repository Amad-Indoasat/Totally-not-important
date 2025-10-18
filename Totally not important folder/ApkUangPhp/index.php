<?php
// app-catatan-keuangan.php (versi Bootstrap lokal + MySQL)

// Konfigurasi koneksi MySQL
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "keuangan_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES);
}

// Tambah transaksi
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_tx') {
    $date = $_POST['date'] ?? date('Y-m-d');
    $desc = trim($_POST['description'] ?? '');
    $cat = trim($_POST['category'] ?? '');
    $type = $_POST['type'] ?? 'expense';
    $amount = str_replace(',', '.', $_POST['amount'] ?? '0');

    if (!in_array($type, ['income', 'expense'])) $errors[] = "Tipe tidak valid.";
    if (!is_numeric($amount)) $errors[] = "Jumlah harus angka.";

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO transactions (date, description, category, type, amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$date, $desc, $cat, $type, (float)$amount]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Hapus transaksi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Kalkulator
$calcResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'calc') {
    $a = str_replace(',', '.', $_POST['a'] ?? '0');
    $b = str_replace(',', '.', $_POST['b'] ?? '0');
    $op = $_POST['op'] ?? '+';
    if (!is_numeric($a) || !is_numeric($b)) {
        $calcResult = "Input harus angka.";
    } else {
        $a = (float)$a;
        $b = (float)$b;
        $calcResult = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $b == 0 ? 'Error: /0' : $a / $b,
            '%' => $b == 0 ? 'Error: /0' : $a % $b,
            default => 'Operator tidak dikenal'
        };
    }
}

// Ambil data
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM transactions";
if ($filter === 'income') $sql .= " WHERE type='income'";
elseif ($filter === 'expense') $sql .= " WHERE type='expense'";
$sql .= " ORDER BY date DESC, id DESC";
$transactions = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$totalIncome = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM transactions WHERE type='income'")->fetchColumn();
$totalExpense = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM transactions WHERE type='expense'")->fetchColumn();
$balance = $totalIncome - $totalExpense;

$categories = $pdo->query("
    SELECT category, SUM(CASE WHEN type='income' THEN amount ELSE -amount END) AS total
    FROM transactions GROUP BY category ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Catatan Keuangan</title>
    <link rel="stylesheet" href="bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-primary mb-4">
        <div class="container">
            <span class="navbar-brand">💰 Catatan Keuangan</span>
            <span class="text-white">Saldo: Rp <?= number_format($balance, 2, ',', '.') ?></span>
        </div>
    </nav>

    <div class="container mb-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3 shadow-sm">
                    <div class="card-header bg-success text-white">Tambah Transaksi</div>
                    <div class="card-body">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="add_tx">
                            <div class="col-md-3"><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-md-3"><input type="text" name="description" class="form-control" placeholder="Deskripsi"></div>
                            <div class="col-md-2"><input type="text" name="category" class="form-control" placeholder="Kategori"></div>
                            <div class="col-md-2">
                                <select name="type" class="form-select">
                                    <option value="expense">Pengeluaran</option>
                                    <option value="income">Pemasukan</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex">
                                <input type="text" name="amount" class="form-control me-2" placeholder="Jumlah">
                                <button class="btn btn-primary">+</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">Daftar Transaksi</div>
                    <div class="card-body">
                        <div class="mb-2">
                            <a href="?filter=all" class="btn btn-outline-secondary btn-sm">Semua</a>
                            <a href="?filter=income" class="btn btn-outline-success btn-sm">Pemasukan</a>
                            <a href="?filter=expense" class="btn btn-outline-danger btn-sm">Pengeluaran</a>
                        </div>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Deskripsi</th>
                                    <th>Kategori</th>
                                    <th>Tipe</th>
                                    <th>Jumlah</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$transactions): ?>
                                    <tr>
                                        <td colspan="6" class="text-muted">Belum ada data</td>
                                    </tr>
                                    <?php else: foreach ($transactions as $t): ?>
                                        <tr>
                                            <td><?= h($t['date']) ?></td>
                                            <td><?= h($t['description']) ?></td>
                                            <td><?= h($t['category']) ?></td>
                                            <td class="<?= $t['type'] == 'income' ? 'text-success' : 'text-danger' ?>"><?= h($t['type']) ?></td>
                                            <td>Rp <?= number_format($t['amount'], 2, ',', '.') ?></td>
                                            <td><a href="?delete=<?= $t['id'] ?>" onclick="return confirm('Hapus data ini?')" class="btn btn-sm btn-danger">Hapus</a></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-3 shadow-sm">
                    <div class="card-header bg-warning text-dark">Kalkulator</div>
                    <div class="card-body">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="calc">
                            <div class="col-4"><input type="text" name="a" class="form-control" placeholder="A"></div>
                            <div class="col-4">
                                <select name="op" class="form-select">
                                    <option value="+">+</option>
                                    <option value="-">-</option>
                                    <option value="*">×</option>
                                    <option value="/">÷</option>
                                    <option value="%">%</option>
                                </select>
                            </div>
                            <div class="col-4"><input type="text" name="b" class="form-control" placeholder="B"></div>
                            <div class="col-12"><button class="btn btn-primary w-100">Hitung</button></div>
                        </form>
                        <?php if ($calcResult !== null): ?><div class="mt-3 alert alert-info">Hasil: <strong><?= h((string)$calcResult) ?></strong></div><?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">Ringkasan</div>
                    <div class="card-body">
                        <p>Pemasukan: <strong class="text-success">Rp <?= number_format($totalIncome, 2, ',', '.') ?></strong></p>
                        <p>Pengeluaran: <strong class="text-danger">Rp <?= number_format($totalExpense, 2, ',', '.') ?></strong></p>
                        <p>Saldo Bersih: <strong>Rp <?= number_format($balance, 2, ',', '.') ?></strong></p>
                        <hr>
                        <h6>Per Kategori:</h6>
                        <ul class="list-group">
                            <?php foreach ($categories as $c): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= h($c['category'] ?: '(tidak diisi)') ?>
                                    <span><?= number_format($c['total'], 2, ',', '.') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>