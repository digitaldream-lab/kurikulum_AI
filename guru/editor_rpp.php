<?php
require '../auth/config.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../auth/login.php");
    exit;
}

set_time_limit(180); // Waktu diperpanjang agar AI tidak timeout

$guru_name = $_SESSION['name'];
$guru_id = $_SESSION['user_id'];

// Variabel untuk mengecek dari mana user berasal
$rpp_id = $_POST['rpp_id'] ?? null;
$class_id = $_POST['class_id'] ?? null;
$material_id = $_POST['material_id'] ?? null;

$is_history = false;
$history_content = "";
$material_title = "RPP Baru";
$subject_name = "Mata Pelajaran";
$class_name = "Kelas";
$jenjang = "Jenjang";
$tahun_pelajaran = date('Y') . "/" . (date('Y') + 1);

// Fungsi untuk menampilkan error dengan antarmuka yang cantik
function showApiError($title, $message, $rawError = "")
{
    $errorHtml = "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error Koneksi API</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='bg-light d-flex align-items-center justify-content-center min-vh-100 p-3'>
        <div class='container text-center'>
            <div class='card shadow-lg border-0 rounded-4 p-4 p-md-5 mx-auto' style='max-width: 600px;'>
                <div class='display-1 mb-3'>⚠️</div>
                <h3 class='text-danger fw-bold'>$title</h3>
                <p class='text-muted mt-3 mb-4'>$message</p>
                " . ($rawError ? "<div class='bg-dark text-warning p-3 rounded-3 text-start mb-4' style='font-family: monospace; font-size: 13px; overflow-x: auto;'><b>Log Pesan Asli (Server):</b><br/>" . htmlspecialchars($rawError) . "</div>" : "") . "
                <div class='alert alert-info border-info text-start small'>
                    <b>💡 Solusi:</b> Silakan kembali ke Dashboard, masuk ke menu <b>Custom Token API</b>, lalu pastikan pengaturan token Anda sudah benar.
                </div>
                <div class='d-flex flex-column flex-md-row gap-2 justify-content-center mt-3'>
                    <a href='dashboard.php?page=generate' class='btn btn-outline-secondary px-4 py-2 fw-bold w-100 w-md-auto'>&larr; Kembali</a>
                    <a href='dashboard.php?page=api_token' class='btn btn-primary px-4 py-2 fw-bold w-100 w-md-auto'>🔑 Atur Token API</a>
                </div>
            </div>
        </div>
    </body>
    </html>";
    die($errorHtml);
}

// ==========================================
// LOGIKA 1: JIKA MEMBUKA DARI RIWAYAT (DATABASE)
// ==========================================
if ($rpp_id) {
    $stmt = $pdo->prepare("SELECT * FROM saved_rpps WHERE id = ? AND guru_id = ?");
    $stmt->execute([$rpp_id, $guru_id]);
    $savedRPP = $stmt->fetch();

    if (!$savedRPP) {
        die("RPP tidak ditemukan di riwayat Anda.");
    }

    $is_history = true;
    $material_title = $savedRPP['title'];
    $history_content = $savedRPP['content'];

    // Ambil Token API User (hanya untuk digunakan di sidebar Editor AI)
    $stmtApi = $pdo->prepare("SELECT api_provider, api_token FROM users WHERE id = ?");
    $stmtApi->execute([$guru_id]);
    $userApi = $stmtApi->fetch();

    $api_key = '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';
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $api_model = 'llama-3.3-70b-versatile';

    $custom_token = trim($userApi['api_token'] ?? '');
    $provider = $userApi['api_provider'] ?? 'groq';

    if (!empty($custom_token)) {
        $api_key = $custom_token;
        if ($provider === 'openai') {
            $url = 'https://api.openai.com/v1/chat/completions';
            $api_model = 'gpt-4o-mini';
        } elseif ($provider === 'gemini') {
            $url = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
            $api_model = 'gemini-2.5-flash';
        } elseif ($provider === 'groq') {
            $url = 'https://api.groq.com/openai/v1/chat/completions';
            $api_model = 'llama-3.3-70b-versatile';
        }
    }
}
// ==========================================
// LOGIKA 2: JIKA GENERATE BARU DENGAN AI
// ==========================================
else {
    if (!$class_id || !$material_id) {
        die("Data tidak valid. Silakan kembali ke dashboard.");
    }

    // Ambil Nama Kelas beserta Jenjangnya
    $stmtClass = $pdo->prepare("SELECT name, jenjang FROM classes WHERE id = ?");
    $stmtClass->execute([$class_id]);
    $classData = $stmtClass->fetch();
    $class_name = $classData['name'] ?? 'Tidak Diketahui';
    $jenjang = $classData['jenjang'] ?? 'SD/MI';

    $stmtMaterial = $pdo->prepare("SELECT m.title, m.file_path, s.name as mapel_name FROM materials m JOIN subjects s ON m.subject_id = s.id WHERE m.id = ?");
    $stmtMaterial->execute([$material_id]);
    $materialData = $stmtMaterial->fetch();
    $material_title = $materialData['title'] ?? 'Materi Default';
    $subject_name = $materialData['mapel_name'] ?? 'Mata Pelajaran Default';
    $file_path = $materialData['file_path'] ?? '';

    // EKSTRAKSI TEKS DARI PDF MENGGUNAKAN LIBRARY
    $pdf_text_content = "(Teks PDF belum dapat dibaca. Pastikan Anda sudah menginstall smalot/pdfparser)";
    $pdf_full_path = "../uploads/" . $file_path;

    if (!empty($file_path) && file_exists($pdf_full_path)) {
        $autoload_path = '';
        if (file_exists('../vendor/autoload.php')) {
            $autoload_path = '../vendor/autoload.php';
        } elseif (file_exists('../../vendor/autoload.php')) {
            $autoload_path = '../../vendor/autoload.php';
        } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php')) {
            $autoload_path = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
        }

        if (!empty($autoload_path)) {
            require_once $autoload_path;
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($pdf_full_path);
                $pdf_text_content = $pdf->getText();

                $pdf_text_content = mb_convert_encoding($pdf_text_content, 'UTF-8', 'UTF-8');
                $pdf_text_content = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $pdf_text_content);
                $pdf_text_content = str_replace(['"', '\\', '/', "\n", "\r", "\t"], ' ', $pdf_text_content);
                $pdf_text_content = preg_replace('/\s+/u', ' ', $pdf_text_content);

                // BATAS EKSTRAKSI DIKURANGI AGAR TIDAK KENA LIMIT API (Max 5000 karakter)
                if (mb_strlen($pdf_text_content, 'UTF-8') > 5000) {
                    $pdf_text_content = mb_substr($pdf_text_content, 0, 5000, 'UTF-8') . "... [TEKS DIPOTONG]";
                }
            } catch (Exception $e) {
                $pdf_text_content = "Error membaca isi PDF: " . $e->getMessage();
            }
        } else {
            $pdf_text_content = "Sistem gagal membaca materi. Silakan install library Smalot PDF Parser di terminal (composer require smalot/pdfparser).";
        }
    }

    // CEK APAKAH INI KELAS TINGGI (4,5,6) ATAU RENDAH (1,2,3) UNTUK SOAL EVALUASI
    $is_high_class = false;
    if (preg_match('/\b(4a|4b|4c|4d|4e|4f|4g|4h|4i|4j|4k|4l|5a|5b|5c|5d|5e|5f|5g|5h|5i|5j|5k|5l|6a|6b|6c|6d|6e|6f|6g|6h|6i|6j|6k|6l|IV|V|VI)\b/i', $class_name)) {
        $is_high_class = true;
    }

    // AMBIL DATA KOGNITIF C1-C6 DARI DATABASE UNTUK PROMPT
    $stmtKognitif = $pdo->query("SELECT * FROM kognitif_c1_c6 ORDER BY level ASC");
    $kognitifs = $stmtKognitif->fetchAll();
    $kognitif_teks = "";
    foreach ($kognitifs as $kg) {
        $kognitif_teks .= "- " . $kg['level'] . " (" . $kg['name'] . "): " . $kg['description'] . "\n";
    }

    // AMBIL DATA 4C DARI DATABASE UNTUK PROMPT
    $stmt4c = $pdo->query("SELECT * FROM four_c");
    $fourCs = $stmt4c->fetchAll();
    $fourc_teks = "";
    foreach ($fourCs as $fc) {
        $fourc_teks .= "- " . $fc['category'] . ": " . $fc['description'] . "\n";
    }

    // PENGECEKAN DALIL MURNI DARI DATABASE (TIDAK MELIBATKAN AI)
    $dalil_html = "";
    $stmtDalils = $pdo->query("SELECT * FROM dalils");
    $dalils = $stmtDalils->fetchAll();
    $keywords = explode(' ', strtolower($material_title));

    foreach ($dalils as $d) {
        foreach ($keywords as $kw) {
            if (strlen($kw) > 3 && strpos(strtolower($d['name']), $kw) !== false) {

                // Cek apakah dalil ini memiliki gambar referensi
                $image_tag = "";
                if (!empty($d['image_path'])) {
                    $img_file = "../uploads/dalils/" . $d['image_path'];
                    // Jika file fisiknya ada di server
                    if (file_exists($img_file)) {
                        // Konversi gambar ke Base64 agar tidak hilang saat di-export ke Word
                        $img_data = base64_encode(file_get_contents($img_file));
                        $mime_type = mime_content_type($img_file);
                        $base64_src = "data:" . $mime_type . ";base64," . $img_data;

                        // Buat tag HTML untuk gambar (menggunakan width kuno untuk Word)
                        $image_tag = "
                        <div style='text-align:center; margin: 15px 0;'>
                            <img src='{$base64_src}' width='80%' style='max-width:100%; border-radius:10px; cursor:pointer; border: 2px solid #eab308;' alt='Gambar Dalil' title='Klik untuk mengubah ukuran' onclick='window.resizeImageForWord(this);'>
                        </div>";
                    }
                }

                $dalil_html = "
                    <div class='cegah-potong' style='background-color:#fffbee; border:3px dashed #f5c000; padding: 20px; border-radius: 20px; margin-top: 30px; box-shadow: 4px 4px 0px rgba(0,0,0,0.05); text-align:center;'>
                        <h3 style='color:#b88f00; margin-top:0; font-weight:900;'>📖 Nilai-Nilai Agama Terkait</h3>
                        <h4 style='color:#333; margin-top:5px; margin-bottom:15px;'>Sumber: " . htmlspecialchars($d['source']) . " (" . htmlspecialchars($d['source_name']) . ")</h4>
                        
                        " . $image_tag . "

                        <div style='margin:15px 0;'>
                            <i style='color:#555; line-height: 1.6; font-size: 16px;'>\"" . htmlspecialchars($d['translation']) . "\"</i>
                        </div>
                        <div style='background-color: white; padding: 15px; border-radius: 10px; display: inline-block; text-align:left;'>
                            <b style='color:#b88f00;'>💡 Kaitan dengan Materi:</b><br>
                            <span style='color:#444; line-height: 1.6;'>" . htmlspecialchars($d['meaning']) . "</span>
                        </div>
                    </div>
                ";
                break 2;
            }
        }
    }

    // PENGATURAN CUSTOM TOKEN API 
    $stmtApi = $pdo->prepare("SELECT api_provider, api_token FROM users WHERE id = ?");
    $stmtApi->execute([$guru_id]);
    $userApi = $stmtApi->fetch();

    $api_key = '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';
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $api_model = 'llama-3.3-70b-versatile';

    $custom_token = trim($userApi['api_token'] ?? '');
    $provider = $userApi['api_provider'] ?? 'groq';

    if (!empty($custom_token)) {
        $api_key = $custom_token;
        if ($provider === 'openai') {
            $url = 'https://api.openai.com/v1/chat/completions';
            $api_model = 'gpt-4o-mini';
        } elseif ($provider === 'gemini') {
            $url = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
            $api_model = 'gemini-2.5-flash';
        } elseif ($provider === 'groq') {
            $url = 'https://api.groq.com/openai/v1/chat/completions';
            $api_model = 'llama-3.3-70b-versatile';
        }
    }

    $system_prompt = "Anda adalah pakar pembuat RPP Kurikulum Merdeka yang ahli dalam pedagogi berbagai jenjang pendidikan. OUTPUTKAN HANYA DALAM FORMAT JSON SAJA. JANGAN ada teks HTML tabel, JANGAN ada kata pengantar. PASTIKAN JSON ANDA VALID DAN TIDAK TERPOTONG.";

    $user_prompt = "
