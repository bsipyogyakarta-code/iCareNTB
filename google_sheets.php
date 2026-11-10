<?php
// google_sheets.php - Modul Integrasi & Sinkronisasi Google Spreadsheet untuk Rumah Produksi BRMP DIY

require_once __DIR__ . '/db.php';

/**
 * Dapatkan URL Web App Google Apps Script
 */
function get_gsheet_url(): string {
    // 1. Cek Environment Variable (misal diset di Vercel Dashboard)
    $envUrl = getenv('GOOGLE_SHEET_URL') ?: ($_ENV['GOOGLE_SHEET_URL'] ?? '');
    if (!empty($envUrl)) {
        return trim($envUrl);
    }

    // 2. Cek file config lokal atau /tmp
    $paths = [
        __DIR__ . '/data/gsheet_config.json',
        sys_get_temp_dir() . '/brmp_data/gsheet_config.json',
        sys_get_temp_dir() . '/gsheet_config.json'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            $data = @json_decode(file_get_contents($path), true);
            if (!empty($data['url'])) {
                return trim($data['url']);
            }
        }
    }

    return '';
}

/**
 * Simpan URL Web App Google Apps Script ke file config
 */
function save_gsheet_url(string $url): bool {
    $cleanUrl = trim($url);
    $data = json_encode(['url' => $cleanUrl, 'updated_at' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);

    $saved = false;
    $paths = [
        __DIR__ . '/data/gsheet_config.json',
        sys_get_temp_dir() . '/brmp_data/gsheet_config.json',
        sys_get_temp_dir() . '/gsheet_config.json'
    ];

    foreach ($paths as $p) {
        $dir = dirname($p);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (@file_put_contents($p, $data) !== false) {
            $saved = true;
        }
    }

    return $saved;
}

/**
 * Kirim request HTTP ke Google Apps Script (mendukung 302 redirect Google)
 */
function send_gsheet_request(string $method = 'GET', array $payload = []): array {
    $url = get_gsheet_url();
    if (empty($url)) {
        return ['success' => false, 'message' => 'URL Google Apps Script belum dikonfigurasi.'];
    }

    $ch = curl_init();
    $timeout = 8; // Timeout 8 detik agar aman dari limit timeout serverless (Vercel)

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
    } else {
        $queryStr = http_build_query($payload);
        $fullUrl = strpos($url, '?') !== false ? "$url&$queryStr" : "$url?$queryStr";
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // WAJIB untuk Google Apps Script 302 redirect
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'message' => 'Koneksi cURL gagal: ' . $curlErr];
    }

    if ($httpCode >= 400) {
        return ['success' => false, 'message' => "Google Apps Script mengembalikan error HTTP $httpCode: $response"];
    }

    $json = @json_decode($response, true);
    if ($json !== null) {
        return $json;
    }

    return ['success' => true, 'raw' => $response, 'message' => 'Respon diterima dari Google Apps Script.'];
}

/**
 * Sinkronisasi penuh: Kirim seluruh data SQLite ke Google Spreadsheet
 */
function sync_all_to_google_sheet(PDO $pdo): array {
    $url = get_gsheet_url();
    if (empty($url)) {
        return ['success' => false, 'message' => 'URL Google Apps Script belum disetel.'];
    }

    // Ambil data bahan baku & produk
    $stmtItems = $pdo->query("SELECT * FROM inventory_items ORDER BY type, id ASC");
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Ambil data mutasi log
    $stmtMut = $pdo->query("
        SELECT m.*, i.name as item_name, i.code as item_code, i.unit, i.type as item_type
        FROM inventory_mutations m
        JOIN inventory_items i ON m.item_id = i.id
        ORDER BY m.mutation_date DESC, m.id DESC
    ");
    $mutations = $stmtMut->fetchAll(PDO::FETCH_ASSOC);

    $payload = [
        'action' => 'sync_all',
        'items' => $items,
        'mutations' => $mutations,
        'synced_at' => date('Y-m-d H:i:s')
    ];

    return send_gsheet_request('POST', $payload);
}

/**
 * Tarik data dari Google Spreadsheet dan simpan ke SQLite
 */
function pull_from_google_sheet(PDO $pdo): array {
    $url = get_gsheet_url();
    if (empty($url)) {
        return ['success' => false, 'message' => 'URL Google Apps Script belum disetel.'];
    }

    $result = send_gsheet_request('GET', ['action' => 'get_all']);
    if (empty($result['success']) || empty($result['items'])) {
        return ['success' => false, 'message' => $result['message'] ?? 'Gagal mengambil data dari Google Sheets.'];
    }

    try {
        $pdo->beginTransaction();

        $upsertStmt = $pdo->prepare("
            INSERT INTO inventory_items (
                id, type, code, name, category, target_stage, unit,
                stock_current, stock_minimum, storage_location, price_per_unit,
                image_url, spec_1_label, spec_1_value, spec_2_label, spec_2_value,
                spec_3_label, spec_3_value, description, updated_at
            ) VALUES (
                :id, :type, :code, :name, :category, :target_stage, :unit,
                :stock_current, :stock_minimum, :storage_location, :price_per_unit,
                :image_url, :spec_1_label, :spec_1_value, :spec_2_label, :spec_2_value,
                :spec_3_label, :spec_3_value, :description, CURRENT_TIMESTAMP
            )
            ON CONFLICT(code) DO UPDATE SET
                name = excluded.name,
                category = excluded.category,
                stock_current = excluded.stock_current,
                stock_minimum = excluded.stock_minimum,
                storage_location = excluded.storage_location,
                price_per_unit = excluded.price_per_unit,
                image_url = CASE WHEN excluded.image_url IS NOT NULL AND excluded.image_url != '' THEN excluded.image_url ELSE inventory_items.image_url END,
                updated_at = CURRENT_TIMESTAMP
        ");

        $count = 0;
        foreach ($result['items'] as $item) {
            $itemType = $item['type'] ?? 'produk';
            $defaultImg = ($itemType === 'bahan_baku')
                ? 'https://images.unsplash.com/photo-1551754655-cd27e38d2076?w=500&auto=format&fit=crop&q=80'
                : 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';
            $imageUrl = !empty($item['image_url']) ? $item['image_url'] : $defaultImg;

            $upsertStmt->execute([
                ':id' => $item['id'] ?? null,
                ':type' => $itemType,
                ':code' => $item['code'] ?? '',
                ':name' => $item['name'] ?? '',
                ':category' => $item['category'] ?? '',
                ':target_stage' => $item['target_stage'] ?? '',
                ':unit' => $item['unit'] ?? 'kg',
                ':stock_current' => (float)($item['stock_current'] ?? 0),
                ':stock_minimum' => (float)($item['stock_minimum'] ?? 10),
                ':storage_location' => $item['storage_location'] ?? '',
                ':price_per_unit' => (int)($item['price_per_unit'] ?? 0),
                ':image_url' => $imageUrl,
                ':spec_1_label' => $item['spec_1_label'] ?? '',
                ':spec_1_value' => $item['spec_1_value'] ?? '',
                ':spec_2_label' => $item['spec_2_label'] ?? '',
                ':spec_2_value' => $item['spec_2_value'] ?? '',
                ':spec_3_label' => $item['spec_3_label'] ?? '',
                ':spec_3_value' => $item['spec_3_value'] ?? '',
                ':description' => $item['description'] ?? ''
            ]);
            $count++;
        }

        $pdo->commit();
        return ['success' => true, 'message' => "Berhasil memuat $count item dari Google Spreadsheet ke database."];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Gagal memperbarui SQLite: ' . $e->getMessage()];
    }
}
