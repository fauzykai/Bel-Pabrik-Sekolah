<?php
/**
 * Halaman Pengaturan WiFi STB
 * Memindai jaringan nirkabel sekitar, menyambungkan STB ke hotspot/router kantor/sekolah,
 * dan memantau status IP STB.
 */

$page_title = 'Pengaturan WiFi - Sistem STB';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Pengaturan Jaringan WiFi</h5>
        <span class="text-secondary small">Koneksikan STB ke router tanpa monitor</span>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm" id="btnRescan" onclick="scanWifi()">
        <i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang
    </button>
</div>

<!-- Kartu Status Jaringan Saat Ini -->
<div class="card mb-3 bg-white">
    <div class="card-body p-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                <i class="bi bi-wifi fs-3"></i>
            </div>
            <div class="flex-grow-1">
                <span class="text-uppercase text-secondary fw-semibold" style="font-size: 0.75rem;">JARINGAN TERHUBUNG</span>
                <h6 class="fw-bold mb-1 text-dark" id="txtCurrentSsid"><?= htmlspecialchars($net_info['connected_ssid']) ?></h6>
                <div class="small text-muted d-flex flex-wrap gap-2">
                    <span>IP STB: <strong class="text-dark" id="txtCurrentIp"><?= htmlspecialchars($net_info['ip_address']) ?></strong></span>
                    <span>•</span>
                    <span class="<?= $net_info['has_internet'] ? 'text-success' : 'text-warning' ?>">
                        <i class="bi <?= $net_info['has_internet'] ? 'bi-check-circle-fill' : 'bi-dash-circle-fill' ?>"></i>
                        <?= $net_info['has_internet'] ? 'Internet Terhubung' : 'Jaringan Lokal Saja' ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="alert alert-light border mt-3 mb-0 small py-2 px-3">
            <i class="bi bi-info-circle text-primary me-1"></i>
            Anda dapat membuka sistem ini dari HP tanpa mengetik IP melalui:
            <strong><code>http://bel.local</code></strong> (mDNS).
        </div>
    </div>
</div>

<!-- Daftar WiFi Sekitar -->
<div class="card bg-white mb-3">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-secondary small text-uppercase">
            <i class="bi bi-broadcast-pin me-1"></i> WiFi Ditemukan Sekitar STB
        </span>
        <button class="btn btn-sm btn-link text-decoration-none p-0 small" onclick="openManualWifiModal()">
            + SSID Tersembunyi
        </button>
    </div>
    <div class="card-body p-0">
        <div id="wifiLoading" class="p-4 text-center text-muted">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div>Sedang memindai jaringan WiFi di sekitar STB...</div>
        </div>
        <div id="wifiListContainer" class="list-group list-group-flush" style="display: none;">
            <!-- Diisi secara dinamis via JavaScript -->
        </div>
    </div>
</div>

<!-- Info Auto-Hotspot Failover -->
<div class="card bg-white">
    <div class="card-body p-3">
        <h6 class="fw-bold mb-1"><i class="bi bi-shield-check text-success me-1"></i> Fitur Auto-Hotspot Darurat</h6>
        <p class="text-secondary small mb-0">
            Jika STB dipindahkan ke lokasi baru atau password WiFi diubah sehingga koneksi terputus,
            STB otomatis menyalakan WiFi sendiri dengan nama <strong><code>BEL-STB-SETUP</code></strong>.
            Anda cukup menyambungkan HP ke hotspot tersebut untuk menyetel WiFi baru.
        </p>
    </div>
</div>

