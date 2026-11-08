<?php
/**
 * Kalkulator Waktu Sholat Standar Kementerian Agama Republik Indonesia (Kemenag RI)
 * Menggunakan hisab astronomi ephemeris (Jean Meeus / Hisab Kontemporer)
 * Dihitung offline (tanpa koneksi internet).
 */

class KemenagPrayer {
    /**
     * Menghitung jadwal sholat untuk timestamp dan koordinat tertentu
     *
     * @param int $timestamp Waktu Unix (timestamp) hari yang dihitung
     * @param float $lat Lintang tempat (Latitude) derajat desimal (contoh: -6.2088)
     * @param float $lon Bujur tempat (Longitude) derajat desimal (contoh: 106.8456)
     * @param float $elevation Ketinggian dpl dalam meter (contoh: 10)
     * @param float $tz Offset zona waktu dalam jam (7 untuk WIB, 8 untuk WITA, 9 untuk WIT)
     * @param array $tune Penyesuaian menit masing-masing sholat ['subuh' => 0, 'dzuhur' => 0, ...]
     * @return array Array asosiatif ['subuh' => '04:06', 'dzuhur' => '11:39', ...]
     */
    public static function getTimes($timestamp, $lat, $lon, $elevation = 10, $tz = 7, $tune = []) {
        $year = (int)date('Y', $timestamp);
        $month = (int)date('m', $timestamp);
        $day = (int)date('d', $timestamp);

        // 1. Hitung Julian Day
        $jd = self::julianDay($year, $month, $day);
        $d = $jd - 2451545.0; // Epoch J2000.0

        // 2. Posisi Matahari
        $g = fmod(357.529 + 0.98560028 * $d, 360);
        if ($g < 0) $g += 360;
        $q = fmod(280.459 + 0.98564736 * $d, 360);
        if ($q < 0) $q += 360;

        $g_rad = deg2rad($g);
        $L = fmod($q + 1.915 * sin($g_rad) + 0.020 * sin(2 * $g_rad), 360);
        if ($L < 0) $L += 360;
        $L_rad = deg2rad($L);

        $e = 23.439 - 0.00000036 * $d;
        $e_rad = deg2rad($e);

        // Deklinasi Matahari
        $sin_d = sin($e_rad) * sin($L_rad);
        $delta = rad2deg(asin($sin_d));
        $delta_rad = deg2rad($delta);

        // Right Ascension (RA) dalam jam
        $ra = rad2deg(atan2(cos($e_rad) * sin($L_rad), cos($L_rad))) / 15;
        if ($ra < 0) $ra += 24;

        // Equation of Time (EoT) dalam jam
        $eot = ($q / 15) - $ra;
        while ($eot > 12) $eot -= 24;
        while ($eot < -12) $eot += 24;

        // 3. Waktu Transit Matahari (Zawal / Solar Noon)
        $transit = 12 + $tz - ($lon / 15) - $eot;

        // Fungsi bantu hitung Sudut Waktu (Hour Angle)
        $lat_rad = deg2rad($lat);
        $calcHA = function($alt_deg) use ($lat_rad, $delta_rad) {
            $alt_rad = deg2rad($alt_deg);
            $cos_h = (sin($alt_rad) - sin($lat_rad) * sin($delta_rad)) / (cos($lat_rad) * cos($delta_rad));
            if ($cos_h > 1 || $cos_h < -1) return null;
            return rad2deg(acos($cos_h)) / 15;
        };

        // 4. Parameter Kemenag RI
        // - Subuh: -20 derajat
        // - Isya: -18 derajat
        // - Maghrib: -0.8333 derajat + koreksi dip elevasi tempat
        // Dip (penurunan ufuk) = 0.0347 * sqrt(ketinggian_meter)
        $dip = 0.0347 * sqrt(max(0, (float)$elevation));
        $sunset_alt = -(0.8333 + $dip);

        $ha_subuh = $calcHA(-20);
        $ha_isya = $calcHA(-18);
        $ha_maghrib = $calcHA($sunset_alt);

        // Ashar (Bayangan = 1 + bayangan saat istiwa / Mazhab Syafii standar Indonesia)
        $tan_ashar = 1 + tan(abs($lat_rad - $delta_rad));
        $alt_ashar = rad2deg(atan(1 / $tan_ashar));
        $ha_ashar = $calcHA($alt_ashar);

        // Ihtiyati (pengaman kehati-hatian) resmi Kemenag RI = +2 menit (+2/60 jam)
        $ihtiyati = 2 / 60;

        $t_subuh = ($transit - $ha_subuh) + $ihtiyati;
        $t_dzuhur = $transit + $ihtiyati;
        $t_ashar = ($transit + $ha_ashar) + $ihtiyati;
        $t_maghrib = ($transit + $ha_maghrib) + $ihtiyati;
        $t_isya = ($transit + $ha_isya) + $ihtiyati;

        // Terapkan penyesuaian (tuning menit) dari pengaturan jika ada
        $tune_subuh = (float)($tune['subuh'] ?? 0);
        $tune_dzuhur = (float)($tune['dzuhur'] ?? 0);
        $tune_ashar = (float)($tune['ashar'] ?? 0);
        $tune_maghrib = (float)($tune['maghrib'] ?? 0);
        $tune_isya = (float)($tune['isya'] ?? 0);

        return [
            'subuh'   => self::formatTime($t_subuh + ($tune_subuh / 60)),
            'dzuhur'  => self::formatTime($t_dzuhur + ($tune_dzuhur / 60)),
            'ashar'   => self::formatTime($t_ashar + ($tune_ashar / 60)),
            'maghrib' => self::formatTime($t_maghrib + ($tune_maghrib / 60)),
            'isya'    => self::formatTime($t_isya + ($tune_isya / 60)),
        ];
    }

