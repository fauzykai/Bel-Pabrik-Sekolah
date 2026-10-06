<?php
/**
 * Background Scheduler Daemon (CLI Worker)
 * Berjalan terus-menerus di latar belakang (background) melalui Systemd Service.
 * Tugas utama:
 * 1. Memeriksa jadwal bel setiap menit dan memutar suara saat waktu cocok.
 * 2. Memeriksa antrian bel manual (play_queue) seketika tanpa jeda.
 * 3. Menjaga heartbeat agar Web UI mengetahui status layanan masih berjalan.
 */

// Pastikan hanya dijalankan melalui antarmuka CLI
if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui CLI/Terminal.\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system.php';

echo "====================================================\n";
echo "   SISTEM BEL OTOMATIS STB - SCHEDULER DAEMON     \n";
echo "====================================================\n";
echo "Worker dimulai pada: " . date('Y-m-d H:i:s') . "\n";

$heartbeat_file = __DIR__ . '/../database/worker_alive.txt';
$last_checked_minute = '';

/**
 * Memutar file audio menggunakan pemutar CLI Linux (mpg123 / aplay / mpv)
 *
 * @param string $filename Nama file di dalam folder uploads/audio/
 * @return bool Berhasil atau gagal
 */
function play_audio_file($filename) {
    $audio_path = realpath(__DIR__ . '/../uploads/audio/' . $filename);
    if (!$audio_path || !file_exists($audio_path)) {
        echo "[ERROR] File audio tidak ditemukan: {$filename}\n";
        return false;
    }

    echo "[PLAYING] Memutar: {$filename} ...\n";

    if (is_linux_os()) {
        $safe_path = escapeshellarg($audio_path);
        // Putar langsung ke perangkat output audio hardware
        if (substr($filename, -4) === '.wav') {
            $cmd = "aplay -q {$safe_path} || mpg123 -q {$safe_path} || mpv --no-video {$safe_path}";
        } else {
            $cmd = "mpg123 -q {$safe_path} || mpv --no-video {$safe_path}";
        }
        passthru($cmd);
    } else {
        // Fallback untuk uji coba di Windows
        $ps = "powershell -c \"(New-Object Media.SoundPlayer '{$audio_path}').PlaySync()\"";
        exec($ps);
    }

    echo "[DONE] Selesai memutar: {$filename}\n";
    return true;
}

/**
 * Memproses antrian bel manual instan yang dipicu oleh admin dari HP/Browser
 *
 * @param PDO $db Objek koneksi database
 */
function process_manual_queue($db) {
    try {
        $stmt = $db->query("SELECT * FROM play_queue WHERE status = 'pending' ORDER BY id ASC");
        $pending = $stmt->fetchAll();

        foreach ($pending as $item) {
            $id = $item['id'];
            $file_raw = $item['audio_file'];
            $label = $item['label'] ?? 'Bel Manual';
            $trigger = $item['triggered_by'] ?? 'manual';

            // Update status menjadi 'playing'
            $db->prepare("UPDATE play_queue SET status = 'playing', played_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

            // Dukung pemutaran berantai jika dipisah karakter '|'
            $files = explode('|', $file_raw);
            foreach ($files as $file) {
                $file = trim($file);
                if (!empty($file)) {
                    play_audio_file($file);
                    usleep(300000); // Jeda 300ms antar nada
                }
            }

            // Update status menjadi 'done'
            $db->prepare("UPDATE play_queue SET status = 'done' WHERE id = ?")->execute([$id]);

            // Simpan ke riwayat log
            log_bell_play(null, $label, $file_raw, $trigger);
        }
    } catch (Exception $e) {
        echo "[EXC] Queue error: " . $e->getMessage() . "\n";
    }
}

/**
 * Memeriksa jadwal otomatis di database untuk hari dan menit saat ini
 *
 * @param PDO $db Objek koneksi database
 * @param string $current_time Waktu saat ini dalam format 'HH:MM'
 * @param int $current_day Hari saat ini (1=Senin .. 7=Minggu)
 */
function process_scheduled_bells($db, $current_time, $current_day) {
    try {
        $active_category = get_setting('active_category', 'reguler');

        // Ambil jadwal yang aktif pada waktu dan kategori saat ini
        $stmt = $db->prepare("SELECT * FROM schedules 
                              WHERE is_active = 1 
                                AND time = ? 
                                AND (category = ? OR category = 'all')");
        $stmt->execute([$current_time, $active_category]);
        $schedules = $stmt->fetchAll();

        foreach ($schedules as $sch) {
            $days = explode(',', $sch['days']);
            // Cek apakah hari ini termasuk dalam konfigurasi jadwal
            if (in_array((string)$current_day, $days) || in_array('all', $days)) {
                echo "[JADWAL] Menjalankan bel: {$sch['name']} ({$sch['time']})\n";

                // 1. Putar Audio Intro (jika ada)
                if (!empty($sch['audio_intro'])) {
                    echo "[INTRO] ";
                    play_audio_file($sch['audio_intro']);
                    usleep(300000);
                }

                // 2. Putar Audio Utama
                if (!empty($sch['audio_file'])) {
                    echo "[UTAMA] ";
                    play_audio_file($sch['audio_file']);
                    usleep(300000);
                }

                // 3. Putar Audio Outro (jika ada)
                if (!empty($sch['audio_outro'])) {
                    echo "[OUTRO] ";
                    play_audio_file($sch['audio_outro']);
                }

                log_bell_play($sch['id'], $sch['name'], $sch['audio_file'], 'schedule');
            }
        }
    } catch (Exception $e) {
        echo "[EXC] Schedule error: " . $e->getMessage() . "\n";
    }
}

// Loop Utama Daemon
while (true) {
    // 1. Tulis detak jantung (heartbeat)
    @file_put_contents($heartbeat_file, time());

    // 2. Set zona waktu dinamis sesuai database
    $tz = get_setting('timezone', 'Asia/Jakarta');
    date_default_timezone_set($tz);

    $db = get_db_connection();

    // 3. Proses antrian perintah instan dari HP
    process_manual_queue($db);

    // 4. Proses jadwal otomatis
    $current_minute = date('H:i');
    $current_day = (int)date('N');

    // Hanya picu sekali per menit untuk menghindari pengulangan bel
    if ($current_minute !== $last_checked_minute) {
        $last_checked_minute = $current_minute;
        process_scheduled_bells($db, $current_minute, $current_day);
    }

    // Istirahat 1 detik sebelum loop berikutnya (sangat hemat CPU)
    sleep(1);
}