<!-- Modal Sambungkan WiFi -->
<div class="modal fade" id="connectWifiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-wifi me-1"></i> Sambungkan ke WiFi</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formConnectWifi" onsubmit="submitConnectWifi(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama WiFi (SSID)</label>
                        <input type="text" class="form-control" name="ssid" id="inpSsid" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Sandi (Password)</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="inpPassword" placeholder="Kosongkan jika jaringan terbuka">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnConnectSubmit">Sambungkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const connectModal = new bootstrap.Modal(document.getElementById('connectWifiModal'));

/**
 * Memindai jaringan WiFi sekitar dan merender hasilnya ke halaman
 */
function scanWifi() {
    const loading = document.getElementById('wifiLoading');
    const container = document.getElementById('wifiListContainer');
    const btnRescan = document.getElementById('btnRescan');

    loading.style.display = 'block';
    container.style.display = 'none';
    btnRescan.disabled = true;

    fetch('api/wifi_action.php?action=scan')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.networks) {
                renderWifiList(data.networks);
            } else {
                container.innerHTML = `<div class="p-4 text-center text-danger small">${data.message || 'Gagal memindai'}</div>`;
            }
        })
        .catch(err => {
            container.innerHTML = `<div class="p-4 text-center text-danger small">Koneksi gagal: ${err}</div>`;
        })
        .finally(() => {
            loading.style.display = 'none';
            container.style.display = 'block';
            btnRescan.disabled = false;
        });
}

/**
 * Menampilkan daftar jaringan WiFi dalam bentuk elemen HTML
 *
 * @param {Array} networks Daftar objek jaringan
 */
function renderWifiList(networks) {
    const container = document.getElementById('wifiListContainer');
    if (networks.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-muted small">Tidak ada jaringan WiFi yang terdeteksi.</div>';
        return;
    }

    let html = '';
    networks.forEach(net => {
        const isCurrent = net.in_use;
        html += `
        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 ${isCurrent ? 'bg-light' : ''}">
            <div class="d-flex align-items-center gap-2">
                <i class="bi ${net.signal > 70 ? 'bi-wifi' : (net.signal > 40 ? 'bi-wifi-2' : 'bi-wifi-1')} text-primary fs-5"></i>
                <div>
                    <span class="fw-semibold d-block text-dark small">${escapeHtml(net.ssid)}</span>
                    <span class="text-muted" style="font-size: 0.7rem;">Sinyal: ${net.signal}% • ${escapeHtml(net.security)}</span>
                </div>
            </div>
            <div>
                ${isCurrent ? 
                    '<span class="badge bg-success">Tersambung</span>' : 
                    `<button type="button" class="btn btn-sm btn-outline-primary" onclick="promptConnect('${escapeHtml(net.ssid)}')">
                        Sambungkan
                    </button>`
                }
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

/**
 * Membuka modal konfirmasi sambung ke SSID yang dipilih
 *
 * @param {string} ssid Nama WiFi
 */
function promptConnect(ssid) {
    document.getElementById('inpSsid').value = ssid;
    document.getElementById('inpPassword').value = '';
    connectModal.show();
}

/**
 * Membuka modal untuk memasukkan SSID tersembunyi secara manual
 */
function openManualWifiModal() {
    document.getElementById('inpSsid').value = '';
    document.getElementById('inpPassword').value = '';
    connectModal.show();
}

/**
 * Menampilkan atau menyembunyikan teks password
 */
function togglePasswordVisibility() {
    const pass = document.getElementById('inpPassword');
    const icon = document.getElementById('eyeIcon');
    if (pass.type === 'password') {
        pass.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pass.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

/**
 * Mengirim permintaan sambung WiFi ke backend
 *
 * @param {Event} e Submit event
 */
function submitConnectWifi(e) {
    e.preventDefault();
    const ssid = document.getElementById('inpSsid').value;
    const password = document.getElementById('inpPassword').value;
    const btn = document.getElementById('btnConnectSubmit');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyambungkan...';

    fetch('api/wifi_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'connect',
            ssid: ssid,
            password: password
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            connectModal.hide();
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(err => {
        showToast('Koneksi terputus saat mencoba menyambungkan: ' + err, false);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Sambungkan';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Otomatis pindai saat halaman dibuka
window.addEventListener('DOMContentLoaded', scanWifi);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
