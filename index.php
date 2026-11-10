<?php
// index.php - Katalog Publik Rumah Produksi BRMP DIY (Khusus Pakan Jadi)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

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
    <title>Katalog Pakan Jadi &bull; Rumah Produksi BRMP DIY</title>
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
                    <a href="index.php" class="relative group block shrink-0" title="Rumah Produksi BRMP DIY">
                        <div class="w-13 h-13 p-1 bg-white/10 hover:bg-white/20 rounded-2xl border border-white/25 shadow-lg backdrop-blur-md transition-smooth flex items-center justify-center">
                            <img src="assets/img/logo-brmp.png" alt="Logo Resmi BRMP DIY" class="w-11 h-11 object-contain drop-shadow-md transform group-hover:scale-105 transition-smooth">
                        </div>
                    </a>
                    <div class="flex items-center space-x-2.5">
                        <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-white uppercase drop-shadow-sm">Rumah Produksi</h1>
                        <span class="bg-amber-400 text-slate-900 text-xs px-2.5 py-0.5 rounded-full font-black tracking-wider uppercase shadow-xs">BRMP DIY</span>
                    </div>
                </div>

                <!-- Right Header Actions (Akses Admin & Refresh) -->
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <button onclick="window.location.reload()" class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-smooth" title="Segarkan Halaman">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>

                    <!-- Panel Admin disembunyikan dari halaman publik sesuai instruksi -->
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
                        Katalog resmi produk formulasi pakan siap pakai untuk ikan air tawar, unggas, dan ternak ruminansia yang diproduksi dengan standar instrumen pengujian mutu BRMP D.I. Yogyakarta.
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
            <div id="no-results-pakan" class="hidden text-center py-16 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2 text-xl font-bold">🔍</div>
                <h3 class="text-base font-bold text-slate-800">Tidak ada varian pakan jadi yang cocok</h3>
                <p class="text-xs text-slate-500 mt-1">Coba sesuaikan kata kunci pencarian atau pilih kategori pakan lain di atas.</p>
            </div>
        </section>

    </main>

    <!-- Footer Publik -->
    <footer class="bg-white border-t border-slate-200 mt-12 py-8 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <div class="flex items-center space-x-3">
                <img src="assets/img/logo-brmp.png" alt="Logo Resmi BRMP DIY" class="w-10 h-10 object-contain">
                <div class="text-left">
                    <p class="font-extrabold text-slate-800 text-sm">Rumah Produksi &bull; BRMP D.I. Yogyakarta</p>
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

            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block">Kategori:</span>
                    <span id="pub-detail-category" class="font-bold text-slate-800">-</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block">Fase Target:</span>
                    <span id="pub-detail-stage" class="font-bold text-slate-800">-</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block">Stok Ketersediaan:</span>
                    <span id="pub-detail-stock" class="font-black text-emerald-700">0</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block">Harga Patokan:</span>
                    <span id="pub-detail-price" class="font-bold text-slate-800">Rp 0</span>
                </div>
            </div>

            <!-- Parameter Mutu -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-2.5">Parameter Mutu & Kandungan Nutrisi (Uji Lab)</h4>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                        <span id="pub-detail-spec1-label" class="text-slate-600 font-medium">Protein Kasar</span>
                        <span id="pub-detail-spec1-val" class="font-bold text-slate-800">-</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                        <span id="pub-detail-spec2-label" class="text-slate-600 font-medium">Kadar Air</span>
                        <span id="pub-detail-spec2-val" class="font-bold text-slate-800">-</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span id="pub-detail-spec3-label" class="text-slate-600 font-medium">FCR Target</span>
                        <span id="pub-detail-spec3-val" class="font-bold text-slate-800">-</span>
                    </div>
                </div>
            </div>

            <div class="text-xs text-slate-500">
                <div>Lokasi Gudang: <span id="pub-detail-location" class="font-semibold text-slate-700">Gudang Utama BRMP DIY</span></div>
                <div class="pt-1.5 text-[11px] text-slate-500 leading-relaxed" id="pub-detail-desc">-</div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
                <button type="button" onclick="closeModal('modal-detail-public')" class="px-6 py-2.5 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 rounded-xl transition-smooth shadow-xs">
                    Tutup
                </button>
            </div>
        </div>
    </dialog>

    <!-- Client Script Publik -->
    <script src="assets/js/public.js"></script>
</body>
</html>
