<?php
/**
 * System Hardware & OS Integration Helper
 * Mengendalikan kontrol hardware Linux STB (Volume ALSA, Jam Sistem, WiFi NetworkManager, Pemutar Audio).
 * Dilengkapi fallback aman jika dijalankan pada lingkungan pengujian/Windows.
 */

require_once __DIR__ . '/db.php';

/**
 * Mengecek apakah aplikasi sedang berjalan di lingkungan Linux
 *
 * @return bool True jika Linux, False jika Windows/lainnya
 */
function is_linux_os() {
    return (stripos(PHP_OS, 'WIN') === false);
}

/**
 * Membaca volume master hardware audio Linux saat ini via amixer
 *
 * @return int Nilai persentase volume 0 - 100
 */
function get_system_volume() {
    if (!is_linux_os()) {
        // Fallback untuk pengujian lokal di Windows: ambil dari database
        return (int)get_setting('volume', 80);
    }

    $card = escapeshellarg(get_setting('audio_device', 'default'));
    $output = @shell_exec("amixer -D {$card} sget Master 2>/dev/null || amixer sget Master 2>/dev/null");
    if ($output && preg_match('/\[(\d+)%\]/', $output, $matches)) {
        return (int)$matches[1];
    }
    return (int)get_setting('volume', 80);
}

/**
 * Mengatur volume master hardware audio Linux menggunakan perintah amixer ALSA
 *
 * @param int $percentage Persentase volume yang diinginkan (0 - 100)
 * @return bool Berhasil atau gagal dieksekusi
 */
function set_system_volume($percentage) {
    $percentage = max(0, min(100, (int)$percentage));
    set_setting('volume', (string)$percentage);

    if (!is_linux_os()) {
        return true; // Sukses secara logis di Windows
    }

    $card = escapeshellarg(get_setting('audio_device', 'default'));
    // Eksekusi amixer dengan sudo (sesuai aturan sudoers www-data)
    $cmd = "sudo amixer -D {$card} -q sset Master {$percentage}% 2>/dev/null || sudo amixer -q sset Master {$percentage}% 2>/dev/null";
    @shell_exec($cmd);
    return true;
}

/**
 * Mengambil informasi waktu, tanggal, dan status NTP dari sistem
 *
 * @return array Informasi waktu lengkap (jam, tanggal, timezone, status NTP)
 */
function get_system_time_info() {
    $tz = get_setting('timezone', 'Asia/Jakarta');
    date_default_timezone_set($tz);

    $info = [
        'current_time' => date('H:i:s'),
        'current_date' => date('Y-m-d'),
        'date_formatted' => date('d F Y'),
        'day_name' => get_indonesian_day(date('N')),
        'day_number' => (int)date('N'), // 1 = Senin .. 7 = Minggu
        'timezone' => $tz,
        'ntp_active' => false,
        'is_linux' => is_linux_os()
    ];

    if (is_linux_os()) {
        $timedate_output = @shell_exec('timedatectl status 2>/dev/null');
        if ($timedate_output) {
            $info['ntp_active'] = (stripos($timedate_output, 'NTP service: active') !== false ||
                                   stripos($timedate_output, 'System clock synchronized: yes') !== false);
        }
    } else {
        $info['ntp_active'] = (bool)get_setting('auto_ntp', 1);
    }

    return $info;
}

/**
 * Mengubah jam dan tanggal sistem Linux secara manual atau sinkronisasi dari HP
 *
 * @param string $datetime_str String tanggal dan waktu dalam format 'YYYY-MM-DD HH:MM:SS'
 * @return array Hasil status eksekusi ['success' => bool, 'message' => string]
 */
function set_system_datetime($datetime_str) {
    $timestamp = strtotime($datetime_str);
    if ($timestamp === false) {
        return ['success' => false, 'message' => 'Format waktu tidak valid'];
    }

    $formatted = date('Y-m-d H:i:s', $timestamp);

    if (!is_linux_os()) {
        return ['success' => true, 'message' => "Simulasi berhasil disetel ke: {$formatted} (Mode Windows)"];
    }

    // Set waktu di Linux dengan date dan tulis ke RTC hardware clock (jika ada)
    $safe_date = escapeshellarg($formatted);
    $cmd = "sudo date -s {$safe_date} && sudo hwclock -w 2>/dev/null";
    $output = @shell_exec($cmd);

    return [
        'success' => true,
        'message' => "Jam STB berhasil disinkronkan ke: {$formatted}",
        'output' => $output
    ];
}

/**
 * Memindai (scan) daftar jaringan WiFi yang tertangkap oleh STB Linux
 *
 * @return array Daftar SSID, kekuatan sinyal (%), keamanan, dan status terkoneksi
 */
function scan_available_wifi() {
    if (!is_linux_os()) {
        // Mock data untuk pengujian lokal
        return [
            ['ssid' => 'WIFI_SEKOLAH_UTAMA', 'signal' => 92, 'security' => 'WPA2', 'in_use' => true],
            ['ssid' => 'KANTOR_GURU_5G', 'signal' => 78, 'security' => 'WPA2', 'in_use' => false],
            ['ssid' => 'LAB_KOMPUTER', 'signal' => 54, 'security' => 'WPA2', 'in_use' => false],
            ['ssid' => 'BEL_HOTSPOT_DEFAULT', 'signal' => 100, 'security' => 'OPEN', 'in_use' => false]
        ];
    }

    // Menggunakan nmcli pada Linux untuk memindai WiFi
    $output = @shell_exec("sudo nmcli -t -f IN-USE,SSID,SIGNAL,SECURITY dev wifi list --rescan yes 2>/dev/null");
    $networks = [];
    $seen = [];

    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) >= 4) {
                $in_use = trim($parts[0]) === '*';
                $ssid = trim($parts[1]);
                $signal = (int)trim($parts[2]);
                $security = trim($parts[3]);

                // Lewati SSID kosong (hidden networks) atau yang sudah terdaftar
                if ($ssid === '' || isset($seen[$ssid])) {
                    continue;
                }
                $seen[$ssid] = true;

                $networks[] = [
                    'ssid' => $ssid,
                    'signal' => $signal,
                    'security' => !empty($security) ? $security : 'OPEN',
                    'in_use' => $in_use
                ];
            }
        }
    }

    // Urutkan berdasarkan sinyal terkuat
    usort($networks, function($a, $b) {
        return $b['signal'] <=> $a['signal'];
    });

    return $networks;
}

