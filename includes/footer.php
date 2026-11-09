<?php
/**
 * Footer Template
 * Navigasi bawah untuk smartphone, toast alert, modal global, dan script JS.
 */
?>
</div> <!-- End of container-fluid -->

<!-- Mobile Bottom Navigation Bar (Ramah Jempol di HP) -->
<nav class="mobile-bottom-nav">
    <a href="index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
    </a>
    <a href="schedules.php" class="<?= $current_page === 'schedules.php' ? 'active' : '' ?>">
        <i class="bi bi-calendar3"></i>
        <span>Jadwal</span>
    </a>
    <a href="iot.php" class="<?= $current_page === 'iot.php' ? 'active' : '' ?>">
        <i class="bi bi-bell-fill"></i>
        <span>Bel IoT</span>
    </a>
    <a href="audio.php" class="<?= $current_page === 'audio.php' ? 'active' : '' ?>">
        <i class="bi bi-music-note-beamed"></i>
        <span>Audio</span>
    </a>
    <a href="settings.php" class="<?= in_array($current_page, ['settings.php', 'time.php', 'wifi.php']) ? 'active' : '' ?>">
        <i class="bi bi-sliders"></i>
        <span>Setelan</span>
    </a>
</nav>

<!-- Toast Notifikasi Global -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="appToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage">
                Pesan notifikasi
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Modal Global Silent Mode -->
<div class="modal fade" id="silentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-bell-slash text-danger me-1"></i> Pengaturan Silent Mode (Hening)
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Saat Mode Silent aktif, seluruh jadwal bel otomatis akan diheningkan sementara tanpa perlu mematikan jadwal satu per satu.
                </p>

                <!-- Status Saat Ini -->
                <?php if ($silent_info['is_silent']): ?>
                    <div class="alert alert-danger py-2 px-3 small mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="d-block fw-bold"><i class="bi bi-volume-mute-fill me-1"></i>Sedang Hening!</span>
                            <span>Sampai: <strong><?= htmlspecialchars($silent_info['formatted_until']) ?></strong> (sisa <?= htmlspecialchars($silent_info['remaining_text']) ?>)</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-dark" onclick="deactivateSilentMode()">
                            Bunyikan Normal
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success py-2 px-3 small mb-3">
                        <i class="bi bi-check-circle-fill me-1"></i> Status: <strong>Normal</strong> (Jadwal bel berbunyi sesuai jadwal).
                    </div>
                <?php endif; ?>

                <!-- Form Pilihan Mode Durasi -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pilih Jenis Durasi Hening:</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="silent_type" id="sil_type_hours" value="hours" checked onchange="toggleSilentType('hours')">
                        <label class="btn btn-outline-primary btn-sm" for="sil_type_hours">
                            <i class="bi bi-hourglass-split me-1"></i> Hitungan Jam
                        </label>

                        <input type="radio" class="btn-check" name="silent_type" id="sil_type_datetime" value="datetime" onchange="toggleSilentType('datetime')">
                        <label class="btn btn-outline-primary btn-sm" for="sil_type_datetime">
                            <i class="bi bi-calendar-event me-1"></i> Sampai Tanggal / Jam
                        </label>
                    </div>
                </div>

                <!-- Blok 1: Pilihan Hitungan Jam -->
                <div id="blockSilentHours">
                    <label class="form-label small fw-semibold">Durasi Hening:</label>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="applySilentHours(1)">1 Jam</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="applySilentHours(2)">2 Jam</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="applySilentHours(4)">4 Jam</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="applySilentHours(8)">8 Jam</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="applySilentHours(24)">1 Hari (24 Jam)</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="applySilentHours(48)">2 Hari</button>
                        </div>
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Kustom Jam:</span>
                        <input type="number" class="form-control" id="customSilentHours" min="0.5" step="0.5" placeholder="Contoh: 3.5">
                        <button type="button" class="btn btn-primary" onclick="applyCustomSilentHours()">Terapkan</button>
                    </div>
                </div>

                <!-- Blok 2: Pilihan Tanggal & Jam Spesifik -->
                <div id="blockSilentDatetime" class="d-none">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Hening Hingga Tanggal & Jam:</label>
                        <input type="datetime-local" class="form-control" id="inpSilentDatetime">
                        <div class="form-text small">Bel akan otomatis aktif normal kembali setelah waktu ini tercapai.</div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm w-100" onclick="applySilentDatetime()">
                        <i class="bi bi-check-lg me-1"></i> Aktifkan Sampai Waktu Tersebut
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Offline JS Bundle -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * Menampilkan pesan toast bootstrap
 *
 * @param {string} message Pesan yang ditampilkan
 * @param {boolean} isSuccess True untuk hijau (sukses), False untuk merah (gagal)
 */
