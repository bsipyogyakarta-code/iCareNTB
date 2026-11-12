<?php
// login.php - Halaman Masuk Administrator iCare NTB
require_once __DIR__ . '/auth.php';

$error = '';
$msg = $_GET['msg'] ?? '';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    admin_logout();
    header('Location: login.php?msg=logged_out');
    exit;
}

if (is_admin_logged_in()) {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (admin_login($username, $password)) {
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Username atau Password salah. Silakan coba kembali.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator &bull; iCare NTB</title>
    <link rel="icon" type="image/png" href="assets/img/logo-brmp.png">
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/custom.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#f8fafc] text-slate-800 min-h-screen flex items-center justify-center p-4 antialiased">

    <div class="w-full max-w-md">
        <!-- Logo & Brand Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block group mb-3">
                <div class="w-18 h-18 p-2 bg-white rounded-3xl shadow-lg border border-slate-100 flex items-center justify-center mx-auto transform group-hover:scale-105 transition-smooth">
                    <img src="assets/img/logo-brmp.png" alt="Logo Resmi iCare NTB" class="w-14 h-14 object-contain">
                </div>
            </a>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">iCare NTB</h1>
            <p class="text-xs font-bold text-emerald-700 tracking-wider uppercase mt-0.5">SISTEM MONITORING & PRODUKSI PAKAN</p>
            <p class="text-xs text-slate-500 mt-1">Panel Khusus Administrator &bull; Manajemen & CRUD Sistem</p>
        </div>

        <!-- Card Form Login -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-[0_10px_35px_rgba(6,78,59,0.07)] border border-slate-100">
            <h2 class="text-lg font-bold text-slate-800 mb-1">Masuk ke Panel Admin</h2>
            <p class="text-xs text-slate-500 mb-6">Kelola seluruh bahan baku, pakan jadi, stok benih & sebaran distribusi.</p>

            <?php if ($msg === 'required'): ?>
            <div class="mb-5 p-3.5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-xs font-medium flex items-center space-x-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Silakan masuk terlebih dahulu untuk mengakses menu manajemen admin.</span>
            </div>
            <?php elseif ($msg === 'logged_out'): ?>
            <div class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-xs font-medium flex items-center space-x-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Anda telah berhasil keluar dari sistem admin.</span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold flex items-center space-x-2">
                <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Username Administrator</label>
                    <div class="relative">
                        <input type="text" name="username" id="username" required value="admin" class="w-full pl-10 pr-3 py-2.5 text-sm border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-smooth">
                        <div class="absolute left-3.5 top-3 text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required placeholder="Masukkan password admin" class="w-full pl-10 pr-3 py-2.5 text-sm border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-smooth">
                        <div class="absolute left-3.5 top-3 text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 bg-[#059669] hover:bg-[#047857] text-white font-bold text-sm rounded-2xl shadow-md hover:shadow-lg transition-smooth flex items-center justify-center space-x-2">
                        <span>Masuk ke Dashboard Admin</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </form>

            <!-- Petunjuk Akun Default -->
            <div class="mt-6 p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-[11px] text-slate-500">
                <div class="font-bold text-slate-700 mb-1">🔑 Kredensial Administrator Sistem:</div>
                <div class="flex items-center justify-between">
                    <span>Username: <strong class="text-slate-800">admin</strong></span>
                    <span>Password: <strong class="text-slate-800">admin123</strong></span>
                </div>
            </div>
        </div>

        <!-- Tombol Kembali ke Halaman Publik -->
        <div class="text-center mt-6">
            <a href="index.php" class="inline-flex items-center space-x-2 text-xs font-bold text-slate-500 hover:text-emerald-700 transition-smooth">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Kembali ke Katalog Publik (Pakan Jadi)</span>
            </a>
        </div>
    </div>

</body>
</html>

