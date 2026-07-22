<?php
require 'config.php';

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'superadmin') header('Location: ../superadmin/dashboard.php');
    else header('Location: ../guru/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password']; // Di tahap produksi disarankan menggunakan password_hash()

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND password = ?');
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        
        if ($user['role'] == 'superadmin') header('Location: ../superadmin/dashboard.php');
        else header('Location: ../guru/dashboard.php');
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AI RPP Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            /* Background Gradient Elegan */
            background: linear-gradient(-45deg, #3b247a 0%, #6038c6 50%, #1e1e2d 100%);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Ornamen Lingkaran Blur di Background */
        .circle-1 {
            position: absolute; width: 400px; height: 400px;
            background: rgba(138, 43, 226, 0.4);
            border-radius: 50%; top: -100px; left: -100px;
            filter: blur(80px); z-index: 0;
        }
        .circle-2 {
            position: absolute; width: 300px; height: 300px;
            background: rgba(0, 212, 255, 0.3);
            border-radius: 50%; bottom: -50px; right: -50px;
            filter: blur(60px); z-index: 0;
        }

        /* Glassmorphism Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 420px;
            z-index: 1;
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        }

        .brand-icon {
            background: linear-gradient(135deg, #6038c6 0%, #00d4ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 3rem;
            line-height: 1;
        }

        /* Styling Input Fields */
        .input-group-text {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-right: none;
            color: #64748b;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }
        
        .form-control {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: none;
            padding: 14px 15px 14px 0;
            font-size: 15px;
            color: #334155;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .form-control:focus {
            background: #fff;
            border-color: #6038c6;
            box-shadow: none;
        }

        .form-control:focus + .input-group-text,
        .input-group:focus-within .input-group-text {
            background: #fff;
            border-color: #6038c6;
            color: #6038c6;
        }
        
        .input-group:focus-within {
            box-shadow: 0 0 0 4px rgba(96, 56, 198, 0.15);
            border-radius: 12px;
        }

        /* Tombol Login */
        .btn-login {
            background: linear-gradient(135deg, #6038c6 0%, #4338ca 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            padding: 14px;
            width: 100%;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(96, 56, 198, 0.3);
            color: white;
        }

        .btn-login:active {
            transform: translateY(1px);
        }

        /* Notifikasi Error */
        .alert-error {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
            border-radius: 8px;
            font-size: 14px;
            padding: 12px 16px;
        }
    </style>
</head>
<body>

    <!-- Dekorasi Background -->
    <div class="circle-1"></div>
    <div class="circle-2"></div>

    <div class="container px-4 d-flex justify-content-center">
        <div class="login-card">
            
            <div class="text-center mb-4">
                <div class="brand-icon mb-2">
                    <i class="bi bi-robot"></i>
                </div>
                <h3 class="fw-bolder text-dark mb-1" style="letter-spacing: -0.5px;">AI RPP Generator</h3>
                <p class="text-muted small mb-0">Platform Cerdas Penyusun Kurikulum</p>
            </div>

            <?php if($error): ?>
                <div class="alert-error d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" required placeholder="Masukkan username">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" required placeholder="Masukkan password">
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    Masuk ke Dashboard <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted" style="font-size: 12px;">&copy; <?= date('Y') ?> Aplikasi Modul Ajar AI. All rights reserved.</small>
            </div>

        </div>
    </div>

</body>
</html>