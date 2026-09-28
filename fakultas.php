<?php
require_once 'auth_check.php';
require_once 'koneksi.php';
require_once 'csrf.php';

wajib_role(['ADMIN']);

/*
|--------------------------------------------------------------------------
| KONFIGURASI
|--------------------------------------------------------------------------
*/
$id_universitas_default = 1;

/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/
$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id_fakultas'] ?? 0);
    $kode = trim($_POST['kode_fakultas'] ?? '');
    $nama = trim($_POST['nama_fakultas'] ?? '');
    $id_universitas = (int)($_POST['id_universitas'] ?? $id_universitas_default);

    /*
    |--------------------------------------------------------------------------
    | TAMBAH
    |--------------------------------------------------------------------------
    */
    if ($aksi === 'tambah') {

        if ($kode === '' || $nama === '') {
            $error = 'Kode fakultas dan nama fakultas wajib diisi.';
        } elseif ($id_universitas <= 0) {
            $error = 'Universitas belum dipilih.';
        } else {

            $sql = "INSERT INTO fakultas
                    (id_universitas, kode_fakultas, nama_fakultas)
                    VALUES (?, ?, ?)";

            $stmt = mysqli_prepare($koneksi, $sql);

            if (!$stmt) {
                $error = 'Gagal menyiapkan query: ' . mysqli_error($koneksi);
            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    'iss',
                    $id_universitas,
                    $kode,
                    $nama
                );

                if (mysqli_stmt_execute($stmt)) {
                    $pesan = 'Fakultas berhasil ditambahkan.';
                } else {
                    $error = 'Gagal menambahkan fakultas: ' . mysqli_stmt_error($stmt);
                }

                mysqli_stmt_close($stmt);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */
    elseif ($aksi === 'edit') {

        if ($id <= 0) {
            $error = 'ID fakultas tidak valid.';
        } elseif ($kode === '' || $nama === '') {
            $error = 'Kode fakultas dan nama fakultas wajib diisi.';
        } else {

            $sql = "UPDATE fakultas
                    SET id_universitas = ?,
                        kode_fakultas = ?,
                        nama_fakultas = ?
                    WHERE id_fakultas = ?";

            $stmt = mysqli_prepare($koneksi, $sql);

            if (!$stmt) {
                $error = 'Gagal menyiapkan query: ' . mysqli_error($koneksi);
            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    'issi',
                    $id_universitas,
                    $kode,
                    $nama,
                    $id
                );

                if (mysqli_stmt_execute($stmt)) {
                    $pesan = 'Fakultas berhasil diperbarui.';
                } else {
                    $error = 'Gagal memperbarui fakultas: ' . mysqli_stmt_error($stmt);
                }

                mysqli_stmt_close($stmt);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS
    |--------------------------------------------------------------------------
    */
    elseif ($aksi === 'hapus') {

        if ($id <= 0) {
            $error = 'ID fakultas tidak valid.';
        } else {

            /*
             * Cek apakah fakultas masih dipakai program studi
             */
            $cek = mysqli_prepare(
                $koneksi,
                "SELECT COUNT(*) AS jumlah
                 FROM program_studi
                 WHERE id_fakultas = ?"
            );

            if ($cek) {

                mysqli_stmt_bind_param($cek, 'i', $id);
                mysqli_stmt_execute($cek);

                $hasil_cek = mysqli_stmt_get_result($cek);
                $data_cek = mysqli_fetch_assoc($hasil_cek);

                mysqli_stmt_close($cek);

                if (($data_cek['jumlah'] ?? 0) > 0) {

                    $error =
                        'Fakultas tidak dapat dihapus karena masih digunakan oleh Program Studi.';

                } else {

                    $stmt = mysqli_prepare(
                        $koneksi,
                        "DELETE FROM fakultas WHERE id_fakultas = ?"
                    );

                    if (!$stmt) {
                        $error =
                            'Gagal menyiapkan query: ' .
                            mysqli_error($koneksi);
                    } else {

                        mysqli_stmt_bind_param($stmt, 'i', $id);

                        if (mysqli_stmt_execute($stmt)) {
                            $pesan = 'Fakultas berhasil dihapus.';
                        } else {
                            $error =
                                'Gagal menghapus fakultas: ' .
                                mysqli_stmt_error($stmt);
                        }

                        mysqli_stmt_close($stmt);
                    }
                }

            } else {
                $error =
                    'Gagal mengecek penggunaan fakultas: ' .
                    mysqli_error($koneksi);
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| DATA UNTUK EDIT
|--------------------------------------------------------------------------
*/
$data_edit = null;

if (isset($_GET['edit'])) {

    $id_edit = (int)$_GET['edit'];

    if ($id_edit > 0) {

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT
                id_fakultas,
                id_universitas,
                kode_fakultas,
                nama_fakultas
             FROM fakultas
             WHERE id_fakultas = ?"
        );

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, 'i', $id_edit);
            mysqli_stmt_execute($stmt);

            $hasil = mysqli_stmt_get_result($stmt);
            $data_edit = mysqli_fetch_assoc($hasil);

            mysqli_stmt_close($stmt);
        }
    }
}

/*
|--------------------------------------------------------------------------
| DATA UNIVERSITAS
|--------------------------------------------------------------------------
*/
$universitas = [];

$result_universitas = mysqli_query(
    $koneksi,
    "SELECT id_universitas, kode_universitas, nama_universitas
     FROM universitas
     ORDER BY nama_universitas ASC"
);

if ($result_universitas) {
    while ($row = mysqli_fetch_assoc($result_universitas)) {
        $universitas[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| DATA FAKULTAS
|--------------------------------------------------------------------------
*/
$fakultas = [];

$sql_fakultas = "
    SELECT
        f.id_fakultas,
        f.id_universitas,
        f.kode_fakultas,
        f.nama_fakultas,
        u.nama_universitas
    FROM fakultas f
    LEFT JOIN universitas u
        ON u.id_universitas = f.id_universitas
    ORDER BY f.nama_fakultas ASC
";

$result_fakultas = mysqli_query($koneksi, $sql_fakultas);

if ($result_fakultas) {
    while ($row = mysqli_fetch_assoc($result_fakultas)) {
        $fakultas[] = $row;
    }
}

function e($text)
{
    return htmlspecialchars(
        (string)$text,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Fakultas - SIM Mahasiswa</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

    <style>
        .page-content {
            padding: 36px 32px;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-header .eyebrow {
            color: #087f73;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 1.2px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .page-header h1 {
            margin: 0;
            font-size: 32px;
            color: #10243e;
        }

        .page-header p {
            margin-top: 8px;
            color: #64748b;
            font-size: 17px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            margin-bottom: 28px;
            overflow: hidden;
        }

        .card-header {
            padding: 22px 26px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            margin: 0;
            color: #10243e;
            font-size: 21px;
        }

        .card-body {
            padding: 26px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #172b4d;
            font-weight: 700;
        }

        .form-control {
            width: 100%;
            box-sizing: border-box;
            padding: 13px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: #ffffff;
            font-size: 15px;
            color: #172b4d;
            outline: none;
        }

        .form-control:focus {
            border-color: #138a5b;
            box-shadow: 0 0 0 3px rgba(19, 138, 91, .12);
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 9px;
            padding: 12px 22px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: #087f45;
            color: white;
        }

        .btn-primary:hover {
            background: #066d3b;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-edit {
            color: #087f45;
            font-weight: 700;
            text-decoration: none;
            margin-right: 15px;
        }

        .btn-delete {
            color: #dc2626;
            font-weight: 700;
            text-decoration: none;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f1f5f9;
            color: #17355c;
            font-size: 13px;
            text-transform: uppercase;
            text-align: left;
            padding: 15px 18px;
        }

        td {
            padding: 16px 18px;
            border-top: 1px solid #e2e8f0;
            color: #334155;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .count {
            color: #64748b;
            font-size: 14px;
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .page-content {
                padding: 25px 18px;
            }
        }
    </style>
</head>

<body>

<?php
/*
|--------------------------------------------------------------------------
| TOPBAR
|--------------------------------------------------------------------------
*/
require_once 'topbar.php';
?>

<div class="app-layout">

    <?php
    /*
    |--------------------------------------------------------------------------
    | SIDEBAR
    |--------------------------------------------------------------------------
    */
    require_once 'sideleftbar.php';
    ?>

    <main class="main-content">

        <div class="page-content">

            <!-- HEADER -->
            <div class="page-header">

                <div class="eyebrow">
                    Data Akademik
                </div>

                <h1>
                    Kelola Fakultas
                </h1>

                <p>
                    Tambah, ubah, dan kelola data fakultas.
                </p>

            </div>


            <!-- PESAN -->
            <?php if ($pesan !== ''): ?>

                <div class="alert alert-success">
                    <?= e($pesan) ?>
                </div>

            <?php endif; ?>


            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>


            <!-- FORM -->
            <div class="card">

                <div class="card-header">

                    <h2>
                        <?= $data_edit ? 'Edit Fakultas' : 'Tambah Fakultas' ?>
                    </h2>

                </div>

                <div class="card-body">

                    <form
                        method="POST"
                        action="fakultas.php<?= $data_edit ? '?edit=' . (int)$data_edit['id_fakultas'] : '' ?>"
                    >

                        <?php
                        if (function_exists('csrf_field')) {
                            echo csrf_field();
                        }
                        ?>

                        <input
                            type="hidden"
                            name="aksi"
                            value="<?= $data_edit ? 'edit' : 'tambah' ?>"
                        >

                        <?php if ($data_edit): ?>

                            <input
                                type="hidden"
                                name="id_fakultas"
                                value="<?= (int)$data_edit['id_fakultas'] ?>"
                            >

                        <?php endif; ?>


                        <div class="form-grid">

                            <!-- UNIVERSITAS -->
                            <div class="form-group">

                                <label for="id_universitas">
                                    Universitas
                                </label>

                                <select
                                    name="id_universitas"
                                    id="id_universitas"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        -- Pilih Universitas --
                                    </option>

                                    <?php foreach ($universitas as $u): ?>

                                        <option
                                            value="<?= (int)$u['id_universitas'] ?>"
                                            <?= (
                                                (int)($data_edit['id_universitas'] ?? $id_universitas_default)
                                                ===
                                                (int)$u['id_universitas']
                                            ) ? 'selected' : '' ?>
                                        >

                                            <?= e($u['kode_universitas']) ?>
                                            -
                                            <?= e($u['nama_universitas']) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- KODE -->
                            <div class="form-group">

                                <label for="kode_fakultas">
                                    Kode Fakultas
                                </label>

                                <input
                                    type="text"
                                    name="kode_fakultas"
                                    id="kode_fakultas"
                                    class="form-control"
                                    placeholder="Contoh: FT"
                                    value="<?= e($data_edit['kode_fakultas'] ?? '') ?>"
                                    required
                                >

                            </div>


                            <!-- NAMA -->
                            <div class="form-group">

                                <label for="nama_fakultas">
                                    Nama Fakultas
                                </label>

                                <input
                                    type="text"
                                    name="nama_fakultas"
                                    id="nama_fakultas"
                                    class="form-control"
                                    placeholder="Contoh: Fakultas Teknik"
                                    value="<?= e($data_edit['nama_fakultas'] ?? '') ?>"
                                    required
                                >

                            </div>

                        </div>


                        <div>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <?= $data_edit ? 'Simpan Perubahan' : 'Tambah' ?>
                            </button>

                            <?php if ($data_edit): ?>

                                <a
                                    href="fakultas.php"
                                    class="btn btn-secondary"
                                >
                                    Batal
                                </a>

                            <?php endif; ?>

                        </div>

                    </form>

                </div>

            </div>


            <!-- DAFTAR FAKULTAS -->
            <div class="card">

                <div class="card-header"
                     style="display:flex;justify-content:space-between;align-items:center;">

                    <h2>
                        Daftar Fakultas
                    </h2>

                    <span class="count">
                        <?= count($fakultas) ?> fakultas
                    </span>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    No
                                </th>

                                <th>
                                    Universitas
                                </th>

                                <th>
                                    Kode
                                </th>

                                <th>
                                    Nama Fakultas
                                </th>

                                <th>
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (count($fakultas) > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($fakultas as $f): ?>

                                <tr>

                                    <td>
                                        <?= $no++ ?>
                                    </td>

                                    <td>
                                        <?= e($f['nama_universitas'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= e($f['kode_fakultas']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e($f['nama_fakultas']) ?>
                                    </td>

                                    <td>

                                        <a
                                            href="fakultas.php?edit=<?= (int)$f['id_fakultas'] ?>"
                                            class="btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="fakultas.php"
                                            style="display:inline;"
                                            onsubmit="return confirm('Yakin ingin menghapus fakultas ini?');"
                                        >

                                            <?php
                                            if (function_exists('csrf_field')) {
                                                echo csrf_field();
                                            }
                                            ?>

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="hapus"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_fakultas"
                                                value="<?= (int)$f['id_fakultas'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-delete"
                                                style="background:none;border:none;padding:0;cursor:pointer;"
                                            >
                                                Hapus
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="5"
                                    style="text-align:center;padding:30px;"
                                >
                                    Belum ada data fakultas.
                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</div>

</body>
</html>