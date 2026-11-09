<?php
/**
 * API Endpoint: IoT Bell Trigger
 * Menerima request HTTP GET atau POST dari perangkat ESP32 / ESP8266
 * Format:
 *   GET /api/iot_trigger.php?button=1
 *   POST /api/iot_trigger.php (dengan body button=1)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system.php';

$button_num = (int)($_REQUEST['button'] ?? $_REQUEST['btn'] ?? $_REQUEST['id'] ?? 0);

if ($button_num < 1 || $button_num > 12) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nomor tombol tidak valid. Gunakan button=1 sampai 12.'
    ]);
    exit;
}

try {
    $db = get_db_connection();

    // Ambil konfigurasi tombol
    $stmt = $db->prepare("SELECT * FROM iot_buttons WHERE button_number = ?");
    $stmt->execute([$button_num]);
    $btn = $stmt->fetch();

    if (!$btn) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => "Konfigurasi Tombol {$button_num} tidak ditemukan di database"
        ]);
        exit;
    }

    if (!$btn['is_active']) {
        echo json_encode([
            'success' => false,
            'message' => "Tombol {$button_num} ({$btn['name']}) sedang dinonaktifkan."
        ]);
        exit;
    }

    // Perbarui waktu terakhir tombol dipicu
    $db->prepare("UPDATE iot_buttons SET last_triggered = CURRENT_TIMESTAMP WHERE button_number = ?")->execute([$button_num]);

    // Rangkai urutan file audio: Intro -> Utama -> Outro
    $sequence = array_values(array_filter([$btn['audio_intro'], $btn['audio_file'], $btn['audio_outro']]));
    $seq_str = implode('|', $sequence);
    $label = "IoT: " . $btn['name'];

    // Masukkan ke antrian audio
    $queue_id = queue_audio_play($seq_str, $label, 'iot');

    // Jika worker background tidak berjalan, jalankan pemutaran langsung
    if (!is_worker_running()) {
        foreach ($sequence as $audio_item) {
            play_audio_direct($audio_item);
            usleep(300000);
        }
    }

    // Periksa apakah ada forwarding STB Sekunder (Multi-STB Lantai 1 & Lantai 2)
    $forward_ip = trim(get_setting('iot_secondary_stb_ip', ''));
    $forward_status = null;
    $is_forwarded = isset($_REQUEST['forwarded']);

    if (!empty($forward_ip) && !$is_forwarded) {
        // Tembak STB sekunder di background secara asynchronous / timeout singkat (1 detik)
        $target_url = "http://{$forward_ip}/api/iot_trigger.php?button={$button_num}&forwarded=1";
        if (function_exists('curl_init')) {
            $ch = curl_init($target_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            @curl_exec($ch);
            curl_close($ch);
            $forward_status = "Forwarded to {$forward_ip}";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Tombol {$button_num} berhasil memicu bel: {$btn['name']}",
        'button' => $button_num,
        'name' => $btn['name'],
        'audio_sequence' => $sequence,
        'queue_id' => $queue_id,
        'multi_stb' => $forward_status
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
    ]);
}
