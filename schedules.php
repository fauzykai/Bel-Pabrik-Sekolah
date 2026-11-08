<?php
/**
 * Halaman Manajemen Jadwal Bel
 * Mengelola jadwal otomatis harian, rangkaian 3 file audio (Intro -> Utama -> Outro),
 * dan pengaturan kategori jadwal.
 */

$page_title = 'Jadwal Bel - Sistem STB';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/prayer.php';

$db = get_db_connection();
$today_prayers = get_today_prayer_times();

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
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($s['name']) ?></span>
                                    <?php if (($s['time_type'] ?? 'static') === 'prayer'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">
                                            <i class="bi bi-moon-stars me-1"></i><?= ucfirst(htmlspecialchars($s['prayer_name'])) ?>
                                            <?= (int)$s['prayer_offset'] > 0 ? '+' . (int)$s['prayer_offset'] . 'm' : ((int)$s['prayer_offset'] < 0 ? (int)$s['prayer_offset'] . 'm' : '') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
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

                    <!-- PILIHAN TIPE WAKTU: STATIS ATAU WAKTU SHOLAT -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold d-block mb-1">Metode Penentuan Jam</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="time_type" id="type_static" value="static" checked onchange="toggleTimeType('static')">
                            <label class="btn btn-outline-primary btn-sm" for="type_static">
                                <i class="bi bi-clock me-1"></i> Jam Statis (Tetap)
                            </label>

                            <input type="radio" class="btn-check" name="time_type" id="type_prayer" value="prayer" onchange="toggleTimeType('prayer')">
                            <label class="btn btn-outline-primary btn-sm" for="type_prayer">
                                <i class="bi bi-moon-stars me-1"></i> Mengikuti Waktu Sholat
                            </label>
                        </div>
                    </div>

                    <!-- BLOK INPUT JAM STATIS -->
                    <div class="row g-2 mb-3" id="blockStaticTime">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jam Berbunyi</label>
                            <input type="time" class="form-control font-monospace" name="time" id="schTime">
                            <span class="text-muted" style="font-size: 0.7rem;">Format 24 jam</span>
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

                    <!-- BLOK INPUT WAKTU SHOLAT DENGAN OFFSET -->
                    <div class="card bg-light border p-3 mb-3 d-none" id="blockPrayerTime">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase text-primary fw-bold small">
                                <i class="bi bi-moon-stars-fill me-1"></i> Waktu Sholat & Offset Bunyi
                            </span>
                            <a href="settings.php#prayer" target="_blank" class="small text-decoration-none">
                                <i class="bi bi-gear me-1"></i>Atur Koordinat Sholat
                            </a>
                        </div>
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-12 col-sm-4">
                                <label class="form-label small fw-semibold mb-1">Pilih Sholat</label>
                                <select class="form-select form-select-sm" name="prayer_name" id="schPrayerName" onchange="recalculatePrayerPreview()">
                                    <option value="subuh">Subuh</option>
                                    <option value="dzuhur" selected>Dzuhur</option>
                                    <option value="ashar">Ashar</option>
                                    <option value="maghrib">Maghrib</option>
                                    <option value="isya">Isya</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-4">
                                <label class="form-label small fw-semibold mb-1">Offset Menit (+ / -)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control font-monospace" name="prayer_offset" id="schPrayerOffset" value="0" step="1" oninput="recalculatePrayerPreview()">
                                    <span class="input-group-text">menit</span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-4">
                                <label class="form-label small fw-semibold mb-1">Kategori</label>
                                <select class="form-select form-select-sm" name="category_prayer" id="schCategoryPrayer">
                                    <option value="reguler">Reguler</option>
                                    <option value="ujian">Ujian</option>
                                    <option value="shift_1">Shift 1</option>
                                    <option value="shift_2">Shift 2</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tombol Cepat Preset Offset -->
                        <div class="d-flex align-items-center gap-1 flex-wrap pt-1 mb-2">
                            <span class="text-muted" style="font-size: 0.7rem;">Preset Offset:</span>
                            <button type="button" class="btn btn-sm btn-white border py-0 px-2" style="font-size: 0.7rem;" onclick="setOffsetPreset(-10)">-10 Menit</button>
                            <button type="button" class="btn btn-sm btn-white border py-0 px-2" style="font-size: 0.7rem;" onclick="setOffsetPreset(-5)">-5 Menit (Persiapan)</button>
                            <button type="button" class="btn btn-sm btn-white border py-0 px-2" style="font-size: 0.7rem;" onclick="setOffsetPreset(0)">Tepat Waktu (+0)</button>
                            <button type="button" class="btn btn-sm btn-white border py-0 px-2" style="font-size: 0.7rem;" onclick="setOffsetPreset(5)">+5 Menit (Iqomah/Doa)</button>
                            <button type="button" class="btn btn-sm btn-white border py-0 px-2" style="font-size: 0.7rem;" onclick="setOffsetPreset(10)">+10 Menit</button>
                        </div>

                        <!-- Live Hasil Jam Efektif Hari Ini -->
                        <div class="alert alert-primary py-2 px-3 mb-0 small d-flex justify-content-between align-items-center">
                            <div>
                                <span class="d-block fw-semibold" id="prayerPreviewText">Dzuhur tepat waktu</span>
                                <span class="text-secondary" style="font-size: 0.75rem;">Mengikuti perhitungan astronomi Kemenag RI</span>
                            </div>
                            <div class="text-end">
                                <span class="text-secondary" style="font-size: 0.7rem;">Jam Hari Ini:</span>
                                <span class="fs-5 fw-bold font-monospace text-primary d-block" id="prayerPreviewCalculatedTime">--:--</span>
                            </div>
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
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0 text-primary">
                                    2. Audio Utama <span class="badge bg-primary text-uppercase">Wajib</span>
                                </label>
                                <!-- Switcher Mode Audio Utama: Pilih File vs TTS Suara Robot/Ucapan -->
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="audio_source_type" id="src_file" value="file" checked onchange="toggleAudioSource('file')">
                                    <label class="btn btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" for="src_file">
                                        <i class="bi bi-file-earmark-music me-1"></i>Pilih File
                                    </label>

                                    <input type="radio" class="btn-check" name="audio_source_type" id="src_tts" value="tts" onchange="toggleAudioSource('tts')">
                                    <label class="btn btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" for="src_tts">
                                        <i class="bi bi-megaphone me-1"></i>Ketik Teks (TTS)
                                    </label>
                                </div>
                            </div>

                            <!-- Pilihan 1: Dropdown File Audio Biasa -->
                            <div id="blockAudioFileSelect">
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

                            <!-- Pilihan 2: Generator Text to Speech (TTS) -->
                            <div id="blockAudioTTS" class="d-none border rounded p-2 bg-white mt-2">
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold mb-1">Pilihan Mesin TTS (Text to Speech):</label>
                                    <select class="form-select form-select-sm" id="ttsEngine" onchange="onTTSEngineChange()">
                                        <option value="google_online" selected>1. Google TTS (Online - Sangat Alami & Fasih Indonesia)</option>
                                        <option value="offline_stb">2. Offline STB (eSpeak-NG / Piper - 100% Tanpa Internet)</option>
                                        <option value="browser_speech">3. Browser Suara HP / Web Speech (Rekam Suara HP)</option>
                                    </select>
                                    <span class="text-muted d-block mt-1" id="ttsEngineHelp" style="font-size: 0.7rem;">
                                        *Membutuhkan internet saat generate file. Saat jadwal bel berbunyi nanti tetap 100% offline.
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold mb-1">Teks Ucapan / Kalimat Bel:</label>
                                    <textarea class="form-control form-control-sm" id="ttsText" rows="2" placeholder="Contoh: Perhatian, waktu istirahat pertama telah selesai. Silakan kembali ke kelas."></textarea>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-primary w-100" id="btnGenerateTTS" onclick="generateTTSAudio()">
                                        <i class="bi bi-magic me-1"></i> <span id="btnTTSText">Buat File Suara & Tes Dengar</span>
                                    </button>
                                </div>

                                <div class="mt-2 small d-none" id="ttsSuccessAlert">
                                    <div class="alert alert-success py-1 px-2 mb-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-check-circle-fill me-1"></i>
                                            <span id="ttsGeneratedFilename">file.mp3</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none py-0" onclick="previewCurrentTTS()">
                                            <i class="bi bi-play-fill"></i> Dengar Ulang
                                        </button>
                                    </div>
                                </div>
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
// Data Jadwal Sholat Hari Ini untuk kalkulasi realtime preview di formulir
const TODAY_PRAYERS = <?= json_encode($today_prayers) ?>;

/**
 * Mengganti tampilan antara pilihan file audio vs pembuat TTS
 *
 * @param {string} mode 'file' atau 'tts'
 */
function toggleAudioSource(mode) {
    const isTTS = mode === 'tts';
    document.getElementById('src_file').checked = !isTTS;
    document.getElementById('src_tts').checked = isTTS;

    const blockFile = document.getElementById('blockAudioFileSelect');
    const blockTTS = document.getElementById('blockAudioTTS');

    if (isTTS) {
        blockFile.classList.add('d-none');
        blockTTS.classList.remove('d-none');
    } else {
        blockFile.classList.remove('d-none');
        blockTTS.classList.add('d-none');
    }
}

/**
 * Memperbarui keterangan petunjuk saat mesin TTS diganti
 */
function onTTSEngineChange() {
    const engine = document.getElementById('ttsEngine').value;
    const help = document.getElementById('ttsEngineHelp');
    if (engine === 'google_online') {
        help.textContent = '*Membutuhkan internet saat generate file. Suara sangat natural & fasih Bahasa Indonesia.';
    } else if (engine === 'offline_stb') {
        help.textContent = '*100% offline langsung di sistem STB Linux (eSpeak-NG/Piper). Sedikit robotik namun tidak butuh internet sama sekali.';
    } else if (engine === 'browser_speech') {
        help.textContent = '*Menggunakan engine Text-to-Speech bawaan browser/HP Anda lalu mengunggahnya ke STB.';
    }
}

/**
 * Membuat file audio TTS melalui backend atau browser Web Speech
 */
function generateTTSAudio() {
    const engine = document.getElementById('ttsEngine').value;
    const text = document.getElementById('ttsText').value.trim();

    if (!text) {
        showToast('Ketikkan teks ucapan terlebih dahulu', false);
        document.getElementById('ttsText').focus();
        return;
    }

    const btn = document.getElementById('btnGenerateTTS');
    const btnText = document.getElementById('btnTTSText');
    btn.disabled = true;
    btnText.textContent = 'Sedang membuat suara...';

    if (engine === 'browser_speech') {
        // Opsi 3: Menggunakan Web Speech API Browser untuk preview langsung & kirim TTS Google sebagai fallback file
        if ('speechSynthesis' in window) {
            const utter = new SpeechSynthesisUtterance(text);
            utter.lang = 'id-ID';
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(utter);
            showToast('Memutar suara dari Web Speech HP Anda', true);
        }
        // Tetap generate file audio server agar tersimpan di STB
        fetch('api/schedule_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'generate_tts',
                engine: 'google_online',
                text: text,
                lang: 'id'
            })
        })
        .then(res => res.json())
        .then(data => {
            handleTTSResponse(data);
        })
        .catch(err => {
            showToast('Gagal memproses audio di STB: ' + err, false);
        })
        .finally(() => {
            btn.disabled = false;
            btnText.textContent = 'Buat File Suara & Tes Dengar';
        });
        return;
    }

    // Opsi 1 (Google Online) & Opsi 2 (Offline STB)
    fetch('api/schedule_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'generate_tts',
            engine: engine,
            text: text,
            lang: 'id'
        })
    })
    .then(res => res.json())
    .then(data => {
        handleTTSResponse(data);
    })
    .catch(err => {
        showToast('Gagal memproses TTS: ' + err, false);
    })
    .finally(() => {
        btn.disabled = false;
        btnText.textContent = 'Buat File Suara & Tes Dengar';
    });
}

