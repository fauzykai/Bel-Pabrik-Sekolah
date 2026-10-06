<?php
/**
 * Halaman Pengaturan Sistem, Jam, Audio & Pemeliharaan
 * Pusat kontrol hardware, sinkronisasi jam, volume, dan pemeliharaan STB.
 */

$page_title = 'Pengaturan Sistem - Bel STB';
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();
$timezone = get_setting('timezone', 'Asia/Jakarta');
$audio_device = get_setting('audio_device', 'default');

// Ambil riwayat log bel terakhir
$logs = $db->query("SELECT * FROM play_logs ORDER BY played_at DESC LIMIT 10")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Pengaturan & Sistem</h5>
        <span class="text-secondary small">Konfigurasi jam, audio hardware, dan status STB</span>
    </div>
</div>

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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
