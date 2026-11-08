<?php
/**
 * API Action Controller - Sistem & Audio
 * Menangani permintaan AJAX dari Web/HP untuk kontrol volume, waktu, pemicu bel manual, dan status.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system.php';
require_once __DIR__ . '/../includes/prayer.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Mengirimkan respon JSON terstruktur dan mengakhiri script
 *
 * @param bool $success Status keberhasilan
 * @param string $message Pesan informasi
 * @param array $data Data tambahan opsional
 */
function json_response($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

try {
    switch ($action) {
        case 'get_status':
            // Mengambil status realtime untuk polling di UI HP
            $timeInfo = get_system_time_info();
            $volume = get_system_volume();
            $netInfo = get_network_info();
            $workerActive = is_worker_running();

            // Ambil jadwal bel berikutnya untuk hari ini
            $db = get_db_connection();
            $todayNum = $timeInfo['day_number'];
            $activeCat = get_setting('active_category', 'reguler');
            $currentTime = substr($timeInfo['current_time'], 0, 5);

            $stmt = $db->prepare("SELECT * FROM schedules 
                                  WHERE is_active = 1 
                                    AND (category = ? OR category = 'all')
                                    AND time >= ?
                                  ORDER BY time ASC LIMIT 1");
            $stmt->execute([$activeCat, $currentTime]);
            $nextSchedule = $stmt->fetch();

            json_response(true, 'Status berhasil diambil', [
                'time_info' => $timeInfo,
                'volume' => $volume,
                'network' => $netInfo,
                'worker_active' => $workerActive,
                'next_schedule' => $nextSchedule ?: null
            ]);
            break;

        case 'set_volume':
            // Mengatur persentase volume hardware
            $volume = isset($_POST['volume']) ? (int)$_POST['volume'] : 80;
            set_system_volume($volume);
            json_response(true, "Volume disetel ke {$volume}%", ['volume' => $volume]);
            break;

        case 'trigger_bell':
            // Memicu bel manual instan dari HP
            $audio_file = trim($_POST['audio_file'] ?? '');
            $label = trim($_POST['label'] ?? 'Bel Manual');

            if (empty($audio_file)) {
                json_response(false, 'File audio harus dipilih');
            }

            // Masukkan ke antrian untuk dieksekusi worker
            $queue_id = queue_audio_play($audio_file, $label, 'manual');

            // Jika worker tidak sedang berjalan, putar langsung sebagai fallback
            if (!is_worker_running()) {
                play_audio_direct($audio_file);
            }

            json_response(true, "Bel berhasil dipicu ({$label})", ['queue_id' => $queue_id]);
            break;

        case 'test_sound':
            // Tes nada pendek di speaker STB
            $audio_file = trim($_POST['audio_file'] ?? 'chime-pendek.wav');
            queue_audio_play($audio_file, 'Tes Suara Speaker', 'test');

            if (!is_worker_running()) {
                play_audio_direct($audio_file);
            }

            json_response(true, 'Memutar suara tes pada speaker STB');
            break;

        case 'sync_time':
            // Sinkronisasi jam STB langsung dari browser/HP admin
            $client_datetime = trim($_POST['client_datetime'] ?? '');
            if (empty($client_datetime)) {
                json_response(false, 'Data waktu HP tidak dikirimkan');
            }

            $res = set_system_datetime($client_datetime);
            json_response($res['success'], $res['message']);
            break;

        case 'set_timezone':
            // Mengubah zona waktu sistem
            $timezone = trim($_POST['timezone'] ?? 'Asia/Jakarta');
            set_setting('timezone', $timezone);
            if (is_linux_os()) {
                $safe_tz = escapeshellarg($timezone);
                @shell_exec("sudo timedatectl set-timezone {$safe_tz} 2>/dev/null");
            }
            json_response(true, "Zona waktu diubah menjadi {$timezone}");
            break;

        case 'set_active_category':
            // Mengubah mode kategori aktif (Reguler, Ujian, Shift)
            $cat = trim($_POST['category'] ?? 'reguler');
            set_setting('active_category', $cat);
            json_response(true, "Kategori jadwal aktif diubah menjadi: {$cat}");
            break;

        case 'save_prayer_settings':
            // Menyimpan koordinat, elevasi, dan tuning menit jadwal sholat Kemenag
            $lat = trim($_POST['latitude'] ?? '-6.2088');
            $lon = trim($_POST['longitude'] ?? '106.8456');
            $elevation = trim($_POST['elevation'] ?? '10');
            $tune_subuh = (int)($_POST['tune_subuh'] ?? 0);
            $tune_dzuhur = (int)($_POST['tune_dzuhur'] ?? 0);
            $tune_ashar = (int)($_POST['tune_ashar'] ?? 0);
            $tune_maghrib = (int)($_POST['tune_maghrib'] ?? 0);
            $tune_isya = (int)($_POST['tune_isya'] ?? 0);

            set_setting('prayer_latitude', $lat);
            set_setting('prayer_longitude', $lon);
            set_setting('prayer_elevation', $elevation);
            set_setting('prayer_tune_subuh', (string)$tune_subuh);
            set_setting('prayer_tune_dzuhur', (string)$tune_dzuhur);
            set_setting('prayer_tune_ashar', (string)$tune_ashar);
            set_setting('prayer_tune_maghrib', (string)$tune_maghrib);
            set_setting('prayer_tune_isya', (string)$tune_isya);

            // Perbarui seluruh jadwal sholat dinamis di tabel schedules
            $db = get_db_connection();
            $stmt = $db->query("SELECT id, prayer_name, prayer_offset FROM schedules WHERE time_type = 'prayer'");
            $prayer_schedules = $stmt->fetchAll();
            $stmt_upd = $db->prepare("UPDATE schedules SET time = ? WHERE id = ?");
            foreach ($prayer_schedules as $ps) {
                $calcTime = calculate_prayer_schedule_time($ps['prayer_name'], (int)($ps['prayer_offset'] ?? 0));
                $stmt_upd->execute([$calcTime, $ps['id']]);
            }

            $times = get_today_prayer_times();
            json_response(true, 'Pengaturan waktu sholat Kemenag berhasil disimpan', ['prayer_times' => $times]);
            break;

        case 'get_prayer_times':
            // Mengambil jadwal sholat hari ini untuk keperluan preview UI
            $times = get_today_prayer_times();
            json_response(true, 'Jadwal sholat berhasil dimuat', ['prayer_times' => $times]);
            break;

        case 'reboot_stb':
            // Restart STB Linux dari remote HP
            if (is_linux_os()) {
                @shell_exec('sudo reboot 2>/dev/null &');
                json_response(true, 'STB sedang direstart. Mohon tunggu 1-2 menit.');
            } else {
                json_response(true, 'Simulasi reboot STB (Mode Windows)');
            }
            break;

        default:
            json_response(false, 'Aksi tidak dikenal');
            break;
    }
} catch (Exception $e) {
    json_response(false, 'Terjadi kesalahan server: ' . $e->getMessage());
}