/**
 * Menangani respon hasil generate file TTS
 *
 * @param {Object} data Respon JSON dari backend
 */
function handleTTSResponse(data) {
    if (data.success && data.filename) {
        // Masukkan file baru ke dalam select audio utama jika belum ada
        const schAudio = document.getElementById('schAudio');
        let optionExists = false;
        for (let i = 0; i < schAudio.options.length; i++) {
            if (schAudio.options[i].value === data.filename) {
                schAudio.selectedIndex = i;
                optionExists = true;
                break;
            }
        }
        if (!optionExists) {
            const opt = new Option(data.filename, data.filename, true, true);
            schAudio.add(opt, 0);
            schAudio.selectedIndex = 0;
        }

        // Tampilkan notifikasi & putar audio preview di HP
        const alertEl = document.getElementById('ttsSuccessAlert');
        const fileLabel = document.getElementById('ttsGeneratedFilename');
        if (alertEl) alertEl.classList.remove('d-none');
        if (fileLabel) fileLabel.textContent = data.filename;

        previewSelectedAudio('schAudio');
        showToast('File suara berhasil dibuat: ' + data.filename, true);
    } else {
        showToast(data.message || 'Gagal membuat file suara', false);
    }
}

/**
 * Memutar ulang preview file audio TTS yang baru di-generate
 */
