<?php
/**
 * Halaman Pengaturan Sistem, Jam, Audio & Pemeliharaan
 * Pusat kontrol hardware, sinkronisasi jam, volume, dan pemeliharaan STB.
 */

$page_title = 'Pengaturan Sistem - Bel STB';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/prayer.php';

$db = get_db_connection();
$timezone = get_setting('timezone', 'Asia/Jakarta');
$audio_device = get_setting('audio_device', 'default');

// Pengaturan Jadwal Sholat
$prayer_lat = get_setting('prayer_latitude', '-6.2088');
$prayer_lon = get_setting('prayer_longitude', '106.8456');
$prayer_elev = get_setting('prayer_elevation', '10');
$prayer_tune_subuh = (int)get_setting('prayer_tune_subuh', '0');
$prayer_tune_dzuhur = (int)get_setting('prayer_tune_dzuhur', '0');
$prayer_tune_ashar = (int)get_setting('prayer_tune_ashar', '0');
$prayer_tune_maghrib = (int)get_setting('prayer_tune_maghrib', '0');
$prayer_tune_isya = (int)get_setting('prayer_tune_isya', '0');

$today_prayers = get_today_prayer_times();

// Ambil riwayat log bel terakhir
$logs = $db->query("SELECT * FROM play_logs ORDER BY played_at DESC LIMIT 10")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Pengaturan & Sistem</h5>
        <span class="text-secondary small">Konfigurasi jam, waktu sholat, audio hardware, dan status STB</span>
    </div>
</div>

<!-- Nav Tab Pengaturan -->
<ul class="nav nav-pills mb-3 gap-1" id="settingsTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active btn-sm" id="tab-system-btn" data-bs-toggle="pill" data-bs-target="#tab-system" type="button" role="tab">
            <i class="bi bi-sliders me-1"></i> Sistem & Hardware
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link btn-sm" id="tab-prayer-btn" data-bs-toggle="pill" data-bs-target="#tab-prayer" type="button" role="tab">
            <i class="bi bi-moon-stars me-1"></i> Pengaturan Jam Sholat
        </button>
    </li>
</ul>

<div class="tab-content" id="settingsTabContent">
    <!-- TAB 1: SISTEM & HARDWARE -->
    <div class="tab-pane fade show active" id="tab-system" role="tabpanel">
        <!-- Bagian 1: Pengaturan Jam & Waktu -->
        <div class="card mb-3 bg-white">
    <div class="card-header bg-transparent py-2">
        <span class="fw-semibold text-secondary small text-uppercase">
            <i class="bi bi-clock me-1"></i> Pengaturan Waktu & Tanggal
        </span>
    </div>
    <div class="card-body p-3">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-12 col-sm-6">
                <span class="text-secondary small d-block">Jam STB Saat Ini:</span>
                <span class="fs-4 fw-bold font-monospace text-primary" id="settingsLiveClock">
                    <?= htmlspecialchars($time_info['current_time']) ?>
                </span>
                <div class="text-muted small">
                    <?= htmlspecialchars($time_info['day_name']) ?>, <?= htmlspecialchars($time_info['date_formatted']) ?>
                </div>
            </div>
            <div class="col-12 col-sm-6 d-flex flex-column gap-2">
                <button type="button" class="btn btn-outline-primary w-100" onclick="syncTimeFromDevice()">
                    <i class="bi bi-phone me-1"></i> Sinkronkan Jam dengan HP Anda
                </button>
                <div class="text-muted" style="font-size: 0.75rem;">
                    *Gunakan tombol ini jika STB baru menyala setelah mati lampu tanpa koneksi internet.
                </div>
            </div>
        </div>

        <hr class="my-2 text-muted">

        <form id="formTimezone" onsubmit="saveTimezone(event)" class="row g-2 align-items-end pt-2">
            <div class="col-12 col-sm-8">
                <label class="form-label small fw-semibold">Zona Waktu (Timezone)</label>
                <select class="form-select form-select-sm" id="inpTimezone" name="timezone">
                    <option value="Asia/Jakarta" <?= $timezone === 'Asia/Jakarta' ? 'selected' : '' ?>>WIB - Waktu Indonesia Barat (Jakarta)</option>
                    <option value="Asia/Makassar" <?= $timezone === 'Asia/Makassar' ? 'selected' : '' ?>>WITA - Waktu Indonesia Tengah (Makassar/Bali)</option>
                    <option value="Asia/Jayapura" <?= $timezone === 'Asia/Jayapura' ? 'selected' : '' ?>>WIT - Waktu Indonesia Timur (Jayapura/Papua)</option>
                </select>
            </div>
            <div class="col-12 col-sm-4">
                <button type="submit" class="btn btn-sm btn-dark w-100">Simpan Zona Waktu</button>
            </div>
        </form>
    </div>