Buatkan konten materi RPP Kurikulum Merdeka untuk:
- Materi Pokok: '$material_title'
- Mata Pelajaran: '$subject_name'
- Target Peserta Didik: Jenjang '$jenjang', Kelas '$class_name'

=== BACA REFERENSI BUKU/MATERI PDF BERIKUT ===
Berikut adalah teks dari file materi PDF yang diunggah oleh guru. Anda WAJIB merumuskan materi (bahan ajar) dan soal evaluasi berdasarkan teks ini:
\"\"\"
$pdf_text_content
\"\"\"
==============================================

WAJIB KEMBALIKAN DALAM FORMAT JSON SEPERTI STRUKTUR BERIKUT.
ATURAN KHUSUS (ATURAN MUTLAK JANGAN DILANGGAR!):
1. TUJUAN PEMBELAJARAN (C1-C6): Buatkan tujuan pembelajaran berdasarkan taksonomi bloom berikut:
$kognitif_teks
Gunakan format 'Tujuan (Level C...)'. Pilih level kognitif yang relevan (tidak harus semua C1-C6, sesuaikan dengan materi).
2. SINKRONISASI KOGNITIF SOAL: Anda WAJIB memastikan bahwa level kognitif (C1-C6) yang muncul di bagian 'soal_evaluasi' SAMA PERSIS dengan level kognitif yang Anda tetapkan di 'Tujuan Pembelajaran'. Jika di tujuan hanya ada C1, C2, C3, maka soal juga HANYA boleh C1, C2, C3. JANGAN memunculkan level yang tidak ada di tujuan!
3. PENGALAMAN BELAJAR (INTI & 4C): Letakkan label 4C (Communication, Collaboration, Creativity, Critical Thinking) DI DALAM poin-poin kegiatan nomor (tahapan rinciannya), BUKAN pada judul tahap utamanya. Contoh yang benar: '1. Siswa dibagi kelompok... (Collaboration)'.
4. KRITERIA RUBRIK TERTAKAR: Pada bagian 'asesmen', deskripsi kriteria penilaian (Sangat Baik, Baik, dsb) WAJIB menggunakan tolak ukur yang to-the-point, pasti, dan blak-blakan menggunakan ANGKA/FREKUENSI. JANGAN menggunakan kata ngambang seperti 'Sangat aktif' atau 'Cukup aktif'. Contoh benar: 'Siswa lebih dari 8 kali terlibat dalam proses pembelajaran'.
5. KATA KERJA KD & INDIKATOR SOAL: Pada 'soal_evaluasi', kata pertama pada 'kd' dan 'indikator' WAJIB menyesuaikan dengan level kognitifnya. Jika level C1 (Mengingat), maka KD wajib berbunyi 'Mengingat konsep...', BUKAN 'Memahami konsep'.
6. JUMLAH SOAL EVALUASI: 
- PILIHAN GANDA: Anda WAJIB menghasilkan TEPAT 10 SOAL Pilihan Ganda.
- URAIAN / ESSAY: " . ($is_high_class ? "Sistem mendeteksi ini KELAS TINGGI. Anda WAJIB menghasilkan TEPAT 5 SOAL Uraian (Essay). JANGAN DIKOSONGKAN!" : "Sistem mendeteksi ini KELAS RENDAH. JANGAN buat soal Uraian, biarkan array 'uraian' KOSONG [].") . "

