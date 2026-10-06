<?php
/**
 * API Action Controller - Manajemen Jadwal
 * Melayani penambahan, pengeditan, penghapusan, dan toggle aktif/nonaktif jadwal bel.
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
function schedule_json_response($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

try {
    $db = get_db_connection();

    switch ($action) {
        case 'toggle_status':
            // Mengubah status aktif/nonaktif jadwal
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT is_active FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if (!$row) {
                schedule_json_response(false, 'Jadwal tidak ditemukan');
            }

            $new_status = $row['is_active'] ? 0 : 1;
            $db->prepare("UPDATE schedules SET is_active = ? WHERE id = ?")->execute([$new_status, $id]);

            schedule_json_response(true, 'Status jadwal diperbarui', ['is_active' => $new_status]);
            break;

        case 'save':
            // Menyimpan jadwal baru atau memperbarui jadwal yang sudah ada (termasuk Intro, Utama, dan Outro)
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $time = trim($_POST['time'] ?? '');
            $days = $_POST['days'] ?? [];
            $audio_intro = trim($_POST['audio_intro'] ?? '');
            $audio_file = trim($_POST['audio_file'] ?? ''); // Audio Utama
            $audio_outro = trim($_POST['audio_outro'] ?? '');
            $category = trim($_POST['category'] ?? 'reguler');

            if (empty($name) || empty($time) || empty($audio_file)) {
                schedule_json_response(false, 'Nama, Waktu, dan File Audio Utama wajib diisi');
            }

            // Normalisasi hari (jika array, gabungkan jadi string koma)
            if (is_array($days)) {
                $days_str = implode(',', $days);
            } else {
                $days_str = trim($days);
            }
            if (empty($days_str)) {
                $days_str = '1,2,3,4,5'; // Default Senin-Jumat
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE schedules SET name = ?, time = ?, days = ?, audio_intro = ?, audio_file = ?, audio_outro = ?, category = ? WHERE id = ?");
                $stmt->execute([$name, $time, $days_str, $audio_intro, $audio_file, $audio_outro, $category, $id]);
                $msg = 'Jadwal berhasil diperbarui';
            } else {
                $stmt = $db->prepare("INSERT INTO schedules (name, time, days, audio_intro, audio_file, audio_outro, category, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$name, $time, $days_str, $audio_intro, $audio_file, $audio_outro, $category]);
                $id = (int)$db->lastInsertId();
                $msg = 'Jadwal baru berhasil ditambahkan';
            }

            schedule_json_response(true, $msg, ['id' => $id]);
            break;

        case 'test_schedule':
            // Memicu tes pemutaran berurutan: Intro -> Utama -> Outro
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            $sch = $stmt->fetch();
            if (!$sch) {
                schedule_json_response(false, 'Jadwal tidak ditemukan');
            }

            $sequence = array_values(array_filter([$sch['audio_intro'], $sch['audio_file'], $sch['audio_outro']]));
            $seq_str = implode('|', $sequence);
            queue_audio_play($seq_str, 'Tes Jadwal: ' . $sch['name'], 'test');

            if (!is_worker_running()) {
                foreach ($sequence as $f) {
                    play_audio_direct($f);
                    usleep(300000);
                }
            }
            schedule_json_response(true, 'Memutar rangkaian bel: ' . $sch['name']);
            break;

        case 'delete':
            // Menghapus jadwal
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("DELETE FROM schedules WHERE id = ?")->execute([$id]);
            schedule_json_response(true, 'Jadwal berhasil dihapus');
            break;

        default:
            schedule_json_response(false, 'Aksi jadwal tidak valid');
            break;
    }
} catch (Exception $e) {
    schedule_json_response(false, 'Kesalahan: ' . $e->getMessage());
}
