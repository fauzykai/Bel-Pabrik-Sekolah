<?php
/**
 * Halaman Manajemen Bel IoT (Pemetaan 12 Tombol ESP)
 * Menghubungkan 12 tombol perangkat IoT ESP dengan audio bel di STB.
 */

$page_title = 'Bel IoT (Tombol ESP) - Sistem STB';
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();
$buttons = $db->query("SELECT * FROM iot_buttons ORDER BY button_number ASC")->fetchAll();

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

$stb_ip = $net_info['ip_address'] ?? '192.168.0.7';
$secondary_stb_ip = get_setting('iot_secondary_stb_ip', '');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Bel IoT (12 Tombol ESP)</h5>
        <span class="text-secondary small">Cocokkan aksi tombol fisik IoT dengan pilihan suara bel</span>
    </div>
    <div class="d-flex gap-1">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#multiStbModal">
            <i class="bi bi-diagram-3 me-1"></i> Sinkronisasi 2 STB
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#espCodeModal">
            <i class="bi bi-code-slash me-1"></i> Contoh Kode ESP
        </button>
    </div>
</div>

<!-- Info URL Endpoint API untuk ESP & Multi-STB Status -->
<div class="card mb-3 bg-white border-primary">
    <div class="card-body p-3">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
            <div>
                <span class="fw-semibold d-block text-primary">
                    <i class="bi bi-broadcast me-1"></i> Endpoint Webhook untuk ESP (WiFi yang sama)
                </span>
                <span class="text-secondary small font-monospace">
                    GET http://<?= htmlspecialchars($stb_ip) ?>/api/iot_trigger.php?button={1..12}
                </span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <?php if (!empty($secondary_stb_ip)): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-link-45deg me-1"></i>Sync STB #2: <?= htmlspecialchars($secondary_stb_ip) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-muted border">
                        <i class="bi bi-link-45deg me-1"></i>1 STB Mandiri
                    </span>
                <?php endif; ?>
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-cpu me-1"></i> Port: 80 (HTTP)
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Grid 12 Tombol IoT -->
<div class="row g-3" id="buttonsContainer">
    <?php foreach ($buttons as $btn): 
        $num = (int)$btn['button_number'];
        $json_btn = htmlspecialchars(json_encode($btn, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
    ?>
        <div class="col-12 col-md-6" id="card-btn-<?= $num ?>">
            <div class="card h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                <?= $num ?>
                            </span>
                            <div>
                                <span class="fw-bold text-dark d-block" id="label-name-<?= $num ?>"><?= htmlspecialchars($btn['name']) ?></span>
                                <span class="text-muted font-monospace" style="font-size: 0.7rem;">
                                    Tombol #<?= $num ?> • <code class="text-primary">?button=<?= $num ?></code>
                                </span>
                            </div>
                        </div>

                        <!-- Toggle Aktif/Nonaktif -->
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" <?= $btn['is_active'] ? 'checked' : '' ?> onchange="toggleIoTButton(<?= $num ?>)" title="Aktif/Nonaktifkan Tombol Ini">
                        </div>
                    </div>

                    <!-- Pemetaan Audio -->
                    <div class="bg-light p-2 rounded mb-2 small text-secondary">
                        <div class="d-flex flex-wrap align-items-center gap-1 font-monospace" style="font-size: 0.75rem;">
                            <?php if (!empty($btn['audio_intro'])): ?>
                                <span class="badge bg-info text-dark">Intro: <?= htmlspecialchars($btn['audio_intro']) ?></span>
                                <i class="bi bi-arrow-right text-muted"></i>
                            <?php endif; ?>

                            <span class="badge bg-primary">Utama: <?= htmlspecialchars($btn['audio_file']) ?></span>

                            <?php if (!empty($btn['audio_outro'])): ?>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-dark">Outro: <?= htmlspecialchars($btn['audio_outro']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Baris Bawah: Status Pemicuan & Tombol Aksi -->
                    <div class="d-flex justify-content-between align-items-center pt-1 border-top small">
                        <span class="text-muted" style="font-size: 0.75rem;" id="last-triggered-<?= $num ?>">
                            <?= !empty($btn['last_triggered']) ? 'Terakhir: ' . htmlspecialchars($btn['last_triggered']) : 'Belum pernah ditekan' ?>
                        </span>

                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="testIoTTrigger(<?= $num ?>)" title="Uji Coba Bunyi Tombol Ini">
                                <i class="bi bi-play-fill me-1"></i> Tes Bunyi
                            </button>
                            <button type="button" class="btn btn-sm btn-light border py-0 px-2 btn-edit-iot" data-button="<?= $json_btn ?>" onclick="openEditIoTModal(this)" title="Ubah Suara Tombol">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Edit Konfigurasi Tombol IoT -->
<div class="modal fade" id="editIoTModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="modalIoTTitle">Edit Tombol Bel IoT</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditIoT" onsubmit="saveIoTButton(event)">
                <input type="hidden" name="button_number" id="inpIoTBtnNum" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama / Lokasi Tombol</label>
                        <input type="text" class="form-control" name="name" id="inpIoTName" required placeholder="Contoh: Tombol 1 - Pos Satpam Depan">
                    </div>

                    <!-- Rangkaian Audio -->
                    <div class="card bg-light border p-3 mb-2">
                        <span class="text-uppercase text-secondary fw-bold small d-block mb-2">
                            <i class="bi bi-music-note-list me-1"></i> Audio yang Dibunyikan
                        </span>

                        <!-- Intro -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">1. Audio Pembuka (Intro) - Opsional</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" name="audio_intro" id="inpIoTAudioIntro">
                                    <option value="">-- (Tanpa Intro / Langsung Utama) --</option>
                                    <?php foreach ($audio_files as $audio): ?>
                                        <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" onclick="previewSelectedAudio('inpIoTAudioIntro')">
                                    <i class="bi bi-play-circle"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Audio Utama -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1 text-primary">2. Audio Utama (Wajib)</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select border-primary" name="audio_file" id="inpIoTAudio" required>
                                    <?php foreach ($audio_files as $audio): ?>
                                        <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-primary" onclick="previewSelectedAudio('inpIoTAudio')">
                                    <i class="bi bi-play-circle"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Outro -->
                        <div class="mb-0">
                            <label class="form-label small fw-semibold mb-1">3. Audio Penutup (Outro) - Opsional</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" name="audio_outro" id="inpIoTAudioOutro">
                                    <option value="">-- (Tanpa Outro) --</option>
                                    <?php foreach ($audio_files as $audio): ?>
                                        <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" onclick="previewSelectedAudio('inpIoTAudioOutro')">
                                    <i class="bi bi-play-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveIoT">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Contoh Template Kode ESP32/ESP8266 -->
<div class="modal fade" id="espCodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-cpu me-1"></i> Contoh Kode Arduino untuk ESP32 / ESP8266</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Kode di bawah dapat disalin langsung ke Arduino IDE. ESP akan terhubung ke WiFi yang sama dengan STB, lalu saat pin tombol tertekan (LOW), ESP akan mengirim HTTP GET ke STB:
                </p>
                <pre class="bg-dark text-light p-3 rounded font-monospace small overflow-auto" style="max-height: 380px;"><code>#include &lt;WiFi.h&gt;          // Gunakan &lt;ESP8266WiFi.h&gt; jika ESP8266
#include &lt;HTTPClient.h&gt;    // Gunakan &lt;ESP8266HTTPClient.h&gt; jika ESP8266

const char* ssid = "NAMA_WIFI";
const char* password = "PASSWORD_WIFI";

// Alamat IP STB Bel
const char* stb_server = "http://<?= htmlspecialchars($stb_ip) ?>/api/iot_trigger.php?button=";

// Daftar 12 Pin Tombol (sesuaikan dengan GPIO ESP Anda)
const int buttonPins[12] = {13, 12, 14, 27, 26, 25, 33, 32, 35, 34, 39, 36};

void setup() {
  Serial.begin(115200);
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Terhubung! IP ESP: " + WiFi.localIP().toString());

  // Inisialisasi pin tombol dengan Internal Pullup (aktif saat ditekan ke GND)
  for (int i = 0; i &lt; 12; i++) {
    pinMode(buttonPins[i], INPUT_PULLUP);
  }
}

void triggerBell(int buttonNumber) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = String(stb_server) + String(buttonNumber);
    http.begin(url);
    int httpCode = http.GET();
    Serial.printf("Pemicu Tombol %d -&gt; Response HTTP: %d\n", buttonNumber, httpCode);
    http.end();
  }
}

