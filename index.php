<?php
/**
 * Dashboard Utama (Remote Kontrol HP & Monitoring Bel)
 * Halaman utama untuk memantau jam, memicu bel manual seketika, dan mengatur volume.
 */

$page_title = 'Dashboard Bel STB';
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();
$active_category = get_setting('active_category', 'reguler');
$today_num = (string)$time_info['day_number'];

// Ambil semua jadwal untuk hari ini
$stmt_today = $db->prepare("SELECT * FROM schedules 
                           WHERE is_active = 1 
                             AND (category = ? OR category = 'all')
                           ORDER BY time ASC");
$stmt_today->execute([$active_category]);
$all_today_schedules = $stmt_today->fetchAll();

// Filter berdasarkan hari ini
$today_schedules = array_filter($all_today_schedules, function($s) use ($today_num) {
    $days = explode(',', $s['days']);
    return in_array($today_num, $days) || in_array('all', $days);
});

// Ambil daftar file audio yang tersedia untuk modal bel custom
$audio_files = [];
$audio_dir = __DIR__ . '/uploads/audio';
if (is_dir($audio_dir)) {
    $scanned = scandir($audio_dir);
    foreach ($scanned as $f) {
        if ($f !== '.' && $f !== '..' && preg_match('/\.(wav|mp3|ogg)$/i', $f)) {
            $audio_files[] = $f;
        }
    }
}
?>

<!-- Widget Jam & Status STB -->
<div class="row g-3 mb-3">
    <div class="col-12 col-md-7">
        <div class="card h-100 bg-white">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-uppercase text-secondary fw-semibold" style="font-size: 0.75rem;">WAKTU STB</span>
                        <div class="clock-display text-primary" id="liveClockDisplay">
                            <?= htmlspecialchars($time_info['current_time']) ?>
                        </div>
                        <div class="text-muted small">
                            <?= htmlspecialchars($time_info['day_name']) ?>, <?= htmlspecialchars($time_info['date_formatted']) ?> 
                            <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($time_info['timezone']) ?></span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnSyncTime" onclick="syncTimeFromDevice()" title="Samakan jam STB dengan jam HP Anda">
                        <i class="bi bi-phone me-1"></i> Samakan Jam HP
                    </button>
                </div>

                <div class="d-flex align-items-center gap-2 pt-2 border-top text-secondary small">
                    <i class="bi bi-router"></i>
                    <span>IP: <strong><?= htmlspecialchars($net_info['ip_address']) ?></strong></span>
                    <span class="mx-1">•</span>
                    <i class="bi bi-hdd-network"></i>
                    <span>SSID: <strong><?= htmlspecialchars($net_info['connected_ssid']) ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Kontrol Cepat Volume -->
    <div class="col-12 col-md-5">
        <div class="card h-100 bg-white">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-uppercase text-secondary fw-semibold" style="font-size: 0.75rem;">VOLUME SPEAKER</span>
                        <span class="fw-bold text-dark" id="volValueText"><?= $current_vol ?>%</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 my-2">
                        <i class="bi bi-volume-down text-secondary"></i>
                        <input type="range" class="form-range" id="volRange" min="0" max="100" step="5" value="<?= $current_vol ?>" oninput="changeVolume(this.value)">
                        <i class="bi bi-volume-up text-secondary"></i>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-light border" onclick="triggerManualBell('chime-pendek.wav', 'Tes Speaker')">
                        <i class="bi bi-play-circle me-1"></i> Tes Suara
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-override="dropdown" data-bs-toggle="dropdown">
                            Mode: <?= ucfirst($active_category) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="changeCategory('reguler')">Reguler</a></li>
                            <li><a class="dropdown-item" href="#" onclick="changeCategory('ujian')">Ujian</a></li>
                            <li><a class="dropdown-item" href="#" onclick="changeCategory('shift_1')">Shift 1</a></li>
                            <li><a class="dropdown-item" href="#" onclick="changeCategory('shift_2')">Shift 2</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Cepat Bel Manual (Remote HP) -->
<div class="card mb-3 bg-white">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-secondary small text-uppercase">
            <i class="bi bi-broadcast me-1"></i> Bel Manual Instan (Remote)
        </span>
        <button class="btn btn-sm btn-link text-decoration-none p-0 text-primary" data-bs-toggle="modal" data-bs-target="#customBellModal">
            Pilihan Lain <i class="bi bi-chevron-right"></i>
        </button>
    </div>
    <div class="card-body p-3">
        <div class="row g-2">
            <div class="col-6 col-sm-3">
                <button type="button" class="btn btn-success w-100 btn-quick-bell" onclick="triggerManualBell('bel-masuk.wav', 'Bel Masuk')">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Bel Masuk</span>
                </button>
            </div>
            <div class="col-6 col-sm-3">
                <button type="button" class="btn btn-warning w-100 btn-quick-bell text-dark" onclick="triggerManualBell('bel-istirahat.wav', 'Bel Istirahat')">
                    <i class="bi bi-cup-hot"></i>
                    <span>Istirahat</span>
                </button>
            </div>
            <div class="col-6 col-sm-3">
                <button type="button" class="btn btn-primary w-100 btn-quick-bell" onclick="triggerManualBell('bel-pulang.wav', 'Bel Pulang')">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Bel Pulang</span>
                </button>
            </div>
            <div class="col-6 col-sm-3">
                <button type="button" class="btn btn-secondary w-100 btn-quick-bell" onclick="triggerManualBell('chime-pendek.wav', 'Chime 2 Nada')">
                    <i class="bi bi-bell"></i>
                    <span>Chime Pendek</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Jadwal Bel Hari Ini -->
<div class="card bg-white">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-secondary small text-uppercase">
            <i class="bi bi-clock-history me-1"></i> Jadwal Bel Hari Ini (<?= htmlspecialchars($time_info['day_name']) ?>)
        </span>
        <a href="schedules.php" class="btn btn-sm btn-link text-decoration-none p-0 text-primary">
            Kelola Jadwal <i class="bi bi-chevron-right"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($today_schedules)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                Tidak ada jadwal bel aktif untuk hari ini.
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php 
                $current_hm = substr($time_info['current_time'], 0, 5);
                foreach ($today_schedules as $s): 
                    $is_passed = ($s['time'] < $current_hm);
                ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 <?= $is_passed ? 'bg-light text-muted' : '' ?>">
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-bold font-monospace fs-5 <?= $is_passed ? 'text-secondary' : 'text-primary' ?>">
                                <?= htmlspecialchars($s['time']) ?>
                            </span>
                            <div>
                                <div class="fw-semibold <?= $is_passed ? 'text-secondary' : 'text-dark' ?>">
                                    <?= htmlspecialchars($s['name']) ?>
                                    <?php if (($s['time_type'] ?? 'static') === 'prayer'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem;">
                                            <i class="bi bi-moon-stars me-1"></i><?= ucfirst(htmlspecialchars($s['prayer_name'])) ?>
                                            <?= (int)$s['prayer_offset'] > 0 ? '+' . (int)$s['prayer_offset'] . 'm' : ((int)$s['prayer_offset'] < 0 ? (int)$s['prayer_offset'] . 'm' : '') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted d-flex flex-wrap align-items-center gap-1">
                                    <?php if (!empty($s['audio_intro'])): ?>
                                        <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.65rem;">Intro: <?= htmlspecialchars($s['audio_intro']) ?></span>
                                    <?php endif; ?>
                                    <span class="badge bg-light text-primary border font-monospace" style="font-size: 0.65rem;">Utama: <?= htmlspecialchars($s['audio_file']) ?></span>
                                    <?php if (!empty($s['audio_outro'])): ?>
                                        <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.65rem;">Outro: <?= htmlspecialchars($s['audio_outro']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <?php if ($is_passed): ?>
                                <span class="badge bg-light text-secondary border">Lewat</span>
                            <?php else: 
                                $seq_files = implode('|', array_values(array_filter([$s['audio_intro'], $s['audio_file'], $s['audio_outro']])));
                            ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="triggerManualBell('<?= htmlspecialchars($seq_files) ?>', '<?= htmlspecialchars($s['name']) ?>')" title="Bunyikan Sekarang">
                                    <i class="bi bi-play-fill"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Pilihan Bel Custom -->
<div class="modal fade" id="customBellModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-music-note-list me-1"></i> Pilih Audio untuk Bel Manual</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($audio_files as $audio): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="small text-truncate"><?= htmlspecialchars($audio) ?></span>
                            <button type="button" class="btn btn-sm btn-primary" onclick="triggerManualBell('<?= htmlspecialchars($audio) ?>', 'Manual: <?= htmlspecialchars($audio) ?>'); bootstrap.Modal.getInstance(document.getElementById('customBellModal')).hide();">
                                <i class="bi bi-play-circle me-1"></i> Bunyikan
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Jam digital realtime di halaman
let serverSec = <?= (int)date('s') ?>;
let serverMin = <?= (int)date('i') ?>;
let serverHour = <?= (int)date('G') ?>;

setInterval(() => {
    serverSec++;
    if (serverSec >= 60) {
        serverSec = 0;
        serverMin++;
        if (serverMin >= 60) {
            serverMin = 0;
            serverHour = (serverHour + 1) % 24;
        }
    }
    const h = String(serverHour).padStart(2, '0');
    const m = String(serverMin).padStart(2, '0');
    const s = String(serverSec).padStart(2, '0');
    const display = document.getElementById('liveClockDisplay');
    if (display) display.textContent = `${h}:${m}:${s}`;
}, 1000);

/**
 * Mengubah kategori jadwal aktif (Reguler, Ujian, dll)
 *
 * @param {string} cat Nama kategori
 */
function changeCategory(cat) {
    fetch('api/system_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'set_active_category',
            category: cat
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            setTimeout(() => location.reload(), 800);
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
