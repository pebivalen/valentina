<?php
require_once 'auth_check.php';
require_once 'koneksi.php';
require_once 'csrf.php';

wajib_role(['ADMIN']);

$pesan = '';
$error = '';
$editData = null;

// Proses tambah / edit / hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $password = $_POST['password'] ?? '';
        $idRole = (int)($_POST['id_role'] ?? 0);
        $status = ($_POST['status_aktif'] ?? 'Aktif') === 'Nonaktif' ? 'Nonaktif' : 'Aktif';

        if ($username === '' || $nama === '' || $password === '' || $idRole <= 0) {
            $error = 'Username, nama, password, dan role wajib diisi.';
        } else {
            $cek = mysqli_prepare($koneksi, 'SELECT id_pengguna FROM pengguna WHERE username = ? LIMIT 1');
            mysqli_stmt_bind_param($cek, 's', $username);
            mysqli_stmt_execute($cek);
            $hasilCek = mysqli_stmt_get_result($cek);
            $sudahAda = mysqli_fetch_assoc($hasilCek);
            mysqli_stmt_close($cek);

            if ($sudahAda) {
                $error = 'Username sudah digunakan.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare(
                    $koneksi,
                    'INSERT INTO pengguna (id_role, username, password_hash, nama_lengkap, status_aktif) VALUES (?, ?, ?, ?, ?)'
                );

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'issss', $idRole, $username, $hash, $nama, $status);
                    if (mysqli_stmt_execute($stmt)) {
                        $pesan = 'Pengguna berhasil ditambahkan.';
                    } else {
                        $error = 'Gagal menambahkan pengguna: ' . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = 'Query tambah pengguna gagal: ' . mysqli_error($koneksi);
                }
            }
        }
    }

    if ($aksi === 'edit') {
        $id = (int)($_POST['id_pengguna'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $password = $_POST['password'] ?? '';
        $idRole = (int)($_POST['id_role'] ?? 0);
        $status = ($_POST['status_aktif'] ?? 'Aktif') === 'Nonaktif' ? 'Nonaktif' : 'Aktif';

        if ($id <= 0 || $username === '' || $nama === '' || $idRole <= 0) {
            $error = 'Data edit belum lengkap.';
        } else {
            $cek = mysqli_prepare($koneksi, 'SELECT id_pengguna FROM pengguna WHERE username = ? AND id_pengguna <> ? LIMIT 1');
            mysqli_stmt_bind_param($cek, 'si', $username, $id);
            mysqli_stmt_execute($cek);
            $hasilCek = mysqli_stmt_get_result($cek);
            $sudahAda = mysqli_fetch_assoc($hasilCek);
            mysqli_stmt_close($cek);

            if ($sudahAda) {
                $error = 'Username sudah digunakan pengguna lain.';
            } else {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare(
                        $koneksi,
                        'UPDATE pengguna SET id_role = ?, username = ?, password_hash = ?, nama_lengkap = ?, status_aktif = ? WHERE id_pengguna = ?'
                    );
                    mysqli_stmt_bind_param($stmt, 'issssi', $idRole, $username, $hash, $nama, $status, $id);
                } else {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        'UPDATE pengguna SET id_role = ?, username = ?, nama_lengkap = ?, status_aktif = ? WHERE id_pengguna = ?'
                    );
                    mysqli_stmt_bind_param($stmt, 'isssi', $idRole, $username, $nama, $status, $id);
                }

                if ($stmt && mysqli_stmt_execute($stmt)) {
                    $pesan = 'Pengguna berhasil diperbarui.';
                    mysqli_stmt_close($stmt);
                } else {
                    $error = 'Gagal memperbarui pengguna.';
                    if ($stmt) mysqli_stmt_close($stmt);
                }
            }
        }
    }

    if ($aksi === 'hapus') {
        $id = (int)($_POST['id_pengguna'] ?? 0);

        // Jangan menghapus akun yang sedang dipakai untuk login.
        if ($id === (int)($_SESSION['id_pengguna'] ?? 0)) {
            $error = 'Akun yang sedang digunakan tidak dapat dihapus.';
        } elseif ($id <= 0) {
            $error = 'ID pengguna tidak valid.';
        } else {
            $stmt = mysqli_prepare($koneksi, 'DELETE FROM pengguna WHERE id_pengguna = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'Pengguna berhasil dihapus.';
            } else {
                $error = 'Pengguna tidak dapat dihapus. Kemungkinan masih digunakan pada data lain.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Ambil data untuk mode edit
if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];
    if ($idEdit > 0) {
        $stmt = mysqli_prepare(
            $koneksi,
            'SELECT id_pengguna, id_role, username, nama_lengkap, status_aktif FROM pengguna WHERE id_pengguna = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'i', $idEdit);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $editData = mysqli_fetch_assoc($res) ?: null;
        mysqli_stmt_close($stmt);
    }
}

// Ambil role
$roles = [];
$resRole = mysqli_query($koneksi, 'SELECT id_role, kode_role, nama_role FROM roles ORDER BY id_role ASC');
if ($resRole) {
    while ($row = mysqli_fetch_assoc($resRole)) {
        $roles[] = $row;
    }
}

// Ambil pengguna
$pengguna = [];
$sql = 'SELECT p.id_pengguna, p.username, p.nama_lengkap, p.status_aktif, p.last_login, r.kode_role, r.nama_role
        FROM pengguna p
        INNER JOIN roles r ON p.id_role = r.id_role
        ORDER BY p.id_pengguna ASC';
$res = mysqli_query($koneksi, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $pengguna[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengguna - SIM Mahasiswa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-body">

<?php include 'topbar.php'; ?>
<?php include 'sideleftbar.php'; ?>

<main class="main-content">
    <div class="page-heading">
        <div>
            <span class="eyebrow">DATA SISTEM</span>
            <h1>Kelola Pengguna</h1>
            <p>Tambah, ubah, dan kelola akun pengguna SIM Mahasiswa.</p>
        </div>
    </div>

    <?php if ($pesan): ?>
        <div class="alert alert-success"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel form-panel">
        <div class="panel-heading">
            <h2><?= $editData ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h2>
        </div>

        <form method="post" class="crud-form">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $editData ? 'edit' : 'tambah' ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="id_pengguna" value="<?= (int)$editData['id_pengguna'] ?>">
            <?php endif; ?>

            <div class="crud-grid">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required maxlength="100"
                           value="<?= htmlspecialchars($editData['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="nama_lengkap">Nama Pengguna</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" required maxlength="150"
                           value="<?= htmlspecialchars($editData['nama_lengkap'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="id_role">Role</label>
                    <select id="id_role" name="id_role" required>
                        <option value="">-- Pilih Role --</option>
                        <?php foreach ($roles as $roleItem): ?>
                            <option value="<?= (int)$roleItem['id_role'] ?>"
                                <?= ((int)($editData['id_role'] ?? 0) === (int)$roleItem['id_role']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($roleItem['nama_role']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status_aktif">Status</label>
                    <select id="status_aktif" name="status_aktif">
                        <option value="Aktif" <?= (($editData['status_aktif'] ?? 'Aktif') === 'Aktif') ? 'selected' : '' ?>>Aktif</option>
                        <option value="Nonaktif" <?= (($editData['status_aktif'] ?? '') === 'Nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password <?= $editData ? '<span class="label-note">(kosongkan jika tidak diubah)</span>' : '' ?></label>
                    <input type="password" id="password" name="password" <?= $editData ? '' : 'required' ?> minlength="6" autocomplete="new-password">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $editData ? 'Simpan Perubahan' : 'Tambah' ?></button>
                <?php if ($editData): ?>
                    <a href="pengguna.php" class="btn btn-outline">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="dashboard-panel table-panel">
        <div class="panel-heading table-heading">
            <h2>Daftar Pengguna</h2>
            <span class="table-count"><?= count($pengguna) ?> pengguna</span>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>USERNAME</th>
                        <th>NAMA</th>
                        <th>ROLE</th>
                        <th>STATUS</th>
                        <th>LOGIN TERAKHIR</th>
                        <th>AKSI</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$pengguna): ?>
                    <tr>
                        <td colspan="6" class="empty-state">Belum ada data pengguna.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pengguna as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['username']) ?></strong></td>
                            <td><?= htmlspecialchars($item['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($item['nama_role']) ?></td>
                            <td>
                                <span class="status-pill <?= $item['status_aktif'] === 'Aktif' ? 'status-pill-active' : 'status-pill-inactive' ?>">
                                    <?= htmlspecialchars($item['status_aktif']) ?>
                                </span>
                            </td>
                            <td><?= $item['last_login'] ? htmlspecialchars(date('d-m-Y H:i', strtotime($item['last_login']))) : '-' ?></td>
                            <td>
                                <div class="action-group">
                                    <a class="action-edit" href="pengguna.php?edit=<?= (int)$item['id_pengguna'] ?>">Edit</a>
                                    <?php if ((int)$item['id_pengguna'] !== (int)$_SESSION['id_pengguna']): ?>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Hapus pengguna ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id_pengguna" value="<?= (int)$item['id_pengguna'] ?>">
                                            <button type="submit" class="action-delete">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}
</script>
</body>
</html>
