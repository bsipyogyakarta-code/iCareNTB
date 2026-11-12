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
    $timeout = 12; // Timeout 12 detik agar aman dari limit timeout serverless (Vercel)

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

    // Ambil data pengaturan (WhatsApp dll)
    $settings = [];
    try {
        $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM app_settings");
        $settings = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}

    $payload = [
        'action' => 'sync_all',
        'items' => $items,
        'mutations' => $mutations,
        'settings' => $settings,
        'synced_at' => date('Y-m-d H:i:s')
    ];

    $res = send_gsheet_request('POST', $payload);

    // Perbarui timestamp sinkronisasi lokal
    $syncPaths = [
        sys_get_temp_dir() . '/brmp_data/last_cloud_pull.txt',
        __DIR__ . '/data/last_cloud_pull.txt'
    ];
    foreach ($syncPaths as $sp) {
        $spDir = dirname($sp);
        if (!is_dir($spDir)) @mkdir($spDir, 0777, true);
        @file_put_contents($sp, date('Y-m-d H:i:s'));
    }

    return $res;
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
            $imageUrl = (!empty($item['image_url']) && trim($item['image_url']) !== '-') ? trim($item['image_url']) : null;

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

        // 2. Tarik dan simpan data Riwayat Mutasi & Distribusi
        $mutCount = 0;
        if (!empty($result['mutations']) && is_array($result['mutations'])) {
            $mutUpsert = $pdo->prepare("
                INSERT INTO inventory_mutations (
                    id, item_id, type, quantity, mutation_date, officer_name,
                    destination_or_source, notes, latitude, longitude, location_name
                ) VALUES (
                    :id, :item_id, :type, :quantity, :mutation_date, :officer_name,
                    :destination_or_source, :notes, :latitude, :longitude, :location_name
                )
                ON CONFLICT(id) DO UPDATE SET
                    item_id = excluded.item_id,
                    type = excluded.type,
                    quantity = excluded.quantity,
                    mutation_date = excluded.mutation_date,
                    officer_name = excluded.officer_name,
                    destination_or_source = excluded.destination_or_source,
                    notes = excluded.notes,
                    latitude = excluded.latitude,
                    longitude = excluded.longitude,
                    location_name = excluded.location_name
            ");

            foreach ($result['mutations'] as $mut) {
                $mItemId = (int)($mut['item_id'] ?? 0);
                if ($mItemId <= 0 && !empty($mut['item_code'])) {
                    $chk = $pdo->prepare("SELECT id FROM inventory_items WHERE code = :code");
                    $chk->execute([':code' => $mut['item_code']]);
                    $mItemId = (int)$chk->fetchColumn();
                }

                if ($mItemId > 0) {
                    $mutUpsert->execute([
                        ':id' => $mut['id'] ?? null,
                        ':item_id' => $mItemId,
                        ':type' => in_array($mut['type'] ?? '', ['IN', 'OUT']) ? $mut['type'] : 'OUT',
                        ':quantity' => (float)($mut['quantity'] ?? 0),
                        ':mutation_date' => !empty($mut['mutation_date']) ? substr($mut['mutation_date'], 0, 10) : date('Y-m-d'),
                        ':officer_name' => $mut['officer_name'] ?? 'Petugas Lapangan iCare NTB',
                        ':destination_or_source' => $mut['destination_or_source'] ?? '-',
                        ':notes' => $mut['notes'] ?? '',
                        ':latitude' => (isset($mut['latitude']) && $mut['latitude'] !== '' && $mut['latitude'] !== null) ? (float)$mut['latitude'] : null,
                        ':longitude' => (isset($mut['longitude']) && $mut['longitude'] !== '' && $mut['longitude'] !== null) ? (float)$mut['longitude'] : null,
                        ':location_name' => $mut['location_name'] ?? null
                    ]);
                    $mutCount++;
                }
            }
        }

        // 3. Tarik Pengaturan (WhatsApp dll) jika ada
        if (!empty($result['settings']) && is_array($result['settings'])) {
            $setStmt = $pdo->prepare("
                INSERT INTO app_settings (setting_key, setting_value, updated_at) 
                VALUES (:k, :v, CURRENT_TIMESTAMP)
                ON CONFLICT(setting_key) DO UPDATE SET 
                    setting_value = excluded.setting_value,
                    updated_at = CURRENT_TIMESTAMP
            ");
            foreach ($result['settings'] as $k => $v) {
                if (is_string($k) && !empty($k)) {
                    $setStmt->execute([':k' => $k, ':v' => (string)$v]);
                }
            }
        }

        $pdo->commit();
        return ['success' => true, 'message' => "Berhasil memuat $count item & $mutCount mutasi dari Google Spreadsheet ke database."];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Gagal memperbarui SQLite: ' . $e->getMessage()];
    }
}
