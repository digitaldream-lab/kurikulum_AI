<?php
require '../auth/config.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    die('Unauthorized');
}

$guru_id = $_SESSION['user_id'];
$title = $_POST['title'] ?? 'RPP Tanpa Judul';
$content = $_POST['content'] ?? '';
$rpp_id = $_POST['rpp_id'] ?? '';

if (empty($content)) {
    die('Content empty');
}

try {
    if (!empty($rpp_id)) {
        // Jika sudah pernah disimpan, lakukan UPDATE (Timpa)
        $stmt = $pdo->prepare("UPDATE saved_rpps SET title = ?, content = ? WHERE id = ? AND guru_id = ?");
        $stmt->execute([$title, $content, $rpp_id, $guru_id]);
        echo $rpp_id;
    } else {
        // Jika belum pernah disimpan, buat riwayat BARU
        $stmt = $pdo->prepare("INSERT INTO saved_rpps (guru_id, title, content) VALUES (?, ?, ?)");
        $stmt->execute([$guru_id, $title, $content]);
        echo $pdo->lastInsertId(); // Mengembalikan ID RPP yang baru dibuat
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>