function showToast(message, isSuccess = true) {
    const toastEl = document.getElementById('appToast');
    const msgEl = document.getElementById('toastMessage');
    toastEl.classList.remove('bg-success', 'bg-danger', 'bg-dark');
    toastEl.classList.add(isSuccess ? 'bg-success' : 'bg-danger');
    msgEl.textContent = message;
    const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
}

/**
 * Mengirim permintaan trigger bel manual ke backend
 *
 * @param {string} audioFile Nama file audio di uploads/audio/
 * @param {string} label Label pemicu
 */
function triggerManualBell(audioFile, label) {
    const btn = event?.currentTarget;
    if (btn) btn.disabled = true;

    fetch('api/system_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'trigger_bell',
            audio_file: audioFile,
            label: label
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
    })
    .catch(err => {
        showToast('Koneksi ke STB terputus', false);
    })
    .finally(() => {
        if (btn) btn.disabled = false;
    });
}

/**
 * Menyinkronkan jam STB dengan waktu HP pengguna dalam satu klik
 */
function syncTimeFromDevice() {
    const now = new Date();
    // Format YYYY-MM-DD HH:MM:SS
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const hh = String(now.getHours()).padStart(2, '0');
    const ii = String(now.getMinutes()).padStart(2, '0');
    const ss = String(now.getSeconds()).padStart(2, '0');
    const clientDateTime = `${yyyy}-${mm}-${dd} ${hh}:${ii}:${ss}`;

    const btn = document.getElementById('btnSyncTime');
    if (btn) btn.disabled = true;

    fetch('api/system_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sync_time',
            client_datetime: clientDateTime
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(err => {
        showToast('Gagal menyinkronkan jam: ' + err, false);
    })
    .finally(() => {
        if (btn) btn.disabled = false;
    });
}

/**
 * Mengubah volume hardware STB secara dinamis
 *
 * @param {number} val Nilai volume 0-100
 */
function changeVolume(val) {
    document.getElementById('volValueText').textContent = val + '%';
    fetch('api/system_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'set_volume',
            volume: val
        })
    })
    .then(res => res.json())
    .then(data => {
        // Volume tersimpan
    });
}

/**
 * Toggle tampilan pilihan durasi hening (jam vs datetime)
 *
 * @param {string} type 'hours' atau 'datetime'
 */
function toggleSilentType(type) {
    const blockHours = document.getElementById('blockSilentHours');
    const blockDatetime = document.getElementById('blockSilentDatetime');
    if (type === 'hours') {
        blockHours.classList.remove('d-none');
        blockDatetime.classList.add('d-none');
    } else {
        blockHours.classList.add('d-none');
        blockDatetime.classList.remove('d-none');
        // Default datetime-local ke 2 jam dari sekarang jika masih kosong
        const inp = document.getElementById('inpSilentDatetime');
        if (!inp.value) {
            const d = new Date(Date.now() + 2 * 3600 * 1000);
            const pad = (n) => String(n).padStart(2, '0');
            inp.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }
    }
}

/**
 * Terapkan hening dengan preset jam
 *
 * @param {number} hours Jumlah jam
 */
function applySilentHours(hours) {
    sendSilentRequest('hours', hours);
}

/**
 * Terapkan hening dengan kustom jam
 */
function applyCustomSilentHours() {
    const val = parseFloat(document.getElementById('customSilentHours').value);
    if (isNaN(val) || val <= 0) {
        showToast('Masukkan jumlah jam yang valid (> 0)', false);
        return;
    }
    sendSilentRequest('hours', val);
}

/**
 * Terapkan hening hingga tanggal dan jam tertentu
 */
function applySilentDatetime() {
    const val = document.getElementById('inpSilentDatetime').value;
    if (!val) {
        showToast('Pilih tanggal dan jam terlebih dahulu', false);
        return;
    }
    // Ganti separator 'T' ke spasi agar format standard SQL
    const formatted = val.replace('T', ' ') + ':00';
    sendSilentRequest('datetime', formatted);
}

/**
 * Kirim request aktifkan silent mode ke server
 *
 * @param {string} modeType 'hours' atau 'datetime'
 * @param {string|number} value Nilai jam atau string datetime
 */
function sendSilentRequest(modeType, value) {
    const params = new URLSearchParams({
        action: 'set_silent_mode',
        mode_type: modeType
    });
    if (modeType === 'hours') {
        params.append('hours', value);
    } else {
        params.append('datetime', value);
    }

    fetch('api/system_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            setTimeout(() => location.reload(), 800);
        }
    })
    .catch(err => {
        showToast('Gagal menghubungi STB: ' + err, false);
    });
}

/**
 * Nonaktifkan silent mode (kembali normal)
 */
function deactivateSilentMode() {
    fetch('api/system_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'clear_silent_mode' })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            setTimeout(() => location.reload(), 800);
        }
    })
    .catch(err => {
        showToast('Gagal menghubungi STB: ' + err, false);
    });
}
</script>
</body>
</html>
