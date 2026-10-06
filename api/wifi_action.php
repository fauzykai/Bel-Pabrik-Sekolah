<?php
/**
 * API Action Controller - WiFi Manager
 * Melayani pemindaian jaringan WiFi sekitar dan menyambungkan STB ke hotspot/router.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Mengirimkan respon JSON terstruktur dan mengakhiri script
 *
 * @param bool $success Status keberhasilan
 * @param string $message Pesan informasi
 * @param array $data Data tambahan opsional
 */
function wifi_json_response($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

try {
    switch ($action) {
        case 'scan':
            // Memindai jaringan WiFi di sekitar STB
            $networks = scan_available_wifi();
            wifi_json_response(true, 'Pemindaian WiFi selesai', ['networks' => $networks]);
            break;

        case 'connect':
            // Menyambungkan STB ke SSID WiFi yang dipilih
            $ssid = trim($_POST['ssid'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($ssid)) {
                wifi_json_response(false, 'Nama SSID harus diisi');
            }

            $res = connect_to_wifi($ssid, $password);
            wifi_json_response($res['success'], $res['message']);
            break;

        case 'status':
            // Mengambil status WiFi saat ini
            $info = get_network_info();
            wifi_json_response(true, 'Status jaringan', ['network' => $info]);
            break;

        default:
            wifi_json_response(false, 'Aksi WiFi tidak valid');
            break;
    }
} catch (Exception $e) {
    wifi_json_response(false, 'Kesalahan: ' . $e->getMessage());
}
