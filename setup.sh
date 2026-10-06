#!/usr/bin/env bash
# ==============================================================================
# Script Instalasi Otomatis Bel Sekolah / Pabrik di STB Linux (Armbian/Debian/Ubuntu)
# Jalankan dengan: sudo bash setup.sh
# ==============================================================================

set -e

if [ "$EUID" -ne 0 ]; then
    echo "[ERROR] Silakan jalankan script ini sebagai root (sudo bash setup.sh)"
    exit 1
fi

APP_DIR="/var/www/html/bell"
CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "--------------------------------------------------------"
echo "  MEMULAI INSTALASI SISTEM BEL OTOMATIS PADA STB LINUX  "
echo "--------------------------------------------------------"

# 1. Update paket dan instalasi dependensi
echo "[1/7] Menginstal dependensi (PHP, SQLite, ALSA, mpg123, Avahi, NetworkManager)..."
apt-get update -y
apt-get install -y php-cli php-fpm php-sqlite3 sqlite3 mpg123 alsa-utils avahi-daemon network-manager nginx curl

# 2. Salin atau pastikan file berada di /var/www/html/bell
echo "[2/7] Menyiapkan direktori aplikasi di ${APP_DIR}..."
if [ "${CURRENT_DIR}" != "${APP_DIR}" ]; then
    mkdir -p "${APP_DIR}"
    cp -ru "${CURRENT_DIR}/." "${APP_DIR}/"
fi

# 3. Inisialisasi Database SQLite & Starter Audio
echo "[3/7] Menginisialisasi database SQLite..."
cd "${APP_DIR}"
php init_db.php
php service/gen_starter_audio.php

# 4. Atur Hak Akses File (Permissions)
echo "[4/7] Mengatur hak akses folder untuk www-data..."
chown -R www-data:www-data "${APP_DIR}"
chmod -R 775 "${APP_DIR}/database"
chmod -R 775 "${APP_DIR}/uploads"

# 5. Konfigurasi Izin Sudo Terbatas (Sudoers) untuk www-data
echo "[5/7] Mengonfigurasi sudoers untuk kontrol hardware via Web..."
cat << 'EOF' > /etc/sudoers.d/bell-web
# Izin eksekusi hardware terbatas untuk sistem Bel STB
www-data ALL=(ALL) NOPASSWD: /usr/bin/nmcli, /usr/bin/date, /usr/bin/timedatectl, /usr/bin/hwclock, /usr/bin/amixer, /sbin/reboot, /bin/systemctl
EOF
chmod 0440 /etc/sudoers.d/bell-web

# 6. Konfigurasi Avahi mDNS (Akses bel.local dari HP)
echo "[6/7] Mengaktifkan hostname mDNS (http://bel.local)..."
sed -i 's/^host-name=.*/host-name=bel/' /etc/avahi/avahi-daemon.conf 2>/dev/null || true
systemctl enable --now avahi-daemon

# 7. Daftarkan dan Jalankan Background Service (bell.service)
echo "[7/7] Mendaftarkan systemd service untuk scheduler background..."
cat << EOF > /etc/systemd/system/bell.service
[Unit]
Description=Layanan Bel Otomatis STB Scheduler Daemon
After=network.target sound.target

[Service]
Type=simple
User=root
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/service/scheduler.php
Restart=always
RestartSec=3
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now bell.service

# Konfigurasi Nginx ringkas jika belum ada
if [ ! -f /etc/nginx/sites-available/bell ]; then
    PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" | head -n 1)
    if [ -n "$PHP_SOCK" ]; then
        cat << EOF > /etc/nginx/sites-available/bell
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    root ${APP_DIR};
    index index.php index.html;
    server_name _;

    client_max_body_size 20M;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF
        rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
        ln -sf /etc/nginx/sites-available/bell /etc/nginx/sites-enabled/bell
        systemctl restart nginx
    fi
fi

IP_LOCAL=$(hostname -I | awk '{print $1}')

echo "--------------------------------------------------------"
echo "  INSTALASI SELESAI DENGAN SUKSES!                      "
echo "--------------------------------------------------------"
echo "Buka sistem ini dari browser HP Anda:"
echo "1. Menggunakan Nama Host:  http://bel.local"
echo "2. Menggunakan Alamat IP:  http://${IP_LOCAL}"
echo "--------------------------------------------------------"
