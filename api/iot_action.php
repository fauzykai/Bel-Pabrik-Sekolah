<?php
/**
 * API Action Controller - Manajemen Tombol Bel IoT
 * Melayani update pemetaan audio tombol, toggle aktif/nonaktif, tes bunyi, dan status realtime.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Respon JSON terstruktur
 */
function iot_json_response($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

try {
    $db = get_db_connection();

    switch ($action) {
        case 'get_buttons':
            // Mengambil daftar 12 tombol beserta status pemicuan terakhir
            $buttons = $db->query("SELECT * FROM iot_buttons ORDER BY button_number ASC")->fetchAll();
            iot_json_response(true, 'Data tombol berhasil dimuat', ['buttons' => $buttons]);
            break;

        case 'save_button':
            // Menyimpan nama dan pemetaan audio untuk sebuah tombol
            $btn_num = (int)($_POST['button_number'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $audio_intro = trim($_POST['audio_intro'] ?? '');
            $audio_file = trim($_POST['audio_file'] ?? '');
            $audio_outro = trim($_POST['audio_outro'] ?? '');

            if ($btn_num < 1 || $btn_num > 12) {
                iot_json_response(false, 'Nomor tombol tidak valid (1-12)');
            }

            if (empty($name) || empty($audio_file)) {
                iot_json_response(false, 'Nama tombol dan file Audio Utama wajib diisi');
            }

            $stmt = $db->prepare("UPDATE iot_buttons SET name = ?, audio_intro = ?, audio_file = ?, audio_outro = ? WHERE button_number = ?");
            $stmt->execute([$name, $audio_intro, $audio_file, $audio_outro, $btn_num]);

            iot_json_response(true, "Pengaturan Tombol {$btn_num} berhasil disimpan");
            break;

        case 'toggle_active':
            // Mengubah status aktif/nonaktif tombol IoT
            $btn_num = (int)($_POST['button_number'] ?? 0);
            $stmt = $db->prepare("SELECT is_active FROM iot_buttons WHERE button_number = ?");
            $stmt->execute([$btn_num]);
            $row = $stmt->fetch();

            if (!$row) {
                iot_json_response(false, 'Tombol tidak ditemukan');
            }

            $new_status = $row['is_active'] ? 0 : 1;
            $db->prepare("UPDATE iot_buttons SET is_active = ? WHERE button_number = ?")->execute([$new_status, $btn_num]);

            iot_json_response(true, 'Status tombol diperbarui', ['is_active' => $new_status]);
            break;

        case 'test_trigger':
            // Memicu tes tombol seolah-olah ditekan dari perangkat ESP
            $btn_num = (int)($_POST['button_number'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM iot_buttons WHERE button_number = ?");
            $stmt->execute([$btn_num]);
            $btn = $stmt->fetch();

            if (!$btn) {
                iot_json_response(false, 'Tombol tidak ditemukan');
            }

            $sequence = array_values(array_filter([$btn['audio_intro'], $btn['audio_file'], $btn['audio_outro']]));
            $seq_str = implode('|', $sequence);
            queue_audio_play($seq_str, 'Tes IoT: ' . $btn['name'], 'test');

            if (!is_worker_running()) {
                foreach ($sequence as $f) {
                    play_audio_direct($f);
                    usleep(300000);
                }
            }

            $db->prepare("UPDATE iot_buttons SET last_triggered = CURRENT_TIMESTAMP WHERE button_number = ?")->execute([$btn_num]);

            // Catat ke log histori sebagai 'web_test'
            $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            try {
                $stmt_log = $db->prepare("INSERT INTO iot_press_logs (button_number, button_name, audio_played, client_ip, status) VALUES (?, ?, ?, ?, 'web_test')");
                $stmt_log->execute([$btn_num, $btn['name'], $seq_str, $client_ip]);
            } catch (Exception $e) {}

            iot_json_response(true, "Memicu tes bel untuk {$btn['name']}");
            break;

        case 'get_press_logs':
            // Mengambil riwayat log pemencetan tombol dengan filter CCTV & statistik
            $date = trim($_GET['date'] ?? '');
            $btn_filter = (int)($_GET['button'] ?? 0);
            $limit = min((int)($_GET['limit'] ?? 100), 500);

            $sql = "SELECT * FROM iot_press_logs WHERE 1=1";
            $params = [];

            if (!empty($date)) {
                $sql .= " AND DATE(pressed_at) = ?";
                $params[] = $date;
            }
            if ($btn_filter > 0) {
                $sql .= " AND button_number = ?";
                $params[] = $btn_filter;
            }

            $sql .= " ORDER BY pressed_at DESC LIMIT " . $limit;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ringkasan statistik cepat hari ini
            $today = date('Y-m-d');
            $stat_total_today = (int)$db->query("SELECT COUNT(*) FROM iot_press_logs WHERE DATE(pressed_at) = '{$today}'")->fetchColumn();
            
            // Tombol paling sering ditekan hari ini
            $top_btn = $db->query("SELECT button_number, button_name, COUNT(*) as cnt 
                                   FROM iot_press_logs 
                                   WHERE DATE(pressed_at) = '{$today}' 
                                   GROUP BY button_number 
                                   ORDER BY cnt DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

            iot_json_response(true, 'Log berhasil dimuat', [
                'logs' => $logs,
                'summary' => [
                    'today_count' => $stat_total_today,
                    'top_button' => $top_btn ? ($top_btn['button_name'] . ' (' . $top_btn['cnt'] . 'x)') : '-'
                ]
            ]);
            break;

        case 'clear_press_logs':
            // Membersihkan riwayat pemencetan tombol
            $days = (int)($_POST['older_than_days'] ?? 0);
            if ($days > 0) {
                $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                $stmt = $db->prepare("DELETE FROM iot_press_logs WHERE pressed_at < ?");
                $stmt->execute([$cutoff]);
                $affected = $stmt->rowCount();
                iot_json_response(true, "Berhasil menghapus {$affected} data log yang lebih lama dari {$days} hari");
            } else {
                $db->exec("DELETE FROM iot_press_logs");
                iot_json_response(true, "Seluruh riwayat log pemencetan tombol berhasil dibersihkan");
            }
            break;

        case 'export_press_logs_csv':
            // Ekspor riwayat log pemencetan ke format CSV untuk analisis CCTV
            $date = trim($_GET['date'] ?? '');
            $btn_filter = (int)($_GET['button'] ?? 0);

            $sql = "SELECT id, button_number, button_name, pressed_at, audio_played, client_ip, status, multi_stb_relay 
                    FROM iot_press_logs WHERE 1=1";
            $params = [];
            if (!empty($date)) {
                $sql .= " AND DATE(pressed_at) = ?";
                $params[] = $date;
            }
            if ($btn_filter > 0) {
                $sql .= " AND button_number = ?";
                $params[] = $btn_filter;
            }
            $sql .= " ORDER BY pressed_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filename = "histori_bel_iot_" . ($date ?: date('Ymd_His')) . ".csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'No Tombol', 'Nama / Lokasi Tombol', 'Waktu Pemencetan (WIB)', 'Audio Diputar', 'IP Perangkat', 'Status', 'Multi-STB Relay']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['button_number'],
                    $row['button_name'],
                    $row['pressed_at'],
                    $row['audio_played'],
                    $row['client_ip'],
                    $row['status'],
                    $row['multi_stb_relay'] ?: '-'
                ]);
            }
            fclose($output);
            exit;

        case 'save_multi_stb':
            // Menyimpan alamat IP STB Sekunder (Lantai 2)
            $secondary_ip = trim($_POST['secondary_ip'] ?? '');
            set_setting('iot_secondary_stb_ip', $secondary_ip);
            if (!empty($secondary_ip)) {
                iot_json_response(true, "Sinkronisasi STB kedua ({$secondary_ip}) berhasil disimpan");
            } else {
                iot_json_response(true, "Sinkronisasi STB kedua dimatikan (mode 1 STB)");
            }
            break;

        default:
            iot_json_response(false, 'Aksi tidak dikenal');
            break;
    }
} catch (Exception $e) {
    iot_json_response(false, 'Kesalahan server: ' . $e->getMessage());
}