function previewCurrentTTS() {
    previewSelectedAudio('schAudio');
}

/**
 * Berpindah tampilan antara input Jam Statis vs Waktu Sholat
 *
 * @param {string} type 'static' atau 'prayer'
 */
function toggleTimeType(type) {
    const isPrayer = type === 'prayer';
    document.getElementById('type_static').checked = !isPrayer;
    document.getElementById('type_prayer').checked = isPrayer;

    const blockStatic = document.getElementById('blockStaticTime');
    const blockPrayer = document.getElementById('blockPrayerTime');
    const schTime = document.getElementById('schTime');

    if (isPrayer) {
        blockStatic.classList.add('d-none');
        blockPrayer.classList.remove('d-none');
        schTime.removeAttribute('required');
        // Samakan kategori dari dropdown statis ke sholat
        document.getElementById('schCategoryPrayer').value = document.getElementById('schCategory').value;
        recalculatePrayerPreview();
    } else {
        blockStatic.classList.remove('d-none');
        blockPrayer.classList.add('d-none');
        schTime.setAttribute('required', 'required');
        // Samakan kategori dari dropdown sholat ke statis
        document.getElementById('schCategory').value = document.getElementById('schCategoryPrayer').value;
    }
}

/**
 * Menghitung dan memperbarui preview teks & jam sholat secara interaktif
 */
