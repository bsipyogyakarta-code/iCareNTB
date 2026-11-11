<?php
// db.php - Database connection & auto initialization for Rumah Produksi BRMP DIY

// Deteksi lingkungan serverless Vercel (read-only filesystem)
$isVercel = isset($_ENV['VERCEL']) || getenv('VERCEL') || (isset($_SERVER['VERCEL']) && $_SERVER['VERCEL']);

if ($isVercel) {
    // Pada Vercel, hanya direktori /tmp yang memiliki hak tulis (writable)
    $dataDir = sys_get_temp_dir() . '/brmp_data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0777, true);
    }
    $dbFile = $dataDir . '/pakan.db';

    // Salin database awal jika belum ada di /tmp
    if (!file_exists($dbFile)) {
        $sourceDb = __DIR__ . '/data/pakan.db';
        if (file_exists($sourceDb)) {
            @copy($sourceDb, $dbFile);
        }
    }
} else {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0777, true);
    }
    $dbFile = $dataDir . '/pakan.db';
}

try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL CHECK(type IN ('bahan_baku', 'produk')),
            code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            target_stage TEXT,
            unit TEXT NOT NULL DEFAULT 'kg',
            stock_current REAL NOT NULL DEFAULT 0,
            stock_minimum REAL NOT NULL DEFAULT 10,
            storage_location TEXT,
            price_per_unit INTEGER NOT NULL DEFAULT 0,
            image_url TEXT,
            spec_1_label TEXT,
            spec_1_value TEXT,
            spec_2_label TEXT,
            spec_2_value TEXT,
            spec_3_label TEXT,
            spec_3_value TEXT,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS inventory_mutations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('IN', 'OUT')),
            quantity REAL NOT NULL,
            mutation_date DATE NOT NULL,
            officer_name TEXT NOT NULL,
            destination_or_source TEXT NOT NULL,
            latitude REAL,
            longitude REAL,
            location_name TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS product_recipes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            raw_material_id INTEGER NOT NULL,
            amount_per_unit REAL NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
            FOREIGN KEY (raw_material_id) REFERENCES inventory_items(id) ON DELETE CASCADE
        );
    ");

    // Check & Migrate columns in inventory_mutations
    $cols = $pdo->query("PRAGMA table_info(inventory_mutations)")->fetchAll();
    $hasLat = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'latitude') { $hasLat = true; break; }
    }
    if (!$hasLat) {
        $pdo->exec("ALTER TABLE inventory_mutations ADD COLUMN latitude REAL");
        $pdo->exec("ALTER TABLE inventory_mutations ADD COLUMN longitude REAL");
        $pdo->exec("ALTER TABLE inventory_mutations ADD COLUMN location_name TEXT");
    }

    // Sinkronisasi cerdas dengan Google Spreadsheet pada setiap container
    try {
        $lastSyncFile = $dataDir . '/last_cloud_pull.txt';
        $itemCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn();
        $mutCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory_mutations")->fetchColumn();

        // Kondisi tarik data:
        // 1. Container baru belum pernah sync (!file_exists)
        // 2. ATAU tabel item kosong ($itemCount === 0)
        // 3. ATAU tabel mutasi kosong ($mutCount === 0)
        // 4. ATAU sudah lewat 30 detik sejak pull terakhir
        $needsPull = !file_exists($lastSyncFile) 
                     || $itemCount === 0 
                     || $mutCount === 0 
                     || (time() - @filemtime($lastSyncFile) > 30);

        if ($needsPull) {
            require_once __DIR__ . '/google_sheets.php';
            $pullRes = @pull_from_google_sheet($pdo);
            if (!empty($pullRes['success'])) {
                @file_put_contents($lastSyncFile, date('Y-m-d H:i:s'));
            }
        }
    } catch (Exception $syncEx) {
        // Silently skip jika offline atau jaringan terkendala
    }
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
