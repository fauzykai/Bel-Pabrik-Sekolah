<?php
/**
 * Header Template
 * Layout bagian atas halaman, navigasi utama, dan aset CSS offline.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/system.php';

$current_page = basename($_SERVER['PHP_SELF']);
$time_info = get_system_time_info();
$net_info = get_network_info();
$worker_active = is_worker_running();
$current_vol = get_system_volume();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title ?? 'Sistem Bel Otomatis STB') ?></title>
    <!-- Offline CSS & JS Assets -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/icons/bootstrap-icons.min.css">
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --app-bg: #f8fafc;
            --app-card-bg: #ffffff;
            --app-primary: #2563eb;
            --app-primary-hover: #1d4ed8;
            --app-dark: #0f172a;
            --app-slate: #334155;
            --app-border: #e2e8f0;
        }
        body {
            background-color: var(--app-bg);
            color: #1e293b;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding-bottom: 75px; /* Ruang untuk mobile bottom nav */
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
        }
        .card {
            border: 1px solid var(--app-border);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        .clock-display {
            font-size: 2.25rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        /* Bottom navigation bar khusus akses smartphone */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #ffffff;
            border-top: 1px solid var(--app-border);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1030;
        }
        .mobile-bottom-nav a {
            color: #64748b;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 4px 10px;
            transition: color 0.15s ease-in-out;
        }
        .mobile-bottom-nav a i {
            font-size: 1.25rem;
            margin-bottom: 2px;
        }
        .mobile-bottom-nav a.active {
            color: var(--app-primary);
            font-weight: 600;
        }
        .btn-quick-bell {
            padding: 1.25rem 1rem;
            border-radius: 0.75rem;
            text-align: center;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: transform 0.1s ease, box-shadow 0.1s ease;
        }
        .btn-quick-bell:active {
            transform: scale(0.97);
        }
        .btn-quick-bell i {
            font-size: 1.75rem;
        }
    </style>
</head>
<body>

<!-- Top Header / Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top" style="background-color: var(--app-dark) !important;">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-bell-fill text-warning"></i>
            <span>BEL STB</span>
            <span class="badge bg-secondary text-uppercase fw-normal ms-1" style="font-size: 0.65rem;">Auto Bell</span>
        </a>

        <!-- Status Badges -->
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="badge d-flex align-items-center gap-1 <?= $worker_active ? 'bg-success' : 'bg-warning text-dark' ?>" title="Status Pemutar Suara">
                <span class="status-dot <?= $worker_active ? 'bg-light' : 'bg-dark' ?>"></span>
                <span class="d-none d-sm-inline"><?= $worker_active ? 'Service Aktif' : 'Standby' ?></span>
            </span>
            <a href="wifi.php" class="badge bg-secondary text-decoration-none d-flex align-items-center gap-1" title="IP Address STB">
                <i class="bi bi-wifi"></i>
                <span class="d-none d-sm-inline"><?= htmlspecialchars($net_info['ip_address']) ?></span>
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid py-3 px-3" style="max-width: 960px;">
