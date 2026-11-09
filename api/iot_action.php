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

            iot_json_response(true, "Memicu tes bel untuk {$btn['name']}");
            break;

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
