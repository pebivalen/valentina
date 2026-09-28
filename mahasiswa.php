<?php
require_once 'auth_check.php';
require_once 'koneksi.php';
require_once 'csrf.php';
wajib_role(['ADMIN','OPERATOR_PRODI']);

$role = $_SESSION['kode_role'] ?? '';
$idProdiUser = (int)($_SESSION['id_program_studi'] ?? 0);
$pesan=''; $error=''; $editData=null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $aksi=$_POST['aksi']??'';
    $npm=trim($_POST['npm']??'');
    $nama=trim($_POST['nama_mahasiswa']??'');
    $jk=$_POST['jenis_kelamin']??'Laki-laki';
    $tempat=trim($_POST['tempat_lahir']??'');
    $tglLahir=$_POST['tanggal_lahir']??'';
    $tglMasuk=$_POST['tanggal_masuk']??'';
    $alamat=trim($_POST['alamat']??'');
    $idProdi=(int)($_POST['id_program_studi']??0);
    if ($role==='OPERATOR_PRODI') $idProdi=$idProdiUser;

    if ($npm==='' || $nama==='' || $idProdi<=0 || $tglMasuk==='') {
        $error='NPM, nama, program studi, dan tanggal masuk wajib diisi.';
    } elseif ($role==='OPERATOR_PRODI' && $idProdi!==$idProdiUser) {
        $error='Anda hanya dapat mengelola mahasiswa pada Program Studi sendiri.';
    } else {
        if ($aksi==='tambah') {
            $cek=mysqli_prepare($koneksi,'SELECT id_mahasiswa FROM mahasiswa WHERE npm=? LIMIT 1');
            mysqli_stmt_bind_param($cek,'s',$npm); mysqli_stmt_execute($cek); $ada=mysqli_fetch_assoc(mysqli_stmt_get_result($cek)); mysqli_stmt_close($cek);
            if ($ada) $error='NPM sudah digunakan.';
            else {
                $stmt=mysqli_prepare($koneksi,'INSERT INTO mahasiswa (id_program_studi,npm,nama_mahasiswa,jenis_kelamin,tempat_lahir,tanggal_lahir,tanggal_masuk,alamat) VALUES (?,?,?,?,?,?,?,?)');
                if($stmt){ mysqli_stmt_bind_param($stmt,'isssssss',$idProdi,$npm,$nama,$jk,$tempat,$tglLahir,$tglMasuk,$alamat); if(mysqli_stmt_execute($stmt)) $pesan='Mahasiswa berhasil ditambahkan.'; else $error='Gagal menambahkan mahasiswa: '.mysqli_stmt_error($stmt); mysqli_stmt_close($stmt);} else $error='Query tambah mahasiswa gagal: '.mysqli_error($koneksi);
            }
        } elseif ($aksi==='edit') {
            $id=(int)($_POST['id_mahasiswa']??0);
            $cek=mysqli_prepare($koneksi,'SELECT id_mahasiswa FROM mahasiswa WHERE npm=? AND id_mahasiswa<>? LIMIT 1');
            mysqli_stmt_bind_param($cek,'si',$npm,$id); mysqli_stmt_execute($cek); $ada=mysqli_fetch_assoc(mysqli_stmt_get_result($cek)); mysqli_stmt_close($cek);
            if($ada) $error='NPM sudah digunakan mahasiswa lain.';
            else {
                $stmt=mysqli_prepare($koneksi,'UPDATE mahasiswa SET id_program_studi=?,npm=?,nama_mahasiswa=?,jenis_kelamin=?,tempat_lahir=?,tanggal_lahir=?,tanggal_masuk=?,alamat=? WHERE id_mahasiswa=?');
                if($stmt){mysqli_stmt_bind_param($stmt,'isssssssi',$idProdi,$npm,$nama,$jk,$tempat,$tglLahir,$tglMasuk,$alamat,$id); if(mysqli_stmt_execute($stmt)) $pesan='Data mahasiswa berhasil diperbarui.'; else $error='Gagal memperbarui: '.mysqli_stmt_error($stmt); mysqli_stmt_close($stmt);}
            }
        } elseif ($aksi==='hapus') {
            $id=(int)($_POST['id_mahasiswa']??0);
            $stmt=mysqli_prepare($koneksi,'DELETE FROM mahasiswa WHERE id_mahasiswa=?'); mysqli_stmt_bind_param($stmt,'i',$id);
            if(mysqli_stmt_execute($stmt)) $pesan='Mahasiswa berhasil dihapus.'; else $error='Mahasiswa tidak dapat dihapus: '.mysqli_stmt_error($stmt); mysqli_stmt_close($stmt);
        }
    }
}

if(isset($_GET['edit'])){
    $id=(int)$_GET['edit'];
    $sql='SELECT m.* FROM mahasiswa m WHERE m.id_mahasiswa=?'.($role==='OPERATOR_PRODI'?' AND m.id_program_studi=?':'').' LIMIT 1';
    $stmt=mysqli_prepare($koneksi,$sql);
    if($role==='OPERATOR_PRODI') mysqli_stmt_bind_param($stmt,'ii',$id,$idProdiUser); else mysqli_stmt_bind_param($stmt,'i',$id);
    mysqli_stmt_execute($stmt); $editData=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))?:null; mysqli_stmt_close($stmt);
}

$prodi=[]; $sqlP='SELECT id_program_studi,nama_program_studi FROM program_studi'.($role==='OPERATOR_PRODI'?' WHERE id_program_studi=?':'').' ORDER BY nama_program_studi';
if($role==='OPERATOR_PRODI'){$stmt=mysqli_prepare($koneksi,$sqlP);mysqli_stmt_bind_param($stmt,'i',$idProdiUser);mysqli_stmt_execute($stmt);$resP=mysqli_stmt_get_result($stmt);}else{$resP=mysqli_query($koneksi,$sqlP);} if($resP)while($r=mysqli_fetch_assoc($resP))$prodi[]=$r;

