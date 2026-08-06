<?php
require '../auth/config.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit;
}

$superadmin_id = $_SESSION['user_id'];
$page = !empty($_GET['page']) ? $_GET['page'] : 'guru';

// ==========================================
// HANDLE POST REQUESTS (CRUD SUPERADMIN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- 1. MANAJEMEN GURU ---
    if (isset($_POST['add_guru'])) {
        $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, 'guru')");
        $stmt->execute([$_POST['name'], $_POST['username'], $_POST['password']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Akun Guru berhasil ditambahkan!'];
    }
    if (isset($_POST['edit_guru'])) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password = ? WHERE id = ? AND role = 'guru'");
        $stmt->execute([$_POST['name'], $_POST['username'], $_POST['password'], $_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Data Guru berhasil diubah!'];
    }
    if (isset($_POST['delete_guru'])) {
        $guru_to_delete = $_POST['id'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM saved_rpps WHERE guru_id = ?"); $stmt->execute([$guru_to_delete]);
            $stmt = $pdo->prepare("DELETE materials FROM materials JOIN subjects ON materials.subject_id = subjects.id JOIN classes ON subjects.class_id = classes.id WHERE classes.guru_id = ?"); $stmt->execute([$guru_to_delete]);
            $stmt = $pdo->prepare("DELETE subjects FROM subjects JOIN classes ON subjects.class_id = classes.id WHERE classes.guru_id = ?"); $stmt->execute([$guru_to_delete]);
            $stmt = $pdo->prepare("DELETE FROM classes WHERE guru_id = ?"); $stmt->execute([$guru_to_delete]);
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'guru'"); $stmt->execute([$guru_to_delete]);
            $pdo->commit();
            $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Akun Guru dan seluruh data terkait berhasil dihapus!'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_msg'] = ['type' => 'danger', 'text' => 'Gagal menghapus guru: ' . $e->getMessage()];
        }
    }

    // --- 2. MANAJEMEN DALIL ---
    if (isset($_POST['add_dalil'])) {
        $image_name = null;
        if (!empty($_FILES["dalil_image"]["name"])) {
            $target_dir = "../uploads/dalils/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $image_name = time() . '_' . basename($_FILES["dalil_image"]["name"]);
            move_uploaded_file($_FILES["dalil_image"]["tmp_name"], $target_dir . $image_name);
        }

        $stmt = $pdo->prepare("INSERT INTO dalils (name, source, source_name, translation, meaning, image_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['source'], $_POST['source_name'], $_POST['translation'], $_POST['meaning'], $image_name]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Dalil referensi berhasil ditambahkan!'];
    }
    if (isset($_POST['edit_dalil'])) {
        $id = $_POST['id'];
        $image_name = $_POST['old_image'];

        if (!empty($_FILES["dalil_image"]["name"])) {
            $target_dir = "../uploads/dalils/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $new_image = time() . '_' . basename($_FILES["dalil_image"]["name"]);
            if (move_uploaded_file($_FILES["dalil_image"]["tmp_name"], $target_dir . $new_image)) {
                $image_name = $new_image;
            }
        }

        $stmt = $pdo->prepare("UPDATE dalils SET name=?, source=?, source_name=?, translation=?, meaning=?, image_path=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['source'], $_POST['source_name'], $_POST['translation'], $_POST['meaning'], $image_name, $id]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Data Dalil berhasil diubah!'];
    }
    if (isset($_POST['delete_dalil'])) {
        $stmt = $pdo->prepare("DELETE FROM dalils WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Dalil referensi berhasil dihapus!'];
    }

    // --- 3. MANAJEMEN 4C ---
    if (isset($_POST['add_4c'])) {
        $stmt = $pdo->prepare("INSERT INTO four_c (category, description) VALUES (?, ?)");
        $stmt->execute([$_POST['category'], $_POST['description']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Indikator 4C berhasil ditambahkan!'];
    }
    if (isset($_POST['edit_4c'])) {
        $stmt = $pdo->prepare("UPDATE four_c SET category = ?, description = ? WHERE id = ?");
        $stmt->execute([$_POST['category'], $_POST['description'], $_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Indikator 4C berhasil diubah!'];
    }
    if (isset($_POST['delete_4c'])) {
        $stmt = $pdo->prepare("DELETE FROM four_c WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Indikator 4C berhasil dihapus!'];
    }

    // --- MANAJEMEN KOGNITIF C1-C6 ---
    if (isset($_POST['add_kognitif'])) {
        $stmt = $pdo->prepare("INSERT INTO kognitif_c1_c6 (level, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['level'], $_POST['name'], $_POST['description']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Level Kognitif berhasil ditambahkan!'];
    }
    if (isset($_POST['edit_kognitif'])) {
        $stmt = $pdo->prepare("UPDATE kognitif_c1_c6 SET level = ?, name = ?, description = ? WHERE id = ?");
        $stmt->execute([$_POST['level'], $_POST['name'], $_POST['description'], $_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Data Kognitif berhasil diubah!'];
    }
    if (isset($_POST['delete_kognitif'])) {
        $stmt = $pdo->prepare("DELETE FROM kognitif_c1_c6 WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Data Kognitif berhasil dihapus!'];
    }

    // --- 4. AUTENTIKASI SUPERADMIN ---
    if (isset($_POST['update_auth'])) {
        $old_username = $_POST['old_username'];
        $old_password = $_POST['old_password'];
        $new_username = !empty($_POST['new_username']) ? $_POST['new_username'] : $_SESSION['username'];
        $new_password = !empty($_POST['new_password']) ? $_POST['new_password'] : $old_password;

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND username = ? AND password = ?");
        $stmt->execute([$superadmin_id, $old_username, $old_password]);
        
        if ($stmt->fetch()) {
            $update_stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
            $update_stmt->execute([$new_username, $new_password, $superadmin_id]);
            $_SESSION['username'] = $new_username; 
            $_SESSION['flash_msg'] = ['type' => 'success', 'text' => 'Kredensial Anda berhasil diperbarui!'];
        } else {
            $_SESSION['flash_msg'] = ['type' => 'danger', 'text' => 'Username atau Password lama salah!'];
        }
    }

    header("Location: dashboard.php?page=$page");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Dashboard AI RPP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style/style.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .dalil-img-preview { width: 50px; height: 50px; object-fit: cover; border-radius: 12px; border: 2px solid #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1);}
        @media (max-width: 768px) {
            .sidebar-desktop { display: none !important; }
            .main-content { padding: 1.5rem !important; margin-left: 0 !important; }
        }
    </style>
</head>
<body>

    <!-- Navbar Mobile (w-100 ditambahkan agar full width) -->
    <nav class="navbar navbar-dark d-md-none px-3 sticky-top w-100 shadow-sm" style="background: linear-gradient(135deg, #1e1e2d 0%, #3b247a 100%);">
        <span class="navbar-brand mb-0 h1 fw-bold text-white d-flex align-items-center"><i class="bi bi-shield-lock-fill me-2 text-info"></i> Super Admin</span>
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
            <a href="?page=guru" class="<?= $page=='guru'?'active':'' ?>"><i class="bi bi-people-fill me-3 fs-5"></i> Manajemen Guru</a>
            <a href="?page=kognitif" class="<?= $page=='kognitif'?'active':'' ?>"><i class="bi bi-bar-chart-steps me-3 fs-5"></i> Kognitif C1-C6</a>
            <a href="?page=4c" class="<?= $page=='4c'?'active':'' ?>"><i class="bi bi-puzzle-fill me-3 fs-5"></i> Manajemen 4C</a>
            <a href="?page=dalil" class="<?= $page=='dalil'?'active':'' ?>"><i class="bi bi-book-half me-3 fs-5"></i> Manajemen Dalil</a>
            <a href="?page=auth" class="<?= $page=='auth'?'active':'' ?>"><i class="bi bi-shield-lock-fill me-3 fs-5"></i> Keamanan Akun</a>
            <div class="mt-auto border-top border-light border-opacity-10 pt-3">
                <a href="../auth/logout.php" class="text-danger fw-bold hover-bg-transparent"><i class="bi bi-box-arrow-right me-3 fs-5"></i> Keluar</a>
            </div>
        </div>
    </div>

    <div class="d-flex min-vh-100">
        <!-- Sidebar Desktop -->
        <div class="sidebar sidebar-nav p-4 d-flex flex-column sidebar-desktop position-fixed h-100">
            <div class="mb-4 d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-10 p-2 rounded-3 text-info">
                    <i class="bi bi-shield-lock-fill fs-3"></i>
                </div>
                <div>
                    <h5 class="text-white m-0 fw-bold">Super Admin</h5>
                    <small class="text-white-50">Sistem Pusat</small>
                </div>
            </div>
            
            <div class="d-flex flex-column flex-grow-1 mt-3 gap-1">
                <a href="?page=guru" class="<?= $page=='guru'?'active':'' ?>"><i class="bi bi-people-fill me-3 fs-5 text-opacity-75 text-white"></i> Data Guru</a>
                <a href="?page=kognitif" class="<?= $page=='kognitif'?'active':'' ?>"><i class="bi bi-bar-chart-steps me-3 fs-5 text-opacity-75 text-white"></i> Kognitif C1-C6</a>
                <a href="?page=4c" class="<?= $page=='4c'?'active':'' ?>"><i class="bi bi-puzzle-fill me-3 fs-5 text-opacity-75 text-white"></i> Kriteria 4C</a>
                <a href="?page=dalil" class="<?= $page=='dalil'?'active':'' ?>"><i class="bi bi-book-half me-3 fs-5 text-opacity-75 text-white"></i> Referensi Dalil</a>
                <a href="?page=auth" class="<?= $page=='auth'?'active':'' ?>"><i class="bi bi-gear-fill me-3 fs-5 text-opacity-75 text-white"></i> Pengaturan</a>
            </div>

            <div class="mt-auto border-top border-light border-opacity-10 pt-3">
                <a href="../auth/logout.php" class="text-danger bg-danger bg-opacity-10 fw-bold hover-bg-transparent"><i class="bi bi-box-arrow-right me-3 fs-5"></i> Keluar Sesi</a>
            </div>
        </div>

        <!-- Main Content (Hapus overflow-hidden agar bisa di scroll) -->
        <div class="main-content flex-grow-1 p-4 p-md-5" style="margin-left: 280px; min-height: 100vh;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bolder text-dark m-0" style="letter-spacing: -0.5px;">
                    <?= $page === 'auth' ? 'Pengaturan Keamanan' : 'Manajemen ' . ucfirst($page) ?>
                </h3>
            </div>

            <!-- Flash Message dengan SweetAlert2 -->
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
                            timer: 3000
                        });
                    });
                </script>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>
            
            <?php if ($page === 'guru'): ?>
                <div class="card mb-4 border-0 shadow-sm col-12 col-xl-9 rounded-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3 text-primary fw-bold">Daftarkan Guru Baru</h5>
                        <form method="POST" class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Nama Lengkap & Gelar</label>
                                <input type="text" name="name" class="form-control" placeholder="Contoh: Susi, S.Pd" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Username Login</label>
                                <input type="text" name="username" class="form-control" placeholder="guru_susi" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Password</label>
                                <input type="text" name="password" class="form-control" required>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" name="add_guru" class="btn btn-primary px-4 fw-bold w-100 w-md-auto">Tambah Guru</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 col-12">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle text-nowrap">
                                <thead class="table-light"><tr><th class="ps-4">No</th><th>Nama Guru</th><th>Username</th><th>Password</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $stmt = $pdo->query("SELECT * FROM users WHERE role='guru' ORDER BY id DESC");
                                    $gurus = $stmt->fetchAll();
                                    $no = 1;
                                    if(count($gurus) > 0):
                                        foreach($gurus as $g): 
                                    ?>
                                        <tr>
                                            <td class="ps-4"><?= $no++ ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($g['name']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($g['username']) ?></span></td>
                                            <td class="text-muted small"><?= htmlspecialchars($g['password']) ?></td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editGuru<?= $g['id'] ?>">Edit</button>
                                                    <form method="POST" class="form-hapus-sweet m-0">
                                                        <input type="hidden" name="delete_guru" value="1">
                                                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-message="Hapus akun guru ini secara permanen beserta semua data yang dimilikinya?">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Modal Edit Guru -->
                                        <div class="modal fade" id="editGuru<?= $g['id'] ?>" tabindex="-1">
                                          <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg">
                                              <form method="POST">
                                                  <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold">Edit Akun Guru</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                  </div>
                                                  <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                                    <div class="mb-3 text-start">
                                                        <label>Nama Lengkap</label>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($g['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3 text-start">
                                                        <label>Username</label>
                                                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($g['username']) ?>" required>
                                                    </div>
                                                    <div class="mb-3 text-start">
                                                        <label>Password</label>
                                                        <input type="text" name="password" class="form-control" value="<?= htmlspecialchars($g['password']) ?>" required>
                                                    </div>
                                                  </div>
                                                  <div class="modal-footer">
                                                    <button type="submit" name="edit_guru" class="btn btn-primary fw-bold w-100">Simpan Perubahan</button>
                                                  </div>
                                              </form>
                                            </div>
                                          </div>
                                        </div>
                                    <?php 
                                        endforeach; 
                                    else:
                                    ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada akun guru yang didaftarkan.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            
            <?php elseif ($page === 'dalil'): ?>
                <div class="card mb-4 border-0 shadow-sm rounded-4 col-12">
                    <div class="card-body p-3 p-md-4">
                        <h5 class="mb-3 text-primary fw-bold">Tambah Dalil Referensi RPP</h5>
                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <div class="col-12 col-md-5">
                                <label class="form-label small text-muted mb-1">Judul / Kata Kunci Dalil (Untuk Pemetaan AI)</label>
                                <input type="text" name="name" class="form-control" placeholder="Contoh: Manfaat Air Hujan" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small text-muted mb-1">Kategori Sumber</label>
                                <select name="source" class="form-select" required>
                                    <option value="Al-Quran">Al-Quran</option>
                                    <option value="Hadist">Hadist</option>
                                    <option value="Kitab">Kitab Lainnya</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Nama Sumber Lengkap</label>
                                <input type="text" name="source_name" class="form-control" placeholder="Contoh: QS. Al-Baqarah: 22" required>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Terjemahan Teks</label>
                                <textarea name="translation" class="form-control" rows="3" placeholder="Tuliskan arti ayat/hadist di sini..." required></textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Makna & Kaitan dengan Pelajaran</label>
                                <textarea name="meaning" class="form-control" rows="3" placeholder="Jelaskan kaitan agama dengan materi sains/sosial..." required></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Upload Gambar Referensi (Opsional)</label>
                                <input type="file" name="dalil_image" class="form-control" accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted">Gambar ini akan disisipkan di RPP Bahan Ajar jika dalil ini terpanggil.</small>
                            </div>

                            <div class="col-12 mt-4 text-md-end text-center">
                                <button type="submit" name="add_dalil" class="btn btn-primary px-5 fw-bold w-100 w-md-auto">💾 Simpan Dalil</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 col-12">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th class="ps-4">No</th><th>Gambar</th><th>Kata Kunci</th><th>Sumber</th><th style="min-width: 250px;">Makna / Kaitan</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $stmt = $pdo->query("SELECT * FROM dalils ORDER BY id DESC");
                                    $dalils = $stmt->fetchAll();
                                    $no = 1;
                                    if(count($dalils) > 0):
                                        foreach($dalils as $d): 
                                    ?>
                                        <tr>
                                            <td class="ps-4"><?= $no++ ?></td>
                                            <td>
                                                <?php if(!empty($d['image_path'])): ?>
                                                    <img src="../uploads/dalils/<?= $d['image_path'] ?>" class="dalil-img-preview" alt="Dalil">
                                                <?php else: ?>
                                                    <div class="bg-light text-muted d-flex align-items-center justify-content-center dalil-img-preview" style="font-size: 10px;">No Img</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-primary text-wrap" style="min-width:150px;"><?= htmlspecialchars($d['name']) ?></td>
                                            <td class="text-nowrap"><span class="badge bg-dark"><?= htmlspecialchars($d['source']) ?></span><br><small><?= htmlspecialchars($d['source_name']) ?></small></td>
                                            <td class="small text-muted text-wrap">
                                                <?= htmlspecialchars($d['meaning']) ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editDalil<?= $d['id'] ?>">Edit</button>
                                                    <form method="POST" class="form-hapus-sweet m-0">
                                                        <input type="hidden" name="delete_dalil" value="1">
                                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-message="Hapus dalil ini?">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Modal Edit Dalil -->
                                        <div class="modal fade" id="editDalil<?= $d['id'] ?>" tabindex="-1">
                                          <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg">
                                              <form method="POST" enctype="multipart/form-data">
                                                  <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold">Edit Dalil</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                  </div>
                                                  <div class="modal-body row g-3 text-start">
                                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                    <input type="hidden" name="old_image" value="<?= $d['image_path'] ?>">
                                                    
                                                    <div class="col-12 col-md-6">
                                                        <label>Kata Kunci / Judul</label>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($d['name']) ?>" required>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label>Kategori Sumber</label>
                                                        <select name="source" class="form-select" required>
                                                            <option value="Al-Quran" <?= $d['source']=='Al-Quran'?'selected':'' ?>>Al-Quran</option>
                                                            <option value="Hadist" <?= $d['source']=='Hadist'?'selected':'' ?>>Hadist</option>
                                                            <option value="Kitab" <?= $d['source']=='Kitab'?'selected':'' ?>>Kitab</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label>Nama Sumber Lengkap</label>
                                                        <input type="text" name="source_name" class="form-control" value="<?= htmlspecialchars($d['source_name']) ?>" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label>Terjemahan</label>
                                                        <textarea name="translation" class="form-control" rows="3" required><?= htmlspecialchars($d['translation']) ?></textarea>
                                                    </div>
                                                    <div class="col-12">
                                                        <label>Makna / Kaitan</label>
                                                        <textarea name="meaning" class="form-control" rows="3" required><?= htmlspecialchars($d['meaning']) ?></textarea>
                                                    </div>
                                                    <div class="col-12">
                                                        <label>Ganti Gambar (Biarkan kosong jika tidak ingin ganti)</label>
                                                        <input type="file" name="dalil_image" class="form-control" accept="image/jpeg,image/png,image/gif">
                                                    </div>
                                                  </div>
                                                  <div class="modal-footer">
                                                    <button type="submit" name="edit_dalil" class="btn btn-primary fw-bold w-100 w-md-auto">Simpan Perubahan</button>
                                                  </div>
                                              </form>
                                            </div>
                                          </div>
                                        </div>
                                    <?php 
                                        endforeach; 
                                    else:
                                    ?>
                                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada dalil yang ditambahkan ke sistem.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($page === 'kognitif'): ?>
                <div class="card mb-4 border-0 shadow-sm col-12 col-xl-9 rounded-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3 text-primary fw-bold">Tambah Level Kognitif (Taksonomi Bloom)</h5>
                        <form method="POST" class="row g-3">
                            <div class="col-12 col-md-3">
                                <label class="form-label small text-muted mb-1">Level (C1 - C6)</label>
                                <input type="text" name="level" class="form-control" placeholder="Contoh: C1" required>
                            </div>
                            <div class="col-12 col-md-9">
                                <label class="form-label small text-muted mb-1">Nama Taksonomi</label>
                                <input type="text" name="name" class="form-control" placeholder="Contoh: Mengingat (Remembering)" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Deskripsi / Kemampuan Siswa</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Murid dapat mengingat..." required></textarea>
                            </div>
                            <div class="col-12 text-md-end text-center">
                                <button type="submit" name="add_kognitif" class="btn btn-primary px-4 fw-bold w-100 w-md-auto">Tambah Kognitif</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 col-12 col-xl-9 rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th class="ps-4">No</th><th>Level</th><th>Nama</th><th style="min-width: 250px;">Deskripsi</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $stmt = $pdo->query("SELECT * FROM kognitif_c1_c6 ORDER BY level ASC");
                                    $kognitifs = $stmt->fetchAll();
                                    $no = 1;
                                    if(count($kognitifs) > 0):
                                        foreach($kognitifs as $kg): 
                                    ?>
                                        <tr>
                                            <td class="ps-4"><?= $no++ ?></td>
                                            <td><span class="badge bg-primary text-white"><?= htmlspecialchars($kg['level']) ?></span></td>
                                            <td class="fw-bold"><?= htmlspecialchars($kg['name']) ?></td>
                                            <td class="small text-wrap"><?= htmlspecialchars($kg['description']) ?></td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editKognitif<?= $kg['id'] ?>">Edit</button>
                                                    <form method="POST" class="form-hapus-sweet m-0">
                                                        <input type="hidden" name="delete_kognitif" value="1">
                                                        <input type="hidden" name="id" value="<?= $kg['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-message="Hapus level kognitif ini?">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Modal Edit Kognitif -->
                                        <div class="modal fade" id="editKognitif<?= $kg['id'] ?>" tabindex="-1">
                                          <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg">
                                              <form method="POST">
                                                  <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold">Edit Level Kognitif</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                  </div>
                                                  <div class="modal-body text-start">
                                                    <input type="hidden" name="id" value="<?= $kg['id'] ?>">
                                                    <div class="mb-3">
                                                        <label>Level (C1 - C6)</label>
                                                        <input type="text" name="level" class="form-control" value="<?= htmlspecialchars($kg['level']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Nama Taksonomi</label>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($kg['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Deskripsi</label>
                                                        <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($kg['description']) ?></textarea>
                                                    </div>
                                                  </div>
                                                  <div class="modal-footer">
                                                    <button type="submit" name="edit_kognitif" class="btn btn-primary fw-bold w-100">Simpan Perubahan</button>
                                                  </div>
                                              </form>
                                            </div>
                                          </div>
                                        </div>
                                    <?php 
                                        endforeach; 
                                    else:
                                    ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data kognitif.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($page === '4c'): ?>
                <div class="card mb-4 border-0 shadow-sm col-12 col-xl-9 rounded-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3 text-primary fw-bold">Tambah Indikator 4C</h5>
                        <form method="POST" class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Kategori 4C</label>
                                <select name="category" class="form-select" required>
                                    <option value="Critical Thinking">Critical Thinking</option>
                                    <option value="Creativity">Creativity</option>
                                    <option value="Collaboration">Collaboration</option>
                                    <option value="Communication">Communication</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label small text-muted mb-1">Deskripsi / Pernyataan Indikator</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Contoh: Murid dapat merumuskan masalah..." required></textarea>
                            </div>
                            <div class="col-12 text-md-end text-center">
                                <button type="submit" name="add_4c" class="btn btn-primary px-4 fw-bold w-100 w-md-auto">Tambah 4C</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 col-12 col-xl-9 rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th class="ps-4">No</th><th>Kategori</th><th style="min-width: 250px;">Deskripsi</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $stmt = $pdo->query("SELECT * FROM four_c ORDER BY id DESC");
                                    $four_cs = $stmt->fetchAll();
                                    $no = 1;
                                    if(count($four_cs) > 0):
                                        foreach($four_cs as $fc): 
                                    ?>
                                        <tr>
                                            <td class="ps-4"><?= $no++ ?></td>
                                            <td class="text-nowrap"><span class="badge bg-info text-dark"><?= htmlspecialchars($fc['category']) ?></span></td>
                                            <td class="small text-wrap"><?= htmlspecialchars($fc['description']) ?></td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#edit4c<?= $fc['id'] ?>">Edit</button>
                                                    <form method="POST" class="form-hapus-sweet m-0">
                                                        <input type="hidden" name="delete_4c" value="1">
                                                        <input type="hidden" name="id" value="<?= $fc['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-message="Hapus indikator 4C ini?">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Modal Edit 4C -->
                                        <div class="modal fade" id="edit4c<?= $fc['id'] ?>" tabindex="-1">
                                          <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg">
                                              <form method="POST">
                                                  <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold">Edit Indikator 4C</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                  </div>
                                                  <div class="modal-body text-start">
                                                    <input type="hidden" name="id" value="<?= $fc['id'] ?>">
                                                    <div class="mb-3">
                                                        <label>Kategori</label>
                                                        <select name="category" class="form-select" required>
                                                            <option value="Critical Thinking" <?= $fc['category']=='Critical Thinking'?'selected':'' ?>>Critical Thinking</option>
                                                            <option value="Creativity" <?= $fc['category']=='Creativity'?'selected':'' ?>>Creativity</option>
                                                            <option value="Collaboration" <?= $fc['category']=='Collaboration'?'selected':'' ?>>Collaboration</option>
                                                            <option value="Communication" <?= $fc['category']=='Communication'?'selected':'' ?>>Communication</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Deskripsi</label>
                                                        <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($fc['description']) ?></textarea>
                                                    </div>
                                                  </div>
                                                  <div class="modal-footer">
                                                    <button type="submit" name="edit_4c" class="btn btn-primary fw-bold w-100">Simpan Perubahan</button>
                                                  </div>
                                              </form>
                                            </div>
                                          </div>
                                        </div>
                                    <?php 
                                        endforeach; 
                                    else:
                                    ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada indikator 4C.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($page === 'auth'): ?>
                <div class="card mb-4 shadow-sm border-0 col-12 col-md-8 col-xl-6 rounded-4">
                    <div class="card-body p-3 p-md-4">
                        <form method="POST">
                            <h5 class="mb-3 text-primary fw-bold border-bottom pb-2">Verifikasi Akun Lama</h5>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Username Saat Ini</label>
                                <input type="text" name="old_username" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted small">Password Saat Ini</label>
                                <input type="password" name="old_password" class="form-control" required>
                            </div>
                            
                            <h5 class="mb-3 mt-4 text-primary fw-bold border-bottom pb-2">Ganti Kredensial <small class="text-muted fw-normal">(Opsional)</small></h5>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Username Baru</label>
                                <input type="text" name="new_username" class="form-control" placeholder="Kosongkan jika tidak diganti">
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted small">Password Baru</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Kosongkan jika tidak diganti">
                            </div>
                            <button type="submit" name="update_auth" class="btn btn-primary w-100 py-2 fw-bold">💾 Perbarui Akun Saya</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Script Global untuk Konfirmasi Hapus SweetAlert2 -->
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
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = form.querySelector('input[type="hidden"]:first-child').name; 
                        hiddenInput.value = '1';
                        form.appendChild(hiddenInput);
                        form.submit();
                    }
                });
            });
        });
    });
    </script>
    
    <!-- Script Anti-Glitch Modal Glassmorphism -->
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