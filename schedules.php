<?php
/**
 * Halaman Manajemen Jadwal Bel
 * Mengelola jadwal otomatis harian, pemilihan file audio, dan kategori jadwal.
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
        <span class="text-secondary small">Atur waktu bel berbunyi otomatis setiap hari</span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="openScheduleModal()">
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

                    <!-- Audio & Hari -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 text-secondary small pt-1 border-top">
                        <div class="d-flex align-items-center gap-1 text-truncate" style="max-width: 200px;">
                            <i class="bi bi-music-note"></i>
                            <span class="text-truncate"><?= htmlspecialchars($s['audio_file']) ?></span>
                        </div>

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

                        <!-- Aksi -->
                        <div class="d-flex gap-1 ms-auto">
                            <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick="triggerManualBell('<?= htmlspecialchars($s['audio_file']) ?>', 'Tes Jadwal: <?= htmlspecialchars($s['name']) ?>')" title="Tes Bunyi">
                                <i class="bi bi-play-fill"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick='editSchedule(<?= json_encode($s) ?>)' title="Edit">
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

<!-- Modal Form Jadwal (Tambah / Edit) -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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
                        <input type="text" class="form-control" name="name" id="schName" placeholder="Contoh: Masuk Pagi, Istirahat, Pulang" required>
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

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih File Suara (Audio)</label>
                        <select class="form-select" name="audio_file" id="schAudio" required>
                            <?php foreach ($audio_files as $audio): ?>
                                <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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
const schModal = new bootstrap.Modal(document.getElementById('scheduleModal'));

/**
 * Membuka modal tambah jadwal baru
 */
function openScheduleModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Jadwal Bel';
    document.getElementById('schId').value = '0';
    document.getElementById('schName').value = '';
    document.getElementById('schTime').value = '';
    document.getElementById('schCategory').value = 'reguler';
    setDaysPreset('workdays');
    schModal.show();
}

/**
 * Mengisi data modal untuk proses edit jadwal
 *
 * @param {object} s Data jadwal dari backend
 */
function editSchedule(s) {
    document.getElementById('modalTitle').textContent = 'Edit Jadwal Bel';
    document.getElementById('schId').value = s.id;
    document.getElementById('schName').value = s.name;
    document.getElementById('schTime').value = s.time;
    document.getElementById('schCategory').value = s.category;
    document.getElementById('schAudio').value = s.audio_file;

    const days = s.days.split(',');
    document.querySelectorAll('.day-check').forEach(chk => {
        chk.checked = days.includes(chk.value) || days.includes('all');
    });

    schModal.show();
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
            schModal.hide();
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
