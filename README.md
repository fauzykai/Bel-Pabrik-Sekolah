# Sistem Bel Sekolah & Pabrik Otomatis (STB Linux)

Sistem bel otomatis hemat daya berbasis web (PHP + SQLite + Bootstrap) yang dirancang khusus untuk **STB Linux Headless** (Armbian/Debian/Ubuntu seperti B860H, HG680P) tanpa memerlukan monitor.

Sistem dikontrol penuh melalui smartphone (HP) via browser di alamat **`http://bel.local`**.

---

## Fitur Utama

1. **Audio Hardware CLI Daemon (`service/scheduler.php`)**:
   - Pemutaran suara dilakukan langsung oleh proses background CLI (`mpg123` / `aplay` / `mpv`) ke jack 3.5mm / USB DAC STB.
   - Tidak membebani browser atau memerlukan Chromium kiosk (sangat hemat RAM ~10MB).
2. **Kontrol Remote HP (Mobile-First UI)**:
   - Dashboard responsif dengan tombol jempol besar.
   - Pemicu Bel Manual Instan (Masuk, Istirahat, Pulang, Chime, atau pilihan suara custom).
3. **Sinkronisasi Jam HP 1-Klik**:
   - Karena STB tidak memiliki baterai CMOS (RTC internal), tombol **"Samakan Jam dengan HP"** di dashboard langsung mencocokkan waktu sistem STB dengan jam HP Anda tanpa perlu koneksi internet.
4. **Pengaturan Jaringan WiFi (`wifi.php`)**:
   - Pindai (scan) WiFi sekitar langsung dari browser HP dan sambungkan STB ke router tanpa monitor.
5. **Kontrol Volume Hardware (`amixer`)**:
   - Slider volume realtime dan tombol "Tes Suara" langsung ke speaker fisik STB.
6. **Manajemen Jadwal Lengkap (`schedules.php`)**:
   - Atur jadwal harian (Senin - Minggu).
   - Pengelompokan kategori: Reguler, Ujian, Shift 1, Shift 2.
   - Toggle switch on/off instan.
7. **Audio Library (`audio.php`)**:
   - Upload file MP3 / WAV baru.
   - Preview suara di browser HP dan tes di speaker STB.

---

## Cara Pasang di STB Linux

1. Salin seluruh isi folder ini ke STB (misal melalui SCP, WinSCP, atau USB flashdisk) ke direktori `/var/www/html/bell`.
2. Jalankan script instalasi otomatis dengan akses root:
   ```bash
   sudo bash /var/www/html/bell/setup.sh
   ```
3. Script akan otomatis:
   - Menginstal paket pendukung (`php-fpm`, `php-sqlite3`, `mpg123`, `alsa-utils`, `avahi-daemon`, `network-manager`).
   - Menginisialisasi database SQLite dan audio default.
   - Menyiapkan izin file dan sudoers untuk user web (`www-data`).
   - Mendaftarkan systemd service `bell.service` agar scheduler otomatis berjalan saat STB dihidupkan (booting).
   - Mengaktifkan nama host mDNS `http://bel.local`.

---

## Cara Mengakses dari HP

1. Pastikan HP tersambung ke jaringan WiFi yang sama dengan STB.
2. Buka browser di HP (Chrome / Safari), ketik:
   ```text
   http://bel.local
   ```
   *(Atau buka langsung alamat IP STB, misalnya `http://192.168.1.50`)*.
