<?php
/**
 * Halaman Manajemen Jadwal Bel
 * Mengelola jadwal otomatis harian, rangkaian 3 file audio (Intro -> Utama -> Outro),
 * dan pengaturan kategori jadwal.
 */

$page_title = 'Jadwal Bel - Sistem STB';
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();

$filter_category = $_GET['cat'] ?? 'all';
if ($filter_category !== 'all') {
    $stmt = $db->prepare("SELECT * FROM schedules WHERE category = ? ORDER BY time ASC");
    $stmt->execute([$filter_category]);
} else {
    $stmt = $db->query("SELECT * FROM schedules ORDER BY category ASC, time ASC");
}
$schedules = $stmt->fetchAll();

// Ambil daftar file audio yang tersedia
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

$days_map = [
    1 => 'Sen',
    2 => 'Sel',
    3 => 'Rab',
    4 => 'Kam',
    5 => 'Jum',
    6 => 'Sab',
    7 => 'Min'
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Manajemen Jadwal Bel</h5>
        <span class="text-secondary small">Setiap jadwal dapat memiliki audio Intro, Utama, dan Outro</span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="btnOpenAddModal" onclick="openScheduleModal()">
        <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
    </button>
</div>

<!-- Filter Kategori -->
<div class="d-flex gap-1 overflow-auto pb-2 mb-3">
    <a href="schedules.php?cat=all" class="btn btn-sm <?= $filter_category === 'all' ? 'btn-dark' : 'btn-light border' ?>">Semua</a>
    <a href="schedules.php?cat=reguler" class="btn btn-sm <?= $filter_category === 'reguler' ? 'btn-dark' : 'btn-light border' ?>">Reguler</a>
    <a href="schedules.php?cat=ujian" class="btn btn-sm <?= $filter_category === 'ujian' ? 'btn-dark' : 'btn-light border' ?>">Ujian</a>
    <a href="schedules.php?cat=shift_1" class="btn btn-sm <?= $filter_category === 'shift_1' ? 'btn-dark' : 'btn-light border' ?>">Shift 1</a>
    <a href="schedules.php?cat=shift_2" class="btn btn-sm <?= $filter_category === 'shift_2' ? 'btn-dark' : 'btn-light border' ?>">Shift 2</a>
</div>

<!-- Daftar Jadwal -->
<?php if (empty($schedules)): ?>
    <div class="card p-4 text-center text-muted bg-white">
        <i class="bi bi-calendar-x fs-1 mb-2"></i>
        Belum ada jadwal pada kategori ini.
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-primary" onclick="openScheduleModal()">
                <i class="bi bi-plus-lg me-1"></i> Buat Jadwal Baru
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="card bg-white">
        <div class="list-group list-group-flush">
            <?php foreach ($schedules as $s): 
                $active_days = explode(',', $s['days']);
                $json_data = htmlspecialchars(json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="list-group-item p-3" id="schedule-row-<?= $s['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-4 fw-bold font-monospace text-primary"><?= htmlspecialchars($s['time']) ?></span>
                            <div>
                                <span class="fw-semibold d-block text-dark"><?= htmlspecialchars($s['name']) ?></span>
                                <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 0.65rem;">
                                    <?= htmlspecialchars($s['category']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Switch Toggle Aktif/Nonaktif -->
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" <?= $s['is_active'] ? 'checked' : '' ?> onchange="toggleSchedule(<?= $s['id'] ?>)">
                        </div>
                    </div>

                    <!-- Rangkaian Audio (Intro -> Utama -> Outro) -->
                    <div class="bg-light p-2 rounded mb-2 small text-secondary">
                        <div class="d-flex flex-wrap align-items-center gap-1 font-monospace" style="font-size: 0.75rem;">
                            <span class="badge bg-secondary">Rangkaian Audio:</span>
                            <?php if (!empty($s['audio_intro'])): ?>
                                <span class="badge bg-info text-dark" title="Audio Pembuka"><i class="bi bi-play-circle me-1"></i>Intro: <?= htmlspecialchars($s['audio_intro']) ?></span>
                                <i class="bi bi-arrow-right text-muted"></i>
                            <?php endif; ?>

                            <span class="badge bg-primary" title="Audio Utama"><i class="bi bi-bell-fill me-1"></i>Utama: <?= htmlspecialchars($s['audio_file']) ?></span>

                            <?php if (!empty($s['audio_outro'])): ?>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-dark" title="Audio Penutup"><i class="bi bi-stop-circle me-1"></i>Outro: <?= htmlspecialchars($s['audio_outro']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hari & Aksi -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 text-secondary small pt-1 border-top">
                        <!-- Hari Aktif Badges -->
                        <div class="d-flex gap-1">
                            <?php foreach ($days_map as $num => $label): 
                                $is_day_on = in_array((string)$num, $active_days) || in_array('all', $active_days);
                            ?>
                                <span class="badge <?= $is_day_on ? 'bg-primary' : 'bg-light text-muted border' ?>" style="font-size: 0.65rem; padding: 3px 5px;">
                                    <?= $label ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <!-- Aksi Tombol -->
                        <div class="d-flex gap-1 ms-auto">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="testSchedule(<?= $s['id'] ?>)" title="Tes Bunyikan Rangkaian Bel Ini">
                                <i class="bi bi-play-fill me-1"></i> Tes Bunyi
                            </button>
                            <button type="button" class="btn btn-sm btn-light border py-0 px-2 btn-edit-sch" data-schedule="<?= $json_data ?>" onclick="openEditScheduleModal(this)" title="Edit Jadwal">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteSchedule(<?= $s['id'] ?>)" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Audio Player Tersembunyi untuk Preview di Browser HP -->
<audio id="browserAudioPreview" preload="none"></audio>

<!-- Modal Form Jadwal (Tambah / Edit) -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="modalTitle">Tambah Jadwal Bel</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSchedule" onsubmit="saveSchedule(event)">
                <input type="hidden" name="id" id="schId" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Jadwal / Keterangan</label>
                        <input type="text" class="form-control" name="name" id="schName" placeholder="Contoh: Masuk Pagi, Istirahat Siang, Jam Pulang" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jam Berbunyi</label>
                            <input type="time" class="form-control font-monospace" name="time" id="schTime" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Kategori</label>
                            <select class="form-select" name="category" id="schCategory">
                                <option value="reguler">Reguler</option>
                                <option value="ujian">Ujian</option>
                                <option value="shift_1">Shift 1</option>
                                <option value="shift_2">Shift 2</option>
                            </select>
                        </div>
                    </div>

                    <!-- PENGATURAN 3 RANGKAIAN AUDIO: INTRO -> UTAMA -> OUTRO -->
                    <div class="card bg-light border p-3 mb-3">
                        <span class="text-uppercase text-secondary fw-bold small d-block mb-2">
                            <i class="bi bi-music-note-list me-1"></i> Rangkaian Audio Bel (Intro - Utama - Outro)
                        </span>

                        <!-- 1. Audio Intro -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1 text-dark">
                                1. Audio Intro / Pembuka <span class="text-muted fw-normal">(Opsional, nada ding-dong awal)</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" name="audio_intro" id="schAudioIntro">
                                    <option value="">-- (Tidak Ada Intro / Langsung Utama) --</option>
                                    <?php foreach ($audio_files as $audio): ?>
                                        <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" onclick="previewSelectedAudio('schAudioIntro')">
                                    <i class="bi bi-play-circle"></i> Tes Dengar
                                </button>
                            </div>
                        </div>

                        <!-- 2. Audio Utama -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1 text-primary">
                                2. Audio Utama <span class="badge bg-primary text-uppercase">Wajib</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <select class="form-select border-primary" name="audio_file" id="schAudio" required>
                                    <?php foreach ($audio_files as $audio): ?>
                                        <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-primary" onclick="previewSelectedAudio('schAudio')">
                                    <i class="bi bi-play-circle"></i> Tes Dengar
                                </button>
                            </div>
                        </div>

                        <!-- 3. Audio Outro -->
                        <div class="mb-0">
                            <label class="form-label small fw-semibold mb-1 text-dark">
                                3. Audio Outro / Penutup <span class="text-muted fw-normal">(Opsional, nada penutup setelah audio utama)</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" name="audio_outro" id="schAudioOutro">
                                    <option value="">-- (Tidak Ada Outro) --</option>
                                    <?php foreach ($audio_files as $audio): ?>
                                        <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" onclick="previewSelectedAudio('schAudioOutro')">
                                    <i class="bi bi-play-circle"></i> Tes Dengar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Hari Aktif -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold mb-0">Hari Aktif</label>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-link text-decoration-none p-0 pe-2 small" onclick="setDaysPreset('workdays')">Sen-Jum</button>
                                <button type="button" class="btn btn-link text-decoration-none p-0 small" onclick="setDaysPreset('all')">Semua Hari</button>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 pt-1">
                            <?php foreach ($days_map as $num => $label): ?>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input day-check" type="checkbox" name="days[]" value="<?= $num ?>" id="day_<?= $num ?>" checked>
                                    <label class="form-check-label small" for="day_<?= $num ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveSchedule">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Mendapatkan instance bootstrap modal secara aman
 *
 * @returns {bootstrap.Modal}
 */
function getScheduleModal() {
    const modalEl = document.getElementById('scheduleModal');
    return bootstrap.Modal.getOrCreateInstance(modalEl);
}

/**
 * Membuka modal tambah jadwal baru
 */
function openScheduleModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Jadwal Bel';
    document.getElementById('schId').value = '0';
    document.getElementById('schName').value = '';
    document.getElementById('schTime').value = '';
    document.getElementById('schCategory').value = 'reguler';
    document.getElementById('schAudioIntro').value = 'chime-pendek.wav';
    document.getElementById('schAudioOutro').value = '';

    const schAudio = document.getElementById('schAudio');
    if (schAudio.options.length > 0) {
        schAudio.selectedIndex = 0;
    }

    setDaysPreset('workdays');
    getScheduleModal().show();
}

/**
 * Membuka modal edit jadwal menggunakan data yang tersimpan di tombol
 *
 * @param {HTMLElement} btn Tombol edit yang diklik
 */
function openEditScheduleModal(btn) {
    try {
        const raw = btn.getAttribute('data-schedule');
        const s = JSON.parse(raw);

        document.getElementById('modalTitle').textContent = 'Edit Jadwal Bel';
        document.getElementById('schId').value = s.id;
        document.getElementById('schName').value = s.name;
        document.getElementById('schTime').value = s.time;
        document.getElementById('schCategory').value = s.category;
        document.getElementById('schAudioIntro').value = s.audio_intro || '';
        document.getElementById('schAudio').value = s.audio_file || '';
        document.getElementById('schAudioOutro').value = s.audio_outro || '';

        const days = (s.days || '').split(',');
        document.querySelectorAll('.day-check').forEach(chk => {
            chk.checked = days.includes(chk.value) || days.includes('all');
        });

        getScheduleModal().show();
    } catch (e) {
        console.error('Gagal membuka modal edit:', e);
        showToast('Gagal memuat data jadwal: ' + e.message, false);
    }
}

/**
 * Memutar file audio pilihan untuk didengarkan pengguna di browser/HP
 *
 * @param {string} selectId ID elemen select
 */
function previewSelectedAudio(selectId) {
    const select = document.getElementById(selectId);
    const filename = select ? select.value : '';
    if (!filename) {
        showToast('Pilih file audio terlebih dahulu', false);
        return;
    }

    const player = document.getElementById('browserAudioPreview');
    player.src = 'uploads/audio/' + encodeURIComponent(filename);
    player.play().catch(err => {
        showToast('Tidak dapat memutar preview di browser: ' + err, false);
    });
}

/**
 * Menyetel preset pilihan hari (hari kerja atau semua hari)
 *
 * @param {string} preset 'workdays' atau 'all'
 */
function setDaysPreset(preset) {
    document.querySelectorAll('.day-check').forEach(chk => {
        if (preset === 'workdays') {
            chk.checked = [1, 2, 3, 4, 5].includes(parseInt(chk.value));
        } else if (preset === 'all') {
            chk.checked = true;
        }
    });
}

/**
 * Menyimpan data jadwal ke backend via AJAX
 *
 * @param {Event} e Form submit event
 */
function saveSchedule(e) {
    e.preventDefault();
    const form = document.getElementById('formSchedule');
    const formData = new FormData(form);
    formData.append('action', 'save');

    const btn = document.getElementById('btnSaveSchedule');
    btn.disabled = true;

    fetch('api/schedule_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            getScheduleModal().hide();
            setTimeout(() => location.reload(), 800);
        }
    })
    .catch(err => {
        showToast('Gagal menyimpan jadwal: ' + err, false);
    })
    .finally(() => {
        btn.disabled = false;
    });
}

/**
 * Menguji dan memutar seluruh rangkaian bel (Intro -> Utama -> Outro) pada speaker STB
 *
 * @param {number} id ID jadwal
 */
function testSchedule(id) {
    fetch('api/schedule_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'test_schedule',
            id: id
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
    })
    .catch(err => {
        showToast('Gagal memicu tes bel', false);
    });
}

/**
 * Mengubah status aktif/nonaktif jadwal secara instan
 *
 * @param {number} id ID jadwal
 */
function toggleSchedule(id) {
    fetch('api/schedule_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'toggle_status',
            id: id
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
    })
    .catch(err => {
        showToast('Gagal mengubah status', false);
    });
}

/**
 * Menghapus jadwal bel
 *
 * @param {number} id ID jadwal
 */
function deleteSchedule(id) {
    if (!confirm('Yakin ingin menghapus jadwal ini?')) return;

    fetch('api/schedule_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'delete',
            id: id
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            const row = document.getElementById('schedule-row-' + id);
            if (row) row.remove();
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