</div>

<!-- Bagian 2: Pengaturan Audio Hardware -->
<div class="card mb-3 bg-white">
    <div class="card-header bg-transparent py-2">
        <span class="fw-semibold text-secondary small text-uppercase">
            <i class="bi bi-volume-up me-1"></i> Audio & Volume Hardware
        </span>
    </div>
    <div class="card-body p-3">
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-semibold mb-0">Master Volume STB</label>
                <span class="fw-bold text-primary" id="volDisplaySettings"><?= $current_vol ?>%</span>
            </div>
            <input type="range" class="form-range" min="0" max="100" step="5" value="<?= $current_vol ?>" oninput="updateSettingsVolume(this.value)">
        </div>

        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="text-muted small">Uji keluaran suara ke speaker fisik STB:</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="triggerManualBell('chime-pendek.wav', 'Tes Speaker')">
                <i class="bi bi-speaker me-1"></i> Tes Suara Sekarang
            </button>
        </div>
    </div>
</div>

<!-- Bagian 3: Kontrol Sistem & Pemeliharaan -->
<div class="card mb-3 bg-white">
    <div class="card-header bg-transparent py-2">
        <span class="fw-semibold text-secondary small text-uppercase">
            <i class="bi bi-cpu me-1"></i> Kontrol Sistem STB
        </span>
    </div>
    <div class="card-body p-3">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <div>
                <span class="fw-semibold d-block text-dark">Status Layanan Pemutar Bel</span>
                <span class="small <?= $worker_active ? 'text-success' : 'text-danger' ?>">
                    <i class="bi <?= $worker_active ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                    <?= $worker_active ? 'Layanan berjalan normal (Scheduler Active)' : 'Layanan tidak terdeteksi aktif' ?>
                </span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="rebootStbPrompt()">
                    <i class="bi bi-power me-1"></i> Reboot STB
                </button>
            </div>
        </div>

        <div class="alert alert-light border small mt-3 mb-0">
            <strong>Tips Penggunaan Headless STB:</strong>
            <ul class="mb-0 ps-3 mt-1">
                <li>Pastikan kabel audio 3.5mm dari STB sudah dicolokkan ke input AUX amplifier pabrik/sekolah.</li>
                <li>Simpan alamat <code>http://bel.local</code> di bookmark browser smartphone Anda.</li>
            </ul>
        </div>
    </div>
</div>