function recalculatePrayerPreview() {
    const prayerSelect = document.getElementById('schPrayerName');
    const prayerName = prayerSelect ? prayerSelect.value : 'dzuhur';
    const prayerLabel = prayerSelect ? prayerSelect.options[prayerSelect.selectedIndex].text : 'Dzuhur';
    const offsetInp = document.getElementById('schPrayerOffset');
    const offset = parseInt(offsetInp ? offsetInp.value : 0) || 0;

    const baseTime = TODAY_PRAYERS[prayerName] || '12:00';
    const [h, m] = baseTime.split(':').map(Number);
    let totalMinutes = (h * 60) + m + offset;
    totalMinutes = ((totalMinutes % 1440) + 1440) % 1440;

    const resH = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
    const resM = String(totalMinutes % 60).padStart(2, '0');
    const calcTime = `${resH}:${resM}`;

    // Update elemen UI
    const calcElem = document.getElementById('prayerPreviewCalculatedTime');
    const textElem = document.getElementById('prayerPreviewText');
    if (calcElem) calcElem.textContent = calcTime;

    if (textElem) {
        if (offset === 0) {
            textElem.textContent = `Tepat saat waktu ${prayerLabel} (${baseTime})`;
        } else if (offset < 0) {
            textElem.textContent = `${Math.abs(offset)} menit SEBELUM waktu ${prayerLabel} (${baseTime})`;
        } else {
            textElem.textContent = `${offset} menit SETELAH waktu ${prayerLabel} (${baseTime})`;
        }
    }

    // Isi nilai jam cadangan
    document.getElementById('schTime').value = calcTime;
}

/**
 * Menyetel cepat preset offset menit
 *
 * @param {number} offset Nilai offset menit (misal: -5, 0, +5)
 */
function setOffsetPreset(offset) {
    const offsetInp = document.getElementById('schPrayerOffset');
    if (offsetInp) {
        offsetInp.value = offset;
        recalculatePrayerPreview();
    }
}

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
    document.getElementById('schCategoryPrayer').value = 'reguler';
    document.getElementById('schAudioIntro').value = 'chime-pendek.wav';
    document.getElementById('schAudioOutro').value = '';

    // Default tipe waktu statis & mode audio file
    toggleTimeType('static');
    toggleAudioSource('file');
    document.getElementById('ttsText').value = '';
    const alertEl = document.getElementById('ttsSuccessAlert');
    if (alertEl) alertEl.classList.add('d-none');
    document.getElementById('schPrayerName').value = 'dzuhur';
    document.getElementById('schPrayerOffset').value = '0';

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
        document.getElementById('schCategoryPrayer').value = s.category;
        document.getElementById('schAudioIntro').value = s.audio_intro || '';
        document.getElementById('schAudio').value = s.audio_file || '';
        document.getElementById('schAudioOutro').value = s.audio_outro || '';

        toggleAudioSource('file');
        const alertEl = document.getElementById('ttsSuccessAlert');
        if (alertEl) alertEl.classList.add('d-none');

        // Deteksi tipe waktu (prayer vs static)
        const timeType = s.time_type || 'static';
        if (timeType === 'prayer') {
            document.getElementById('schPrayerName').value = s.prayer_name || 'dzuhur';
            document.getElementById('schPrayerOffset').value = s.prayer_offset || 0;
            toggleTimeType('prayer');
            recalculatePrayerPreview();
        } else {
            toggleTimeType('static');
        }

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

    // Sinkronisasi kategori tergantung blok yang aktif
    const isPrayer = document.getElementById('type_prayer').checked;
    if (isPrayer) {
        formData.set('category', document.getElementById('schCategoryPrayer').value);
    }

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
