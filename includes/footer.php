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
</script>
</body>
</html>