(Isi semua nilainya dengan teks yang detail sesuai aturan di atas):
{
    \"identifikasi\": {
        \"pengetahuan_awal\": \"Penjelasan detail 1-2 kalimat menyesuaikan jenjang $jenjang\",
        \"minat_belajar\": \"Penjelasan detail 1-2 kalimat menyesuaikan jenjang $jenjang\",
        \"kebutuhan_bermakna\": \"Penjelasan detail 1-2 kalimat menyesuaikan jenjang $jenjang\",
        \"kebutuhan_sadar\": \"Penjelasan detail 1-2 kalimat menyesuaikan jenjang $jenjang\",
        \"kebutuhan_senang\": \"Penjelasan detail 1-2 kalimat menyesuaikan jenjang $jenjang\",
        \"materi_pokok\": [\"poin materi 1\", \"poin materi 2\", \"poin materi 3\"],
        \"dimensi_profil\": [\"DPL 1 Keimanan...\", \"DPL 3 Penalaran...\"]
    },
    \"desain\": {
        \"capaian\": \"Kalimat capaian sesuai tingkat $jenjang...\",
        \"lintas_disiplin\": \"<b>Mapel 1</b><br>- kaitannya...<br><b>Mapel 2</b><br>- kaitannya...\",
        \"tujuan\": [\"1. Mengingat... (C1)\", \"2. Memahami... (C2)\", \"3. Menerapkan... (C3)\"],
        \"pendekatan_model\": \"<b>Pendekatan:</b> Deep Learning<br><b>Model:</b> Problem Based Learning<br><b>Sintaks:</b><br>1. Orientasi<br>2. Mengorganisasi<br>3. Membimbing<br>4. Menyajikan<br>5. Evaluasi<br><b>Metode:</b> Diskusi, Tanya Jawab\",
        \"kemitraan\": \"Teman sebaya: Murid saling...\",
        \"lingkungan\": [\"1. Ruang kelas...\", \"2. Ruang virtual...\", \"3. Budaya belajar...\"],
        \"digital\": [\"1. Video Youtube...\", \"2. Presentasi Canva...\"]
    },
    \"pengalaman\": {
        \"pendahuluan\": {
            \"orientasi\": [\"1. Guru membuka pembelajaran dengan salam...\", \"2. Apersepsi yang menggugah pikiran...\"],
            \"motivasi\": [\"1. Murid dengan panduan guru melakukan aktivitas unik/ice breaking...\", \"2. ...\"]
        },
        \"inti\": [
            {
                \"tahap\": \"Tahap 1. Mengorientasikan murid terhadap masalah\",
                \"kegiatan\": [\"1. Siswa diberikan contoh soal terkait materi (Communication)\", \"2. ...\"]
            },
            {
                \"tahap\": \"Tahap 2. Mengorganisasikan murid untuk belajar bersama\",
                \"kegiatan\": [\"1. Siswa dibagi menjadi kelompok untuk mengerjakan soal (Collaboration)\", \"2. ...\"]
            },
            {
                \"tahap\": \"Tahap 3. Guru Membimbing Penyelidikan\",
                \"kegiatan\": [\"1. ... (Critical Thinking)\", \"2. ...\"]
            },
            {
                \"tahap\": \"Tahap 4. Mengembangkan dan Menyajikan Hasil\",
                \"kegiatan\": [\"1. ... (Creativity)\", \"2. ...\"]
            },
            {
                \"tahap\": \"Tahap 5. Menganalisis dan Mengevaluasi Proses\",
                \"kegiatan\": [\"1. ... (Communication)\", \"2. ...\"]
            }
        ],
        \"penutup\": [\"1. Murid menyimpulkan\", \"2. Mengerjakan evaluasi\", \"3. Berdoa\"]
    },
    \"asesmen\": {
        \"awal_pertanyaan\": [\"Pertanyaan pemantik dari materi?\", \"Pertanyaan pemantik 2?\", \"Pertanyaan pemantik 3?\"],
        \"awal_mahir\": \"Menjawab 4-5 pertanyaan dengan benar dan logis...\",
        \"awal_cakap\": \"Menjawab 2-3 pertanyaan dengan benar...\",
        \"awal_berkembang\": \"Menjawab 0-1 pertanyaan dengan benar...\",
        \"proses_terlibat\": [\"Siswa lebih dari 8 kali terlibat dalam proses pembelajaran (4)\", \"Siswa 5-7 kali terlibat (3)\", \"Siswa 2-4 kali terlibat (2)\", \"Siswa 0-1 kali terlibat (1)\"],
        \"proses_analisis\": [\"Siswa mampu mengurai 4 elemen materi dengan tepat (4)\", \"Siswa mampu mengurai 3 elemen (3)\", \"Siswa mampu mengurai 2 elemen (2)\", \"Siswa hanya mengurai 1 elemen (1)\"],
        \"proses_kerjasama\": [\"Siswa memimpin diskusi dan membantu lebih dari 2 temannya (4)\", \"Siswa berkontribusi ide 3 kali (3)\", \"Siswa hanya mendengarkan (2)\", \"Siswa tidak fokus pada tugas kelompok (1)\"],
        \"proses_saji\": [\"Presentasi memenuhi 4 kriteria kelengkapan (4)\", \"Presentasi memenuhi 3 kriteria (3)\", \"Presentasi memenuhi 2 kriteria (2)\", \"Presentasi memenuhi 1 kriteria (1)\"]
    },
    \"soal_evaluasi\": {
        \"pilihan_ganda\": [
            {\"level_kognitif\": \"C1 (Mengingat)\", \"kd\": \"Mengingat konsep...\", \"indikator\": \"Murid dapat mengingat...\", \"soal\": \"[Soal 1] ...<br>a. Opsi spesifik<br>b. Opsi spesifik<br>c. Opsi spesifik<br>d. Opsi spesifik\", \"kunci\": \"A\"},
            {\"level_kognitif\": \"C1 (Mengingat)\", \"kd\": \"Mengingat konsep...\", \"indikator\": \"Murid dapat mengingat...\", \"soal\": \"[Soal 2] ...\", \"kunci\": \"B\"},
            {\"level_kognitif\": \"C2 (Memahami)\", \"kd\": \"Memahami konsep...\", \"indikator\": \"Murid dapat memahami...\", \"soal\": \"[Soal 3] ...\", \"kunci\": \"C\"}
        ],
        \"uraian\": [
            " . ($is_high_class ? "
            {\"level_kognitif\": \"C3 (Menerapkan)\", \"kd\": \"Menerapkan rumus...\", \"indikator\": \"Murid dapat menerapkan...\", \"soal\": \"[Soal Essay 1] ...\", \"kunci\": \"Penjelasan kunci jawaban panjang...\"},
            {\"level_kognitif\": \"C3 (Menerapkan)\", \"kd\": \"Menerapkan rumus...\", \"indikator\": \"Murid dapat menerapkan...\", \"soal\": \"[Soal Essay 2] ...\", \"kunci\": \"...\"},
            {\"level_kognitif\": \"C4 (Menganalisis)\", \"kd\": \"Menganalisis masalah...\", \"indikator\": \"Murid dapat menganalisis...\", \"soal\": \"[Soal Essay 3] ...\", \"kunci\": \"...\"},
            {\"level_kognitif\": \"C4 (Menganalisis)\", \"kd\": \"Menganalisis masalah...\", \"indikator\": \"Murid dapat menganalisis...\", \"soal\": \"[Soal Essay 4] ...\", \"kunci\": \"...\"},
            {\"level_kognitif\": \"C4 (Menganalisis)\", \"kd\": \"Menganalisis masalah...\", \"indikator\": \"Murid dapat menganalisis...\", \"soal\": \"[Soal Essay 5] ...\", \"kunci\": \"...\"}
            " : "") . "
        ]
    },
    \"lkpd\": {
        \"tahap1\": \"Cerita/masalah pemantik diskusi kelompok dari materi PDF...\",
        \"tahap3\": \"<ol><li>Pertanyaan diskusi 1?</li><li>Pertanyaan diskusi 2?</li></ol>\",
        \"tahap5\": \"<ol><li>Pertanyaan refleksi 1?</li><li>Pertanyaan refleksi 2?</li><li>Pertanyaan refleksi 3?</li><li>Pertanyaan refleksi 4?</li><li>Pertanyaan refleksi 5?</li></ol>\"
    },
    \"bahan_ajar\": {
        \"pengertian\": \"Tulis 2 paragraf penjelasan utama secara komprehensif (Apa itu materi ini?). WAJIB SElipkan nilai agama/spiritual seperti 'diciptakan oleh Allah'.\",
        \"tahapan\": [
            {\"judul\": \"Sub Bab 1\", \"deskripsi\": \"Penjelasan mendetail.\"},
            {\"judul\": \"Sub Bab 2\", \"deskripsi\": \"Penjelasan mendetail.\"},
            {\"judul\": \"Sub Bab 3\", \"deskripsi\": \"Penjelasan mendetail.\"}
        ],
        \"manfaat\": [\"Manfaat 1 secara detail...\", \"Manfaat 2 secara detail...\", \"Manfaat 3 secara detail...\"],
        \"kosakata\": [
            {\"kata\": \"Istilah 1\", \"arti\": \"Arti istilah 1\"}
        ]
    }
}
";

    $data = [
        'model' => $api_model,
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.5,
        'max_tokens' => 4500 // Memperpanjang nafas AI
    ];

    $json_payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

    if ($json_payload === false) {
        showApiError("Gagal Membentuk Request", "Sistem tidak dapat memproses teks dari PDF Anda karena mengandung karakter yang sangat tidak umum/rusak. (Error PHP: " . json_last_error_msg() . "). Solusi: Coba gunakan file PDF lain yang teksnya lebih bersih.");
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode == 200) {
        $resObj = json_decode($response, true);
        $ai_text = $resObj['choices'][0]['message']['content'];
        $rpp = json_decode($ai_text, true);

        // Jika JSON gagal di-parse
        if (json_last_error() !== JSON_ERROR_NONE) {
            showApiError("Gagal Membaca Data (JSON Error)", "AI berhasil dihubungi, tetapi format balasan (JSON) tidak sempurna/terpotong. Ini sering terjadi karena output terlalu panjang untuk dibaca oleh model yang Anda gunakan.");
        }
    } else {
        $resObj = json_decode($response, true);
        $apiError = isset($resObj['error']['message']) ? $resObj['error']['message'] : $response;
        showApiError("Gagal Menghubungi API (HTTP $httpCode)", "Sistem gagal terhubung ke provider <b>" . strtoupper($provider) . "</b>. Kemungkinan besar Token API yang Anda gunakan salah (Invalid API Key), kedaluwarsa, atau limit kuotanya habis.", $apiError);
    }
}

function listToHtml($array)
{
    if (!is_array($array))
        return $array;
    $html = "<ol style='margin:0; padding-left:20px;'>";
    foreach ($array as $item) {
        // Bersihkan angka berulang jika AI sudah memasukkan angka di teksnya
        $cleanItem = preg_replace('/^\d+\.\s*/', '', $item);
        $html .= "<li>$cleanItem</li>";
    }
    $html .= "</ol>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPP Editor AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f1f3f4;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .editor-wrapper {
            padding: 40px 0;
        }

        /* Container Horizontal Scroll khusus Layar HP */
        .editor-scroll-container {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 20px;
        }

        /* TAMPILAN LAYAR ALA GOOGLE DOCS (Presisi Kertas A4) */
        #editor {
            width: 210mm;
            /* Lebar Pasti Kertas A4 */
            min-height: 297mm;
            /* Tinggi A4 */
            padding: 1.5cm;
            /* Padding disamakan dengan margin Print */
            background: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            box-sizing: border-box;
            /* Agar padding masuk ke dalam ukuran 210mm */
            position: relative;
            outline: none;
            line-height: 1.6;
        }

        /* Tampilan Pemisah Halaman Manual */
        .manual-page-break {
            page-break-before: always;
            break-before: page;
            height: 2px;
            background: #cbd5e1;
            margin: 40px -1.5cm;
            /* Rentangkan full seluas A4 */
            position: relative;
        }

        .manual-page-break::after {
            content: "✂️ Pindah Halaman Manual";
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 0 10px;
            font-size: 11px;
            color: #64748b;
            font-weight: bold;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
        }

        #editor table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-bottom: 20px;
            font-size: 11pt;
        }

        #editor table,
        #editor th,
        #editor td {
            border: 1px solid #000 !important;
        }

        #editor th {
            background-color: #f2f2f2;
            text-align: left;
        }

        .float-menu {
            position: absolute;
            display: none;
            background: #212529;
            border-radius: 8px;
            padding: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .float-menu button {
            background: transparent;
            border: none;
            color: white;
            padding: 6px 12px;
            font-size: 13px;
            cursor: pointer;
        }

        .float-menu button:hover {
            background: #343a40;
            border-radius: 5px;
        }

        /* Sidebar AI Styles Responsive */
        .ai-sidebar {
            position: fixed;
            top: 0;
            right: -100%;
            /* Hidden by default */
            width: 100%;
            max-width: 400px;
            height: 100vh;
            background: #fff;
            box-shadow: -4px 0 15px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease;
            z-index: 1050;
            display: flex;
            flex-direction: column;
        }

        .ai-sidebar.open {
            right: 0;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-body {
            padding: 20px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .quote-box {
            border-left: 4px solid #a855f7;
            background: #f3e8ff;
            padding: 10px 15px;
            border-radius: 0 8px 8px 0;
            font-style: italic;
            font-size: 13px;
            color: #4c1d95;
            margin-bottom: 20px;
        }

        .quick-btn {
            display: block;
            width: 100%;
            text-align: left;
            padding: 10px 15px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
            color: #334155;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quick-btn:hover {
            border-color: #a855f7;
            color: #a855f7;
            background: #faf5ff;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
            }

            .editor-wrapper {
                padding: 0 !important;
            }

            .editor-scroll-container {
                overflow: visible !important;
            }

            #editor {
                width: 100% !important;
                /* Kembalikan ke full 100% saat dicetak */
                min-height: auto !important;
                padding: 0 !important;
                /* Padding dihapus karena margin diatur di @page */
                margin: 0 !important;
                box-shadow: none !important;
                background-image: none !important;
                /* Hapus garis visual saat diprint */
            }

            .manual-page-break {
                background: transparent;
                height: 0;
                border: none;
                margin: 0;
            }

            .manual-page-break::after {
                display: none;
            }

            @page {
                size: A4;
                margin: 1.5cm;
                /* Margin wajib agar tata letak tidak lari */
            }

            /* PENTING AGAR WARNA TIDAK HILANG SAAT DIPRINT (Chrome/Edge Only) */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Mengizinkan tabel, baris, dan sel untuk terpotong secara alami */
            table {
                page-break-inside: auto !important;
                break-inside: auto !important;
                border-collapse: separate !important;
                border-spacing: 0 !important;
            }

            tr {
                page-break-inside: auto !important;
                page-break-after: auto !important;
            }

            td,
            th {
                page-break-inside: auto !important;
                border: 1px solid #000 !important;
            }

            /* Kelas khusus hanya untuk elemen KECIL yang tidak boleh terpotong di tengah */
            .cegah-potong {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            /* Cegah judul tertinggal sendirian di bawah halaman */
            h1,
            h2,
            h3,
            h4,
            h5,
            h6 {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
        }
    </style>
</head>

<body>
    <div class="no-print d-flex flex-wrap gap-2 justify-content-between p-3 bg-white shadow-sm sticky-top align-items-center"
        style="z-index: 999;">
        <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
            <a href="dashboard.php?page=generate" class="btn btn-outline-secondary">&larr; Kembali</a>
            <h5 class="m-0 fw-bold text-primary fs-6 fs-md-5 text-truncate" style="max-width: 250px;">✨ Editor RPP -
                <?= htmlspecialchars($material_title) ?></h5>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <!-- Form Helper Id RPP -->
            <input type="hidden" id="current_rpp_id" value="<?= htmlspecialchars($rpp_id ?? '') ?>">

            <button onclick="saveRPPToDatabase()" id="btnSave"
                class="btn btn-sm btn-warning fw-bold text-dark shadow-sm">💾 Simpan ke Database</button>
            <button onclick="exportToWord()" class="btn btn-sm btn-primary fw-bold text-white shadow-sm">📄 Unduh
                Word</button>
            <button onclick="window.print()" class="btn btn-sm btn-success fw-bold shadow-sm">🖨️ Print PDF</button>
        </div>
    </div>

    <!-- NOTIFIKASI JIKA PDF GAGAL DIBACA KARENA LIBRARY -->
    <?php if (isset($pdf_text_content) && (strpos($pdf_text_content, 'Sistem gagal membaca materi') !== false || strpos($pdf_text_content, 'belum terinstal') !== false) && !$is_history): ?>
        <div
            class="bg-warning text-dark px-4 py-2 text-center text-sm fw-bold no-print shadow-sm border-bottom border-warning">
            ⚠️ PERINGATAN: Library Pembaca PDF (Smalot PDFParser) belum diinstal. Teks referensi tidak dapat dibaca oleh AI.
        </div>
    <?php endif; ?>

    <div class="container-fluid my-5 relative editor-wrapper">
        <div id="float-menu" class="float-menu no-print">
            <button onclick="document.getElementById('imageUploader').click()">📷 Upload Gambar</button>
            <div
                style="width:1px; background:#666; margin:0 5px; display:inline-block; height:15px; vertical-align:middle;">
            </div>
            <button onclick="generateGambar()">🖼️ Generate AI Gambar</button>
            <div
                style="width:1px; background:#666; margin:0 5px; display:inline-block; height:15px; vertical-align:middle;">
            </div>
            <button onclick="editAI()">✨ Edit Text dgn AI</button>
        </div>

        <input type="file" id="imageUploader" accept="image/*" style="display:none;">

        <!-- Wrapper Layout Responsive Horizontal Scroll -->
        <div class="editor-scroll-container">
            <div id="editor" contenteditable="true">

                <?php if ($is_history): ?>

                    <!-- Jika membuka dari riwayat, tampilkan HTML murni dari Database -->
                    <?= $history_content ?>

                <?php else: ?>

                    <!-- ============================================== -->
                    <!-- JIKA MEMBUAT BARU (GENERATE VIA AI) -->
                    <!-- ============================================== -->

                    <h2 style='text-align:center;'>RENCANA PELAKSANAAN PEMBELAJARAN (RPP)</h2>

                    <table style='width:100%; border-collapse:collapse; margin-bottom:20px;' border='1'>
                        <tr style='background:#e0e0e0;'>
                            <td colspan='4' style='padding:10px; text-align:center;'><b>IDENTITAS KURIKULUM</b></td>
                        </tr>
                        <tr>
                            <td style='width:15%; padding:8px;'>Nama Penyusun</td>
                            <td style='width:35%; padding:8px;'><?= $guru_name ?></td>
                            <td style='width:15%; padding:8px;'>Sekolah</td>
                            <td style='width:35%; padding:8px;'></td>
                        </tr>
                        <tr>
                            <td style='padding:8px;'>Tahun Pelajaran</td>
                            <td style='padding:8px;'><?= $tahun_pelajaran ?></td>
                            <td style='padding:8px;'>Mata Pelajaran</td>
                            <td style='padding:8px;'><?= $subject_name ?></td>
                        </tr>
                        <tr>
                            <td style='padding:8px;'>Kelas</td>
                            <td style='padding:8px;'><?= $class_name ?> (<?= $jenjang ?>)</td>
                            <td style='padding:8px;'>Semester</td>
                            <td style='padding:8px;'></td>
                        </tr>
                        <tr>
                            <td style='padding:8px;'>Sub Materi</td>
                            <td style='padding:8px;'><?= $material_title ?></td>
                            <td style='padding:8px;'>Alokasi Waktu</td>
                            <td style='padding:8px;'></td>
                        </tr>
                    </table>

                    <table style='width:100%; border-collapse:collapse; margin-bottom:20px;' border='1'>
                        <tr style='background:#f2cd5c;'>
                            <td colspan='2' style='padding:10px;'><b>A. Identifikasi</b></td>
                        </tr>
                        <tr>
                            <td style='width:25%; padding:8px; vertical-align:top;'><b>Murid</b></td>
                            <td style='padding:8px;'>
                                <div style='margin-bottom:8px;'>1. Pengetahuan Awal</div>
                                <div style='margin-left:20px; margin-bottom:8px;'>-
                                    <?= $rpp['identifikasi']['pengetahuan_awal'] ?? '' ?></div>

                                <div style='margin-bottom:8px;'>2. Minat Belajar</div>
                                <div style='margin-left:20px; margin-bottom:8px;'>-
                                    <?= $rpp['identifikasi']['minat_belajar'] ?? '' ?></div>

                                <div style='margin-bottom:8px;'>3. Kebutuhan Belajar</div>
                                <div style='margin-left:20px;'>
                                    - <b>Pembelajaran yang Bermakna <i>(Meaningful Learning)</i></b><br>
                                    &nbsp;&nbsp;<?= $rpp['identifikasi']['kebutuhan_bermakna'] ?? '' ?><br><br>
                                    - <b>Pembelajaran yang Berkesadaran <i>(Mindful Learning)</i></b><br>
                                    &nbsp;&nbsp;<?= $rpp['identifikasi']['kebutuhan_sadar'] ?? '' ?><br><br>
                                    - <b>Pembelajaran yang Menyenangkan <i>(Joyful Learning)</i></b><br>
                                    &nbsp;&nbsp;<?= $rpp['identifikasi']['kebutuhan_senang'] ?? '' ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Materi<br>Pelajaran</b></td>
                            <td style='padding:8px;'>
                                Pokok materi yang akan dipelajari dalam bab ini:<br>
                                <?= listToHtml($rpp['identifikasi']['materi_pokok'] ?? []) ?>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Dimensi<br>Profil<br>Lulusan</b></td>
                            <td style='padding:8px;'>
                                <?= listToHtml($rpp['identifikasi']['dimensi_profil'] ?? []) ?>
                            </td>
                        </tr>
                    </table>

                    <table style='width:100%; border-collapse:collapse; margin-bottom:20px;' border='1'>
                        <tr style='background:#f2cd5c;'>
                            <td colspan='2' style='padding:10px;'><b>B. Desain Pembelajaran</b></td>
                        </tr>
                        <tr>
                            <td style='width:25%; padding:8px; vertical-align:top;'><b>Capaian<br>Pembelajaran</b></td>
                            <td style='padding:8px;'><?= $rpp['desain']['capaian'] ?? '' ?></td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Lintas<br>Disiplin Ilmu</b></td>
                            <td style='padding:8px;'><?= $rpp['desain']['lintas_disiplin'] ?? '' ?></td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Tujuan<br>Pembelajaran</b></td>
                            <td style='padding:8px;'><?= listToHtml($rpp['desain']['tujuan'] ?? []) ?></td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Topik<br>Pembelajaran</b></td>
                            <td style='padding:8px;'><?= $material_title ?></td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Praktik<br>Pedagogis</b></td>
                            <td style='padding:8px;'><?= $rpp['desain']['pendekatan_model'] ?? '' ?></td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Kemitraan<br>Pembelajaran</b></td>
                            <td style='padding:8px;'><?= $rpp['desain']['kemitraan'] ?? '' ?></td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Lingkungan<br>Pembelajaran</b></td>
                            <td style='padding:8px;'><?= listToHtml($rpp['desain']['lingkungan'] ?? []) ?></td>
                        </tr>
                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Pemanfaatan<br>Digital</b></td>
                            <td style='padding:8px;'><?= listToHtml($rpp['desain']['digital'] ?? []) ?></td>
                        </tr>
                    </table>

                    <table style='width:100%; border-collapse:collapse; margin-bottom:20px;' border='1'>
                        <tr style='background:#f2cd5c;'>
                            <td colspan='2' style='padding:10px;'><b>C. Pengalaman Belajar</b></td>
                        </tr>
                        <tr style='background:#f9f9f9;'>
                            <td colspan='2' style='padding:8px;'><b>Langkah-Langkah Pembelajaran</b></td>
                        </tr>

                        <tr>
                            <td style='width:25%; padding:8px; vertical-align:top;'><b>Pendahuluan<br>(5-10 menit)</b></td>
                            <td style='padding:8px; vertical-align:top;'>
                                <div style="font-weight:bold; margin-bottom:5px;">Orientasi dan Apersepsi Bermakna</div>
                                <?= listToHtml($rpp['pengalaman']['pendahuluan']['orientasi'] ?? []) ?>

                                <div style="font-weight:bold; margin-top:15px; margin-bottom:5px;">Motivasi yang
                                    Menggembirakan</div>
                                <?= listToHtml($rpp['pengalaman']['pendahuluan']['motivasi'] ?? []) ?>
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Inti<br>(50 menit)</b></td>
                            <td style='padding:8px; vertical-align:top;'>
                                <?php
                                if (isset($rpp['pengalaman']['inti']) && is_array($rpp['pengalaman']['inti'])) {
                                    foreach ($rpp['pengalaman']['inti'] as $tahap) {
                                        echo "<div style='margin-bottom:15px;'>";
                                        echo "<div style='font-weight:bold; margin-bottom:5px;'>{$tahap['tahap']}</div>";
                                        echo listToHtml($tahap['kegiatan']);
                                        echo "</div>";
                                    }
                                }
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Penutup<br>(10-15 menit)</b></td>
                            <td style='padding:8px; vertical-align:top;'>
                                <?= listToHtml($rpp['pengalaman']['penutup'] ?? []) ?>
                            </td>
                        </tr>
                    </table>

                    <table style='width:100%; border-collapse:collapse; margin-bottom:20px;' border='1'>
                        <tr style='background:#f2cd5c;'>
                            <td colspan='2' style='padding:10px;'><b>D. Asesmen Pembelajaran</b></td>
                        </tr>

                        <tr>
                            <td style='width:25%; padding:8px; vertical-align:top;'><b>Asesmen
                                    pada<br>Awal<br>Pembelajaran</b></td>
                            <td style='padding:8px; vertical-align:top;'>
                                <b>Tujuan:</b> Mengidentifikasi pengetahuan awal murid tentang <?= $material_title ?>.<br>
                                <b>Bentuk Asesmen:</b> Pertanyaan lisan dan pengamatan sederhana<br>
                                <b>Pertanyaan:</b><br>
                                <?= listToHtml($rpp['asesmen']['awal_pertanyaan'] ?? []) ?>
                                <br><b>Tabel Tingkat Keberhasilan</b><br>
                                <table border='1' style='width:100%; border-collapse:collapse; margin-top:10px;'>
                                    <tr style='background:#f9f9f9; text-align:left;'>
                                        <th>Tingkat</th>
                                        <th>Kriteria</th>
                                        <th>Tindak Lanjut</th>
                                    </tr>
                                    <tr>
                                        <td style='vertical-align:top; width:20%;'>Mahir</td>
                                        <td style='vertical-align:top; text-align:justify;'>
                                            <?= $rpp['asesmen']['awal_mahir'] ?? '' ?></td>
                                        <td style='vertical-align:top;'>Diberikan pengayaan dan tantangan lebih kompleks
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='vertical-align:top;'>Cakap</td>
                                        <td style='vertical-align:top; text-align:justify;'>
                                            <?= $rpp['asesmen']['awal_cakap'] ?? '' ?></td>
                                        <td style='vertical-align:top;'>Dilanjutkan ke pembelajaran</td>
                                    </tr>
                                    <tr>
                                        <td style='vertical-align:top;'>Berkembang</td>
                                        <td style='vertical-align:top; text-align:justify;'>
                                            <?= $rpp['asesmen']['awal_berkembang'] ?? '' ?></td>
                                        <td style='vertical-align:top;'>Diberikan bimbingan tambahan dan penguatan konsep
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Asesmen pada<br>Proses<br>Pembelajaran</b></td>
                            <td style='padding:8px; vertical-align:top;'>
                                <b>Tujuan:</b> Mengukur keterlibatan, kolaborasi, dan kemampuan berpikir kritis murid<br>
                                <b>Bentuk Asesmen:</b> Observasi dan penilaian LKPD<br>
                                <b>Aspek yang dinilai:</b> Keterlibatan murid, analisis hasil, kerjasama, kemampuan
                                menyajikan hasil.<br><br>
                                <b>Tabel Tingkat Keberhasilan</b><br>
                                <table border='1' style='width:100%; border-collapse:collapse; text-align:left;'>
                                    <tr style='background:#f9f9f9; text-align:center;'>
                                        <th style="width:15%;">Kriteria<br>Penilaian</th>
                                        <th>Sangat Baik<br>(4)</th>
                                        <th>Baik<br>(3)</th>
                                        <th>Cukup<br>(2)</th>
                                        <th>Perlu<br>Perbaikan<br>(1)</th>
                                    </tr>
                                    <tr>
                                        <td style='vertical-align:top;'>Keterlibatan</td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_terlibat'][0] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_terlibat'][1] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_terlibat'][2] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_terlibat'][3] ?? '' ?></td>
                                    </tr>
                                    <tr>
                                        <td style='vertical-align:top;'>Analisis hasil</td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_analisis'][0] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_analisis'][1] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_analisis'][2] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_analisis'][3] ?? '' ?></td>
                                    </tr>
                                    <tr>
                                        <td style='vertical-align:top;'>Kerjasama</td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_kerjasama'][0] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_kerjasama'][1] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_kerjasama'][2] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_kerjasama'][3] ?? '' ?></td>
                                    </tr>
                                    <tr>
                                        <td style='vertical-align:top;'>Kemampuan<br>menyajikan hasil</td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_saji'][0] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_saji'][1] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_saji'][2] ?? '' ?></td>
                                        <td style='vertical-align:top; text-align:justify; font-size: 13px; padding:6px;'>
                                            <?= $rpp['asesmen']['proses_saji'][3] ?? '' ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:8px; vertical-align:top;'><b>Asesmen pada<br>Akhir<br>Pembelajaran</b></td>
                            <td style='padding:8px; vertical-align:top;'>
                                <b>Tujuan:</b> Mengukur pemahaman murid tentang <?= $material_title ?> setelah
                                pembelajaran.<br>
                                <b>Bentuk Asesmen:</b> Tes Tertulis<br><br>
                                <div style='text-align:center; font-weight:bold; margin-bottom:5px; text-transform:uppercase;'>RUBRIK PENILAIAN SOAL EVALUASI</div>
                                <table border='1' style='width:90%; border-collapse:collapse; text-align:center; margin:0 auto; margin-bottom: 20px; font-family: "Times New Roman", Times, serif; font-size: 15px;'>
                                    <tr style='background:#ffff00;'>
                                        <th colspan='2' style='padding:5px;'>Pilihan Ganda</th>
                                    </tr>
                                    <tr>
                                        <th style='padding:5px;'>Nomor Soal</th>
                                        <th style='padding:5px;'>Bobot Soal</th>
                                    </tr>
                                    <?php
                                    $pg_soals = $rpp['soal_evaluasi']['pilihan_ganda'] ?? [];
                                    $total_pg = count($pg_soals);
                                    if ($total_pg > 0):
                                        $bobot_pg = round(100 / $total_pg);
                                        for ($i = 1; $i <= $total_pg; $i++):
                                            ?>
                                            <tr>
                                                <td style='padding:5px;'><?= $i ?></td>
                                                <td style='padding:5px;'><?= $bobot_pg ?></td>
                                            </tr>
                                        <?php
                                        endfor;
                                    endif;
                                    ?>
                                    <tr style='background:#f9f9f9;'>
                                        <th style='padding:5px; text-align:left;'>Skor Maksimal PG</th>
                                        <th style='padding:5px;'>100</th>
                                    </tr>

                                    <?php
                                    $uraian_soals = $rpp['soal_evaluasi']['uraian'] ?? [];
                                    $total_uraian = count($uraian_soals);
                                    if ($total_uraian > 0): 
                                        $bobot_uraian = round(100 / $total_uraian);
                                    ?>
                                    <tr style='background:#ffff00;'>
                                        <th colspan='2' style='padding:5px;'>Uraian</th>
                                    </tr>
                                    <tr>
                                        <th style='padding:5px;'>Nomor Soal</th>
                                        <th style='padding:5px;'>Bobot Soal</th>
                                    </tr>
                                    <?php for ($i = 1; $i <= $total_uraian; $i++): ?>
                                            <tr>
                                                <td style='padding:5px;'><?= $i ?></td>
                                                <td style='padding:5px; font-weight:bold;'><?= $bobot_uraian ?></td>
                                            </tr>
                                    <?php endfor; ?>
                                    <tr style='background:#f9f9f9;'>
                                        <th style='padding:5px; text-align:left;'>Skor Maksimal Uraian</th>
                                        <th style='padding:5px;'>100</th>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <table style='width:100%; border-collapse:collapse; margin-bottom:20px;' border='1'>
                        <tr style='background:#ffd700;'>
                            <td colspan='3' style='padding:10px;'><b>E. Refleksi</b></td>
                        </tr>

                        <tr style='background:#fff;'>
                            <td colspan='3' align='center' style='padding:15px 10px;'><b>TABEL REFLEKSI UNTUK MURID</b></td>
                        </tr>
                        <tr style='background:#ffcc00; text-align:center;'>
                            <th style='width:5%; padding:8px;'>No</th>
                            <th style='width:45%; padding:8px;'>Pertanyaan</th>
                            <th style='width:50%; padding:8px;'>Jawaban</th>
                        </tr>
                        <tr>
                            <td align='center'>1</td>
                            <td style='padding:8px;'>Bagaimana perasaan kalian setelah belajar materi ini?</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td align='center'>2</td>
                            <td style='padding:8px;'>Bagian materi yang mana yang belum kalian pahami?</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td align='center'>3</td>
                            <td style='padding:8px;'>Bagian mana dari pembelajaran yang menurut kalian menyenangkan?</td>
                            <td></td>
                        </tr>

                        <tr style='background:#fff;'>
                            <td colspan='3' align='center' style='padding:15px 10px;'><b>TABEL REFLEKSI UNTUK GURU</b></td>
                        </tr>
                        <tr style='background:#ffcc00; text-align:center;'>
                            <th style='padding:8px;'>NO</th>
                            <th style='padding:8px;'>PERTANYAAN</th>
                            <th style='padding:8px;'>JAWABAN</th>
                        </tr>
                        <tr>
                            <td align='center'>1</td>
                            <td style='padding:8px;'>Apakah manajemen kelas telah memenuhi tujuan pembelajaran yang hendak
                                dicapai?</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td align='center'>2</td>
                            <td style='padding:8px;'>Apakah dalam menyampaikan materi, konsentrasi belajar murid dapat terus
                                terjaga dengan baik?</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td align='center'>3</td>
                            <td style='padding:8px;'>Apakah lingkungan kolaboratif, kooperatif, dan interaksi antar murid,
                                dan guru dapat terbentuk hingga menghasilkan pembelajaran yang berkualitas?</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td align='center'>4</td>
                            <td style='padding:8px;'>Apakah murid mengalami kesulitan dan hambatan menerima materi pelajaran
                                dengan metode mengajar yang digunakan?</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td align='center'>5</td>
                            <td style='padding:8px;'>Apakah pelaksanaan pembelajaran dapat meningkatkan minat belajar murid
                                dalam materi <?= $material_title ?>?</td>
                            <td></td>
                        </tr>
                    </table>

                    <!-- PAGE BREAK UNTUK LAMPIRAN (Manual Break) -->
                    <div class="manual-page-break" contenteditable="false"></div>

                    <!-- F. LAMPIRAN EVALUASI SOAL -->
                    <div style='border:4px solid #475569; border-radius:15px; padding:25px; margin-bottom:30px;'>
                        <h3 style='text-align:center; color:#334155; margin-bottom:25px;'>F. LAMPIRAN EVALUASI SOAL</h3>

                        <!-- TABEL KISI-KISI PILIHAN GANDA -->
                        <table style='width:100%; border-collapse:collapse; margin-bottom:30px; font-size:14px;' border='1'>
                            <tr style='background:#e0e0e0;'>
                                <td colspan='7' style='padding:10px;'><b>KARTU SOAL PILIHAN GANDA</b></td>
                            </tr>
                            <tr style='background:#f9f9f9; text-align:center;'>
                                <th>No</th>
                                <th>Tujuan Pembelajaran</th>
                                <th>Indikator</th>
                                <th>Jenis Soal</th>
                                <th>Nomor Soal</th>
                                <th>Soal</th>
                                <th>Kunci</th>
                            </tr>
                            <?php
                            if (isset($rpp['soal_evaluasi']['pilihan_ganda']) && is_array($rpp['soal_evaluasi']['pilihan_ganda'])) {
                                $idx = 1;
                                $current_level_pg = ""; // Variabel tracker kognitif
                                foreach ($rpp['soal_evaluasi']['pilihan_ganda'] as $soal) {
                                    $level_kognitif = $soal['level_kognitif'] ?? '';
                                    
                                    // Pengecekan jika level kognitif berubah, buat baris pemisah (separator)
                                    if (!empty($level_kognitif) && $level_kognitif !== $current_level_pg) {
                                        echo "<tr><td colspan='7' style='padding:8px 15px; font-weight:bold; background-color:#f8f9fa; border-top:2px solid #555; text-align:left;'>{$level_kognitif}</td></tr>";
                                        $current_level_pg = $level_kognitif;
                                    }

                                    $kd = $soal['kd'] ?? '-';
                                    $indikator = $soal['indikator'] ?? '-';
                                    $jenis = 'PG';
                                    $soal_teks = $soal['soal'] ?? '-';
                                    $kunci = $soal['kunci'] ?? '-';

                                    echo "<tr>";
                                    echo "<td align='center' style='vertical-align:top;'>{$idx}</td>";
                                    echo "<td style='vertical-align:top;'>{$kd}</td>";
                                    echo "<td style='vertical-align:top;'>{$indikator}</td>";
                                    echo "<td align='center' style='vertical-align:top;'>{$jenis}</td>";
                                    echo "<td align='center' style='vertical-align:top;'>{$idx}</td>";
                                    echo "<td style='vertical-align:top; text-align:left; padding:8px;'>{$soal_teks}</td>";
                                    echo "<td align='center' style='vertical-align:top;'><b>{$kunci}</b></td>";
                                    echo "</tr>";
                                    $idx++;
                                }
                            }
                            ?>
                        </table>

                        <!-- TABEL KISI-KISI URAIAN JIKA ADA -->
                        <?php if (isset($rpp['soal_evaluasi']['uraian']) && is_array($rpp['soal_evaluasi']['uraian']) && count($rpp['soal_evaluasi']['uraian']) > 0): ?>
                        <table style='width:100%; border-collapse:collapse; margin-bottom:30px; font-size:14px;' border='1'>
                            <tr style='background:#d4edda;'>
                                <td colspan='7' style='padding:10px;'><b>KARTU SOAL URAIAN / ESSAY</b></td>
                            </tr>
                            <tr style='background:#f9f9f9; text-align:center;'>
                                <th>No</th>
                                <th>Kompetensi Dasar</th>
                                <th>Indikator</th>
                                <th>Jenis Soal</th>
                                <th>Nomor Soal</th>
                                <th>Soal</th>
                                <th>Kunci & Rubrik</th>
                            </tr>
                            <?php
                                $idx_uraian = 1;
                                $current_level_uraian = ""; // Variabel tracker kognitif
                                foreach ($rpp['soal_evaluasi']['uraian'] as $soal) {
                                    $level_kognitif = $soal['level_kognitif'] ?? '';

                                    // Pengecekan jika level kognitif berubah, buat baris pemisah (separator)
                                    if (!empty($level_kognitif) && $level_kognitif !== $current_level_uraian) {
                                        echo "<tr><td colspan='7' style='padding:8px 15px; font-weight:bold; background-color:#f8f9fa; border-top:2px solid #555; text-align:left;'>{$level_kognitif}</td></tr>";
                                        $current_level_uraian = $level_kognitif;
                                    }

                                    $kd = $soal['kd'] ?? '-';
                                    $indikator = $soal['indikator'] ?? '-';
                                    $jenis = 'Uraian';
                                    $soal_teks = $soal['soal'] ?? '-';
                                    $kunci = $soal['kunci'] ?? '-';

                                    echo "<tr>";
                                    echo "<td align='center' style='vertical-align:top;'>{$idx_uraian}</td>";
                                    echo "<td style='vertical-align:top;'>{$kd}</td>";
                                    echo "<td style='vertical-align:top;'>{$indikator}</td>";
                                    echo "<td align='center' style='vertical-align:top;'>{$jenis}</td>";
                                    echo "<td align='center' style='vertical-align:top;'>{$idx_uraian}</td>";
                                    echo "<td style='vertical-align:top; text-align:left; padding:8px;'>{$soal_teks}</td>";
                                    echo "<td style='vertical-align:top; padding:8px;'><span style='font-size:12px; color:#555;'>{$kunci}</span></td>";
                                    echo "</tr>";
                                    $idx_uraian++;
                                }
                            ?>
                        </table>
                        <?php endif; ?>

                        <div style="border: 2px dashed #94a3b8; padding: 30px; border-radius: 8px; background: #f8fafc;">
                            <h4 style="text-align:center; font-weight:bold; margin-bottom:10px; color: #334155;">LEMBAR SOAL EVALUASI</h4>
                            
                            <h5 style="margin-top: 20px; color: #1e293b; font-weight: bold; border-bottom: 2px solid #cbd5e1; padding-bottom: 5px;">A. Pilihan Ganda</h5>
                            <?php
                            if (isset($rpp['soal_evaluasi']['pilihan_ganda']) && is_array($rpp['soal_evaluasi']['pilihan_ganda'])) {
                                $idx = 1;
                                foreach ($rpp['soal_evaluasi']['pilihan_ganda'] as $soal) {
                                    $soal_teks = $soal['soal'] ?? '';
                                    echo "<div class='cegah-potong' style='margin-bottom:20px; padding-left:25px; text-indent:-20px; line-height: 1.6;'>";
                                    echo "<b>{$idx}.</b> " . $soal_teks;
                                    echo "</div>";
                                    $idx++;
                                }
                            }
                            ?>

                            <?php if (isset($rpp['soal_evaluasi']['uraian']) && is_array($rpp['soal_evaluasi']['uraian']) && count($rpp['soal_evaluasi']['uraian']) > 0): ?>
                            <h5 style="margin-top: 40px; color: #1e293b; font-weight: bold; border-bottom: 2px solid #cbd5e1; padding-bottom: 5px;">B. Uraian / Essay</h5>
                            <?php
                                $idx = 1;
                                foreach ($rpp['soal_evaluasi']['uraian'] as $soal) {
                                    $soal_teks = $soal['soal'] ?? '';
                                    echo "<div class='cegah-potong' style='margin-bottom:40px; padding-left:25px; text-indent:-20px; line-height: 1.6;'>";
                                    echo "<b>{$idx}.</b> " . $soal_teks;
                                    echo "<br><br><span style='color:#ccc;'>Jawaban: __________________________________________________________________<br>___________________________________________________________________________</span>";
                                    echo "</div>";
                                    $idx++;
                                }
                            ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- G. LAMPIRAN LKPD (Manual Break) -->
                    <div class="manual-page-break" contenteditable="false"></div>

                    <div
                        style='border:4px solid #00bcd4; border-radius:15px; padding:25px; margin-bottom:20px; background-color: #f4fcff;'>
                        <h3
                            style='text-align:center; color:#007b83; font-weight: 900; font-size: 24px; text-transform: uppercase;'>
                            LEMBAR KERJA PESERTA DIDIK (LKPD)</h3>

                        <!-- TAHAP 1 -->
                        <div class='cegah-potong'
                            style="margin-top: 50px; margin-bottom: 40px; border: 4px solid #52c2d6; border-radius: 20px; padding: 50px 30px 25px 30px; position: relative; background: white; box-shadow: 6px 6px 0px rgba(82, 194, 214, 0.3);">
                            <div
                                style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); border: 3px solid #222; border-radius: 30px; background: white; box-shadow: 4px 4px 0px #222; padding: 8px 35px; text-align: center; z-index: 2; min-width: 280px;">
                                <b style="font-size: 16px; color: #222; display: block; line-height: 1.4;">Tahap
                                    1<br>Menentukan Pertanyaan Mendasar</b>
                            </div>
                            <div style="font-size: 15px; color: #333; line-height: 1.6; text-align: justify;">
                                <b style="color: #007b83;">Perhatikan studi kasus / narasi berikut:</b><br><br>
                                <?= $rpp['lkpd']['tahap1'] ?? '' ?>
                            </div>
                        </div>

                        <!-- TAHAP 2 -->
                        <div class='cegah-potong'
                            style="margin-top: 50px; margin-bottom: 40px; border: 4px solid #52c2d6; border-radius: 20px; padding: 50px 30px 25px 30px; position: relative; background: white; box-shadow: 6px 6px 0px rgba(82, 194, 214, 0.3);">
                            <div
                                style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); border: 3px solid #222; border-radius: 30px; background: white; box-shadow: 4px 4px 0px #222; padding: 8px 35px; text-align: center; z-index: 2; min-width: 280px;">
                                <b style="font-size: 16px; color: #222; display: block; line-height: 1.4;">Tahap
                                    2<br>Mengorganisasikan Belajar</b>
                            </div>
                            <div style="font-size: 15px; color: #333; line-height: 1.6;">
                                <ul style="margin: 0; padding-left: 20px; font-weight: 500;">
                                    <li style="margin-bottom: 8px;">Duduklah berdasarkan kelompok yang sudah ditentukan!
                                    </li>
                                    <li style="margin-bottom: 8px;">Dengarkan arahan guru tentang aturan dalam diskusi
                                        kelompok!</li>
                                    <li>Setelah mendapatkan LKPD, diskusikan tugas ini bersama rekan sekelompokmu dengan
                                        penuh tanggung jawab!</li>
                                </ul>
                            </div>
                        </div>

                        <!-- TAHAP 3 & 4 -->
                        <div class='cegah-potong'
                            style="margin-top: 50px; margin-bottom: 40px; border: 4px solid #52c2d6; border-radius: 20px; padding: 50px 30px 25px 30px; position: relative; background: white; box-shadow: 6px 6px 0px rgba(82, 194, 214, 0.3);">
                            <div
                                style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); border: 3px solid #222; border-radius: 30px; background: white; box-shadow: 4px 4px 0px #222; padding: 8px 35px; text-align: center; z-index: 2; min-width: 280px;">
                                <b style="font-size: 16px; color: #222; display: block; line-height: 1.4;">Tahap 3 &
                                    4<br>Penyelidikan & Menyajikan Hasil</b>
                            </div>
                            <div style="font-size: 15px; color: #333; line-height: 1.6;">
                                <b style="color: #007b83;">Jawablah pertanyaan diskusi berikut secara berkelompok untuk
                                    memecahkan kasus di atas!</b><br><br>
                                <?= $rpp['lkpd']['tahap3'] ?? '' ?>

                                <div
                                    style="margin-top: 25px; border: 2px dashed #a0d8e4; border-radius: 15px; padding: 20px; min-height: 250px; background: #fafafa;">
                                    <span style="color: #aaa; font-style: italic;">(Ruang untuk siswa menulis lembar jawaban
                                        hasil diskusi)</span>
                                </div>

                                <div style="text-align: center; margin-top: 25px;">
                                    <div
                                        style="display: inline-block; border: 2px solid #52c2d6; border-radius: 25px; padding: 10px 25px; background: #e6f7ff; color: #007b83; font-weight: bold; font-size: 14px;">
                                        📢 Apabila sudah menjawab semua pertanyaan, presentasikan temuanmu di depan kelas!
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAHAP 5 -->
                        <div class='cegah-potong'
                            style="margin-top: 50px; margin-bottom: 40px; border: 4px solid #52c2d6; border-radius: 20px; padding: 50px 30px 25px 30px; position: relative; background: white; box-shadow: 6px 6px 0px rgba(82, 194, 214, 0.3);">
                            <div
                                style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); border: 3px solid #222; border-radius: 30px; background: white; box-shadow: 4px 4px 0px #222; padding: 8px 35px; text-align: center; z-index: 2; min-width: 280px;">
                                <b style="font-size: 16px; color: #222; display: block; line-height: 1.4;">Tahap
                                    5<br>Menganalisis dan Mengevaluasi</b>
                            </div>
                            <div style="font-size: 15px; color: #333; line-height: 1.6;">
                                <b style="color: #007b83;">Setelah melaksanakan presentasi, mari merenung sejenak dan
                                    simpulkan hasil belajarmu hari ini:</b><br><br>
                                <?= $rpp['lkpd']['tahap5'] ?? '' ?>
                            </div>

                            <div style="text-align: center; margin-top: 30px; font-size: 45px;">
                                👨‍🏫 👩‍🏫 📚 ✏️
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- H. LAMPIRAN BAHAN AJAR (Manual Break) -->
                    <!-- ============================================== -->
                    <div class="manual-page-break" contenteditable="false"></div>

                    <h2 style='text-align:center; color:#333; margin-bottom: 30px; text-transform: uppercase;'>H. BAHAN AJAR
                        SISWA</h2>

                    <div class='cegah-potong'
                        style="background-color: #e6f7ff; border: 4px solid #c2eaf7; border-radius: 20px; padding: 30px; position: relative; overflow: hidden; font-family: 'Segoe UI', sans-serif;">
                        <!-- Dekorasi Matahari & Awan (CSS murni) -->
                        <div style="position: absolute; top: 20px; left: -20px; font-size: 60px; opacity: 0.8;">☀️</div>
                        <div style="position: absolute; top: 10px; right: 20px; font-size: 50px; opacity: 0.6;">☁️</div>
                        <div style="position: absolute; top: 60px; right: 80px; font-size: 40px; opacity: 0.4;">☁️</div>

                        <div style="position: relative; z-index: 10;">
                            <h1 style="color: #005f73; font-size: 28px; margin-bottom: 20px; font-weight: 900;">Apa itu
                                <?= $material_title ?>?</h1>

                            <div
                                style="color: #444; font-size: 16px; line-height: 1.8; margin-bottom: 30px; text-align: justify;">
                                <?= str_replace("\n", "<br><br>", $rpp['bahan_ajar']['pengertian'] ?? 'Pengertian materi belum tersedia.') ?>
                            </div>

                            <?php if (!empty($dalil_html)): ?>
                                <!-- TAMPILKAN DALIL DI DALAM BAHAN AJAR -->
                                <?= $dalil_html ?>
                            <?php endif; ?>

                            <div style="margin-top: 40px;">
                                <?php
                                if (isset($rpp['bahan_ajar']['tahapan']) && is_array($rpp['bahan_ajar']['tahapan'])) {
                                    foreach ($rpp['bahan_ajar']['tahapan'] as $tahap) {
                                        echo "
                                    <div class='cegah-potong' style='background-color: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 6px solid #ffca28;'>
                                        <h3 style='color: #005f73; margin-top: 0; font-size: 20px;'>" . ($tahap['judul'] ?? 'Tahap') . "</h3>
                                        <p style='color: #555; margin-bottom: 0; line-height: 1.8; font-size: 15px; text-align: justify;'>" . str_replace("\n", "<br><br>", ($tahap['deskripsi'] ?? '')) . "</p>
                                    </div>
                                    ";
                                    }
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Dekorasi Lautan di bawah -->
                        <div
                            style="position: absolute; bottom: -10px; left: 0; width: 100%; height: 40px; background-color: #3bb2d0; border-top-left-radius: 50% 10px; border-top-right-radius: 50% 10px;">
                        </div>
                    </div>

                    <!-- HALAMAN KEDUA BAHAN AJAR: MANFAAT & KOSAKATA (Manual Break) -->
                    <div class="manual-page-break" contenteditable="false"></div>

                    <div
                        style="background-color: #e6f7ff; border: 4px solid #c2eaf7; border-radius: 20px; padding: 30px; position: relative; font-family: 'Segoe UI', sans-serif;">
                        <div style="position: absolute; top: -15px; right: -15px; font-size: 80px; opacity: 0.8;">☀️</div>

                        <h2 style="color: #005f73; font-size: 26px; margin-bottom: 20px; font-weight: 900;">Manfaat
                            <?= $material_title ?></h2>
                        <div style="margin-bottom: 40px;">
                            <?php
                            if (isset($rpp['bahan_ajar']['manfaat'])) {
                                // Jika AI merespons dengan Array (Sesuai format)
                                if (is_array($rpp['bahan_ajar']['manfaat']) && count($rpp['bahan_ajar']['manfaat']) > 0) {
                                    $no = 1;
                                    foreach ($rpp['bahan_ajar']['manfaat'] as $manfaat) {
                                        echo "
                                    <div class='cegah-potong' style='background-color: white; border-radius: 25px; padding: 12px 20px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-size: 15px; color: #444; font-weight: bold;'>
                                        $no. $manfaat
                                    </div>
                                    ";
                                        $no++;
                                    }
                                }
                                // Jika AI ngaco dan merespons dengan Teks Paragraf Biasa (String)
                                else if (is_string($rpp['bahan_ajar']['manfaat'])) {
                                    echo "
                                <div class='cegah-potong' style='background-color: white; border-radius: 25px; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-size: 15px; color: #444; text-align: justify;'>
                                    " . $rpp['bahan_ajar']['manfaat'] . "
                                </div>
                                ";
                                } else {
                                    echo "<div style='color: #888; font-style: italic;'>Manfaat belum tersedia.</div>";
                                }
                            } else {
                                echo "<div style='color: #888; font-style: italic;'>Manfaat belum tersedia.</div>";
                            }
                            ?>
                        </div>

                        <h2 style="color: #005f73; font-size: 26px; margin-bottom: 20px; font-weight: 900;">Kamus Kosakata
                        </h2>
                        <div class='cegah-potong'
                            style="background-color: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                            <table style="width: 100%; border: none !important; font-size: 15px;">
                                <?php
                                if (isset($rpp['bahan_ajar']['kosakata'])) {
                                    if (is_array($rpp['bahan_ajar']['kosakata']) && count($rpp['bahan_ajar']['kosakata']) > 0) {
                                        foreach ($rpp['bahan_ajar']['kosakata'] as $kosa) {
                                            // Deteksi jika struktur array kosa kata sesuai atau meleset
                                            $kata = is_array($kosa) ? ($kosa['kata'] ?? '') : '';
                                            $arti = is_array($kosa) ? ($kosa['arti'] ?? '') : $kosa;

                                            if (!empty($kata) || !empty($arti)) {
                                                echo "
                                            <tr>
                                                <td style='width: 30%; padding: 12px 10px; font-weight: bold; color: #005f73; border: none !important; border-bottom: 1px solid #eee !important; vertical-align: top;'>" . htmlspecialchars($kata) . "</td>
                                                <td style='padding: 12px 10px; color: #555; border: none !important; border-bottom: 1px solid #eee !important;'>" . htmlspecialchars($arti) . "</td>
                                            </tr>
                                            ";
                                            }
                                        }
                                    } else if (is_string($rpp['bahan_ajar']['kosakata'])) {
                                        // Jika AI memuntahkan teks biasa
                                        echo "<tr><td style='padding: 10px; border: none !important; color: #555;'>" . $rpp['bahan_ajar']['kosakata'] . "</td></tr>";
                                    } else {
                                        echo "<tr><td style='padding: 10px; border: none !important; color: #888; font-style: italic;'>Kosakata belum tersedia.</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td style='padding: 10px; border: none !important; color: #888; font-style: italic;'>Kosakata belum tersedia.</td></tr>";
                                }
                                ?>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div> <!-- End Editor -->
        </div> <!-- End Scroll Container Mobile -->
    </div> <!-- End Main Container -->

    <!-- AI Sidebar Assistant -->
    <div id="aiSidebar" class="ai-sidebar no-print">
        <div class="sidebar-header">
            <h5 class="m-0 fw-bold" style="color: #4338ca;">Asisten AI <?= ucfirst($provider) ?></h5>
            <button type="button" class="btn-close" style="font-size: 14px;" onclick="closeSidebar()"
                aria-label="Close"></button>
        </div>
        <div class="sidebar-body">
            <div class="quote-box" id="sidebarSelectedText"></div>

            <label class="form-label small text-muted">Perintah Manual Anda (Prompt):</label>
            <textarea id="sidebarPrompt" class="form-control mb-3" rows="3"
                placeholder="Contoh: Ubah kalimat ini menjadi bahasa anak-anak yang ceria..."></textarea>

            <button id="btnSubmitAI" class="btn w-100 text-white mb-4"
                style="background: linear-gradient(135deg, #c084fc, #a855f7); font-weight: 600; border: none;"
                onclick="processSidebarAI()">🚀 Kirim Instruksi Manual</button>

            <div id="quickTemplatesContainer">
                <p class="small text-muted mb-2">Atau gunakan template cepat:</p>
                <button class="quick-btn"
                    onclick="processSidebarAI('Perpanjang dan lengkapi teks ini agar lebih mendalam dan akademis.')">📈
                    Perpanjang Kalimat RPP</button>
                <button class="quick-btn"
                    onclick="processSidebarAI('Parafrase dan perbaiki susunan kalimat ini agar lebih profesional dan mudah dibaca.')">🔄
                    Parafrase Kalimat</button>
                <button class="quick-btn"
                    onclick="processSidebarAI('Buatkan panduan kegiatan / langkah-langkah belajar siswa yang praktis berdasarkan teks ini.')">👩‍🏫
                    Buat Panduan Kegiatan Siswa</button>
            </div>

            <!-- Area Hasil AI (Muncul setelah load) -->
            <div id="aiResultArea"
                style="display: none; margin-top: 25px; padding-top: 20px; border-top: 1px dashed #ccc;">
                <label class="form-label small text-success fw-bold">Hasil Generate AI:</label>
                <div id="aiResultText" class="p-3 bg-light rounded border mb-3"
                    style="font-size: 13px; min-height: 100px; max-height: 250px; overflow-y: auto; white-space: pre-wrap;">
                </div>
                <button class="btn btn-success w-100 fw-bold shadow-sm" onclick="applySidebarAI()">✓ Terapkan ke
                    Editor</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mengoper variabel PHP ke Javascript agar AI Interaktif juga menggunakan Token Custom
        const API_URL = "<?= $url ?>";
        const API_KEY = "<?= $api_key ?>";
        const API_MODEL = "<?= $api_model ?>";

        const editor = document.getElementById('editor');
        const floatMenu = document.getElementById('float-menu');
        let currentSelection = null;
        let selectedString = "";
        let finalAIResult = "";

        // ==========================================
        // FITUR SIMPAN RPP KE DATABASE (AJAX)
        // ==========================================
        async function saveRPPToDatabase() {
            const btn = document.getElementById('btnSave');
            const editorContent = document.getElementById('editor').innerHTML;
            const title = "<?= htmlspecialchars($material_title) ?>";
            const currentRppId = document.getElementById('current_rpp_id').value;

            btn.innerHTML = '⏳ Menyimpan...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('title', title);
            formData.append('content', editorContent);
            if (currentRppId !== "") {
                formData.append('rpp_id', currentRppId);
            }

            try {
                const response = await fetch('save_rpp.php', {
                    method: 'POST',
                    body: formData
                });

                const responseText = await response.text();

                if (response.ok) {
                    document.getElementById('current_rpp_id').value = responseText.trim();
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: 'Data berhasil disimpan.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Terjadi Kesalahan',
                        text: 'Silakan coba kembali.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            } catch (error) {
                alert("⚠️ Terjadi kesalahan jaringan: " + error.message);
            }

            btn.innerHTML = '💾 Simpan ke Database';
            btn.disabled = false;
        }

        // ==========================================
        // FITUR EXPORT MICROSOFT WORD (.DOC)
        // ==========================================
        function exportToWord() {
            const htmlContent = document.getElementById('editor').innerHTML;
            // Gunakan header standar Microsoft Word
            const header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' " +
                "xmlns:w='urn:schemas-microsoft-com:office:word' " +
                "xmlns='http://www.w3.org/TR/REC-html40'>" +
                "<head><meta charset='utf-8'><title>RPP Export</title></head><body>";
            const footer = "</body></html>";
            const sourceHTML = header + htmlContent + footer;
            const source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);

            const fileDownload = document.createElement("a");
            document.body.appendChild(fileDownload);
            fileDownload.href = source;
            fileDownload.download = 'RPP_<?= addslashes($material_title) ?>.doc';
            fileDownload.click();
            document.body.removeChild(fileDownload);
        }

        // ==========================================
        // FITUR FLOATING MENU & TEXT SELECTION
        // ==========================================
        editor.addEventListener('mouseup', function (e) {
            const selection = window.getSelection();
            selectedString = selection.toString().trim();
            if (selectedString.length > 0) {
                const rect = selection.getRangeAt(0).getBoundingClientRect();

                // Perhitungan Posisi Responsif (Termasuk jika di-scroll ke kanan di HP)
                const scrollContainer = document.querySelector('.editor-scroll-container');
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const scrollLeft = scrollContainer.scrollLeft;

                floatMenu.style.top = (rect.top + scrollTop - 50) + 'px';

                let menuLeft = (rect.left + scrollLeft + (rect.width / 2) - 100);
                if (menuLeft < 10) menuLeft = 10;
                floatMenu.style.left = menuLeft + 'px';

                floatMenu.style.display = 'block';
                currentSelection = selection.getRangeAt(0).cloneRange();
            } else {
                floatMenu.style.display = 'none';
            }
        });

        editor.addEventListener('keyup', () => floatMenu.style.display = 'none');

        // ==========================================
        // FITUR UPLOAD & RESIZE GAMBAR (WORD FIX)
        // ==========================================

        // Fungsi global untuk diakses dari elemen HTML hasil generate
        window.resizeImageForWord = function (imgElement) {
            // Ambil lebar saat ini (hilangkan tanda %)
            let currentWidth = imgElement.getAttribute('width') || '60%';
            currentWidth = currentWidth.replace('%', '');

            let w = prompt('Atur persentase lebar gambar (10-100):', currentWidth);
            if (w) {
                // Sangat penting: Word HANYA membaca atribut width="...", BUKAN style="width:..." pada Base64
                imgElement.setAttribute('width', w + '%');
                // Opsional untuk tampilan browser
                imgElement.style.width = w + '%';
            }
        };

        // Event Listener untuk Upload File Manual
        document.getElementById('imageUploader').addEventListener('change', function (e) {
            floatMenu.style.display = 'none';
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                const base64Url = event.target.result;

                // Gunakan format width="..." tanpa unit persentase pada style, agar aman untuk Word
                const imgHTML = `
                    <div contenteditable="false" style="text-align:center; margin: 20px 0;">
                        <img src="${base64Url}" width="60%" style="border-radius:8px; cursor:pointer; max-width:100%;" 
                             alt="Gambar Manual" title="Klik gambar untuk mengubah ukuran"
                             onclick="window.resizeImageForWord(this);">
                        <div style="font-size:11px; color:#888; font-style:italic;">(Klik pada gambar untuk mengubah ukurannya)</div>
                    </div><br/>`;

                if (currentSelection) {
                    currentSelection.deleteContents();
                    const el = document.createElement("div");
                    el.innerHTML = imgHTML;
                    currentSelection.insertNode(el);
                    window.getSelection().removeAllRanges();
                } else {
                    document.getElementById('editor').innerHTML += imgHTML;
                }
            };
            reader.readAsDataURL(file);
            e.target.value = ''; // Reset input
        });

        function generateGambar() {
            floatMenu.style.display = 'none';
            if (!currentSelection) return;
            const imgWord = encodeURIComponent(selectedString.split(" ").slice(0, 4).join(" "));

            // Ganti provider placeholder yang error (via.placeholder.com) ke placehold.co
            const imgHTML = `
            <div contenteditable="false" style="text-align:center; margin: 20px 0; padding:10px; border:2px dashed #0dcaf0; background:#f8ffff; border-radius:10px;">
                <p style="font-size:12px; color:#17a2b8; margin-bottom:5px; font-weight:bold;">[Ilustrasi AI: ${selectedString}]</p>
                <img src="https://placehold.co/700x350/e0f7fa/0dcaf0?text=${imgWord}" width="80%" style="border-radius:8px; cursor:pointer; max-width:100%;" 
                     title="Klik gambar untuk mengubah ukuran"
                     onclick="window.resizeImageForWord(this);" alt="AI Generated">
                <p style="font-size:11px; color:#666; font-style:italic;">(Klik pada gambar untuk mengubah ukurannya)</p>
            </div><br/>`;

            currentSelection.deleteContents();
            const el = document.createElement("div");
            el.innerHTML = imgHTML;
            currentSelection.insertNode(el);
            window.getSelection().removeAllRanges();
        }

        // ==========================================
        // FITUR AI EDITOR SIDEBAR
        // ==========================================
        function editAI() {
            floatMenu.style.display = 'none';
            document.getElementById('sidebarSelectedText').innerText = `"${selectedString}"`;
            document.getElementById('aiSidebar').classList.add('open');
            document.getElementById('aiResultArea').style.display = 'none';
            document.getElementById('sidebarPrompt').value = '';
        }

        function closeSidebar() {
            document.getElementById('aiSidebar').classList.remove('open');
        }

        async function processSidebarAI(quickPrompt = null) {
            const btn = document.getElementById('btnSubmitAI');
            let userPrompt = quickPrompt;

            if (!userPrompt) {
                userPrompt = document.getElementById('sidebarPrompt').value.trim();
                if (!userPrompt) {
                    document.getElementById('sidebarPrompt').style.borderColor = 'red';
                    return;
                }
            }
            document.getElementById('sidebarPrompt').style.borderColor = '#dee2e6';

            btn.innerHTML = '⏳ AI Sedang Memikirkan...';
            btn.disabled = true;
            document.getElementById('aiResultArea').style.display = 'none';

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${API_KEY}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        model: API_MODEL,
                        messages: [
                            { role: "system", content: "Anda adalah asisten editor teks pendidikan. Berikan HANYA teks balasan langsung yang sudah diperbarui sesuai instruksi. Jangan menambahkan kata pengantar seperti 'Tentu, ini perbaikannya:'." },
                            { role: "user", content: `Instruksi: ${userPrompt}\n\nTeks Asli yang direvisi: ${selectedString}` }
                        ],
                        temperature: 0.5
                    })
                });

                const data = await response.json();

                if (data.choices && data.choices.length > 0) {
                    finalAIResult = data.choices[0].message.content;
                    document.getElementById('aiResultText').innerText = finalAIResult;
                    document.getElementById('aiResultArea').style.display = 'block';

                    // Otomatis scroll ke hasil
                    const sidebarBody = document.querySelector('.sidebar-body');
                    sidebarBody.scrollTop = sidebarBody.scrollHeight;
                } else {
                    const errorMsg = data.error ? data.error.message : "Respons dari AI kosong.";
                    document.getElementById('aiResultText').innerText = "Gagal memproses AI: " + errorMsg;
                    document.getElementById('aiResultText').style.color = "red";
                    document.getElementById('aiResultArea').style.display = 'block';
                }
            } catch (err) {
                document.getElementById('aiResultText').innerText = "Terjadi kesalahan jaringan: " + err;
                document.getElementById('aiResultText').style.color = "red";
                document.getElementById('aiResultArea').style.display = 'block';
            }

            btn.innerHTML = '🚀 Kirim Instruksi Manual';
            btn.disabled = false;
        }

        function applySidebarAI() {
            if (!currentSelection || !finalAIResult) return;

            // Ganti teks yang diseleksi di editor dengan hasil AI
            currentSelection.deleteContents();
            currentSelection.insertNode(document.createTextNode(finalAIResult));

            // Hapus seleksi kursor dan tutup sidebar
            window.getSelection().removeAllRanges();
            closeSidebar();
        }
    </script>
</body>

</html>