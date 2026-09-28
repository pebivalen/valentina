<?php
require_once 'auth_check.php';
require_once 'koneksi.php';

$role = $_SESSION['kode_role'];
$namaRole = $_SESSION['nama_role'];
$idProdi = $_SESSION['id_program_studi'] ?? null;
$idFakultas = $_SESSION['id_fakultas'] ?? null;
$idUniversitas = $_SESSION['id_universitas'] ?? null;

$jumlahMahasiswa = 0;
$jumlahProdi = 0;
$jumlahFakultas = 0;

if ($role === 'MAHASISWA') {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT COUNT(*) AS total
         FROM mahasiswa m
         INNER JOIN pengguna p ON m.id_pengguna = p.id_pengguna
         WHERE m.id_pengguna = ?"
    );
    if ($stmt) {
        $idPengguna = (int) $_SESSION['id_pengguna'];
        mysqli_stmt_bind_param($stmt, "i", $idPengguna);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $jumlahMahasiswa = (int) $row['total'];
        mysqli_stmt_close($stmt);
    }
} elseif ($role === 'OPERATOR_PRODI') {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT COUNT(*) AS total FROM mahasiswa WHERE id_program_studi = ?"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $idProdi);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $jumlahMahasiswa = (int) $row['total'];
        mysqli_stmt_close($stmt);
    }
} elseif ($role === 'DEKANAT') {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT COUNT(*) AS total
         FROM mahasiswa m
         INNER JOIN program_studi ps ON m.id_program_studi = ps.id_program_studi
         WHERE ps.id_fakultas = ?"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $idFakultas);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $jumlahMahasiswa = (int) $row['total'];
        mysqli_stmt_close($stmt);
    }
} else {
    $res = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM mahasiswa");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $jumlahMahasiswa = (int) $row['total'];
    }
}

if ($role === 'ADMIN' || $role === 'REKTORAT') {
    $res = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM program_studi WHERE status_aktif = 'Aktif'");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $jumlahProdi = (int) $row['total'];
    }

    $res = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM fakultas");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $jumlahFakultas = (int) $row['total'];
    }
} elseif ($role === 'DEKANAT') {
    $stmt = mysqli_prepare($koneksi, "SELECT COUNT(*) AS total FROM program_studi WHERE id_fakultas = ? AND status_aktif = 'Aktif'");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $idFakultas);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $jumlahProdi = (int) $row['total'];
        mysqli_stmt_close($stmt);
    }
}

$prodiLabel = 'Seluruh Program Studi';

if ($role === 'OPERATOR_PRODI' && $idProdi) {
    $stmt = mysqli_prepare($koneksi, "SELECT nama_program_studi FROM program_studi WHERE id_program_studi = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $idProdi);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $prodiLabel = $row['nama_program_studi'];
        }
        mysqli_stmt_close($stmt);
    }
}

if ($role === 'MAHASISWA') {
    $prodiLabel = 'Portal Mahasiswa';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SIM Mahasiswa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-body">

<?php include 'topbar.php'; ?>
<?php include 'sideleftbar.php'; ?>

<main class="main-content">
    <div class="page-heading">
        <div>
            <span class="eyebrow">DASHBOARD</span>
            <h1>Selamat datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?></h1>
            <p><?= htmlspecialchars($namaRole) ?> · <?= htmlspecialchars($prodiLabel) ?></p>
        </div>
    </div>

    <?php if ($role === 'MAHASISWA'): ?>
        <div class="welcome-card">
            <div>
                <span class="eyebrow">PORTAL MAHASISWA</span>
                <h2>Data akademik Anda</h2>
                <p>
                    Anda dapat melihat rekord mahasiswa yang terdaftar.
                    NPM dan tanggal masuk merupakan data yang dikelola oleh
                    pihak yang memiliki kewenangan.
                </p>
            </div>
            <a href="#" class="btn btn-primary">Lihat Profil</a>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👨‍🎓</div>
            <div>
                <span>Total Mahasiswa</span>
                <strong><?= number_format($jumlahMahasiswa, 0, ',', '.') ?></strong>
            </div>
        </div>

        <?php if ($role !== 'MAHASISWA'): ?>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div>
                    <span>Program Studi</span>
                    <strong><?= number_format($jumlahProdi, 0, ',', '.') ?></strong>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🏛</div>
                <div>
                    <span>Fakultas</span>
                    <strong><?= number_format($jumlahFakultas, 0, ',', '.') ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <div class="stat-card">
            <div class="stat-icon">🔐</div>
            <div>
                <span>Hak Akses</span>
                <strong class="role-value"><?= htmlspecialchars($namaRole) ?></strong>
            </div>
        </div>
    </div>

    <div class="dashboard-panel">
        <div class="panel-heading">
            <h2>Informasi Sistem</h2>
        </div>
        <div class="info-grid">
            <div>
                <span>Universitas</span>
                <strong>Universitas Muhammadiyah Bengkulu</strong>
            </div>
            <div>
                <span>Role</span>
                <strong><?= htmlspecialchars($namaRole) ?></strong>
            </div>
            <div>
                <span>Username</span>
                <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
            </div>
            <div>
                <span>Status</span>
                <strong class="status-active">Aktif</strong>
            </div>
        </div>
    </div>
</main>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}
</script>
</body>
</html>
