<?php
// admin.php - Panel Dashboard Manajemen Khusus Administrator BRMP DIY
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/google_sheets.php';

// Pastikan hanya admin yang bisa mengakses
require_admin_login();

$gsheetUrl = get_gsheet_url();
$isGsheetConnected = !empty($gsheetUrl);

// Fetch items for each feature
$bahanBakuList = $pdo->query("SELECT * FROM inventory_items WHERE type = 'bahan_baku' ORDER BY id ASC")->fetchAll();
$produkList = $pdo->query("SELECT * FROM inventory_items WHERE type = 'produk' ORDER BY id ASC")->fetchAll();

// Categories for filter pills
$catBahanBaku = array_unique(array_column($bahanBakuList, 'category'));
$catProduk = array_unique(array_column($produkList, 'category'));

// All items for mutation dropdown
$allItems = $pdo->query("SELECT id, name, code, unit, stock_current, type FROM inventory_items ORDER BY type, name")->fetchAll();

// Mutations log lengkap
$mutations = $pdo->query("
    SELECT m.*, i.name as item_name, i.code as item_code, i.unit, i.type as item_type
    FROM inventory_mutations m
    JOIN inventory_items i ON m.item_id = i.id
    ORDER BY m.mutation_date DESC, m.id DESC
    LIMIT 25
")->fetchAll();

// Metrics per feature
function getMetrics($list) {
    $total = count($list);
    $critical = 0;
    $totalStock = 0;
    foreach ($list as $item) {
        $totalStock += $item['stock_current'];
        if ($item['stock_current'] <= $item['stock_minimum']) $critical++;
    }
    return ['total' => $total, 'critical' => $critical, 'stock' => $totalStock];
}

$metricsBB = getMetrics($bahanBakuList);
$metricsPRD = getMetrics($produkList);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin &bull; Rumah Produksi BRMP DIY</title>
    <!-- Favicon Logo Resmi BRMP DIY -->
    <link rel="icon" type="image/png" href="assets/img/logo-brmp.png">
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">
    <!-- Leaflet Minimalist Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        brmp: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#f8fafc] text-slate-800 min-h-screen flex flex-col font-sans antialiased selection:bg-emerald-500 selection:text-white">

    <!-- Top Admin Header -->
    <header class="bg-gradient-to-r from-[#064e3b] via-[#047857] to-[#065f46] text-white shadow-xl sticky top-0 z-40 border-b border-emerald-600/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Branding -->
                <div class="flex items-center space-x-3.5">
                    <a href="admin.php" class="relative group block shrink-0" title="Panel Administrator BRMP DIY">
                        <div class="w-13 h-13 p-1 bg-white/10 hover:bg-white/20 rounded-2xl border border-white/25 shadow-lg backdrop-blur-md transition-smooth flex items-center justify-center">
                            <img src="assets/img/logo-brmp.png" alt="Logo Resmi BRMP DIY" class="w-11 h-11 object-contain drop-shadow-md transform group-hover:scale-105 transition-smooth">
                        </div>
                    </a>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-white uppercase drop-shadow-sm">Panel Admin</h1>
                            <span class="bg-amber-400 text-slate-900 text-xs px-2.5 py-0.5 rounded-full font-black tracking-wider uppercase shadow-xs">BRMP DIY</span>
                        </div>
                        <p class="text-xs text-emerald-100/90 font-medium flex items-center space-x-1.5 mt-0.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse inline-block"></span>
                            <span>Manajemen CRUD & Monitoring Penuh Sistem</span>
                        </p>
                    </div>
                </div>

                <!-- Admin Action Buttons -->
                <div class="flex items-center space-x-2 sm:space-x-2.5">
                    <!-- Google Sheets Sync Button -->
                    <button onclick="openModal('modal-gsheet')" class="inline-flex items-center space-x-1.5 px-3 py-2 text-xs sm:text-sm font-bold <?= $isGsheetConnected ? 'text-emerald-950 bg-amber-300 hover:bg-amber-400' : 'text-emerald-100 bg-white/10 hover:bg-white/20 border border-white/20' ?> rounded-full shadow-md transition-smooth" title="Integrasi Google Spreadsheet">
                        <svg class="w-4 h-4 <?= $isGsheetConnected ? 'text-emerald-950' : 'text-emerald-200' ?>" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h10v2H7zm0-3h10v2H7zm0 6h7v2H7z"/>
                        </svg>
                        <span class="hidden sm:inline">Google Sheets</span>
                        <span class="sm:hidden">Sheets</span>
                        <?php if ($isGsheetConnected): ?>
                        <span class="w-2 h-2 rounded-full bg-emerald-700 animate-ping inline-block"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Catat Mutasi Manual -->
                    <button onclick="openModal('modal-mutation')" class="inline-flex items-center space-x-1.5 px-3.5 py-2 text-xs sm:text-sm font-bold text-emerald-900 bg-white hover:bg-emerald-50 rounded-full shadow-md transition-smooth">
                        <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        <span class="hidden sm:inline">Catat Mutasi</span>
                        <span class="sm:hidden">Mutasi</span>
                    </button>

                    <!-- Tambah Data Baru (CRUD) -->
                    <button onclick="openModal('modal-add-item')" class="inline-flex items-center space-x-1.5 px-4 py-2 text-xs sm:text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-full shadow-md transition-smooth border border-emerald-400/30">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>+ Tambah Data</span>
                    </button>

                    <!-- Lihat Web Publik -->
                    <a href="index.php" target="_blank" class="inline-flex items-center space-x-1.5 px-3 py-2 text-xs font-bold text-emerald-100 hover:text-white bg-white/10 hover:bg-white/20 rounded-full backdrop-blur-md border border-white/20 transition-smooth" title="Buka Halaman Publik (Pakan Jadi)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        <span class="hidden md:inline">Web Publik</span>
                    </a>

                    <!-- Logout Button -->
                    <a href="login.php?action=logout" class="p-2 text-rose-200 hover:text-white hover:bg-rose-600/30 rounded-full transition-smooth" title="Keluar dari Panel Admin">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container Admin -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 flex-1 w-full space-y-6">

        <!-- ============================================== -->
        <!-- MONITORING METRICS BAR (SEMUA FITUR) -->
        <!-- ============================================== -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Metric 1: Bahan Baku -->
            <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-[0_4px_25px_rgba(0,0,0,0.03)] flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">1. Stok Bahan Baku</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= $metricsBB['total'] ?> <span class="text-xs font-semibold text-slate-500">Item Komposisi</span></span>
                    <span class="text-[11px] font-bold <?= $metricsBB['critical'] > 0 ? 'text-rose-600' : 'text-emerald-600' ?> mt-0.5 block">
                        <?= $metricsBB['critical'] ?> item stok mendekati batas minimum
                    </span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xl shrink-0 border border-emerald-100">
                    🌾
                </div>
            </div>

            <!-- Metric 2: Pakan Jadi (Produk) -->
            <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-[0_4px_25px_rgba(0,0,0,0.03)] flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">2. Pakan Jadi (Produk)</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= $metricsPRD['total'] ?> <span class="text-xs font-semibold text-slate-500">Varian Olahan</span></span>
                    <span class="text-[11px] font-bold text-emerald-600 mt-0.5 block">
                        Tersedia: <?= number_format($metricsPRD['stock'], 0, ',', '.') ?> kg siap salur
                    </span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold text-xl shrink-0 border border-amber-100">
                    📦
                </div>
            </div>

            <!-- Metric 3: Aktivitas Mutasi -->
            <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-[0_4px_25px_rgba(0,0,0,0.03)] flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">3. Total Transaksi Mutasi</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= count($mutations) ?> <span class="text-xs font-semibold text-slate-500">Catatan Terkini</span></span>
                    <span class="text-[11px] font-bold text-slate-600 mt-0.5 block">
                        Audit trail otomatis sistem
                    </span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xl shrink-0 border border-indigo-100">
                    📊
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- NAVIGASI TAB KELOLA ADMIN (MENU LENGKAP) -->
        <!-- ============================================== -->
        <nav class="bg-white/90 backdrop-blur-md p-1.5 rounded-full border border-slate-200/80 shadow-[0_2px_15px_rgba(0,0,0,0.04)] flex flex-wrap items-center justify-between gap-1.5 no-print" aria-label="Menu Admin">
            <div class="flex flex-wrap items-center gap-1.5">
                <!-- Tab 1: Pakan Jadi (Produk) - Default Active -->
                <button data-target="tab-produk" class="feature-tab-btn px-5 py-2.5 rounded-full font-bold text-xs sm:text-sm bg-[#059669] text-white shadow-md flex items-center space-x-2 transition-smooth">
                    <span>📦 1. Pakan Jadi (Produk)</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-white text-emerald-800 font-bold"><?= $metricsPRD['total'] ?></span>
                </button>

                <!-- Tab 2: Bahan Baku -->
                <button data-target="tab-bahan-baku" class="feature-tab-btn px-5 py-2.5 rounded-full font-bold text-xs sm:text-sm bg-transparent text-slate-600 hover:text-emerald-800 hover:bg-slate-100/70 flex items-center space-x-2 transition-smooth">
                    <span>🌾 2. Bahan Baku</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold"><?= $metricsBB['total'] ?></span>
                </button>

                <!-- Tab 3: Log Mutasi -->
                <button data-target="tab-mutasi" class="feature-tab-btn px-5 py-2.5 rounded-full font-bold text-xs sm:text-sm bg-transparent text-slate-600 hover:text-emerald-800 hover:bg-slate-100/70 flex items-center space-x-2 transition-smooth">
                    <span>📋 3. Log Mutasi & Audit</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold"><?= count($mutations) ?></span>
                </button>
            </div>

            <!-- Petugas Active Badge -->
            <div class="hidden sm:flex items-center space-x-2 pr-3 text-xs text-slate-500 font-bold">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                <span>Login: <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Administrator') ?></span>
            </div>
        </nav>


        <!-- ======================================================== -->
        <!-- TAB 1: KELOLA PAKAN JADI (PRODUK) - LENGKAP CRUD & BOM -->
        <!-- ======================================================== -->
        <section id="section-produk" class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pt-1">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Kelola Produk Pakan Jadi Olahan</h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl leading-relaxed">
                        Manajemen katalog produk pakan jadi olahan BRMP DIY. Pantau stok ketersediaan siap salur, lakukan distribusi ke kelompok binaan, dan kelola varian pakan olahan.
                    </p>
                </div>
                <div class="flex items-center space-x-3 shrink-0">
                    <button onclick="refreshData()" class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-semibold shadow-xs transition-smooth">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Segarkan Data</span>
                    </button>
                    <button onclick="openAddModalWithType('produk')" class="inline-flex items-center space-x-1.5 px-5 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold shadow-xs transition-smooth">
                        <span>+ Tambah Pakan Jadi</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Cards Grid Produk (CRUD Penuh) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 pt-2">
                <?php foreach ($produkList as $item): 
                    $isCritical = ($item['stock_current'] <= $item['stock_minimum']);
                ?>
                <div class="item-card-produk bg-white rounded-3xl p-5 border border-slate-200/90 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:border-emerald-300 hover:shadow-[0_16px_32px_rgba(5,150,105,0.08)] item-card-hover flex flex-col justify-between group transition-all"
                     data-name="<?= htmlspecialchars($item['name']) ?>"
                     data-code="<?= htmlspecialchars($item['code']) ?>"
                     data-category="<?= htmlspecialchars($item['category']) ?>"
                     data-critical="<?= $isCritical ? '1' : '0' ?>">
                    <div>
                        <!-- Product Image Area with Admin Quick Action Badges -->
                        <div class="h-44 w-full bg-linear-to-b from-slate-50 to-slate-100/70 rounded-2xl flex items-center justify-center p-3 mb-3.5 overflow-hidden relative border border-slate-100 group-hover:border-slate-200 transition-colors">
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="max-h-full max-w-full object-contain rounded-xl transition-transform duration-300 group-hover:scale-105"
                                 loading="lazy">

                            <!-- Top Badges: Edit/Delete on Left, Code on Right -->
                            <div class="absolute top-2.5 left-2.5 flex items-center space-x-1 bg-white/90 backdrop-blur-md p-1 rounded-xl shadow-xs border border-slate-200/70 opacity-90 group-hover:opacity-100 transition-opacity">
                                <button type="button" onclick='event.stopPropagation(); openEditModal(<?= json_encode($item) ?>)' class="p-1 hover:bg-slate-100 text-slate-600 hover:text-blue-600 rounded-lg transition-smooth" title="Edit Data Pakan Jadi">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <span class="w-px h-3 bg-slate-200"></span>
                                <button type="button" onclick='event.stopPropagation(); confirmDeleteItem(<?= $item['id'] ?>, "<?= htmlspecialchars(addslashes($item['name'])) ?>")' class="p-1 hover:bg-rose-50 text-slate-600 hover:text-rose-600 rounded-lg transition-smooth" title="Hapus Produk">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>

                            <span class="absolute top-2.5 right-2.5 bg-slate-900/80 backdrop-blur-md text-[10px] font-mono text-white font-bold px-2 py-0.5 rounded-lg shadow-xs">
                                <?= htmlspecialchars($item['code']) ?>
                            </span>

                            <?php if (!empty($item['category'])): ?>
                            <span class="absolute bottom-2.5 left-2.5 bg-white/90 backdrop-blur-md text-[10px] font-bold text-slate-700 px-2.5 py-0.5 rounded-full shadow-2xs border border-slate-200/80">
                                <?= htmlspecialchars($item['category']) ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Status Badge -->
                        <div class="flex items-center justify-between">
                            <?php if ($isCritical): ?>
                            <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/70 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                <span>Stok Kritis</span>
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/70 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>Tersedia</span>
                            </span>
                            <?php endif; ?>

                            <span class="text-[11px] font-semibold text-slate-400">
                                Min: <?= number_format($item['stock_minimum'], 0, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?>
                            </span>
                        </div>

                        <!-- Title -->
                        <h3 class="text-[15px] font-black text-slate-900 mt-2 line-clamp-1 group-hover:text-emerald-700 transition-smooth" title="<?= htmlspecialchars($item['name']) ?>">
                            <?= htmlspecialchars($item['name']) ?>
                        </h3>

                        <!-- Spesifikasi Nutrisi Ringkas -->
                        <div class="mt-2 py-1.5 px-2.5 bg-slate-50/80 rounded-xl border border-slate-100 flex items-center justify-between text-[11px] text-slate-600">
                            <div>
                                <span class="text-slate-400 block text-[9px] uppercase font-bold tracking-wider"><?= htmlspecialchars($item['spec_1_label'] ?: 'Protein') ?></span>
                                <span class="font-black text-slate-800"><?= htmlspecialchars($item['spec_1_value'] ?: '-') ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400 block text-[9px] uppercase font-bold tracking-wider"><?= htmlspecialchars($item['spec_2_label'] ?: 'Kadar Air') ?></span>
                                <span class="font-black text-slate-800"><?= htmlspecialchars($item['spec_2_value'] ?: '-') ?></span>
                            </div>
                        </div>

                        <!-- Stock Card Container with Integrated Stepper -->
                        <div class="bg-slate-50/80 rounded-2xl p-2.5 border border-slate-100 flex items-center justify-between mt-2.5">
                            <div>
                                <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Stok Ketersediaan</span>
                                <span id="card-stock-<?= $item['id'] ?>" class="font-black text-emerald-700 text-sm">
                                    <?= number_format($item['stock_current'], 0, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?>
                                </span>
                            </div>
                            <div class="inline-flex items-center bg-white rounded-xl p-0.5 border border-slate-200 shadow-2xs" onclick="event.stopPropagation()">
                                <button type="button" onclick="quickStockAdjust(<?= $item['id'] ?>, -1, 'Pengurangan 1 <?= $item['unit'] ?>')" class="w-6 h-6 flex items-center justify-center rounded-lg hover:bg-rose-50 text-slate-500 hover:text-rose-600 font-black text-xs transition-smooth" title="Kurang 1">-</button>
                                <span class="w-px h-3 bg-slate-200"></span>
                                <button type="button" onclick="quickStockAdjust(<?= $item['id'] ?>, 1, 'Penambahan 1 <?= $item['unit'] ?>')" class="w-6 h-6 flex items-center justify-center rounded-lg hover:bg-emerald-50 text-slate-500 hover:text-emerald-700 font-black text-xs transition-smooth" title="Tambah 1">+</button>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons Section -->
                    <div class="mt-4 pt-3 border-t border-slate-100 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" onclick='openDistributeModal(<?= json_encode($item) ?>)' class="inline-flex items-center justify-center space-x-1.5 py-2 px-3 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs transition-smooth" title="Salurkan Produk">
                                <span>📦 Salurkan</span>
                            </button>
                            <button type="button" onclick='openProductMapModal(<?= json_encode($item) ?>)' class="inline-flex items-center justify-center space-x-1.5 py-2 px-2.5 text-xs font-bold text-slate-700 hover:text-emerald-800 bg-slate-50 hover:bg-emerald-50 rounded-xl border border-slate-200 hover:border-emerald-300 transition-smooth" title="Lihat Peta Distribusi">
                                <span>📍 Peta Sebaran</span>
                            </button>
                        </div>

                        <button type="button" onclick='openDetailModal(<?= json_encode($item) ?>)' class="w-full inline-flex items-center justify-center space-x-1 text-xs font-bold text-slate-500 hover:text-emerald-700 py-1 transition-smooth cursor-pointer group/link">
                            <span>Detail Spesifikasi & Mutu</span>
                            <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover/link:translate-x-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty Search State -->
            <div id="no-results-produk" class="hidden text-center py-16 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <h3 class="text-base font-bold text-slate-800">Tidak ada pakan jadi yang cocok</h3>
                <p class="text-xs text-slate-500 mt-1">Coba sesuaikan kata kunci pencarian atau pilih kategori lain.</p>
            </div>
        </section>


        <!-- ======================================================== -->
        <!-- TAB 2: KELOLA BAHAN BAKU (CRUD PENUH) -->
        <!-- ======================================================== -->
        <section id="section-bahan-baku" class="hidden space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pt-1">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Kelola Stok Bahan Baku Pakan</h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl leading-relaxed">
                        Inventarisasi bahan baku pakan untuk Rumah Produksi BRMP DIY. Stok bahan baku ini akan berkurang otomatis ketika pakan jadi diproduksi.
                    </p>
                </div>
                <div class="flex items-center space-x-3 shrink-0">
                    <button onclick="refreshData()" class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-semibold shadow-xs transition-smooth">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Segarkan Data</span>
                    </button>
                    <button onclick="openAddModalWithType('bahan_baku')" class="inline-flex items-center space-x-1.5 px-5 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold shadow-xs transition-smooth">
                        <span>+ Tambah Bahan Baku</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Cards Grid Bahan Baku (CRUD Penuh) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 pt-2">
                <?php foreach ($bahanBakuList as $item): 
                    $isCritical = ($item['stock_current'] <= $item['stock_minimum']);
                ?>
                <div class="item-card-bahan-baku bg-white rounded-3xl p-5 border border-slate-200/90 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:border-emerald-300 hover:shadow-[0_16px_32px_rgba(5,150,105,0.08)] item-card-hover flex flex-col justify-between group transition-all"
                     data-name="<?= htmlspecialchars($item['name']) ?>"
                     data-code="<?= htmlspecialchars($item['code']) ?>"
                     data-category="<?= htmlspecialchars($item['category']) ?>"
                     data-critical="<?= $isCritical ? '1' : '0' ?>">
                    <div>
                        <!-- Product Image Area with Quick Action Badges -->
                        <div class="h-44 w-full bg-linear-to-b from-slate-50 to-slate-100/70 rounded-2xl flex items-center justify-center p-3 mb-3.5 overflow-hidden relative border border-slate-100 group-hover:border-slate-200 transition-colors">
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="max-h-full max-w-full object-contain rounded-xl transition-transform duration-300 group-hover:scale-105"
                                 loading="lazy">

                            <!-- Top Badges: Edit/Delete on Left, Code on Right -->
                            <div class="absolute top-2.5 left-2.5 flex items-center space-x-1 bg-white/90 backdrop-blur-md p-1 rounded-xl shadow-xs border border-slate-200/70 opacity-90 group-hover:opacity-100 transition-opacity">
                                <button type="button" onclick='event.stopPropagation(); openEditModal(<?= json_encode($item) ?>)' class="p-1 hover:bg-slate-100 text-slate-600 hover:text-blue-600 rounded-lg transition-smooth" title="Edit Item">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <span class="w-px h-3 bg-slate-200"></span>
                                <button type="button" onclick='event.stopPropagation(); confirmDeleteItem(<?= $item['id'] ?>, "<?= htmlspecialchars(addslashes($item['name'])) ?>")' class="p-1 hover:bg-rose-50 text-slate-600 hover:text-rose-600 rounded-lg transition-smooth" title="Hapus Item">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>

                            <span class="absolute top-2.5 right-2.5 bg-slate-900/80 backdrop-blur-md text-[10px] font-mono text-white font-bold px-2 py-0.5 rounded-lg shadow-xs">
                                <?= htmlspecialchars($item['code']) ?>
                            </span>

                            <?php if (!empty($item['category'])): ?>
                            <span class="absolute bottom-2.5 left-2.5 bg-white/90 backdrop-blur-md text-[10px] font-bold text-slate-700 px-2.5 py-0.5 rounded-full shadow-2xs border border-slate-200/80">
                                <?= htmlspecialchars($item['category']) ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Status Badge -->
                        <div class="flex items-center justify-between">
                            <?php if ($isCritical): ?>
                            <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/70 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                <span>Stok Kritis</span>
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/70 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>Tersedia</span>
                            </span>
                            <?php endif; ?>

                            <span class="text-[11px] font-semibold text-slate-400">
                                Min: <?= number_format($item['stock_minimum'], 0, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?>
                            </span>
                        </div>

                        <!-- Title -->
                        <h3 class="text-[15px] font-black text-slate-900 mt-2 line-clamp-1 group-hover:text-emerald-700 transition-smooth" title="<?= htmlspecialchars($item['name']) ?>">
                            <?= htmlspecialchars($item['name']) ?>
                        </h3>

                        <!-- Spesifikasi Nutrisi Ringkas -->
                        <div class="mt-2 py-1.5 px-2.5 bg-slate-50/80 rounded-xl border border-slate-100 flex items-center justify-between text-[11px] text-slate-600">
                            <div>
                                <span class="text-slate-400 block text-[9px] uppercase font-bold tracking-wider"><?= htmlspecialchars($item['spec_1_label'] ?: 'Parameter 1') ?></span>
                                <span class="font-black text-slate-800"><?= htmlspecialchars($item['spec_1_value'] ?: '-') ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400 block text-[9px] uppercase font-bold tracking-wider"><?= htmlspecialchars($item['spec_2_label'] ?: 'Parameter 2') ?></span>
                                <span class="font-black text-slate-800"><?= htmlspecialchars($item['spec_2_value'] ?: '-') ?></span>
                            </div>
                        </div>

                        <!-- Stock Card Container with Integrated Stepper -->
                        <div class="bg-slate-50/80 rounded-2xl p-2.5 border border-slate-100 flex items-center justify-between mt-2.5">
                            <div>
                                <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Stok Gudang</span>
                                <span id="card-stock-<?= $item['id'] ?>" class="font-black text-emerald-700 text-sm">
                                    <?= number_format($item['stock_current'], 0, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?>
                                </span>
                            </div>
                            <div class="inline-flex items-center bg-white rounded-xl p-0.5 border border-slate-200 shadow-2xs" onclick="event.stopPropagation()">
                                <button type="button" onclick="quickStockAdjust(<?= $item['id'] ?>, -10, 'Pengurangan 10 <?= $item['unit'] ?>')" class="w-6 h-6 flex items-center justify-center rounded-lg hover:bg-rose-50 text-slate-500 hover:text-rose-600 font-black text-xs transition-smooth" title="Kurang 10">-</button>
                                <span class="w-px h-3 bg-slate-200"></span>
                                <button type="button" onclick="quickStockAdjust(<?= $item['id'] ?>, 10, 'Penambahan 10 <?= $item['unit'] ?>')" class="w-6 h-6 flex items-center justify-center rounded-lg hover:bg-emerald-50 text-slate-500 hover:text-emerald-700 font-black text-xs transition-smooth" title="Tambah 10">+</button>
                            </div>
                        </div>
                    </div>

                    <!-- Card Bottom Action Link -->
                    <div class="mt-4 pt-3 border-t border-slate-100 space-y-2">
                        <button type="button" onclick='openQuickMutation(<?= $item['id'] ?>, "<?= htmlspecialchars(addslashes($item['name'])) ?>", "<?= htmlspecialchars($item['unit']) ?>", "IN")' class="w-full inline-flex items-center justify-center space-x-1.5 py-2 px-3 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs transition-smooth">
                            <span>➕ Tambah Stok Bahan Baku</span>
                        </button>
                        <button type="button" onclick='openDetailModal(<?= json_encode($item) ?>)' class="w-full inline-flex items-center justify-center space-x-1 text-xs font-bold text-slate-500 hover:text-emerald-700 py-1 transition-smooth cursor-pointer group/link">
                            <span>Detail Spesifikasi & Nutrisi</span>
                            <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover/link:translate-x-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty Search State -->
            <div id="no-results-bahan-baku" class="hidden text-center py-16 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <h3 class="text-base font-bold text-slate-800">Tidak ada bahan baku yang cocok</h3>
                <p class="text-xs text-slate-500 mt-1">Coba sesuaikan kata kunci pencarian atau pilih kategori lain.</p>
            </div>
        </section>


        <!-- ======================================================== -->
        <!-- TAB 3: LOG MUTASI LENGKAP & AUDIT TRAIL -->
        <!-- ======================================================== -->
        <section id="section-mutasi" class="hidden space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-1">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Riwayat Log Mutasi & Audit Trail</h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl leading-relaxed">
                        Catatan audit seluruh pergerakan barang masuk, keluar, hasil produksi pakan olahan, serta penyaluran distribusi ke mitra.
                    </p>
                </div>
                <button onclick="openModal('modal-mutation')" class="inline-flex items-center space-x-1.5 px-4 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold shadow-xs transition-smooth">
                    <span>+ Catat Mutasi Manual</span>
                </button>
            </div>

            <!-- Tabel Mutasi -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-[0_4px_25px_rgba(0,0,0,0.03)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/80 text-slate-600 uppercase text-[10px] border-b border-slate-100">
                            <tr>
                                <th class="py-3.5 px-4 font-bold">Tanggal</th>
                                <th class="py-3.5 px-4 font-bold">Nama Item</th>
                                <th class="py-3.5 px-4 font-bold">Tipe Komoditas</th>
                                <th class="py-3.5 px-4 font-bold">Jenis</th>
                                <th class="py-3.5 px-4 font-bold text-right">Jumlah</th>
                                <th class="py-3.5 px-4 font-bold">Petugas</th>
                                <th class="py-3.5 px-4 font-bold">Catatan / Lokasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($mutations)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-slate-400">Belum ada catatan mutasi inventaris.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($mutations as $m): ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3.5 px-4 font-medium text-slate-600"><?= htmlspecialchars(date('d M Y', strtotime($m['mutation_date']))) ?></td>
                                <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($m['item_name']) ?> <span class="text-[10px] text-slate-400 font-mono">(<?= htmlspecialchars($m['item_code']) ?>)</span></td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                                        <?= $m['item_type'] === 'produk' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $m['item_type'])) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase
                                        <?= $m['type'] === 'IN' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                        <?= $m['type'] === 'IN' ? 'MASUK' : 'KELUAR' ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-black <?= $m['type'] === 'IN' ? 'text-emerald-700' : 'text-rose-600' ?>">
                                    <?= $m['type'] === 'IN' ? '+' : '-' ?><?= number_format((float)($m['quantity'] ?? 0), 0, ',', '.') ?> <?= htmlspecialchars($m['unit']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 font-medium"><?= htmlspecialchars($m['officer_name'] ?? 'Tim BRMP') ?></td>
                                <td class="py-3.5 px-4 text-slate-500">
                                    <div class="line-clamp-1" title="<?= htmlspecialchars($m['notes'] ?? '-') ?>"><?= htmlspecialchars($m['notes'] ?? '-') ?></div>
                                    <?php if (!empty($m['location_name'])): ?>
                                    <div class="text-[10px] text-emerald-700 font-bold mt-0.5">📍 <?= htmlspecialchars($m['location_name']) ?></div>
                                    <?php elseif (!empty($m['destination_or_source'])): ?>
                                    <div class="text-[10px] text-slate-500 font-semibold mt-0.5">🏢 <?= htmlspecialchars($m['destination_or_source']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer Admin -->
    <footer class="bg-white border-t border-slate-200 mt-12 py-8 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <div class="flex items-center space-x-3">
                <img src="assets/img/logo-brmp.png" alt="Logo Resmi BRMP DIY" class="w-10 h-10 object-contain">
                <div class="text-left">
                    <p class="font-extrabold text-slate-800 text-sm">Rumah Produksi &bull; BRMP D.I. Yogyakarta</p>
                    <p class="text-[11px] text-slate-500">Panel Manajemen Khusus Administrator &bull; Hak Akses CRUD Terbatas</p>
                </div>
            </div>
            <div class="text-center sm:text-right text-[11px] text-slate-400">
                <p>&copy; <?= date('Y') ?> Balai Penerapan Standar Instrumen Pertanian / BRMP D.I. Yogyakarta.</p>
                <p class="mt-0.5"><a href="index.php" class="text-emerald-600 font-bold hover:underline">Kunjungi Halaman Publik (Pakan Jadi)</a></p>
            </div>
        </div>
    </footer>


    <!-- ======================================================== -->
    <!-- MODAL DIALOGS ADMIN (CRUD LENGKAP) -->
    <!-- ======================================================== -->

    <!-- Modal 1: Detail Spesifikasi Item -->
    <dialog id="modal-detail-item" class="w-full max-w-md bg-white p-6 rounded-3xl shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <span id="detail-code" class="text-xs font-mono font-bold text-emerald-600">CODE</span>
                <h3 id="detail-name" class="text-lg font-black text-slate-800 leading-tight">Nama Item</h3>
            </div>
            <button onclick="closeModal('modal-detail-item')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="space-y-4 mt-4">
            <!-- Product Image -->
            <div class="h-40 w-full bg-slate-50 rounded-2xl flex items-center justify-center p-3 overflow-hidden border border-slate-100">
                <img id="detail-img" src="" alt="Foto Item" class="max-h-full max-w-full object-contain rounded-xl">
            </div>

            <!-- General Info -->
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block">Tipe Fitur:</span>
                    <span id="detail-type" class="font-bold text-slate-800 uppercase">-</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block">Kategori:</span>
                    <span id="detail-category" class="font-bold text-slate-800">-</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block">Stok Tersedia:</span>
                    <span id="detail-stock" class="font-bold text-emerald-700">0</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block">Batas Minimum:</span>
                    <span id="detail-min" class="font-bold text-slate-800">0</span>
                </div>
            </div>

            <!-- Parameter Mutu / Spesifikasi Laboratorium -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-2.5">Parameter Mutu & Standar Uji BRMP</h4>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                        <span id="detail-spec1-label" class="text-slate-600 font-medium">Spesifikasi 1</span>
                        <span id="detail-spec1-val" class="font-bold text-slate-800">-</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                        <span id="detail-spec2-label" class="text-slate-600 font-medium">Spesifikasi 2</span>
                        <span id="detail-spec2-val" class="font-bold text-slate-800">-</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span id="detail-spec3-label" class="text-slate-600 font-medium">Spesifikasi 3</span>
                        <span id="detail-spec3-val" class="font-bold text-slate-800">-</span>
                    </div>
                </div>
            </div>

            <div class="text-xs text-slate-500 space-y-1">
                <div>Lokasi Gudang: <span id="detail-location" class="font-semibold text-slate-700">Gudang Utama</span></div>
                <div>Standar Harga: <span id="detail-price" class="font-semibold text-slate-700">Rp 0</span></div>
                <div class="pt-1 text-[11px] text-slate-400" id="detail-desc">Deskripsi singkat</div>
            </div>

            <!-- Footer Modal Detail -->
            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto">
                    <button id="btn-distribute-from-detail" type="button" onclick="triggerDistributeFromDetail()" class="px-3 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-xs transition-smooth">
                        📦 Salurkan
                    </button>
                    <button id="btn-map-from-detail" type="button" onclick="triggerMapFromDetail()" class="hidden px-3 py-1.5 text-xs font-bold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 rounded-xl shadow-xs transition-smooth">
                        📍 Peta
                    </button>
                    <button type="button" onclick="triggerEditFromDetail()" class="px-3 py-1.5 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-xl transition-smooth">
                        ✏️ Edit Data
                    </button>
                    <button type="button" onclick="triggerDeleteFromDetail()" class="px-2.5 py-1.5 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition-smooth" title="Hapus Item">
                        🗑️
                    </button>
                </div>
                <button type="button" onclick="closeModal('modal-detail-item')" class="w-full sm:w-auto px-5 py-2 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 rounded-xl transition-smooth">
                    Tutup
                </button>
            </div>
        </div>
    </dialog>

    <!-- Modal 2: Peta Sebaran Khusus Produk -->
    <dialog id="modal-product-map" class="w-full max-w-4xl bg-white p-6 rounded-3xl shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center space-x-3.5">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 p-1 border border-emerald-100 flex items-center justify-center overflow-hidden shrink-0">
                    <img id="pmap-img" src="" alt="Foto Produk" class="max-h-full max-w-full object-contain rounded-xl">
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span id="pmap-code" class="text-xs font-mono font-bold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded-md">CODE</span>
                        <span id="pmap-badge-type" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">KOMODITAS</span>
                    </div>
                    <h3 id="pmap-title" class="text-lg font-black text-slate-800 leading-tight mt-0.5">Peta Distribusi Komoditas</h3>
                </div>
            </div>
            <button onclick="closeModal('modal-product-map')" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition-smooth">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="space-y-4 mt-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-3 bg-emerald-50/70 rounded-2xl border border-emerald-100/80">
                    <span class="text-[11px] font-bold text-emerald-800 block">Total Volume Tersalurkan</span>
                    <span id="pmap-stat-volume" class="text-lg font-black text-emerald-700">0 Unit</span>
                </div>
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-500 block">Jumlah Titik Penyaluran</span>
                    <span id="pmap-stat-points" class="text-lg font-black text-slate-800">0 Titik Lokasi</span>
                </div>
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-500 block">Cakupan Wilayah DIY</span>
                    <span id="pmap-stat-areas" class="text-xs font-bold text-slate-700 mt-1 block truncate">Sleman, Bantul, dll</span>
                </div>
            </div>

            <div id="pmap-content-grid" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 relative">
                    <div id="product-specific-map" class="w-full h-[320px] sm:h-[350px] rounded-2xl overflow-hidden border border-slate-200 z-10 shadow-inner"></div>
                    <div class="absolute bottom-3 left-3 z-[400] bg-white/95 backdrop-blur-xs px-3 py-1.5 rounded-xl border border-slate-200 text-[11px] text-slate-600 shadow-sm flex items-center space-x-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                        <span class="font-medium">Peta Sebaran Minimalis &bull; Klik titik untuk info rincian</span>
                    </div>
                </div>

                <div class="flex flex-col h-[320px] sm:h-[350px]">
                    <div class="text-xs font-bold text-slate-700 mb-2 flex items-center justify-between">
                        <span>Titik Penyaluran</span>
                        <span class="text-[10px] text-slate-400 font-normal">Klik untuk zoom</span>
                    </div>
                    <div id="pmap-points-list" class="overflow-y-auto space-y-2 pr-1 flex-1 custom-scroll">
                        <div class="text-xs text-slate-400 py-8 text-center">Memuat daftar lokasi...</div>
                    </div>
                </div>
            </div>

            <div id="pmap-empty-state" class="hidden text-center py-10 bg-slate-50 rounded-2xl border border-slate-200/80">
                <div class="w-12 h-12 rounded-2xl bg-slate-200 text-slate-500 flex items-center justify-center mx-auto mb-2 text-xl">📍</div>
                <h4 class="text-sm font-bold text-slate-700">Belum Ada Titik Distribusi untuk Item Ini</h4>
                <p class="text-xs text-slate-500 mt-0.5 max-w-md mx-auto">Klik tombol <strong>"Salurkan"</strong> untuk mencatat penyaluran pertama dan memplot titik lokasi di peta.</p>
                <button type="button" onclick="closeModal('modal-product-map'); openDistributeModal(currentMapProductItem);" class="mt-3 px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl transition-smooth shadow-xs">
                    + Salurkan Sekarang
                </button>
            </div>
        </div>
    </dialog>

    <!-- Modal 3: Catat Distribusi & Tentukan Lokasi Peta -->
    <dialog id="modal-distribute" class="w-full max-w-2xl bg-white p-6 rounded-3xl shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center space-x-3">
                <div class="p-2.5 bg-emerald-100 text-emerald-700 rounded-2xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800">Catat Distribusi & Lokasi Penyaluran</h3>
                    <p class="text-xs text-slate-500">Salurkan produk/benih ke penerima dan tandai titik lokasi pada peta D.I. Yogyakarta</p>
                </div>
            </div>
            <button onclick="closeModal('modal-distribute')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="form-distribute" class="space-y-4 mt-4">
            <input type="hidden" id="dist-item-id" name="item_id">

            <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-100 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-mono font-bold text-emerald-700 uppercase" id="dist-item-code">CODE</span>
                    <h4 class="text-sm font-black text-slate-900" id="dist-item-name">Nama Komoditas</h4>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-slate-500 block">Stok Gudang:</span>
                    <span class="text-sm font-black text-emerald-800" id="dist-item-stock">0 Unit</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Disalurkan *</label>
                    <div class="relative">
                        <input type="number" step="0.1" min="0.1" id="dist-amount" name="amount" required placeholder="0" class="w-full pl-3 pr-12 py-2 text-sm border border-slate-300 rounded-xl font-bold focus:ring-2 focus:ring-emerald-500">
                        <span id="dist-unit-label" class="absolute right-3 top-2.5 text-xs font-bold text-slate-400">kg</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Distribusi *</label>
                    <input type="date" name="mutation_date" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Tempat / Kelompok Tani Penerima *</label>
                <input type="text" id="dist-recipient" name="recipient_location" required placeholder="Contoh: Gapoktan Tani Makmur, Kalasan, Sleman" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl">
            </div>

            <!-- Bagian Pemilihan Lokasi di Peta -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-slate-700">Tentukan Titik Koordinat Lokasi di Peta *</label>
                    <span class="text-[11px] text-slate-400">Klik langsung pada peta untuk menandai</span>
                </div>

                <div class="flex flex-wrap gap-1.5 mb-1.5">
                    <span class="text-[10px] text-slate-400 font-bold self-center">Pilihan Cepat DIY:</span>
                    <button type="button" onclick="setDistCoords(-7.7156, 110.3556, 'Sleman - Balai Benih & Pakan')" class="text-[11px] px-2.5 py-0.5 bg-slate-100 hover:bg-emerald-100 text-slate-700 rounded-lg">📍 Sleman</button>
                    <button type="button" onclick="setDistCoords(-7.8878, 110.3278, 'Bantul - Gapoktan Imogiri')" class="text-[11px] px-2.5 py-0.5 bg-slate-100 hover:bg-emerald-100 text-slate-700 rounded-lg">📍 Bantul</button>
                    <button type="button" onclick="setDistCoords(-7.9667, 110.6000, 'Gunungkidul - Wonosari')" class="text-[11px] px-2.5 py-0.5 bg-slate-100 hover:bg-emerald-100 text-slate-700 rounded-lg">📍 Gunungkidul</button>
                    <button type="button" onclick="setDistCoords(-7.8572, 110.1589, 'Kulon Progo - Wates')" class="text-[11px] px-2.5 py-0.5 bg-slate-100 hover:bg-emerald-100 text-slate-700 rounded-lg">📍 Kulon Progo</button>
                    <button type="button" onclick="setDistCoords(-7.7956, 110.3695, 'Kota Yogyakarta - Gondomanan')" class="text-[11px] px-2.5 py-0.5 bg-slate-100 hover:bg-emerald-100 text-slate-700 rounded-lg">📍 Kota Yogya</button>
                </div>

                <div id="dist-picker-map" class="w-full h-[220px] rounded-2xl overflow-hidden border border-slate-300 shadow-inner z-10"></div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-mono text-slate-400">Latitude</label>
                        <input type="text" id="dist-lat" name="latitude" required readonly class="w-full px-2.5 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-slate-400">Longitude</label>
                        <input type="text" id="dist-lng" name="longitude" required readonly class="w-full px-2.5 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Petugas Penyaluran</label>
                    <input type="text" name="officer_name" value="<?= htmlspecialchars($_SESSION['admin_user'] ?? 'Petugas Lapangan BRMP DIY') ?>" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Tambahan</label>
                    <input type="text" name="notes" placeholder="No. SPK / Berita Acara Serah Terima..." class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl">
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-distribute')" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-smooth">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-md transition-smooth flex items-center space-x-1.5">
                    <span>Simpan & Plot ke Peta</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </div>
        </form>
    </dialog>

    <!-- Modal 4: Hasil Konfirmasi Plot Minimap -->
    <dialog id="modal-distribution-result" class="w-full max-w-lg bg-white p-6 rounded-3xl shadow-2xl">
        <div class="text-center pb-2">
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto mb-2 text-xl font-bold">
                ✓
            </div>
            <h3 class="text-base font-black text-slate-800">Distribusi Berhasil Dicatat & Diplot!</h3>
            <p class="text-xs text-slate-500">Stok telah berkurang dan titik lokasi telah disematkan ke minimap sebaran BRMP DIY.</p>
        </div>

        <div class="space-y-3 mt-3">
            <div id="result-minimap" class="w-full h-[220px] rounded-2xl overflow-hidden border border-slate-200 shadow-inner z-10"></div>
            <div class="p-3 bg-slate-50 rounded-2xl text-xs space-y-1 text-slate-600 border border-slate-100">
                <div>Komoditas: <span id="res-item-name" class="font-bold text-slate-900">-</span></div>
                <div>Jumlah Disalurkan: <span id="res-item-amount" class="font-bold text-rose-600">-</span></div>
                <div>Lokasi Penerima: <span id="res-item-location" class="font-bold text-emerald-800">-</span></div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="button" onclick="closeModal('modal-distribution-result')" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md transition-smooth">
                    Selesai & Tutup
                </button>
            </div>
        </div>
    </dialog>

    <!-- Modal 5: Tambah Komoditas Baru (Super Simpel & Multi-Slide untuk Pakan Jadi) -->
    <dialog id="modal-add-item" class="w-full max-w-lg bg-white p-6 sm:p-7 rounded-3xl shadow-2xl">
        <!-- Header Modal -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center space-x-3">
                <div id="add-modal-icon-container" class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xl border border-emerald-100">
                    📦
                </div>
                <div>
                    <h3 id="add-modal-title" class="text-base sm:text-lg font-black text-slate-900">Tambah Pakan Jadi Baru</h3>
                    <p id="add-modal-subtitle" class="text-[11px] text-slate-400 font-semibold">Langkah 1 dari 2: Informasi Pakan</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-add-item')" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition-smooth">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Stepper Indicator (Hanya muncul jika menambah Pakan Jadi) -->
        <div id="add-stepper-bar" class="flex items-center justify-between mt-3 mb-2 px-1">
            <div class="flex items-center space-x-2">
                <span id="step-dot-1" class="w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center shadow-xs">1</span>
                <span id="step-text-1" class="text-xs font-bold text-emerald-800">1. Nama, Berat & Foto</span>
            </div>
            <div class="h-0.5 flex-1 bg-slate-200 mx-3"></div>
            <div class="flex items-center space-x-2">
                <span id="step-dot-2" class="w-6 h-6 rounded-full bg-slate-200 text-slate-500 font-bold text-xs flex items-center justify-center">2</span>
                <span id="step-text-2" class="text-xs font-semibold text-slate-400">2. Potong Bahan Baku</span>
            </div>
        </div>

        <form id="form-add-item" enctype="multipart/form-data" class="mt-4">
            <input type="hidden" name="type" id="add-type" value="produk">

            <!-- SLIDE 1: Info Dasar (Nama, Jumlah Berat, Foto) -->
            <div id="add-slide-1" class="space-y-4">
                <!-- 1. Nama Item -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Nama <span id="label-name-type">Pakan Jadi</span> <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" id="add-name" required placeholder="Contoh: Pelet Apung Nila Super" 
                           class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-2xl font-bold focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all placeholder:text-slate-400 placeholder:font-normal">
                </div>

                <!-- 2. Jumlah Berat -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Jumlah Berat <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number" step="0.1" min="0.1" name="stock_current" id="add-stock" required placeholder="0" 
                               class="w-full pl-3.5 pr-14 py-2.5 text-base border border-slate-300 rounded-2xl font-black text-slate-900 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all">
                        <span class="absolute right-3.5 top-3 text-xs font-bold text-slate-400 uppercase">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Total kuantitas berat awal yang ditambahkan ke stok gudang.</p>
                </div>

                <!-- 3. Foto Produk / Bahan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Foto Produk / Bahan
                    </label>
                    <div class="space-y-2.5">
                        <div class="flex items-center gap-2">
                            <input type="file" name="image_file" id="add-image-file" accept="image/*" onchange="previewAddImage(this)"
                                   class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-slate-200 rounded-2xl p-1 bg-slate-50/50">
                        </div>
                        <div class="relative">
                            <input type="text" name="image_url" id="add-image-url" placeholder="Atau paste link URL gambar foto (https://...)" 
                                   oninput="previewAddImageUrl(this.value)"
                                   class="w-full px-3.5 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 placeholder:text-slate-400">
                        </div>

                        <!-- Preview Foto Box -->
                        <div id="add-image-preview-container" class="hidden h-32 w-full bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden flex items-center justify-center p-2 relative group">
                            <img id="add-image-preview" src="" alt="Preview Foto" class="max-h-full max-w-full object-contain rounded-xl">
                            <span class="absolute bottom-1.5 right-2 bg-black/60 backdrop-blur-xs text-white text-[10px] px-2 py-0.5 rounded-full font-mono">Foto Terpilih</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons Slide 1 -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeModal('modal-add-item')" class="px-4 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-smooth">
                        Batal
                    </button>
                    <!-- Untuk Pakan Jadi: Tombol Lanjut ke Slide 2 -->
                    <button type="button" id="btn-next-slide" onclick="goToSlide2()" class="px-5 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-md transition-smooth flex items-center space-x-1.5">
                        <span>Lanjut: Potong Bahan Baku</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <!-- Untuk Bahan Baku: Tombol Langsung Simpan -->
                    <button type="submit" id="btn-submit-single" class="hidden px-5 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-md transition-smooth">
                        Simpan Bahan Baku
                    </button>
                </div>
            </div>

            <!-- SLIDE 2: Pengurangan Bahan Baku (Hanya Pakan Jadi, Fleksibel) -->
            <div id="add-slide-2" class="hidden space-y-4">
                <div class="bg-amber-50/80 border border-amber-200/90 rounded-2xl p-3.5">
                    <div class="flex items-center space-x-2 text-amber-800 text-xs font-bold mb-1">
                        <span>🌾 Pengurangan Stok Bahan Baku (Fleksibel & Opsional)</span>
                    </div>
                    <p class="text-[11px] text-amber-700 leading-relaxed">
                        Pilih bahan baku yang digunakan untuk pembuatan <strong id="summary-feed-name" class="font-extrabold text-amber-900 underline">Pakan</strong> ini. Stok bahan baku yang dipilih akan otomatis dipotong dari gudang. Jika tidak ingin mengurangi bahan baku, bagian ini bisa dilewati.
                    </p>
                </div>

                <!-- Dynamic List Pengurangan Bahan Baku -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-700">Daftar Bahan Baku yang Dikurangi:</label>
                        <button type="button" onclick="addDeductRow()" class="text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-xl border border-emerald-200 transition-smooth flex items-center space-x-1 cursor-pointer">
                            <span>+ Tambah Bahan Baku</span>
                        </button>
                    </div>

                    <div id="deduct-rows-container" class="space-y-2 max-h-56 overflow-y-auto pr-1 custom-scroll">
                        <!-- Dynamic Rows generated by JS -->
                    </div>

                    <div id="empty-deduct-notice" class="text-center py-4 px-3 bg-slate-50 border border-dashed border-slate-200 rounded-2xl text-xs text-slate-400">
                        Belum ada bahan baku yang dipilih untuk dipotong. Klik <strong class="text-emerald-700">+ Tambah Bahan Baku</strong> di atas untuk memotong stok bahan baku.
                    </div>
                </div>

                <!-- Action Buttons Slide 2 -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <button type="button" onclick="goToSlide1()" class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-smooth flex items-center space-x-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span>Kembali</span>
                    </button>
                    <button type="submit" id="btn-submit-with-deduct" class="px-6 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-md transition-smooth flex items-center space-x-1.5 cursor-pointer">
                        <span>Simpan Pakan Jadi & Potong Stok</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </div>
            </div>
        </form>
    </dialog>

    <!-- Modal 6: Edit Item (CRUD Sederhana: Nama, Berat, Foto) -->
    <dialog id="modal-edit-item" class="w-full max-w-md bg-white p-6 rounded-3xl shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center space-x-2.5">
                <span class="text-xl">✏️</span>
                <h3 class="text-base sm:text-lg font-black text-slate-800">Edit Data Komoditas</h3>
            </div>
            <button type="button" onclick="closeModal('modal-edit-item')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="form-edit-item" enctype="multipart/form-data" class="space-y-4 mt-4">
            <input type="hidden" id="edit-id" name="id">
            <input type="hidden" id="edit-type" name="type">

            <!-- Nama -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Item *</label>
                <input type="text" id="edit-name" name="name" required class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-2xl font-bold focus:ring-2 focus:ring-emerald-500">
            </div>

            <!-- Jumlah Berat / Stok Saat Ini -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Jumlah Berat (Stok Saat Ini) *</label>
                <div class="relative">
                    <input type="number" step="0.1" id="edit-stock-current" name="stock_current" required class="w-full pl-3.5 pr-14 py-2.5 text-base border border-slate-300 rounded-2xl font-black text-slate-900 focus:ring-2 focus:ring-emerald-500">
                    <span id="edit-unit-label" class="absolute right-3.5 top-3 text-xs font-bold text-slate-400 uppercase">kg</span>
                </div>
            </div>

            <!-- Foto -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Foto Produk / Bahan</label>
                <div class="space-y-2">
                    <input type="file" name="image_file" id="edit-image-file" accept="image/*" onchange="previewEditImage(this)"
                           class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-slate-200 rounded-2xl p-1 bg-slate-50/50">
                    <input type="text" id="edit-image" name="image_url" placeholder="Atau link URL foto (https://...)" oninput="previewEditImageUrl(this.value)"
                           class="w-full px-3.5 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                    <div id="edit-image-preview-container" class="h-28 w-full bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden flex items-center justify-center p-2 relative">
                        <img id="edit-image-preview" src="" alt="Foto Item" class="max-h-full max-w-full object-contain rounded-xl">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                <button type="button" onclick="confirmDeleteItemFromEdit()" class="inline-flex items-center space-x-1.5 px-3.5 py-2 text-xs font-bold text-rose-600 hover:text-white bg-rose-50 hover:bg-rose-600 rounded-xl border border-rose-200 transition-smooth">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>Hapus</span>
                </button>
                <div class="flex items-center space-x-2">
                    <button type="button" onclick="closeModal('modal-edit-item')" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-smooth">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-md transition-smooth">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </dialog>

    <!-- Modal 9: Catat Mutasi Manual (Masuk / Keluar) -->
    <dialog id="modal-mutation" class="w-full max-w-md bg-white p-6 rounded-3xl shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-800">Catat Mutasi Stok Manual</h3>
            <button onclick="closeModal('modal-mutation')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="form-mutation" class="space-y-4 mt-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Komoditas *</label>
                <select name="item_id" id="mutation-item-id" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl">
                    <option value="">-- Pilih Item --</option>
                    <optgroup label="Bahan Baku">
                        <?php foreach ($bahanBakuList as $it): ?>
                        <option value="<?= $it['id'] ?>" data-unit="<?= htmlspecialchars($it['unit']) ?>" data-stock="<?= $it['stock_current'] ?>">
                            🌾 <?= htmlspecialchars($it['name']) ?> (Stok: <?= number_format($it['stock_current'], 0, ',', '.') ?> <?= $it['unit'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Produk Pakan Jadi">
                        <?php foreach ($produkList as $it): ?>
                        <option value="<?= $it['id'] ?>" data-unit="<?= htmlspecialchars($it['unit']) ?>" data-stock="<?= $it['stock_current'] ?>">
                            📦 <?= htmlspecialchars($it['name']) ?> (Stok: <?= number_format($it['stock_current'], 0, ',', '.') ?> <?= $it['unit'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Arah Mutasi *</label>
                    <select name="type" id="mutation-type" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl font-bold">
                        <option value="IN">➕ Stok MASUK</option>
                        <option value="OUT">➖ Stok KELUAR</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah *</label>
                    <input type="number" step="0.1" min="0.1" name="amount" id="mutation-amount" required placeholder="0" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl font-black">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Transaksi</label>
                <input type="date" name="mutation_date" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Petugas BRMP</label>
                <input type="text" name="officer_name" value="<?= htmlspecialchars($_SESSION['admin_user'] ?? 'Petugas Gudang BRMP DIY') ?>" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan / Keterangan</label>
                <input type="text" name="notes" placeholder="Penerimaan suplai, retur, atau penggunaan..." class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl">
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-mutation')" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-smooth">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-md transition-smooth">
                    Simpan Mutasi
                </button>
            </div>
        </form>
    </dialog>

    <!-- Modal 10: Integrasi Google Spreadsheet & Vercel Sync -->
    <dialog id="modal-gsheet" class="w-full max-w-xl bg-white p-6 rounded-3xl shadow-2xl backdrop:bg-slate-900/60">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center space-x-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                    📊
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900">Integrasi Google Spreadsheet</h3>
                    <p class="text-xs text-slate-500 font-medium">Sinkronisasi permanen cloud (Vercel & Render ready)</p>
                </div>
            </div>
            <button onclick="closeModal('modal-gsheet')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="space-y-4 mt-4">
            <!-- Status Badge -->
            <div id="gsheet-status-box" class="p-3.5 rounded-2xl border <?= $isGsheetConnected ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' ?> flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full <?= $isGsheetConnected ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500' ?>"></span>
                    <span id="gsheet-status-text" class="text-xs font-bold <?= $isGsheetConnected ? 'text-emerald-900' : 'text-amber-900' ?>">
                        <?= $isGsheetConnected ? 'Terhubung ke Google Apps Script' : 'Belum Terhubung: Masukkan Web App URL di bawah' ?>
                    </span>
                </div>
                <span class="text-[11px] font-semibold text-slate-500">Google Drive API</span>
            </div>

            <!-- Form Simpan URL -->
            <form id="form-gsheet-url" onsubmit="saveGsheetUrl(event)" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        URL Web App Google Apps Script *
                    </label>
                    <input type="url" id="input-gsheet-url" required value="<?= htmlspecialchars($gsheetUrl) ?>" placeholder="https://script.google.com/macros/s/.../exec" class="w-full px-3 py-2 text-xs font-mono border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                    <p class="text-[11px] text-slate-500 mt-1">
                        Didapat dari menu <em>Extensions > Apps Script > Deploy as Web App</em> pada file Google Spreadsheet Anda.
                    </p>
                </div>

                <div class="flex items-center justify-end space-x-2">
                    <button type="submit" id="btn-save-gsheet" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow transition-smooth">
                        💾 Simpan & Tes Koneksi
                    </button>
                </div>
            </form>

            <!-- Aksi Sinkronisasi Manual -->
            <div class="pt-3 border-t border-slate-100">
                <span class="block text-xs font-bold text-slate-700 mb-2">Aksi Sinkronisasi Database:</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <button type="button" onclick="syncGsheetNow()" id="btn-sync-gsheet" class="flex items-center justify-center space-x-2 px-3.5 py-2.5 text-xs font-bold text-emerald-900 bg-emerald-100 hover:bg-emerald-200 rounded-xl transition-smooth">
                        <span>⚡</span>
                        <span>Kirim Data ke Google Sheet</span>
                    </button>
                    <button type="button" onclick="pullGsheetNow()" id="btn-pull-gsheet" class="flex items-center justify-center space-x-2 px-3.5 py-2.5 text-xs font-bold text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-smooth">
                        <span>📥</span>
                        <span>Tarik Data dari Sheet ke Web</span>
                    </button>
                </div>
            </div>

            <!-- Panduan Singkat 4 Langkah -->
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 text-[11px] text-slate-600 space-y-1.5">
                <p class="font-bold text-slate-800 text-xs flex items-center space-x-1.5">
                    <span>💡</span>
                    <span>Cara Pasang Google Sheet (Cuma 2 Menit):</span>
                </p>
                <ol class="list-decimal list-inside space-y-1 pl-1">
                    <li>Buat file baru di Google Spreadsheet (beri nama misal: <em>Database BRMP DIY</em>).</li>
                    <li>Buka menu <strong>Extensions (Ekstensi) &gt; Apps Script</strong>.</li>
                    <li>Copy seluruh isi file <code class="bg-white px-1 py-0.5 rounded border border-slate-200 font-mono text-emerald-700">google-apps-script.js</code> lalu paste di Apps Script.</li>
                    <li>Klik <strong>Deploy &gt; New deployment &gt; Web app</strong> (pilih Who has access: <em>Anyone</em>), lalu salin URL-nya ke form di atas!</li>
                </ol>
            </div>
        </div>
    </dialog>

    <!-- Toast Notification -->
    <div id="toast-notification" class="fixed bottom-5 right-5 z-50 flex items-center p-4 text-white bg-emerald-600 rounded-xl shadow-2xl transition-all duration-300 transform translate-y-10 opacity-0 pointer-events-none">
        <svg id="toast-icon" class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span id="toast-message" class="text-sm font-semibold">Operasi berhasil</span>
    </div>

    <!-- Scripts -->
    <script>
        const initialRawMaterials = <?= json_encode(array_map(function($b) {
            return [
                'id' => (int)$b['id'],
                'name' => $b['name'],
                'stock' => (float)$b['stock_current'],
                'unit' => $b['unit']
            ];
        }, $bahanBakuList), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>