void loop() {
  for (int i = 0; i &lt; 12; i++) {
    if (digitalRead(buttonPins[i]) == LOW) {
      triggerBell(i + 1); // Tombol 1 sampai 12
      delay(800);         // Debounce jeda tekan
    }
  }
  delay(50);
}
</code></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfigurasi Sinkronisasi 2 STB (Lantai 1 & Lantai 2) -->
<div class="modal fade" id="multiStbModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-diagram-3 me-1"></i> Sinkronisasi Suara Multi-STB</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formMultiStb" onsubmit="saveMultiStbConfig(event)">
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Jika Anda memiliki 2 STB (misal: Lantai 1 & Lantai 2), isi alamat IP STB kedua di bawah ini. Ketika tombol IoT ditekan, STB ini akan membunyikan bel lokal sekaligus memicu STB kedua dalam jaringan lokal.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">IP Address STB Kedua / Lantai Lain:</label>
                        <input type="text" class="form-control font-monospace" name="secondary_ip" id="inpSecondaryStbIp" value="<?= htmlspecialchars($secondary_stb_ip) ?>" placeholder="Contoh: 192.168.0.8 (kosongkan jika hanya 1 STB)">
                        <span class="text-muted" style="font-size: 0.7rem;">*Pastikan kedua STB terhubung pada router / WiFi yang sama.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveMultiStb">Simpan Konfigurasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<audio id="browserAudioPreview" preload="none"></audio>

