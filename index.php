<?php
// index.php - Katalog Publik Rumah Produksi BRMP DIY (Khusus Pakan Jadi)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$currentWhatsApp = get_app_setting($pdo, 'whatsapp_number', '');

// Ambil HANYA komoditas Produk / Pakan Jadi
$pakanList = $pdo->query("SELECT * FROM inventory_items WHERE type = 'produk' ORDER BY id ASC")->fetchAll();

// Kategori unik untuk filter pills
$catPakan = array_unique(array_filter(array_column($pakanList, 'category')));

// Metrik ringkas publik
$totalVarian = count($pakanList);
$totalStokKg = 0;
$stokTersediaCount = 0;

foreach ($pakanList as $item) {
    $totalStokKg += $item['stock_current'];
    if ($item['stock_current'] > $item['stock_minimum']) {
        $stokTersediaCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Pakan Jadi &bull; iCare NTB</title>
    <!-- Favicon Logo Resmi -->
    <link rel="icon" type="image/png" href="assets/img/logo-brmp.png">
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">
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

    <!-- Top Navigation Header -->
    <header class="bg-gradient-to-r from-[#064e3b] via-[#047857] to-[#065f46] text-white shadow-xl sticky top-0 z-40 border-b border-emerald-600/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Branding -->
                <div class="flex items-center space-x-3.5">
                    <a href="index.php" class="relative group block shrink-0" title="iCare NTB - Sistem Monitoring Produksi Pakan">
                        <div class="w-13 h-13 p-1 bg-white/10 hover:bg-white/20 rounded-2xl border border-white/25 shadow-lg backdrop-blur-md transition-smooth flex items-center justify-center">
                            <img src="assets/img/logo-brmp.png" alt="Logo Resmi iCare NTB" class="w-11 h-11 object-contain drop-shadow-md transform group-hover:scale-105 transition-smooth">
                        </div>
                    </a>
                    <div class="flex items-center space-x-2.5">
                        <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-white uppercase drop-shadow-sm">Rumah Produksi</h1>
                        <span class="bg-amber-400 text-slate-900 text-xs px-2.5 py-0.5 rounded-full font-black tracking-wider uppercase shadow-xs">iCare NTB</span>
                    </div>
                </div>

                <!-- Right Header Actions (WhatsApp & Refresh) -->
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <?php if (!empty($currentWhatsApp)): 
                        $waClean = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $currentWhatsApp));
                    ?>
                    <a href="https://wa.me/<?= $waClean ?>?text=Halo%20Admin%20iCare%20NTB,%20saya%20tertarik%20dengan%20katalog%20pakan%20jadi%20dan%20ingin%20berkonsultasi%20atau%20memesan." target="_blank" class="inline-flex items-center space-x-1.5 px-3 sm:px-4 py-2 rounded-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold text-xs sm:text-sm shadow-md transition-smooth">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        <span class="hidden sm:inline">Pesan via WhatsApp</span>
                        <span class="sm:hidden">WhatsApp</span>
                    </a>
                    <?php endif; ?>

                    <button onclick="window.location.reload()" class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-smooth" title="Segarkan Halaman">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 flex-1 w-full space-y-8">

        <!-- ======================================================== -->
        <!-- HERO AREA: KATALOG KHUSUS PAKAN JADI -->
        <!-- ======================================================== -->
        <section class="bg-gradient-to-br from-white via-white to-emerald-50/50 rounded-3xl p-6 sm:p-8 border border-slate-100 shadow-[0_4px_25px_rgba(0,0,0,0.03)] relative overflow-hidden">
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs font-bold mb-3 shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Katalog Resmi Komoditas Pakan Jadi</span>
                    </div>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
                        Ketersediaan Pakan Jadi Bermutu & Bersertifikasi
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-2 leading-relaxed">
                        Katalog resmi produk formulasi pakan siap pakai untuk ikan air tawar, unggas, dan ternak ruminansia yang diproduksi dengan standar instrumen pengujian mutu iCare NTB.
                    </p>
                </div>

                <!-- 2 Quick Metrics Cards -->
                <div class="grid grid-cols-2 gap-2.5 sm:gap-3.5 shrink-0">
                    <div class="bg-white/90 backdrop-blur-md p-3 sm:p-4 rounded-2xl border border-slate-200/80 shadow-2xs text-center min-w-[120px] sm:min-w-[140px]">
                        <span class="text-[11px] font-bold text-slate-400 block uppercase">Varian Pakan</span>
                        <span class="text-xl sm:text-2xl font-black text-slate-900 block mt-0.5"><?= $totalVarian ?></span>
                        <span class="text-[10px] text-emerald-700 font-bold block mt-0.5">Formulasi Siap Pakai</span>
                    </div>
                    <div class="bg-white/90 backdrop-blur-md p-3 sm:p-4 rounded-2xl border border-slate-200/80 shadow-2xs text-center min-w-[120px] sm:min-w-[140px]">
                        <span class="text-[11px] font-bold text-slate-400 block uppercase">Total Stok</span>
                        <span class="text-xl sm:text-2xl font-black text-[#059669] block mt-0.5"><?= number_format($totalStokKg, 0, ',', '.') ?></span>
                        <span class="text-[10px] text-slate-500 font-medium block mt-0.5">Kilogram Tersedia</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- GRID KATALOG PAKAN JADI (4 KOLOM SESUAI REFERENSI) -->
        <!-- ======================================================== -->
        <section class="space-y-4">
            <div id="grid-pakan-jadi" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 pt-2">
                <?php foreach ($pakanList as $item): 
                    $isCritical = ($item['stock_current'] <= $item['stock_minimum']);
                ?>
                <div class="item-card-pakan bg-white rounded-3xl p-5 border border-slate-200/90 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:border-emerald-300 hover:shadow-[0_16px_32px_rgba(5,150,105,0.08)] item-card-hover flex flex-col justify-between group transition-all"
                     data-name="<?= htmlspecialchars($item['name']) ?>"
                     data-code="<?= htmlspecialchars($item['code']) ?>"
                     data-category="<?= htmlspecialchars($item['category']) ?>"
                     data-critical="<?= $isCritical ? '1' : '0' ?>">
                    <div>
                        <!-- Product Image Area -->
                        <div class="h-44 w-full bg-linear-to-b from-slate-50 to-slate-100/70 rounded-2xl flex items-center justify-center p-3 mb-3.5 overflow-hidden relative border border-slate-100 group-hover:border-slate-200 transition-colors">
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="max-h-full max-w-full object-contain rounded-xl transition-transform duration-300 group-hover:scale-105"
                                 loading="lazy">
                            <span class="absolute top-2.5 right-2.5 bg-slate-900/80 backdrop-blur-md text-[10px] font-mono text-white font-bold px-2 py-0.5 rounded-lg shadow-xs">
                                <?= htmlspecialchars($item['code']) ?>
                            </span>
                            <?php if (!empty($item['category'])): ?>
                            <span class="absolute bottom-2.5 left-2.5 bg-white/90 backdrop-blur-md text-[10px] font-bold text-slate-700 px-2.5 py-0.5 rounded-full shadow-2xs border border-slate-200/80">
                                <?= htmlspecialchars($item['category']) ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Status Badge (Tersedia / Stok Terbatas) -->
                        <div class="flex items-center justify-between">
                            <?php if ($isCritical): ?>
                            <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/70 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                <span>Stok Terbatas</span>
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

                        <!-- Stock Box Container -->
                        <div class="bg-slate-50/80 rounded-2xl p-2.5 border border-slate-100 flex items-center justify-between mt-2.5">
                            <span class="text-[11px] font-semibold text-slate-500">Stok Ketersediaan:</span>
                            <span class="font-black text-emerald-700 text-sm">
                                <?= number_format($item['stock_current'], 0, ',', '.') ?> <?= htmlspecialchars($item['unit']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card Bottom Action Link (Read-Only Publik) -->
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <button type="button" onclick='openDetailModalPublic(<?= json_encode($item) ?>)' class="w-full inline-flex items-center justify-center space-x-1.5 py-2.5 px-4 text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/60 rounded-xl transition-smooth shadow-2xs cursor-pointer group/link">
                            <span>Lihat Detail & Pemesanan</span>
                            <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover/link:translate-x-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty Search State -->
            <div id="no-results-pakan" class="hidden text-center py-16 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2 text-xl font-bold">🔍</div>
                <h3 class="text-base font-bold text-slate-800">Tidak ada varian pakan jadi yang cocok</h3>
                <p class="text-xs text-slate-500 mt-1">Coba sesuaikan kata kunci pencarian atau pilih kategori pakan lain di atas.</p>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- PETA SEBARAN DISTRIBUSI PENYALURAN PAKAN (PUBLIK) -->
        <!-- ======================================================== -->
        <section class="mt-14 pt-8 border-t border-slate-200/80">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-5">
                <div>
                    <div class="inline-flex items-center space-x-2 text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200/60 mb-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Peta Interaktif Penyaluran Pakan</span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Peta Sebaran Titik Distribusi Pakan</h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">Transparansi titik lokasi penyaluran komoditas pakan ke kelompok tani binaan dan peternak di wilayah Nusa Tenggara Barat (NTB) dan sekitarnya.</p>
                </div>
                <div class="flex items-center space-x-2 shrink-0">
                    <button type="button" onclick="fitPublicDistributionMap()" class="inline-flex items-center space-x-1.5 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl shadow-xs transition-smooth">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        <span>Lihat Semua Titik</span>
                    </button>
                    <span id="pub-map-point-count" class="px-3 py-2 text-xs font-bold text-emerald-800 bg-emerald-100 rounded-xl">0 Titik Terdata</span>
                </div>
            </div>

            <!-- Map & Distribution Details Container Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- Big Map Container (8 cols on large screens) -->
                <div class="lg:col-span-8 bg-white rounded-3xl p-3 sm:p-4 border border-slate-200 shadow-[0_8px_30px_rgba(0,0,0,0.04)] relative flex flex-col">
                    <div id="public-distribution-map" class="w-full h-[460px] sm:h-[550px] rounded-2xl overflow-hidden border border-slate-200 shadow-inner z-10"></div>
                    <div class="mt-3 flex flex-wrap items-center justify-between text-xs text-slate-500 px-2 gap-2">
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center space-x-1.5">
                                <span class="w-3 h-3 rounded-full bg-emerald-600 border-2 border-white shadow-xs inline-block"></span>
                                <span class="text-[11px] font-semibold text-slate-600">Titik Penyaluran Pakan</span>
                            </div>
                            <span class="text-slate-300">|</span>
                            <span class="text-[11px] text-slate-400">Klik marker pada peta untuk melihat rincian produk yang disalurkan</span>
                        </div>
                        <span class="text-[11px] text-slate-400 font-mono">iCare NTB Geo-Distribution</span>
                    </div>
                </div>

                <!-- Side Panel: Rincian Produk & Lokasi Penyaluran (4 cols on large screens) -->
                <div class="lg:col-span-4 bg-white rounded-3xl p-4 sm:p-5 border border-slate-200 shadow-[0_8px_30px_rgba(0,0,0,0.04)] flex flex-col h-[520px] sm:h-[610px]">
                    <div class="pb-3 border-b border-slate-100 shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-black text-slate-900 flex items-center space-x-2">
                                <span>🌾</span>
                                <span>Rincian Produk Tersalurkan</span>
                            </h3>
                            <span id="pub-total-volume-badge" class="px-2.5 py-1 text-[11px] font-black text-emerald-800 bg-emerald-50 rounded-xl border border-emerald-100">
                                Total: 0 kg
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Daftar produk pakan yang telah disalurkan ke kelompok tani & peternak.</p>
                        
                        <!-- Search / Filter in Side Panel -->
                        <div class="mt-3 relative">
                            <input type="text" id="pub-dist-search" placeholder="Cari nama produk / lokasi..." 
                                   oninput="filterPublicDistList(this.value)"
                                   class="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none placeholder:text-slate-400">
                            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Scrollable list of distributed items -->
                    <div id="pub-dist-list-container" class="flex-1 overflow-y-auto space-y-2.5 mt-3 pr-1 custom-scroll">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer Publik -->
    <footer class="bg-white border-t border-slate-200 mt-12 py-8 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <div class="flex items-center space-x-3">
                <img src="assets/img/logo-brmp.png" alt="Logo Resmi iCare NTB" class="w-10 h-10 object-contain">
                <div class="text-left">
                    <p class="font-extrabold text-slate-800 text-sm">Rumah Produksi &bull; iCare NTB</p>
                    <p class="text-[11px] text-slate-500">Katalog Resmi Ketersediaan Pakan Jadi Olahan Terstandar</p>
                </div>
            </div>
            <div class="text-center sm:text-right text-[11px] text-slate-400">
                <p>&copy; <?= date('Y') ?> iCare NTB - Sistem Monitoring Produksi Pakan.</p>
            </div>
        </div>
    </footer>


    <!-- ======================================================== -->
    <!-- MODAL DETAIL PAKAN JADI (READ-ONLY PUBLIK) -->
    <!-- ======================================================== -->
    <dialog id="modal-detail-public" class="w-full max-w-md bg-white p-6 rounded-3xl shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <span id="pub-detail-code" class="text-xs font-mono font-bold text-emerald-600">CODE</span>
                <h3 id="pub-detail-name" class="text-lg font-black text-slate-800 leading-tight">Nama Pakan Jadi</h3>
            </div>
            <button onclick="closeModal('modal-detail-public')" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="space-y-4 mt-4">
            <div class="h-44 w-full bg-slate-50 rounded-2xl flex items-center justify-center p-3 overflow-hidden border border-slate-100">
                <img id="pub-detail-img" src="" alt="Foto Pakan Jadi" class="max-h-full max-w-full object-contain rounded-xl">
            </div>

            <!-- Stok Ketersediaan Saja -->
            <div class="p-4 bg-emerald-50/80 rounded-2xl border border-emerald-100 flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-emerald-800/80 block">Stok Ketersediaan</span>
                    <span id="pub-detail-stock" class="text-2xl font-black text-emerald-700">0 kg</span>
                </div>
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">Tersedia</span>
            </div>

            <!-- Nomor WhatsApp yang Bisa Dihubungi (Kosongkan dulu) -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                <span class="text-xs font-semibold text-slate-500 block mb-1.5">Nomor WhatsApp yang Bisa Dihubungi:</span>
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                    </div>
                    <span id="pub-detail-wa" class="font-bold text-slate-700 text-sm">-</span>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
                <button type="button" onclick="closeModal('modal-detail-public')" class="px-6 py-2.5 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 rounded-xl transition-smooth shadow-xs">
                    Tutup
                </button>
            </div>
        </div>
    </dialog>

    <!-- Client Script Publik -->
        <script>
        window.APP_WHATSAPP = <?= json_encode($currentWhatsApp) ?>;
    </script>
    <script src="assets/js/public.js"></script>
</body>
</html>
