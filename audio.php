<?php
/**
 * Halaman Manajemen File Audio
 * Upload rekaman bel MP3/WAV, preview di HP, tes di speaker STB, dan hapus file.
 */

$page_title = 'Kelola Audio Bel - Sistem STB';
require_once __DIR__ . '/includes/header.php';

$audio_dir = __DIR__ . '/uploads/audio';
if (!is_dir($audio_dir)) {
    mkdir($audio_dir, 0777, true);
}

// Proses Upload File Audio
$upload_msg = '';
$upload_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio_file'])) {
    $file = $_FILES['audio_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['mp3', 'wav', 'ogg'])) {
            // Bersihkan nama file agar aman dari spasi/karakter aneh
            $clean_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file['name']);
            $target = $audio_dir . '/' . $clean_name;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $upload_msg = "File audio {$clean_name} berhasil diunggah.";
                $upload_success = true;
            } else {
                $upload_msg = "Gagal memindahkan file ke folder audio.";
            }
        } else {
            $upload_msg = "Format file tidak didukung. Gunakan .mp3, .wav, atau .ogg.";
        }
    } else {
        $upload_msg = "Terjadi kesalahan upload (Error Code: " . $file['error'] . ").";
    }
}

// Proses Hapus File Audio
if (isset($_GET['delete'])) {
    $delete_target = basename($_GET['delete']);
    $file_path = $audio_dir . '/' . $delete_target;
    if (file_exists($file_path)) {
        unlink($file_path);
        header('Location: audio.php?msg=deleted');
        exit;
    }
}

// Pindai file yang ada
$audio_list = [];
$scanned = scandir($audio_dir);
foreach ($scanned as $f) {
    if ($f !== '.' && $f !== '..' && preg_match('/\.(wav|mp3|ogg)$/i', $f)) {
        $path = $audio_dir . '/' . $f;
        $audio_list[] = [
            'name' => $f,
            'size' => round(filesize($path) / 1024, 1) . ' KB',
            'mtime' => date('d M Y H:i', filemtime($path)),
            'url' => 'uploads/audio/' . rawurlencode($f)
        ];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Koleksi Suara Bel</h5>
        <span class="text-secondary small">Upload & tes rekaman suara atau nada bel</span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-cloud-arrow-up me-1"></i> Upload Audio
    </button>
</div>

<?php if (!empty($upload_msg)): ?>
    <div class="alert alert-<?= $upload_success ? 'success' : 'danger' ?> alert-dismissible fade show small mb-3">
        <?= htmlspecialchars($upload_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success alert-dismissible fade show small mb-3">
        File audio berhasil dihapus.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Daftar File Audio -->
<div class="card bg-white">
    <div class="list-group list-group-flush">
        <?php foreach ($audio_list as $a): ?>
            <div class="list-group-item p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-music-fill text-primary fs-4"></i>
                        <div>
                            <span class="fw-semibold d-block text-dark text-break"><?= htmlspecialchars($a['name']) ?></span>
                            <span class="text-secondary small"><?= $a['size'] ?> • <?= $a['mtime'] ?></span>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="triggerManualBell('<?= htmlspecialchars($a['name']) ?>', 'Tes Audio: <?= htmlspecialchars($a['name']) ?>')" title="Tes di Speaker STB">
                            <i class="bi bi-speaker me-1"></i> <span class="d-none d-sm-inline">Tes Speaker</span>
                        </button>
                        <a href="audio.php?delete=<?= urlencode($a['name']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus file <?= htmlspecialchars($a['name']) ?>?')" title="Hapus">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </div>

                <!-- Preview Audio di HP / Browser -->
                <div class="pt-2 border-top">
                    <audio controls preload="none" class="w-100" style="height: 36px;">
                        <source src="<?= $a['url'] ?>" type="audio/mpeg">
                        Browser Anda tidak mendukung preview audio.
                    </audio>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Upload File Audio -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-cloud-arrow-up me-1"></i> Upload File Audio Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih File dari HP/Komputer</label>
                        <input type="file" name="audio_file" class="form-control" accept=".mp3,.wav,.ogg" required>
                        <div class="form-text small">Mendukung format <code>.mp3</code>, <code>.wav</code>, atau <code>.ogg</code>.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Mulai Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