<script>
/**
 * Membuka modal edit konfigurasi tombol
 */
function openEditIoTModal(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-button'));
        document.getElementById('modalIoTTitle').textContent = `Edit Pemetaan Tombol #${data.button_number}`;
        document.getElementById('inpIoTBtnNum').value = data.button_number;
        document.getElementById('inpIoTName').value = data.name;
        document.getElementById('inpIoTAudioIntro').value = data.audio_intro || '';
        document.getElementById('inpIoTAudio').value = data.audio_file || '';
        document.getElementById('inpIoTAudioOutro').value = data.audio_outro || '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('editIoTModal')).show();
    } catch (e) {
        showToast('Gagal membuka modal: ' + e.message, false);
    }
}

/**
 * Menyimpan konfigurasi tombol IoT
 */
function saveIoTButton(e) {
    e.preventDefault();
    const form = document.getElementById('formEditIoT');
    const formData = new FormData(form);
    formData.append('action', 'save_button');

    const btn = document.getElementById('btnSaveIoT');
    btn.disabled = true;

    fetch('api/iot_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editIoTModal')).hide();
            setTimeout(() => location.reload(), 700);
        }
    })
    .catch(err => {
        showToast('Gagal menyimpan: ' + err, false);
    })
    .finally(() => {
        btn.disabled = false;
    });
}

/**
 * Mengubah status aktif/nonaktif tombol IoT
 */
function toggleIoTButton(btnNum) {
    fetch('api/iot_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'toggle_active',
            button_number: btnNum
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
 * Uji coba pemicuan tombol seolah-olah ditekan dari ESP
 */
function testIoTTrigger(btnNum) {
    fetch('api/iot_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'test_trigger',
            button_number: btnNum
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            const timeSpan = document.getElementById('last-triggered-' + btnNum);
            if (timeSpan) {
                const now = new Date();
                timeSpan.textContent = 'Terakhir: Baru saja dipicu';
                timeSpan.classList.add('text-success', 'fw-bold');
            }
        }
    })
    .catch(err => {
        showToast('Gagal memicu tombol', false);
    });
}

/**
 * Menyimpan konfigurasi multi-STB
 */
function saveMultiStbConfig(e) {
    e.preventDefault();
    const secondaryIp = document.getElementById('inpSecondaryStbIp').value.trim();

    fetch('api/iot_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'save_multi_stb',
            secondary_ip: secondaryIp
        })
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success);
        if (data.success) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('multiStbModal')).hide();
            setTimeout(() => location.reload(), 700);
        }
    })
    .catch(err => {
        showToast('Gagal menyimpan multi-STB: ' + err, false);
    });
}

/**
 * Memutar file audio pilihan untuk preview di browser
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
        showToast('Tidak dapat memutar preview: ' + err, false);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
