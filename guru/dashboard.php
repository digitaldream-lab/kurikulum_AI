<?php
require '../auth/config.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../auth/login.php");
    exit;
}

$guru_id = $_SESSION['user_id'];
$guru_name = $_SESSION['name'];
$page = !empty($_GET['page']) ? $_GET['page'] : 'kelas';

// ==========================================
// HANDLE POST REQUESTS (CRUD GURU)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- 1. MANAJEMEN KELAS ---
    if (isset($_POST['add_kelas'])) {
        $stmt = $pdo->prepare("INSERT INTO classes (guru_id, name, jenjang) VALUES (?, ?, ?)");
        $stmt->execute([$guru_id, $_POST['name'], $_POST['jenjang']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Kelas berhasil ditambahkan!'];
    }
    if (isset($_POST['edit_kelas'])) {
        $stmt = $pdo->prepare("UPDATE classes SET name = ?, jenjang = ? WHERE id = ? AND guru_id = ?");
        $stmt->execute([$_POST['name'], $_POST['jenjang'], $_POST['id'], $guru_id]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Data Kelas berhasil diubah!'];
    }
    if (isset($_POST['delete_kelas'])) { // Menggunakan Input Hidden
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ? AND guru_id = ?");
        $stmt->execute([$_POST['id'], $guru_id]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Kelas berhasil dihapus beserta semua isinya!'];
    }

    // --- 2. MANAJEMEN MAPEL ---
    if (isset($_POST['add_mapel'])) {
        $stmt = $pdo->prepare("INSERT INTO subjects (class_id, name) VALUES (?, ?)");
        $stmt->execute([$_POST['class_id'], $_POST['name']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Mata Pelajaran berhasil ditambahkan!'];
    }
    if (isset($_POST['edit_mapel'])) {
        $stmt = $pdo->prepare("UPDATE subjects SET class_id = ?, name = ? WHERE id = ?");
        $stmt->execute([$_POST['class_id'], $_POST['name'], $_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Mata Pelajaran berhasil diubah!'];
    }
    if (isset($_POST['delete_mapel'])) {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Mata Pelajaran berhasil dihapus!'];
    }

    // --- 3. MANAJEMEN MATERI / PDF ---
    if (isset($_POST['add_materi'])) {
        $file_name = null;
        if (!empty($_FILES["file_pdf"]["name"])) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $file_name = time() . '_' . basename($_FILES["file_pdf"]["name"]);
            move_uploaded_file($_FILES["file_pdf"]["tmp_name"], $target_dir . $file_name);
        }

        $stmt = $pdo->prepare("INSERT INTO materials (subject_id, title, file_path) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['subject_id'], $_POST['title'], $file_name]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Materi berhasil diunggah!'];
    }
    if (isset($_POST['edit_materi'])) {
        $id = $_POST['id'];
        $file_name = $_POST['old_file'];

        if (!empty($_FILES["file_pdf"]["name"])) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $new_file = time() . '_' . basename($_FILES["file_pdf"]["name"]);
            if (move_uploaded_file($_FILES["file_pdf"]["tmp_name"], $target_dir . $new_file)) {
                $file_name = $new_file; 
            }
        }

        $stmt = $pdo->prepare("UPDATE materials SET subject_id=?, title=?, file_path=? WHERE id=?");
        $stmt->execute([$_POST['subject_id'], $_POST['title'], $file_name, $id]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Materi berhasil diperbarui!'];
    }
    if (isset($_POST['delete_materi'])) {
        $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Materi berhasil dihapus!'];
    }

    // --- 4. HAPUS RPP TERSIMPAN ---
    if (isset($_POST['delete_rpp'])) {
        $stmt = $pdo->prepare("DELETE FROM saved_rpps WHERE id = ? AND guru_id = ?");
        $stmt->execute([$_POST['id'], $guru_id]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'RPP berhasil dihapus dari riwayat!'];
    }

    // --- 5. PENGATURAN TOKEN API ---
    if (isset($_POST['save_api'])) {
        $stmt = $pdo->prepare("UPDATE users SET api_provider = ?, api_token = ? WHERE id = ?");
        $stmt->execute([$_POST['provider'], $_POST['api_token'], $guru_id]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Token API berhasil disimpan!'];
    }
    if (isset($_POST['default_api'])) {
        $stmt = $pdo->prepare("UPDATE users SET api_provider = 'groq', api_token = NULL WHERE id = ?");
        $stmt->execute([$guru_id]);
        $_SESSION['flash_msg'] = ['type' => 'info', 'text' => 'Sistem dikembalikan ke Token Default.'];
    }

    // --- 6. AUTENTIKASI PROFIL ---
    if (isset($_POST['update_auth'])) {
        $old_username = $_POST['old_username'];
        $old_password = $_POST['old_password'];
        $new_username = !empty($_POST['new_username']) ? $_POST['new_username'] : $_SESSION['username'];
        $new_password = !empty($_POST['new_password']) ? $_POST['new_password'] : $old_password;

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND username = ? AND password = ?");
        $stmt->execute([$guru_id, $old_username, $old_password]);
        
        if ($stmt->fetch()) {
            $update_stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
            $update_stmt->execute([$new_username, $new_password, $guru_id]);
            $_SESSION['username'] = $new_username; 
            $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Profil Keamanan berhasil diperbarui!'];
        } else {
            $_SESSION['flash_msg'] = ['type' => 'danger', 'text' => 'Username atau Password lama salah!'];
        }
    }

    header("Location: dashboard.php?page=$page");
    exit;
}

// Fungsi Helper untuk mengambil Kelas Guru ini
function getMyClasses($pdo, $guru_id) {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE guru_id = ? ORDER BY id DESC");
    $stmt->execute([$guru_id]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guru - Dashboard AI RPP</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style/style.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media (max-width: 768px) {
            .sidebar-desktop { display: none !important; }
            .main-content { padding: 1.5rem !important; margin-left: 0 !important; }
        }
        /* Responsiveness untuk kartu Generate RPP */
        .robot-icon {
            font-size: 4rem; /* Ukuran default lebih kecil di HP */
            line-height: 1;
        }
        @media (min-width: 768px) {
            .robot-icon { font-size: 6rem; }
        }
        @media (min-width: 1200px) {
            .robot-icon { font-size: 8rem; }
        }
    </style>
</head>
<body class="bg-light">

    <!-- Navbar Mobile -->
    <nav class="navbar navbar-dark d-md-none px-3 sticky-top" style="background: linear-gradient(135deg, #1e1e2d 0%, #3b247a 100%);">
        <span class="navbar-brand mb-0 h1 fw-bold text-white d-flex align-items-center"><i class="bi bi-person-workspace me-2 text-info"></i> Ruang Guru</span>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
            <span class="navbar-toggler-icon"></span>
        </button>
    </nav>

    <!-- Offcanvas Sidebar Mobile -->
    <div class="offcanvas offcanvas-start text-white border-0" tabindex="-1" id="sidebarOffcanvas" style="width: 280px;">
        <div class="offcanvas-header border-bottom border-light border-opacity-10">
            <h5 class="offcanvas-title fw-bold d-flex align-items-center"><i class="bi bi-robot me-2 text-info"></i> AI RPP</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-3 sidebar">
            <a href="?page=kelas" class="<?= $page=='kelas'?'active':'' ?>"><i class="bi bi-houses-fill me-3 fs-5"></i> Manajemen Kelas</a>
            <a href="?page=mapel" class="<?= $page=='mapel'?'active':'' ?>"><i class="bi bi-journal-bookmark-fill me-3 fs-5"></i> Mata Pelajaran</a>
            <a href="?page=materi" class="<?= $page=='materi'?'active':'' ?>"><i class="bi bi-file-earmark-richtext-fill me-3 fs-5"></i> Materi / PDF</a>
            <a href="?page=generate" class="<?= $page=='generate'?'active':'' ?>"><i class="bi bi-magic me-3 fs-5 text-info"></i> Buat RPP AI</a>
            <div class="mt-auto border-top border-light border-opacity-10 pt-3">
                <a href="?page=api_token" class="<?= $page=='api_token'?'active':'' ?> mb-2"><i class="bi bi-key-fill me-3 fs-5"></i> API Token</a>
                <a href="?page=auth" class="<?= $page=='auth'?'active':'' ?> mb-3"><i class="bi bi-person-circle me-3 fs-5"></i> Profil Akun</a>
                <a href="../auth/logout.php" class="text-danger fw-bold hover-bg-transparent"><i class="bi bi-box-arrow-right me-3 fs-5"></i> Keluar</a>
            </div>
        </div>
    </div>

    <div class="d-flex min-vh-100">
        <!-- Sidebar Desktop -->
        <div class="sidebar sidebar-nav p-4 d-flex flex-column sidebar-desktop position-fixed h-100">
            <div class="mb-4 d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-10 p-2 rounded-3 text-info">
                    <i class="bi bi-person-workspace fs-3"></i>
                </div>
                <div class="overflow-hidden">
                    <h5 class="text-white m-0 fw-bold text-truncate"><?= htmlspecialchars($guru_name) ?></h5>
                    <small class="text-white-50">Guru Pengajar</small>
                </div>
            </div>
            
            <div class="d-flex flex-column flex-grow-1 mt-3 gap-1">
                <a href="?page=kelas" class="<?= $page=='kelas'?'active':'' ?>"><i class="bi bi-houses-fill me-3 fs-5 text-white text-opacity-75"></i> Kelas Anda</a>
                <a href="?page=mapel" class="<?= $page=='mapel'?'active':'' ?>"><i class="bi bi-journal-bookmark-fill me-3 fs-5 text-white text-opacity-75"></i> Mata Pelajaran</a>
                <a href="?page=materi" class="<?= $page=='materi'?'active':'' ?>"><i class="bi bi-file-earmark-richtext-fill me-3 fs-5 text-white text-opacity-75"></i> File Materi</a>
                
                <hr class="border-light border-opacity-25 my-3">
                
                <a href="?page=generate" class="<?= $page=='generate'?'active':'' ?> bg-primary bg-opacity-25 text-white fw-bold"><i class="bi bi-magic me-3 fs-5 text-info"></i> Buat RPP AI</a>
            </div>

            <div class="mt-auto border-top border-light border-opacity-10 pt-3 d-flex flex-column gap-1">
                <a href="?page=api_token" class="<?= $page=='api_token'?'active':'' ?> text-white-50"><i class="bi bi-key me-3 fs-5"></i> API Token</a>
                <a href="?page=auth" class="<?= $page=='auth'?'active':'' ?> text-white-50"><i class="bi bi-person-circle me-3 fs-5"></i> Akun</a>
                <a href="../auth/logout.php" class="text-danger bg-danger bg-opacity-10 fw-bold mt-2 hover-bg-transparent"><i class="bi bi-box-arrow-right me-3 fs-5"></i> Keluar</a>
            </div>
        </div>

        <!-- Main Content -->
        <!-- HAPUS overflow-hidden agar bisa d-scroll ke bawah saat daftar RPP panjang -->
        <div class="main-content flex-grow-1 p-4 p-md-5" style="margin-left: 280px; min-height: 100vh;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bolder text-dark m-0" style="letter-spacing: -0.5px;">
                    <?php 
                        if($page === 'generate') echo 'Lab Kurikulum AI';
                        elseif($page === 'api_token') echo 'Pengaturan Provider AI';
                        elseif($page === 'auth') echo 'Profil Keamanan';
                        else echo 'Manajemen ' . ucfirst($page);
                    ?>
                </h3>
            </div>

            <!-- Flash Message dengan SweetAlert jika ada -->
            <?php if (isset($_SESSION['flash_msg'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: '<?= $_SESSION['flash_msg']['type'] === 'danger' ? 'error' : $_SESSION['flash_msg']['type'] ?>',
                            title: 'Pemberitahuan',
                            text: '<?= $_SESSION['flash_msg']['text'] ?>',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                    });
                </script>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <!-- KONTEN HALAMAN -->
            <?php if ($page === 'kelas'): ?>
                <div class="row g-4">
                    <div class="col-12 col-xl-4 w-100">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-4">
                                <h5 class="mb-3 text-primary fw-bold">Tambah Kelas Baru</h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Nama Kelas</label>
                                        <input type="text" name="name" class="form-control" placeholder="Contoh: Kelas 4A" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label small text-muted mb-1">Jenjang Pendidikan</label>
                                        <select name="jenjang" class="form-select" required>
                                            <option value="SD/MI">SD / MI</option>
                                            <option value="SMP/MTs">SMP / MTs</option>
                                            <option value="SMA/SMK">SMA / SMK / MA</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="add_kelas" class="btn btn-primary w-100 fw-bold py-2">Tambah Kelas</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-8 w-100">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 align-middle">
                                        <thead class="table-light"><tr><th class="ps-4">No</th><th>Nama Kelas</th><th>Jenjang</th><th class="text-center">Aksi</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $classes = getMyClasses($pdo, $guru_id);
                                            $no = 1;
                                            if(count($classes)>0): foreach($classes as $c): ?>
                                                <tr>
                                                    <td class="ps-4"><?= $no++ ?></td>
                                                    <td class="fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($c['jenjang']) ?></span></td>
                                                    <td class="text-center">
                                                        <div class="d-flex gap-1 justify-content-center">
                                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editKelas<?= $c['id'] ?>">Edit</button>
                                                            <!-- Form Hapus (Fixed dengan Hidden Input) -->
                                                            <form method="POST" class="form-hapus-sweet m-0">
                                                                <input type="hidden" name="delete_kelas" value="1">
                                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger" data-message="Hapus kelas ini? Semua mapel dan materi di dalamnya akan ikut terhapus!">Hapus</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <!-- Modal Edit -->
                                                <div class="modal fade" id="editKelas<?= $c['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <form method="POST">
                                                                <div class="modal-header bg-light"><h5 class="modal-title fw-bold">Edit Kelas</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                                <div class="modal-body text-start">
                                                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                                    <div class="mb-3"><label>Nama Kelas</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>" required></div>
                                                                    <div class="mb-3"><label>Jenjang</label>
                                                                        <select name="jenjang" class="form-select" required>
                                                                            <option value="SD/MI" <?= $c['jenjang']=='SD/MI'?'selected':'' ?>>SD / MI</option>
                                                                            <option value="SMP/MTs" <?= $c['jenjang']=='SMP/MTs'?'selected':'' ?>>SMP / MTs</option>
                                                                            <option value="SMA/SMK" <?= $c['jenjang']=='SMA/SMK'?'selected':'' ?>>SMA / SMK / MA</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer"><button type="submit" name="edit_kelas" class="btn btn-primary w-100 fw-bold">Simpan</button></div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; else: ?>
                                                <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada kelas.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($page === 'mapel'): ?>
                <div class="card mb-4 border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Pilih Kelas</label>
                                <select name="class_id" class="form-select" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach(getMyClasses($pdo, $guru_id) as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Nama Mata Pelajaran</label>
                                <input type="text" name="name" class="form-control" placeholder="Contoh: Matematika" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="add_mapel" class="btn btn-primary w-100 fw-bold">Tambah Mapel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th class="ps-4">No</th><th>Mata Pelajaran</th><th>Kelas</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT s.*, c.name as class_name FROM subjects s JOIN classes c ON s.class_id = c.id WHERE c.guru_id = ? ORDER BY s.id DESC");
                                    $stmt->execute([$guru_id]);
                                    $subjects = $stmt->fetchAll();
                                    $no = 1;
                                    if(count($subjects)>0): foreach($subjects as $s): ?>
                                        <tr>
                                            <td class="ps-4"><?= $no++ ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($s['name']) ?></td>
                                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($s['class_name']) ?></span></td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editMapel<?= $s['id'] ?>">Edit</button>
                                                    <form method="POST" class="form-hapus-sweet m-0">
                                                        <input type="hidden" name="delete_mapel" value="1">
                                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-message="Hapus mata pelajaran ini? Materi di dalamnya juga akan terhapus.">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Modal Edit -->
                                        <div class="modal fade" id="editMapel<?= $s['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header bg-light"><h5 class="modal-title fw-bold">Edit Mapel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                        <div class="modal-body text-start">
                                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                            <div class="mb-3"><label>Kelas</label>
                                                                <select name="class_id" class="form-select" required>
                                                                    <?php foreach(getMyClasses($pdo, $guru_id) as $c): ?>
                                                                        <option value="<?= $c['id'] ?>" <?= $s['class_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3"><label>Nama Mata Pelajaran</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($s['name']) ?>" required></div>
                                                        </div>
                                                        <div class="modal-footer"><button type="submit" name="edit_mapel" class="btn btn-primary w-100 fw-bold">Simpan</button></div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada mata pelajaran.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($page === 'materi'): ?>
                <div class="card mb-4 border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Mata Pelajaran</label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="">-- Pilih Mapel --</option>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT s.id, s.name, c.name as class_name FROM subjects s JOIN classes c ON s.class_id = c.id WHERE c.guru_id = ?");
                                    $stmt->execute([$guru_id]);
                                    foreach($stmt->fetchAll() as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'] . " (" . $s['class_name'] . ")") ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Judul Bab / Materi</label>
                                <input type="text" name="title" class="form-control" placeholder="Contoh: Sistem Tata Surya" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Upload PDF Materi</label>
                                <input type="file" name="file_pdf" class="form-control" accept=".pdf" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="add_materi" class="btn btn-primary w-100 fw-bold">Upload</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th class="ps-4">No</th><th>Judul Materi</th><th>Mata Pelajaran</th><th>File</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT m.*, s.name as subject_name, c.name as class_name FROM materials m JOIN subjects s ON m.subject_id = s.id JOIN classes c ON s.class_id = c.id WHERE c.guru_id = ? ORDER BY m.id DESC");
                                    $stmt->execute([$guru_id]);
                                    $materials = $stmt->fetchAll();
                                    $no = 1;
                                    if(count($materials)>0): foreach($materials as $m): ?>
                                        <tr>
                                            <td class="ps-4"><?= $no++ ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($m['title']) ?></td>
                                            <td><?= htmlspecialchars($m['subject_name']) ?> <small class="text-muted">(<?= htmlspecialchars($m['class_name']) ?>)</small></td>
                                            <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-file-pdf"></i> <?= htmlspecialchars($m['file_path']) ?></span></td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editMateri<?= $m['id'] ?>">Edit</button>
                                                    <form method="POST" class="form-hapus-sweet m-0">
                                                        <input type="hidden" name="delete_materi" value="1">
                                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-message="Hapus materi ini?">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Modal Edit Materi -->
                                        <div class="modal fade" id="editMateri<?= $m['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="POST" enctype="multipart/form-data">
                                                        <div class="modal-header bg-light"><h5 class="modal-title fw-bold">Edit Materi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                        <div class="modal-body text-start">
                                                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                            <input type="hidden" name="old_file" value="<?= htmlspecialchars($m['file_path']) ?>">
                                                            
                                                            <div class="mb-3"><label>Mapel</label>
                                                                <select name="subject_id" class="form-select" required>
                                                                    <?php 
                                                                    $stmtSub = $pdo->prepare("SELECT s.id, s.name, c.name as class_name FROM subjects s JOIN classes c ON s.class_id = c.id WHERE c.guru_id = ?");
                                                                    $stmtSub->execute([$guru_id]);
                                                                    foreach($stmtSub->fetchAll() as $sOpt): ?>
                                                                        <option value="<?= $sOpt['id'] ?>" <?= $m['subject_id']==$sOpt['id']?'selected':'' ?>><?= htmlspecialchars($sOpt['name'] . " (" . $sOpt['class_name'] . ")") ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3"><label>Judul Materi</label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($m['title']) ?>" required></div>
                                                            <div class="mb-3">
                                                                <label>Ganti File PDF <small class="text-muted">(Kosongkan jika tidak diganti)</small></label>
                                                                <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                                                                <small class="d-block mt-1">File saat ini: <?= htmlspecialchars($m['file_path']) ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer"><button type="submit" name="edit_materi" class="btn btn-primary w-100 fw-bold">Simpan</button></div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada materi/PDF.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($page === 'api_token'): ?>
                <?php 
                $stmtApi = $pdo->prepare("SELECT api_provider, api_token FROM users WHERE id = ?");
                $stmtApi->execute([$guru_id]);
                $userApi = $stmtApi->fetch();
                $currentProvider = $userApi['api_provider'] ?? 'groq';
                $currentToken = $userApi['api_token'] ?? '';
                ?>
                <div class="card mb-4 shadow-sm border-0 col-12 col-xl-9 rounded-4 w-100">
                    <div class="card-body p-4 p-md-5">
                        <div class="alert alert-info mb-4 border-0 bg-info bg-opacity-10 d-flex align-items-center rounded-3">
                            <i class="bi bi-info-circle-fill fs-3 me-3 text-info"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Privasi & Kuota API</h6>
                                <p class="mb-0 small text-dark">Sistem ini dapat menggunakan kunci API (API Key) Anda sendiri dari berbagai provider agar generasi RPP lebih cepat dan tidak berebut kuota limit dengan pengguna lain.</p>

                                <br>

                                <h6 class="fw-bold mb-1">Pilihan Token API Groq</h6>
                                <p class="mb-0 small text-dark">Gunakan Token yang ada di Link G-drive Di Bawah dan masukkan ke dalam inputan API Token jika terjadi masalah pada token default</p>
                                <a href="https://docs.google.com/document/d/1BEtFMhwNXoszQs4MBmJWwYfyc89G-3WAPC7tvCQk6ss/edit?usp=sharing" target="_blank">File G-Drive</a>
                            </div>
                                
                        </div>

                        <form method="POST" id="apiForm">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold">Provider AI</label>
                                    <select name="provider" class="form-select form-select-lg" required>
                                        <option value="groq" <?= $currentProvider == 'groq' ? 'selected' : '' ?>>Groq (Llama 3)</option>
                                        <option value="openai" <?= $currentProvider == 'openai' ? 'selected' : '' ?>>OpenAI (ChatGPT)</option>
                                        <option value="gemini" <?= $currentProvider == 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
                                        <option value="anthropic" <?= $currentProvider == 'anthropic' ? 'selected' : '' ?>>Anthropic (Claude)</option>
                                        <option value="deepseek" <?= $currentProvider == 'deepseek' ? 'selected' : '' ?>>DeepSeek</option>
                                        <option value="mistral" <?= $currentProvider == 'mistral' ? 'selected' : '' ?>>Mistral AI</option>
                                        <option value="together" <?= $currentProvider == 'together' ? 'selected' : '' ?>>Together AI</option>
                                        <option value="cohere" <?= $currentProvider == 'cohere' ? 'selected' : '' ?>>Cohere</option>
                                        <option value="huggingface" <?= $currentProvider == 'huggingface' ? 'selected' : '' ?>>Hugging Face</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label text-muted small fw-bold">API Token / Secret Key</label>
                                    <input type="text" name="api_token" id="inputToken" class="form-control form-control-lg font-monospace fs-6" value="<?= htmlspecialchars($currentToken) ?>" placeholder="Masukkan token API Anda di sini...">
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-md-row gap-3 mt-4 mt-md-5">
                                <button type="button" class="btn btn-primary flex-grow-1 py-3 fw-bold shadow-sm" onclick="validateToken(event)">💾 Simpan & Pakai Token Ini</button>
                                
                                <button type="submit" name="default_api" class="btn btn-outline-secondary px-4 py-3 fw-bold shadow-sm" formnovalidate>
                                    <i class="bi bi-arrow-counterclockwise me-2"></i> Gunakan Default (Gratis)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function validateToken(e) {
                        const token = document.getElementById('inputToken').value.trim();
                        if(token === "") {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Token Kosong!',
                                text: 'Token API harus diisi jika Anda ingin menyimpannya!\n\nJika Anda ingin menggunakan versi gratis sistem, silakan klik tombol "Gunakan Default (Gratis)".',
                                confirmButtonColor: '#6038c6'
                            });
                        } else {
                            // Ciptakan input hidden agar dikenali sebagai submit save_api
                            const form = document.getElementById('apiForm');
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'save_api';
                            hiddenInput.value = '1';
                            form.appendChild(hiddenInput);
                            form.submit();
                        }
                    }
                </script>

            <?php elseif ($page === 'auth'): ?>
                <div class="card mb-4 shadow-sm border-0 col-12 col-md-8 col-xl-6 rounded-4 w-100">
                    <div class="card-body p-4 p-md-5">
                        <form method="POST">
                            <h5 class="mb-4 text-primary fw-bold border-bottom pb-2">Verifikasi Akun Lama</h5>
                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Username Saat Ini</label>
                                <input type="text" name="old_username" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted small mb-1">Password Saat Ini</label>
                                <input type="password" name="old_password" class="form-control" required>
                            </div>
                            
                            <h5 class="mb-4 mt-5 text-primary fw-bold border-bottom pb-2">Ganti Kredensial <span class="text-muted fw-normal fs-6">(Opsional)</span></h5>
                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Username Baru</label>
                                <input type="text" name="new_username" class="form-control" placeholder="Kosongkan jika tidak ingin diganti">
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted small mb-1">Password Baru</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Kosongkan jika tidak ingin diganti">
                            </div>
                            <button type="submit" name="update_auth" class="btn btn-primary w-100 py-3 mt-2 fw-bold shadow-sm">💾 Perbarui Akun Saya</button>
                        </form>
                    </div>
                </div>

            <?php elseif ($page === 'generate'): ?>
                <!-- KARTU UTAMA UNTUK GENERATE RPP BARU VIA AI -->
                <div class="card mb-5 border-0 shadow-lg rounded-4 overflow-hidden generate-card position-relative">
                    <div class="position-absolute top-0 end-0 opacity-10 p-4 pe-none">
                        <i class="bi bi-robot robot-icon"></i>
                    </div>
                    <div class="card-body p-4 p-md-5 position-relative z-1">
                        <div class="text-center mb-5">
                            <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-magic fs-1"></i>
                            </div>
                            <h2 class="fw-bolder text-dark mb-2">AI RPP Generator</h2>
                            <p class="text-muted">Pilih referensi materi Anda, dan biarkan AI menyusun RPP, Asesmen, dan LKPD secara lengkap.</p>
                        </div>
                        
                        <form action="editor_rpp.php" method="POST" class="row g-4 justify-content-center">
                            <div class="col-12 col-md-5">
                                <label class="form-label fw-bold text-dark mb-2">1. Pilih Kelas & Jenjang</label>
                                <select name="class_id" id="classSelect" class="form-select form-select-lg bg-light" required onchange="fetchMaterials()">
                                    <option value="">-- Silakan Pilih --</option>
                                    <?php foreach(getMyClasses($pdo, $guru_id) as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'] . " (" . $c['jenjang'] . ")") ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label fw-bold text-dark mb-2">2. Pilih Referensi Materi</label>
                                <select name="material_id" id="materialSelect" class="form-select form-select-lg bg-light" required disabled>
                                    <option value="">-- Pilih Kelas Dahulu --</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-10 mt-5">
                                <button type="submit" id="btnGenerate" class="btn btn-primary w-100 py-3 fs-5 fw-bold shadow position-relative overflow-hidden" disabled>
                                    🚀 Generate Kurikulum Sekarang
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- DAFTAR RPP YANG PERNAH DISIMPAN -->
                <div class="d-flex align-items-center mb-4 mt-5">
                    <h4 class="fw-bold m-0"><i class="bi bi-clock-history me-2 text-warning"></i> Riwayat Dokumen RPP Tersimpan</h4>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th class="ps-4">No</th><th>Judul Dokumen RPP</th><th>Tgl Terakhir Disimpan</th><th class="text-center">Aksi Buka Dokumen</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT * FROM saved_rpps WHERE guru_id = ? ORDER BY updated_at DESC");
                                    $stmt->execute([$guru_id]);
                                    $saved_rpps = $stmt->fetchAll();
                                    $no = 1;
                                    if(count($saved_rpps)>0): foreach($saved_rpps as $r): ?>
                                        <tr>
                                            <td class="ps-4"><?= $no++ ?></td>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($r['title']) ?></td>
                                            <td><small class="text-muted"><i class="bi bi-calendar-event me-1"></i> <?= date('d M Y, H:i', strtotime($r['updated_at'])) ?></small></td>
                                            <td class="text-center">
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <!-- Form untuk memuat RPP yang disimpan ke Editor -->
                                                    <form action="editor_rpp.php" method="POST" class="m-0">
                                                        <input type="hidden" name="rpp_id" value="<?= $r['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-info fw-bold text-white shadow-sm px-3"><i class="bi bi-folder2-open me-1"></i> Buka / Edit</button>
                                                    </form>
                                                    <form method="POST" class="form-hapus-sweet m-0">
                                                        <input type="hidden" name="delete_rpp" value="1">
                                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger shadow-sm" data-message="Yakin ingin menghapus RPP ini dari riwayat? Anda tidak dapat membatalkan tindakan ini."><i class="bi bi-trash3-fill"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">Belum ada RPP yang tersimpan di riwayat Anda.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- AJAX SCRIPT MENGAMBIL DAFTAR MATERI BERDASARKAN KELAS -->
                <script>
                    const materialsData = <?php 
                        $stmtAll = $pdo->prepare("SELECT m.id, m.title, c.id as class_id FROM materials m JOIN subjects s ON m.subject_id = s.id JOIN classes c ON s.class_id = c.id WHERE c.guru_id = ?");
                        $stmtAll->execute([$guru_id]);
                        echo json_encode($stmtAll->fetchAll());
                    ?>;

                    function fetchMaterials() {
                        const classId = document.getElementById('classSelect').value;
                        const matSelect = document.getElementById('materialSelect');
                        const btnGen = document.getElementById('btnGenerate');
                        
                        matSelect.innerHTML = '<option value="">-- Silakan Pilih --</option>';
                        
                        if(classId) {
                            const filtered = materialsData.filter(m => m.class_id == classId);
                            if(filtered.length > 0) {
                                filtered.forEach(m => {
                                    matSelect.innerHTML += `<option value="${m.id}">${m.title}</option>`;
                                });
                                matSelect.disabled = false;
                                btnGen.disabled = false;
                            } else {
                                matSelect.innerHTML = '<option value="">⚠️ Kelas ini belum memiliki materi PDF</option>';
                                matSelect.disabled = true;
                                btnGen.disabled = true;
                            }
                        } else {
                            matSelect.innerHTML = '<option value="">-- Pilih Kelas Dahulu --</option>';
                            matSelect.disabled = true;
                            btnGen.disabled = true;
                        }
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>

    <!-- Script Global untuk Konfirmasi Hapus SweetAlert2 yang benar (Submitting form) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteForms = document.querySelectorAll('.form-hapus-sweet');
        deleteForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = form.querySelector('button[type="submit"]');
                const message = btn.getAttribute('data-message') || "Yakin ingin menghapus data ini?";
                
                Swal.fire({
                    title: 'Konfirmasi Tindakan',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Lanjutkan!',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: { popup: 'rounded-4 border-0 shadow-lg' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Karena disubmit via JS, kita tambahkan hidden input untuk mendeteksi submit name
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = form.querySelector('input[type="hidden"]:first-child').name; // ambil nama dari input pertama, misal delete_materi
                        hiddenInput.value = '1';
                        form.appendChild(hiddenInput);
                        form.submit();
                    }
                });
            });
        });
    });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                document.body.appendChild(modal); // Pindahkan modal ke luar struktur kaca
            });
        });
    </script>
</body>
</html>