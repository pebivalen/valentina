<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/csrf.php';

$namaPengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$namaRole = $_SESSION['nama_role'] ?? 'Pengguna';
?>
<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
        <div>
            <strong>SIM Mahasiswa</strong>
            <span>Universitas Muhammadiyah Bengkulu</span>
        </div>
    </div>

    <div class="topbar-right">
        <div class="user-menu">
            <div class="avatar"><?= strtoupper(substr($namaPengguna, 0, 1)) ?></div>
            <div class="user-info">
                <strong><?= htmlspecialchars($namaPengguna) ?></strong>
                <small><?= htmlspecialchars($namaRole) ?></small>
            </div>
        </div>
        <form action="logout.php" method="post" class="logout-form"
      onsubmit="return confirm('Apakah Anda yakin ingin keluar?');">
    <?= csrf_field() ?>
    <button type="submit" class="logout-link logout-button">Keluar</button>
</form>
    </div>
</header>