<!-- Bagian 4: Riwayat Log Pemutaran Terakhir -->
<div class="card bg-white">
    <div class="card-header bg-transparent py-2">
        <span class="fw-semibold text-secondary small text-uppercase">
            <i class="bi bi-clock-history me-1"></i> Riwayat 10 Bel Terakhir
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="p-3 text-center text-muted small">Belum ada riwayat pemutaran bel.</div>
        <?php else: ?>
            <div class="list-group list-group-flush small">
                <?php foreach ($logs as $log): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <div>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($log['label']) ?></span>
                            <span class="text-muted d-block" style="font-size: 0.7rem;">
                                File: <?= htmlspecialchars($log['audio_file']) ?> • Pemicu: <?= htmlspecialchars($log['triggered_by']) ?>
                            </span>
                        </div>
                        <span class="badge bg-light text-secondary border font-monospace">
                            <?= htmlspecialchars($log['played_at']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
    </div> <!-- END TAB 1 -->

    <!-- TAB 2: PENGATURAN JAM SHOLAT (METODE KEMENAG RI) -->
    <div class="tab-pane fade" id="tab-prayer" role="tabpanel">
        <form id="formPrayerSettings" onsubmit="savePrayerSettings(event)">
            <!-- Widget Jadwal Hari Ini Berdasarkan Perhitungan Kemenag -->
            <div class="card mb-3 bg-white border-primary">
                <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold small text-uppercase">
                        <i class="bi bi-clock-history me-1"></i> Jadwal Sholat Hari Ini (Standar Kemenag)
                    </span>
                    <span class="badge bg-white text-primary font-monospace" style="font-size: 0.7rem;">Metode Kemenag RI (+2m Ihtiyat)</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 text-center" id="prayerDisplayContainer">
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-secondary small text-uppercase fw-semibold" style="font-size: 0.7rem;">Subuh</span>
                                <span class="fs-5 fw-bold text-dark font-monospace" id="prev_subuh"><?= htmlspecialchars($today_prayers['subuh']) ?></span>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-primary small text-uppercase fw-semibold" style="font-size: 0.7rem;">Dzuhur</span>
                                <span class="fs-5 fw-bold text-primary font-monospace" id="prev_dzuhur"><?= htmlspecialchars($today_prayers['dzuhur']) ?></span>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-secondary small text-uppercase fw-semibold" style="font-size: 0.7rem;">Ashar</span>
                                <span class="fs-5 fw-bold text-dark font-monospace" id="prev_ashar"><?= htmlspecialchars($today_prayers['ashar']) ?></span>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-secondary small text-uppercase fw-semibold" style="font-size: 0.7rem;">Maghrib</span>
                                <span class="fs-5 fw-bold text-dark font-monospace" id="prev_maghrib"><?= htmlspecialchars($today_prayers['maghrib']) ?></span>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-secondary small text-uppercase fw-semibold" style="font-size: 0.7rem;">Isya</span>
                                <span class="fs-5 fw-bold text-dark font-monospace" id="prev_isya"><?= htmlspecialchars($today_prayers['isya']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small mt-2" style="font-size: 0.75rem;">
                        <i class="bi bi-info-circle me-1"></i> Perhitungan otomatis offline setiap hari menggunakan hisab astronomi Kemenag (Subuh -20°, Isya -18°, ihtiyati +2 menit).
                    </div>
                </div>
            </div>

            <!-- Koordinat & Lokasi -->
            <div class="card mb-3 bg-white">
                <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-secondary small text-uppercase">
                        <i class="bi bi-geo-alt me-1"></i> Lokasi & Elevasi
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="detectGpsLocation()">
                        <i class="bi bi-crosshair me-1"></i> Ambil GPS HP
                    </button>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Latitude (Garis Lintang)</label>
                            <input type="number" step="0.000001" class="form-control form-control-sm font-monospace" name="latitude" id="inpPrayerLat" value="<?= htmlspecialchars($prayer_lat) ?>" required>
                            <span class="text-muted" style="font-size: 0.7rem;">Contoh: -6.2088 (Jakarta)</span>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Longitude (Garis Bujur)</label>
                            <input type="number" step="0.000001" class="form-control form-control-sm font-monospace" name="longitude" id="inpPrayerLon" value="<?= htmlspecialchars($prayer_lon) ?>" required>
                            <span class="text-muted" style="font-size: 0.7rem;">Contoh: 106.8456 (Jakarta)</span>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Elevasi / Ketinggian (meter dpl)</label>
                            <input type="number" step="1" class="form-control form-control-sm font-monospace" name="elevation" id="inpPrayerElev" value="<?= htmlspecialchars($prayer_elev) ?>" required>
                            <span class="text-muted" style="font-size: 0.7rem;">Ketinggian tanah dpl</span>
                        </div>
                    </div>

                    <!-- Preset Kota Cepat -->
                    <div class="d-flex align-items-center gap-1 flex-wrap pt-1">
                        <span class="text-muted small" style="font-size: 0.75rem;">Preset Cepat:</span>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.75rem;" onclick="setCityPreset(-6.2088, 106.8456, 10)">Jakarta</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.75rem;" onclick="setCityPreset(-6.9175, 107.6191, 768)">Bandung</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.75rem;" onclick="setCityPreset(-6.9932, 110.4203, 15)">Semarang</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.75rem;" onclick="setCityPreset(-7.7956, 110.3695, 113)">Yogyakarta</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.75rem;" onclick="setCityPreset(-7.2575, 112.7521, 5)">Surabaya</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.75rem;" onclick="setCityPreset(-5.1477, 119.4327, 10)">Makassar</button>
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.75rem;" onclick="setCityPreset(-2.5489, 140.7181, 10)">Jayapura</button>
                    </div>
                </div>
            </div>

            <!-- Tuning Menit (+/- Tiap Waktu Sholat) -->
            <div class="card mb-3 bg-white">
                <div class="card-header bg-transparent py-2">
                    <span class="fw-semibold text-secondary small text-uppercase">
                        <i class="bi bi-sliders2 me-1"></i> Tuning Menit (+/- Koreksi Tiap Waktu Sholat)
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="text-muted small mb-3">
                        Gunakan tuning ini jika ingin menyamakan persis dengan jadwal masjid setempat (+/- menit):
                    </div>
                    <div class="row g-2">
                        <div class="col">
                            <label class="form-label small fw-semibold text-center d-block">Subuh</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control text-center font-monospace" name="tune_subuh" value="<?= $prayer_tune_subuh ?>" step="1">
                                <span class="input-group-text">m</span>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label small fw-semibold text-center d-block text-primary">Dzuhur</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control text-center font-monospace border-primary" name="tune_dzuhur" value="<?= $prayer_tune_dzuhur ?>" step="1">
                                <span class="input-group-text">m</span>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label small fw-semibold text-center d-block">Ashar</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control text-center font-monospace" name="tune_ashar" value="<?= $prayer_tune_ashar ?>" step="1">
                                <span class="input-group-text">m</span>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label small fw-semibold text-center d-block">Maghrib</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control text-center font-monospace" name="tune_maghrib" value="<?= $prayer_tune_maghrib ?>" step="1">
                                <span class="input-group-text">m</span>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label small fw-semibold text-center d-block">Isya</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control text-center font-monospace" name="tune_isya" value="<?= $prayer_tune_isya ?>" step="1">
                                <span class="input-group-text">m</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tombol Simpan -->
            <button type="submit" class="btn btn-primary w-100 py-2 mb-3" id="btnSavePrayer">
                <i class="bi bi-check-lg me-1"></i> Simpan Pengaturan Jam Sholat
            </button>
        </form>
    </div> <!-- END TAB 2 -->
</div>

<script>
/**
 * Memperbarui slider volume dan mengirim data ke backend
 *
 * @param {number} val Nilai volume
 */
function updateSettingsVolume(val) {
    document.getElementById('volDisplaySettings').textContent = val + '%';
    changeVolume(val);
}

/**
 * Menyimpan pilihan zona waktu baru
 *
 * @param {Event} e Submit event
 */
function saveTimezone(e) {
    e.preventDefault();
    const tz = document.getElementById('inpTimezone').value;

    fetch('api/system_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'set_timezone',
            timezone: tz
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            setTimeout(() => location.reload(), 1000);
        }
    });
}

