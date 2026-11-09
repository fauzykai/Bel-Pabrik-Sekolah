<?php
/**
 * Dedicated Database Initialization & Migration Page
 * Mempersiapkan tabel-tabel SQLite dan data default untuk sistem Bel Otomatis.
 * Dapat diakses langsung via browser (http://.../init_db.php) atau CLI (php init_db.php).
 */

header('Content-Type: text/html; charset=utf-8');

$db_dir = __DIR__ . '/database';
$db_file = $db_dir . '/bell.db';

// Pastikan direktori database ada
if (!is_dir($db_dir)) {
    mkdir($db_dir, 0777, true);
}

$is_new = !file_exists($db_file);

try {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Tabel Konfigurasi / Pengaturan Sistem
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        description TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Tabel Jadwal Bel
    $db->exec("CREATE TABLE IF NOT EXISTS schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        time TEXT NOT NULL,                -- Format 'HH:MM' (contoh: '07:00')
        days TEXT NOT NULL,                -- '1,2,3,4,5' (1=Senin..7=Minggu) atau 'all'
        audio_intro TEXT DEFAULT '',       -- File audio pembuka (opsional)
        audio_file TEXT NOT NULL,          -- File audio utama
        audio_outro TEXT DEFAULT '',       -- File audio penutup (opsional)
        category TEXT DEFAULT 'reguler',   -- 'reguler', 'ujian', 'shift_1', 'shift_2'
        is_active INTEGER DEFAULT 1,       -- 1 = Aktif, 0 = Nonaktif
        play_duration INTEGER DEFAULT 0,   -- Durasi potong detik (0 = full)
        time_type TEXT DEFAULT 'static',   -- 'static' (jam tetap) atau 'prayer' (mengikuti waktu sholat)
        prayer_name TEXT DEFAULT '',       -- 'subuh', 'dzuhur', 'ashar', 'maghrib', 'isya'
        prayer_offset INTEGER DEFAULT 0,   -- Offset menit terhadap waktu sholat (misal: -5 atau +0)
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrasi otomatis jika tabel schedules sudah ada sebelumnya
    $cols = $db->query("PRAGMA table_info(schedules)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($cols, 'name');
    if (!in_array('audio_intro', $col_names)) {
        $db->exec("ALTER TABLE schedules ADD COLUMN audio_intro TEXT DEFAULT ''");
    }
    if (!in_array('audio_outro', $col_names)) {
        $db->exec("ALTER TABLE schedules ADD COLUMN audio_outro TEXT DEFAULT ''");
    }
    if (!in_array('time_type', $col_names)) {
        $db->exec("ALTER TABLE schedules ADD COLUMN time_type TEXT DEFAULT 'static'");
    }
    if (!in_array('prayer_name', $col_names)) {
        $db->exec("ALTER TABLE schedules ADD COLUMN prayer_name TEXT DEFAULT ''");
    }
    if (!in_array('prayer_offset', $col_names)) {
        $db->exec("ALTER TABLE schedules ADD COLUMN prayer_offset INTEGER DEFAULT 0");
    }

    // 3. Tabel Pemetaan Tombol IoT (ESP 12 Tombol)
    $db->exec("CREATE TABLE IF NOT EXISTS iot_buttons (
        button_number INTEGER PRIMARY KEY, -- 1 s/d 12
        name TEXT NOT NULL,                -- Label tombol (misal: 'Tombol 1 - Pos Satpam')
        audio_intro TEXT DEFAULT '',       -- File audio pembuka (opsional)
        audio_file TEXT NOT NULL,          -- File audio utama yang dibunyikan
        audio_outro TEXT DEFAULT '',       -- File audio penutup (opsional)
        is_active INTEGER DEFAULT 1,       -- 1 = Aktif, 0 = Nonaktif
        last_triggered DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Inisialisasi 12 tombol default jika tabel baru
    $count_iot = $db->query("SELECT COUNT(*) FROM iot_buttons")->fetchColumn();
    if ($count_iot == 0) {
        $stmt_iot = $db->prepare("INSERT INTO iot_buttons (button_number, name, audio_intro, audio_file, audio_outro, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $default_audios = [
            1 => ['Tombol 1 - Bel Masuk', 'chime-pendek.wav', 'bel-masuk.wav', ''],
            2 => ['Tombol 2 - Bel Istirahat', 'chime-pendek.wav', 'bel-istirahat.wav', ''],
            3 => ['Tombol 3 - Bel Pulang', 'chime-pendek.wav', 'bel-pulang.wav', ''],
            4 => ['Tombol 4 - Chime Pengumuman', '', 'chime-pendek.wav', ''],
            5 => ['Tombol 5 - Bel 5', '', 'chime-pendek.wav', ''],
            6 => ['Tombol 6 - Bel 6', '', 'chime-pendek.wav', ''],
            7 => ['Tombol 7 - Bel 7', '', 'chime-pendek.wav', ''],
            8 => ['Tombol 8 - Bel 8', '', 'chime-pendek.wav', ''],
            9 => ['Tombol 9 - Bel 9', '', 'chime-pendek.wav', ''],
            10 => ['Tombol 10 - Bel 10', '', 'chime-pendek.wav', ''],
            11 => ['Tombol 11 - Bel 11', '', 'chime-pendek.wav', ''],
            12 => ['Tombol 12 - Bel 12', '', 'chime-pendek.wav', '']
        ];
        foreach ($default_audios as $btn_num => $data) {
            $stmt_iot->execute([$btn_num, $data[0], $data[1], $data[2], $data[3], 1]);
        }
    }

    // 4. Tabel Antrian Pemutaran Audio Realtime (Queue)
    // Digunakan oleh Web UI untuk memberi perintah ke daemon background pemutar audio
    $db->exec("CREATE TABLE IF NOT EXISTS play_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        audio_file TEXT NOT NULL,
        label TEXT,
        triggered_by TEXT DEFAULT 'manual', -- 'manual', 'schedule', 'test', 'iot'
        status TEXT DEFAULT 'pending',      -- 'pending', 'playing', 'done', 'failed'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        played_at DATETIME
    )");

    // 5. Tabel Riwayat / Log Pemutaran Bel
    $db->exec("CREATE TABLE IF NOT EXISTS play_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        schedule_id INTEGER NULL,
        label TEXT,
        audio_file TEXT,
        triggered_by TEXT DEFAULT 'schedule',
        played_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. Tabel Histori Khusus Pemencetan Tombol Bel IoT (untuk CCTV & analisa kebiasaan)
    $db->exec("CREATE TABLE IF NOT EXISTS iot_press_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        button_number INTEGER NOT NULL,
        button_name TEXT NOT NULL,
        audio_played TEXT,
        client_ip TEXT,
        status TEXT DEFAULT 'success',      -- 'success', 'disabled', 'error'
        multi_stb_relay TEXT,
        pressed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_iot_press_time ON iot_press_logs(pressed_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_iot_press_button ON iot_press_logs(button_number)");

    // Inisialisasi Pengaturan Default
    $default_settings = [
        ['volume', '80', 'Volume master hardware Linux (0-100%)'],
        ['timezone', 'Asia/Jakarta', 'Zona waktu sistem (WIB/WITA/WIT)'],
        ['audio_device', 'default', 'Output ALSA perangkat audio (default, hw:0, dll)'],
        ['active_category', 'reguler', 'Kategori jadwal yang sedang aktif (reguler, ujian, shift_1, shift_2)'],
        ['auto_ntp', '1', 'Sinkronisasi jam otomatis via internet NTP (1=Aktif, 0=Manual)'],
        ['admin_pin', '1234', 'PIN pengaman untuk ubah setting kritis dari HP (opsional)'],
        ['hotspot_ssid', 'BEL-STB-SETUP', 'SSID WiFi saat mode darurat Access Point aktif'],
        ['prayer_latitude', '-6.2088', 'Garis lintang (Latitude) lokasi untuk hisab waktu sholat'],
        ['prayer_longitude', '106.8456', 'Garis bujur (Longitude) lokasi untuk hisab waktu sholat'],
        ['prayer_elevation', '10', 'Ketinggian lokasi dari permukaan laut (meter dpl)'],
        ['prayer_tune_subuh', '0', 'Koreksi penyesuaian menit waktu Subuh (+/- menit)'],
        ['prayer_tune_dzuhur', '0', 'Koreksi penyesuaian menit waktu Dzuhur (+/- menit)'],
        ['prayer_tune_ashar', '0', 'Koreksi penyesuaian menit waktu Ashar (+/- menit)'],
        ['prayer_tune_maghrib', '0', 'Koreksi penyesuaian menit waktu Maghrib (+/- menit)'],
        ['prayer_tune_isya', '0', 'Koreksi penyesuaian menit waktu Isya (+/- menit)']
    ];

    $stmt_setting = $db->prepare("INSERT OR IGNORE INTO settings (key, value, description) VALUES (?, ?, ?)");
    foreach ($default_settings as $s) {
        $stmt_setting->execute($s);
    }

    // Inisialisasi Contoh Jadwal Standar (Sekolah / Pabrik) jika database baru
    $count = $db->query("SELECT COUNT(*) FROM schedules")->fetchColumn();
    if ($count == 0) {
        $default_schedules = [
            ['Masuk Kerja / Masuk Sekolah', '07:00', '1,2,3,4,5,6', 'chime-pendek.wav', 'bel-masuk.wav', 'chime-pendek.wav', 'reguler', 1],
            ['Istirahat Siang', '12:00', '1,2,3,4,5,6', 'chime-pendek.wav', 'bel-istirahat.wav', 'chime-pendek.wav', 'reguler', 1],
            ['Masuk Setelah Istirahat', '13:00', '1,2,3,4,5,6', 'chime-pendek.wav', 'bel-masuk.wav', '', 'reguler', 1],
            ['Selesai / Jam Pulang', '16:00', '1,2,3,4,5', 'chime-pendek.wav', 'bel-pulang.wav', 'chime-pendek.wav', 'reguler', 1],
            ['Selesai Sabtu (Setengah Hari)', '14:00', '6', 'chime-pendek.wav', 'bel-pulang.wav', '', 'reguler', 1]
        ];

        $stmt_sch = $db->prepare("INSERT INTO schedules (name, time, days, audio_intro, audio_file, audio_outro, category, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($default_schedules as $sch) {
            $stmt_sch->execute($sch);
        }
    }

    $success_msg = "Database SQLite berhasil diinisialisasi pada: " . $db_file;
} catch (Exception $e) {
    $error_msg = "Gagal inisialisasi database: " . $e->getMessage();
}

// Jika dipanggil via CLI
if (php_sapi_name() === 'cli') {
    echo isset($success_msg) ? "[OK] " . $success_msg . "\n" : "[ERROR] " . $error_msg . "\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inisialisasi Database Bel Otomatis</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/icons/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container py-4" style="max-width: 600px;">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <?php if (isset($success_msg)): ?>
                    <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
                    <h4 class="card-title text-success fw-bold">Inisialisasi Database Berhasil</h4>
                    <p class="text-secondary small mb-4"><?= htmlspecialchars($success_msg) ?></p>
                    <div class="alert alert-light border text-start small">
                        <strong>Tabel yang dipersiapkan:</strong>
                        <ul class="mb-0 mt-1">
                            <li><code>schedules</code> : Penyimpanan jadwal otomatis</li>
                            <li><code>settings</code> : Konfigurasi volume, timezone, NTP, dll</li>
                            <li><code>play_queue</code> : Antrian perintah pemutaran audio dari Web UI</li>
                            <li><code>play_logs</code> : Catatan log waktu bel berbunyi</li>
                        </ul>
                    </div>
                    <a href="index.php" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-speedometer2 me-1"></i> Buka Dashboard Bel
                    </a>
                <?php else: ?>
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-3"></i>
                    <h4 class="card-title text-danger fw-bold">Inisialisasi Gagal</h4>
                    <p class="text-danger small mb-4"><?= htmlspecialchars($error_msg ?? 'Terjadi kesalahan') ?></p>
                    <a href="init_db.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