$mahasiswa=[]; $sql='SELECT m.id_mahasiswa,m.npm,m.nama_mahasiswa,m.jenis_kelamin,m.tempat_lahir,m.tanggal_lahir,m.tanggal_masuk,m.alamat,ps.nama_program_studi FROM mahasiswa m INNER JOIN program_studi ps ON m.id_program_studi=ps.id_program_studi'.($role==='OPERATOR_PRODI'?' WHERE m.id_program_studi=?':'').' ORDER BY m.id_mahasiswa DESC';
if($role==='OPERATOR_PRODI'){$stmt=mysqli_prepare($koneksi,$sql);mysqli_stmt_bind_param($stmt,'i',$idProdiUser);mysqli_stmt_execute($stmt);$res=mysqli_stmt_get_result($stmt);}else{$res=mysqli_query($koneksi,$sql);} if($res)while($r=mysqli_fetch_assoc($res))$mahasiswa[]=$r;
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Mahasiswa - SIM Mahasiswa</title><link rel="stylesheet" href="style.css"></head><body class="app-body">
<?php include 'topbar.php'; include 'sideleftbar.php'; ?>
<main class="main-content"><div class="page-heading"><div><span class="eyebrow">DATA AKADEMIK</span><h1>Kelola Mahasiswa</h1><p>Tambah, ubah, dan kelola data mahasiswa.</p></div></div>
<?php if($pesan):?><div class="alert alert-success"><?=htmlspecialchars($pesan)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="dashboard-panel form-panel"><div class="panel-heading"><h2><?=$editData?'Edit Mahasiswa':'Tambah Mahasiswa'?></h2></div><form method="post" class="crud-form"><?=csrf_field()?><input type="hidden" name="aksi" value="<?=$editData?'edit':'tambah'?>"><?php if($editData):?><input type="hidden" name="id_mahasiswa" value="<?=intval($editData['id_mahasiswa'])?>"><?php endif;?><div class="crud-grid">
<div class="form-group"><label>NPM</label><input name="npm" required value="<?=htmlspecialchars($editData['npm']??'')?>"></div>
<div class="form-group"><label>Nama Mahasiswa</label><input name="nama_mahasiswa" required value="<?=htmlspecialchars($editData['nama_mahasiswa']??'')?>"></div>
<div class="form-group"><label>Jenis Kelamin</label><select name="jenis_kelamin"><option <?=($editData['jenis_kelamin']??'Laki-laki')==='Laki-laki'?'selected':''?>>Laki-laki</option><option <?=($editData['jenis_kelamin']??'')==='Perempuan'?'selected':''?>>Perempuan</option></select></div>
<div class="form-group"><label>Tempat Lahir</label><input name="tempat_lahir" value="<?=htmlspecialchars($editData['tempat_lahir']??'')?>"></div>
<div class="form-group"><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir" value="<?=htmlspecialchars($editData['tanggal_lahir']??'')?>"></div>
<div class="form-group"><label>Tanggal Masuk</label><input type="date" name="tanggal_masuk" required value="<?=htmlspecialchars($editData['tanggal_masuk']??'')?>"></div>
<div class="form-group"><label>Alamat</label><input name="alamat" value="<?=htmlspecialchars($editData['alamat']??'')?>"></div>
<div class="form-group"><label>Program Studi</label><select name="id_program_studi" required <?=$role==='OPERATOR_PRODI'?'disabled':''?>><?php foreach($prodi as $p):?><option value="<?=intval($p['id_program_studi'])?>" <?=intval($editData['id_program_studi']??$idProdiUser)===intval($p['id_program_studi'])?'selected':''?>><?=htmlspecialchars($p['nama_program_studi'])?></option><?php endforeach;?></select><?php if($role==='OPERATOR_PRODI'):?><input type="hidden" name="id_program_studi" value="<?=$idProdiUser?>"><?php endif;?></div>
</div><div class="form-actions"><button class="btn btn-primary" type="submit"><?=$editData?'Simpan Perubahan':'Tambah'?></button><?php if($editData):?><a class="btn btn-outline" href="mahasiswa.php">Batal</a><?php endif;?></div></form></section>
<section class="dashboard-panel table-panel"><div class="panel-heading table-heading"><h2>Daftar Mahasiswa</h2><span class="table-count"><?=count($mahasiswa)?> mahasiswa</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>NPM</th><th>NAMA</th><th>JK</th><th>PRODI</th><th>TGL MASUK</th><th>AKSI</th></tr></thead><tbody><?php if(!$mahasiswa):?><tr><td colspan="6" class="empty-state">Belum ada data mahasiswa.</td></tr><?php else: foreach($mahasiswa as $m):?><tr><td><?=htmlspecialchars($m['npm'])?></td><td><?=htmlspecialchars($m['nama_mahasiswa'])?></td><td><?=htmlspecialchars($m['jenis_kelamin'])?></td><td><?=htmlspecialchars($m['nama_program_studi'])?></td><td><?=htmlspecialchars($m['tanggal_masuk'])?></td><td><div class="action-group"><a class="action-edit" href="mahasiswa.php?edit=<?=intval($m['id_mahasiswa'])?>">Edit</a><form method="post" class="inline-form" onsubmit="return confirm('Hapus mahasiswa ini?')"><?=csrf_field()?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_mahasiswa" value="<?=intval($m['id_mahasiswa'])?>"><button class="action-delete">Hapus</button></form></div></td></tr><?php endforeach; endif;?></tbody></table></div></section></main><script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('show');}</script></body></html>
