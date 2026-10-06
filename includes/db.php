<?php
/**
 * Database Helper Functions
 * Mengelola koneksi PDO SQLite dan operasi query untuk sistem Bel Otomatis.
 */

define('DB_FILE', __DIR__ . '/../database/bell.db');

/**
 * Mendapatkan koneksi tunggal (Singleton) PDO ke database SQLite
 *
 * @return PDO Objek koneksi database PDO
 * @throws PDOException jika file database tidak dapat dibuka
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        if (!file_exists(DB_FILE)) {
            // Jika database belum dibuat, alihkan pengguna ke halaman init
            if (php_sapi_name() !== 'cli') {
                header('Location: init_db.php');
                exit;
            } else {
                die("Database belum diinisialisasi. Jalankan: php init_db.php\n");
            }
        }
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

/**
 * Mengambil nilai konfigurasi dari tabel settings berdasarkan kunci
 *
 * @param string $key Nama kunci setting (contoh: 'volume', 'timezone')
 * @param mixed $default Nilai default jika key tidak ditemukan
 * @return mixed Nilai setting atau default
 */
function get_setting($key, $default = null) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row !== false ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Memperbarui atau menyimpan nilai konfigurasi baru ke tabel settings
 *
 * @param string $key Nama kunci setting
 * @param string $value Nilai baru yang akan disimpan
 * @return bool Berhasil atau gagal
 */
function set_setting($key, $value) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("INSERT INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
                              ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Memasukkan perintah pemutaran audio ke dalam tabel antrian (play_queue)
 *
 * @param string $audio_file Nama file audio di folder uploads/audio/
 * @param string $label Keterangan suara (contoh: 'Bel Manual Petugas', 'Tes Suara')
 * @param string $triggered_by Pemicu ('manual', 'schedule', 'test')
 * @return int ID antrian yang baru dibuat
 */
function queue_audio_play($audio_file, $label = 'Manual Bell', $triggered_by = 'manual') {
    $db = get_db_connection();
    $stmt = $db->prepare("INSERT INTO play_queue (audio_file, label, triggered_by, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$audio_file, $label, $triggered_by]);
    return (int)$db->lastInsertId();
}

/**
 * Mencatat riwayat pemutaran bel yang telah berhasil dibunyikan
 *
 * @param int|null $schedule_id ID jadwal jika dipicu otomatis, atau null jika manual
 * @param string $label Label deskripsi bel
 * @param string $audio_file Nama file audio yang diputar
 * @param string $triggered_by Sumber pemicu ('schedule', 'manual', 'test')
 * @return bool Status keberhasilan penyimpanan log
 */
function log_bell_play($schedule_id, $label, $audio_file, $triggered_by = 'schedule') {
    $db = get_db_connection();
    $stmt = $db->prepare("INSERT INTO play_logs (schedule_id, label, audio_file, triggered_by) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$schedule_id, $label, $audio_file, $triggered_by]);
}