/**
 * Menyambungkan STB Linux ke jaringan WiFi tertentu via nmcli
 *
 * @param string $ssid Nama SSID WiFi
 * @param string $password Kata sandi WiFi (kosong jika jaringan terbuka)
 * @return array Hasil eksekusi koneksi
 */
function connect_to_wifi($ssid, $password = '') {
    $ssid = trim($ssid);
    if (empty($ssid)) {
        return ['success' => false, 'message' => 'SSID tidak boleh kosong'];
    }

    if (!is_linux_os()) {
        return ['success' => true, 'message' => "Simulasi berhasil terhubung ke {$ssid} (Mode Windows)"];
    }

    $safe_ssid = escapeshellarg($ssid);
    if (!empty($password)) {
        $safe_pass = escapeshellarg($password);
        $cmd = "sudo nmcli dev wifi connect {$safe_ssid} password {$safe_pass} 2>&1";
    } else {
        $cmd = "sudo nmcli dev wifi connect {$safe_ssid} 2>&1";
    }

    $output = @shell_exec($cmd);
    $is_success = (stripos($output, 'successfully activated') !== false || stripos($output, 'connection profile') !== false);

    return [
        'success' => $is_success,
        'message' => $is_success ? "Berhasil terhubung ke WiFi {$ssid}" : "Gagal menyambung: " . trim($output)
    ];
}

/**
 * Mendapatkan informasi IP Address dan interface jaringan STB yang aktif
 *
 * @return array Daftar interface (WLAN/ETH), IP lokal, dan status internet
 */
function get_network_info() {
    $info = [
        'ip_address' => '127.0.0.1',
        'interface' => 'Localhost',
        'connected_ssid' => '-',
        'has_internet' => false
    ];

    if (is_linux_os()) {
        // Ambil IP utama
        $ip_cmd = @shell_exec("hostname -I | awk '{print $1}'");
        if ($ip_cmd) {
            $info['ip_address'] = trim($ip_cmd);
        }

        // Ambil SSID WiFi yang sedang aktif
        $ssid_cmd = @shell_exec("nmcli -t -f active,ssid dev wifi | grep '^yes' | cut -d: -f2");
        if ($ssid_cmd) {
            $info['connected_ssid'] = trim($ssid_cmd);
        }

        // Uji koneksi internet singkat
        $ping = @shell_exec("ping -c 1 -W 1 1.1.1.1 2>/dev/null");
        $info['has_internet'] = ($ping && stripos($ping, '1 received') !== false);
    } else {
        $info['ip_address'] = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        $info['connected_ssid'] = 'Mode Pengujian (Windows)';
        $info['has_internet'] = true;
    }

    return $info;
}

/**
 * Mengecek apakah background worker (scheduler daemon) sedang berjalan aktif
 *
 * @return bool True jika service/proses scheduler aktif
 */
function is_worker_running() {
    if (!is_linux_os()) {
        // Di Windows, cek apakah ada file lock atau status aktif
        return file_exists(__DIR__ . '/../database/worker_alive.txt');
    }

    // Cek systemd status atau pgrep process
    $status = @shell_exec("pgrep -f 'service/scheduler.php' 2>/dev/null");
    return !empty(trim($status));
}

/**
 * Memutar file audio langsung ke hardware audio Linux di background
 *
 * @param string $audio_file_name Nama file di direktori uploads/audio/
 * @return bool Status apakah perintah pemutaran berhasil diluncurkan
 */
function play_audio_direct($audio_file_name) {
    $filepath = __DIR__ . '/../uploads/audio/' . basename($audio_file_name);
    if (!file_exists($filepath)) {
        return false;
    }

    $safe_path = escapeshellarg($filepath);

    if (is_linux_os()) {
        // Gunakan mpg123, mpv, atau aplay sesuai ketersediaan tools audio
        $cmd = "mpg123 -q {$safe_path} >/dev/null 2>&1 &";
        if (substr($filepath, -4) === '.wav') {
            $cmd = "(aplay -q {$safe_path} || mpg123 -q {$safe_path}) >/dev/null 2>&1 &";
        }
        @shell_exec($cmd);
        return true;
    } else {
        // Windows fallback: putar via powershell System.Media.SoundPlayer secara async
        $psCmd = "powershell -c \"(New-Object Media.SoundPlayer '{$filepath}').PlaySync()\"";
        pclose(popen("start /B " . $psCmd, "r"));
        return true;
    }
}

/**
 * Mengubah index hari (1-7) menjadi nama hari dalam bahasa Indonesia
 *
 * @param int $dayNum Nomor hari (1=Senin, 7=Minggu)
 * @return string Nama hari dalam bahasa Indonesia
 */
function get_indonesian_day($dayNum) {
    $days = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    return $days[(int)$dayNum] ?? 'Hari Tidak Diketahui';
}