    /**
     * Mengambil offset zona waktu (jam) berdasarkan nama timezone
     *
     * @param string $tzName Nama timezone (Asia/Jakarta, Asia/Makassar, Asia/Jayapura)
     * @return float Offset jam (7, 8, atau 9)
     */
    public static function getTimezoneOffsetHours($tzName = 'Asia/Jakarta') {
        try {
            $tz = new DateTimeZone($tzName);
            $dt = new DateTime('now', $tz);
            return $tz->getOffset($dt) / 3600;
        } catch (Exception $e) {
            return 7.0; // Default WIB
        }
    }

    /**
     * Menghitung nilai Julian Day
     */
    private static function julianDay($year, $month, $day) {
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        $A = (int)floor($year / 100);
        $B = 2 - $A + (int)floor($A / 4);
        return floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $B - 1524.5;
    }

    /**
     * Konversi jam desimal ke format HH:MM dengan pembulatan detik standar
     */
    private static function formatTime($decHours) {
        $decHours = fmod($decHours + 24, 24);
        $h = (int)floor($decHours);
        $m = (int)floor(($decHours - $h) * 60);
        $sec = ($decHours - $h) * 3600 - ($m * 60);
        if ($sec >= 30) {
            $m++;
        }
        if ($m >= 60) {
            $m = 0;
            $h = ($h + 1) % 24;
        }
        return sprintf('%02d:%02d', $h, $m);
    }
}

/**
 * Mengambil jadwal sholat hari ini berdasarkan data konfigurasi di database
 *
 * @param int|null $timestamp Waktu hari yang ingin dihitung (default sekarang)
 * @return array Jadwal sholat ['subuh' => '04:06', ...]
 */
function get_today_prayer_times($timestamp = null) {
    if ($timestamp === null) {
        $timestamp = time();
    }
    $lat = (float)get_setting('prayer_latitude', '-6.2088');
    $lon = (float)get_setting('prayer_longitude', '106.8456');
    $elev = (float)get_setting('prayer_elevation', '10');
    $tzName = get_setting('timezone', 'Asia/Jakarta');
    $tzOffset = KemenagPrayer::getTimezoneOffsetHours($tzName);

    $tune = [
        'subuh'   => (int)get_setting('prayer_tune_subuh', '0'),
        'dzuhur'  => (int)get_setting('prayer_tune_dzuhur', '0'),
        'ashar'   => (int)get_setting('prayer_tune_ashar', '0'),
        'maghrib' => (int)get_setting('prayer_tune_maghrib', '0'),
        'isya'    => (int)get_setting('prayer_tune_isya', '0'),
    ];

    return KemenagPrayer::getTimes($timestamp, $lat, $lon, $elev, $tzOffset, $tune);
}

/**
 * Menghitung jam efektif bel untuk jadwal sholat dengan offset menit
 * Contoh: target 'dzuhur', offset -5 -> jika dzuhur 11:39, hasil '11:34'
 *
 * @param string $prayerName 'subuh', 'dzuhur', 'ashar', 'maghrib', 'isya'
 * @param int $offsetMinutes Menit sebelum (-) atau sesudah (+) waktu sholat
 * @param int|null $timestamp Timestamp hari yang dihitung
 * @return string Waktu format 'HH:MM'
 */
function calculate_prayer_schedule_time($prayerName, $offsetMinutes = 0, $timestamp = null) {
    $times = get_today_prayer_times($timestamp);
    $prayerKey = strtolower(trim($prayerName));
    if (!isset($times[$prayerKey])) {
        return '12:00';
    }

    $baseTime = $times[$prayerKey]; // HH:MM
    list($h, $m) = explode(':', $baseTime);
    $totalMinutes = ((int)$h * 60) + (int)$m + (int)$offsetMinutes;

    // Normalisasi dalam 24 jam (1440 menit)
    $totalMinutes = ($totalMinutes % 1440 + 1440) % 1440;
    $resH = (int)floor($totalMinutes / 60);
    $resM = $totalMinutes % 60;

    return sprintf('%02d:%02d', $resH, $resM);
}