/**
 * Konfirmasi sebelum melakukan reboot STB
 */
function rebootStbPrompt() {
    if (!confirm('Yakin ingin merestart (reboot) perangkat STB sekarang? STB akan offline sementara selama 1-2 menit.')) return;

    fetch('api/system_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'reboot_stb' })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
    })
    .catch(err => {
        showToast('Perintah reboot terkirim', true);
    });
}
/**
 * Mengisi nilai koordinat & elevasi cepat berdasarkan kota preset
 *
 * @param {number} lat Latitude
 * @param {number} lon Longitude
 * @param {number} elev Elevasi meter dpl
 */
function setCityPreset(lat, lon, elev) {
    document.getElementById('inpPrayerLat').value = lat;
    document.getElementById('inpPrayerLon').value = lon;
    document.getElementById('inpPrayerElev').value = elev;
    showToast('Koordinat preset disetel. Klik "Simpan Pengaturan" untuk menerapkan.', true);
}

/**
 * Mendeteksi lokasi GPS melalui browser smartphone/laptop secara langsung
 */
function detectGpsLocation() {
    if (!navigator.geolocation) {
        showToast('Browser Anda tidak mendukung deteksi lokasi Geolocation', false);
        return;
    }

    showToast('Mencari koordinat GPS perangkat...', true);
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude.toFixed(6);
            const lon = pos.coords.longitude.toFixed(6);
            const alt = pos.coords.altitude ? Math.round(pos.coords.altitude) : 10;
            document.getElementById('inpPrayerLat').value = lat;
            document.getElementById('inpPrayerLon').value = lon;
            document.getElementById('inpPrayerElev').value = alt;
            showToast(`GPS terdeteksi: Lat ${lat}, Lon ${lon}`, true);
        },
        (err) => {
            showToast('Gagal mengambil GPS: ' + err.message, false);
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

/**
 * Menyimpan pengaturan koordinat, elevasi, dan tuning jam sholat Kemenag
 *
 * @param {Event} e Submit event
 */
function savePrayerSettings(e) {
    e.preventDefault();
    const form = document.getElementById('formPrayerSettings');
    const formData = new FormData(form);
    formData.append('action', 'save_prayer_settings');

    const btn = document.getElementById('btnSavePrayer');
    btn.disabled = true;

    fetch('api/system_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success && data.prayer_times) {
            // Perbarui preview tampilan kotak waktu sholat secara realtime tanpa refresh
            if (data.prayer_times.subuh) document.getElementById('prev_subuh').textContent = data.prayer_times.subuh;
            if (data.prayer_times.dzuhur) document.getElementById('prev_dzuhur').textContent = data.prayer_times.dzuhur;
            if (data.prayer_times.ashar) document.getElementById('prev_ashar').textContent = data.prayer_times.ashar;
            if (data.prayer_times.maghrib) document.getElementById('prev_maghrib').textContent = data.prayer_times.maghrib;
            if (data.prayer_times.isya) document.getElementById('prev_isya').textContent = data.prayer_times.isya;
        }
    })
    .catch(err => {
        showToast('Gagal menyimpan pengaturan sholat: ' + err, false);
    })
    .finally(() => {
        btn.disabled = false;
    });
}

// Buka tab sholat otomatis jika terdapat parameter URL #tab-prayer
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#prayer' || window.location.hash === '#tab-prayer') {
        const prayerTabBtn = document.getElementById('tab-prayer-btn');
        if (prayerTabBtn) {
            bootstrap.Tab.getOrCreateInstance(prayerTabBtn).show();
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
