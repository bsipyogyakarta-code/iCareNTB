<?php
// api.php - REST API handler for Rumah Produksi iCare NTB
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/google_sheets.php';

/**
 * Simpan string gambar Base64 ke file fisik jika direktori writable (VPS/hosting lokal)
 * Jika serverless read-only (Vercel), fungsi ini mempertahankan data URL Base64 secara aman.
 */
function save_base64_image_if_possible(string $base64Url): string {
    if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64Url, $matches)) {
        return $base64Url;
    }
    $ext = strtolower($matches[1]);
    if ($ext === 'jpeg') $ext = 'jpg';
    if (!in_array($ext, ['jpg', 'png', 'webp', 'gif'])) $ext = 'jpg';

    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0777, true);
    }
    if (is_dir($uploadsDir) && is_writable($uploadsDir)) {
        $decoded = @base64_decode($matches[2]);
        if ($decoded !== false) {
            $filename = 'img_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (@file_put_contents($uploadsDir . '/' . $filename, $decoded) !== false) {
                return 'uploads/' . $filename;
            }
        }
    }
    return $base64Url;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Proteksi hak akses CRUD khusus admin
$mutationActions = [
    'add_item', 'update_item', 'delete_item', 'quick_adjust', 'quick_stock_adjust',
    'add_mutation', 'distribute_item', 'produce_product', 'save_whatsapp', 'save_gsheet_url', 'sync_gsheet', 'pull_gsheet'
];

if (in_array($action, $mutationActions, true)) {
    if (!is_admin_logged_in()) {
        echo json_encode([
            'success' => false, 
            'message' => 'Akses ditolak: Operasi ini hanya dapat dilakukan oleh Administrator. Silakan login terlebih dahulu.'
        ]);
        exit;
    }
}

try {
    switch ($action) {
        case 'get_items':
            $type = $_GET['type'] ?? '';
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $category = $_GET['category'] ?? '';

            $sql = "SELECT * FROM inventory_items WHERE 1=1";
            $params = [];

            if (!empty($type)) {
                $sql .= " AND type = :type";
                $params[':type'] = $type;
            }

            if (!empty($category)) {
                $sql .= " AND category = :cat";
                $params[':cat'] = $category;
            }

            if (!empty($search)) {
                $sql .= " AND (name LIKE :search OR code LIKE :search OR category LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }

            if (!empty($status)) {
                if ($status === 'critical') {
                    $sql .= " AND stock_current <= stock_minimum";
                } elseif ($status === 'safe') {
                    $sql .= " AND stock_current > stock_minimum";
                }
            }

            $sql .= " ORDER BY id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'get_locations':
            $itemId = $_GET['item_id'] ?? null;
            $type = $_GET['type'] ?? null;
            $sql = "
                SELECT m.*, 
                       COALESCE(i.name, 'Item #' || m.item_id) as item_name, 
                       COALESCE(i.code, 'PRD-DIST') as item_code, 
                       COALESCE(i.unit, 'kg') as unit, 
                       COALESCE(i.type, 'produk') as item_type, 
                       i.image_url, 
                       COALESCE(i.category, 'Pakan Jadi') as item_category
                FROM inventory_mutations m
                LEFT JOIN inventory_items i ON m.item_id = i.id
                WHERE m.latitude IS NOT NULL AND m.longitude IS NOT NULL AND (m.latitude != 0 OR m.longitude != 0)
            ";
            $params = [];
            if (!empty($itemId)) {
                $sql .= " AND m.item_id = :item_id";
                $params[':item_id'] = $itemId;
            }
            if (!empty($type)) {
                $sql .= " AND (i.type = :itype OR (i.type IS NULL AND :itype = 'produk'))";
                $params[':itype'] = $type;
            }
            $sql .= " ORDER BY m.mutation_date DESC, m.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $locations = $stmt->fetchAll();
            echo json_encode(['success' => true, 'locations' => $locations]);
            break;

        case 'add_item':
            $type = trim($_POST['type'] ?? 'produk');
            if (!in_array($type, ['produk', 'bahan_baku'])) {
                $type = 'produk';
            }

            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Nama item wajib diisi!']);
                exit;
            }

            $rawStock = str_replace(',', '.', trim($_POST['stock_current'] ?? '0'));
            $stockCurrent = max(0.0, (float)$rawStock);

            // Handle Foto: Base64, URL, atau Upload file
            $imageUrl = trim($_POST['image_url'] ?? '');

            if (empty($imageUrl) && !empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $uploadsDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadsDir)) {
                        @mkdir($uploadsDir, 0777, true);
                    }
                    $filename = 'item_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (@move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadsDir . '/' . $filename)) {
                        $imageUrl = 'uploads/' . $filename;
                    } else {
                        // Fallback ke Base64 jika serverless read-only (misal Vercel)
                        $raw = @file_get_contents($_FILES['image_file']['tmp_name']);
                        if ($raw) {
                            $mime = @mime_content_type($_FILES['image_file']['tmp_name']) ?: 'image/jpeg';
                            $imageUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
                        }
                    }
                }
            }

            if (empty($imageUrl)) {
                $imageUrl = ($type === 'bahan_baku')
                    ? 'https://images.unsplash.com/photo-1551754655-cd27e38d2076?w=500&auto=format&fit=crop&q=80'
                    : 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';
            } else {
                $imageUrl = save_base64_image_if_possible($imageUrl);
            }

            try {
                $pdo->beginTransaction();

                // Generate Kode Barang Otomatis jika tidak ada
                $codePrefix = ($type === 'produk') ? 'PRD' : 'BB';
                $cleanName = strtoupper(preg_replace('/[^a-zA-Z]/', '', $name));
                $subName = substr($cleanName, 0, 3);
                if (strlen($subName) < 3) $subName = 'ITM';

                $uniqueCode = '';
                for ($attempt = 0; $attempt < 20; $attempt++) {
                    $testCode = $codePrefix . '-' . $subName . '-' . rand(10, 99);
                    $chk = $pdo->prepare("SELECT id FROM inventory_items WHERE code = :code");
                    $chk->execute([':code' => $testCode]);
                    if (!$chk->fetch()) {
                        $uniqueCode = $testCode;
                        break;
                    }
                }
                if (empty($uniqueCode)) {
                    $uniqueCode = $codePrefix . '-' . time();
                }

                $category = ($type === 'produk') ? 'Pakan Jadi Olahan' : 'Bahan Baku';
                $unit = 'kg';
                $stockMinimum = 10;
                $location = 'Gudang Utama iCare NTB';
                $price = 0;

                // Simpan Item Baru
                $insert = $pdo->prepare("
                    INSERT INTO inventory_items (
                        type, code, name, category, target_stage, unit, stock_current, stock_minimum,
                        storage_location, price_per_unit, image_url, spec_1_label, spec_1_value,
                        spec_2_label, spec_2_value, spec_3_label, spec_3_value, description
                    ) VALUES (
                        :type, :code, :name, :category, '-', :unit, :stock_current, :stock_minimum,
                        :storage_location, :price_per_unit, :image_url, 'Protein Kasar', 'Standar SNI',
                        'Kadar Air', '< 12%', 'Status Mutu', 'Teruji iCare NTB', 'Pencatatan komoditas mandiri sistem iCare NTB'
                    )
                ");
                $insert->execute([
                    ':type' => $type,
                    ':code' => $uniqueCode,
                    ':name' => $name,
                    ':category' => $category,
                    ':unit' => $unit,
                    ':stock_current' => $stockCurrent,
                    ':stock_minimum' => $stockMinimum,
                    ':storage_location' => $location,
                    ':price_per_unit' => $price,
                    ':image_url' => $imageUrl
                ]);

                $newItemId = (int)$pdo->lastInsertId();

                // Catat Mutasi Masuk (IN) untuk item baru ini jika stok > 0
                if ($stockCurrent > 0) {
                    $insMutation = $pdo->prepare("
                        INSERT INTO inventory_mutations (
                            item_id, type, quantity, mutation_date, officer_name, destination_or_source, notes
                        ) VALUES (
                            :item_id, 'IN', :quantity, :mutation_date, :officer_name, :dest, :notes
                        )
                    ");
                    $insMutation->execute([
                        ':item_id' => $newItemId,
                        ':quantity' => $stockCurrent,
                        ':mutation_date' => date('Y-m-d'),
                        ':officer_name' => $_SESSION['admin_user'] ?? 'Petugas Gudang iCare NTB',
                        ':dest' => 'Pencatatan Komoditas Baru',
                        ':notes' => "Penambahan awal {$name} sebanyak {$stockCurrent} {$unit}"
                    ]);
                }

                // Pengurangan Stok Bahan Baku (Fleksibel untuk Pakan Jadi)
                $deductedCount = 0;
                $deductedDetails = [];

                if ($type === 'produk' && !empty($_POST['deduct_material_id']) && is_array($_POST['deduct_material_id'])) {
                    $updBB = $pdo->prepare("UPDATE inventory_items SET stock_current = MAX(0, stock_current - :qty), updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $insRec = $pdo->prepare("INSERT INTO product_recipes (product_id, raw_material_id, amount_per_unit) VALUES (:product_id, :raw_material_id, :amount_per_unit)");
                    $insMatMutation = $pdo->prepare("
                        INSERT INTO inventory_mutations (
                            item_id, type, quantity, mutation_date, officer_name, destination_or_source, notes
                        ) VALUES (
                            :item_id, 'OUT', :quantity, :mutation_date, :officer_name, :dest, :notes
                        )
                    ");

                    foreach ($_POST['deduct_material_id'] as $idx => $rawMatId) {
                        $rawMatId = (int)$rawMatId;
                        $deductQty = max(0.0, (float)str_replace(',', '.', $_POST['deduct_quantity'][$idx] ?? 0));

                        if ($rawMatId > 0 && $deductQty > 0) {
                            $bbStmt = $pdo->prepare("SELECT name, unit, stock_current FROM inventory_items WHERE id = :id AND type = 'bahan_baku'");
                            $bbStmt->execute([':id' => $rawMatId]);
                            $bbItem = $bbStmt->fetch();

                            if ($bbItem) {
                                $updBB->execute([':qty' => $deductQty, ':id' => $rawMatId]);

                                $insMatMutation->execute([
                                    ':item_id' => $rawMatId,
                                    ':quantity' => $deductQty,
                                    ':mutation_date' => date('Y-m-d'),
                                    ':officer_name' => $_SESSION['admin_user'] ?? 'Petugas Produksi iCare NTB',
                                    ':dest' => "Produksi {$name}",
                                    ':notes' => "Bahan dipotong untuk pembuatan {$name} ({$stockCurrent} kg)"
                                ]);

                                $amountPerUnit = ($stockCurrent > 0) ? ($deductQty / $stockCurrent) : $deductQty;
                                $insRec->execute([
                                    ':product_id' => $newItemId,
                                    ':raw_material_id' => $rawMatId,
                                    ':amount_per_unit' => round($amountPerUnit, 4)
                                ]);

                                $deductedCount++;
                                $deductedDetails[] = "{$bbItem['name']} (-{$deductQty} {$bbItem['unit']})";
                            }
                        }
                    }
                }

                $pdo->commit();

                $msg = ($type === 'produk')
                    ? "Pakan jadi '{$name}' ({$stockCurrent} kg) berhasil disimpan!"
                    : "Bahan baku '{$name}' ({$stockCurrent} kg) berhasil disimpan!";

                if ($deductedCount > 0) {
                    $msg .= " " . $deductedCount . " bahan baku berhasil dipotong: " . implode(', ', $deductedDetails);
                }

                // Auto-sync langsung ke Google Spreadsheet sebelum respon dikirim
                @sync_all_to_google_sheet($pdo);

                echo json_encode([
                    'success' => true,
                    'message' => $msg,
                    'item_id' => $newItemId
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan item ke database: ' . $e->getMessage()]);
            }
            break;

        case 'add_mutation':
            $itemId = (int)($_POST['item_id'] ?? $_POST['product_id'] ?? 0);
            $type = strtoupper(trim($_POST['type'] ?? ''));
            $rawQty = str_replace(',', '.', trim((string)($_POST['quantity'] ?? $_POST['amount'] ?? 0)));
            $quantity = (float)$rawQty;
            $date = trim($_POST['mutation_date'] ?? date('Y-m-d'));
            $officer = trim($_POST['officer_name'] ?? 'Petugas Lapangan iCare NTB');
            $destination = trim($_POST['destination_or_source'] ?? $_POST['recipient_location'] ?? '-');
            $notes = trim($_POST['notes'] ?? '');

            $latitude = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? (float)$_POST['latitude'] : null;
            $longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? (float)$_POST['longitude'] : null;
            $locationName = trim($_POST['location_name'] ?? $_POST['recipient_location'] ?? '');
            if (empty($locationName) && !empty($destination) && $destination !== '-') {
                $locationName = $destination;
            }
            
            // Auto deduct flag (default true for product production)
            $autoDeduct = isset($_POST['auto_deduct_raw']) ? ($_POST['auto_deduct_raw'] === '1' || $_POST['auto_deduct_raw'] === 'true' || $_POST['auto_deduct_raw'] === 'on') : true;

            if (!$itemId || !in_array($type, ['IN', 'OUT']) || $quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data mutasi tidak valid (kuantitas harus > 0)!']);
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Fetch current item
                $stmt = $pdo->prepare("SELECT id, name, type, stock_current, unit FROM inventory_items WHERE id = :id");
                $stmt->execute([':id' => $itemId]);
                $item = $stmt->fetch();

                if (!$item) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan!']);
                    exit;
                }

                $currentStock = (float)$item['stock_current'];
                if ($type === 'OUT' && $quantity > $currentStock) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => "Stok tidak mencukupi! Sisa stok {$item['name']}: {$currentStock} {$item['unit']}"]);
                    exit;
                }

                // Update product/item stock
                $newStock = ($type === 'IN') ? ($currentStock + $quantity) : ($currentStock - $quantity);

                $mutStmt = $pdo->prepare("
                    INSERT INTO inventory_mutations (item_id, type, quantity, mutation_date, officer_name, destination_or_source, notes, latitude, longitude, location_name)
                    VALUES (:item_id, :type, :quantity, :mutation_date, :officer_name, :destination_or_source, :notes, :latitude, :longitude, :location_name)
                ");
                $mutStmt->execute([
                    ':item_id' => $itemId,
                    ':type' => $type,
                    ':quantity' => $quantity,
                    ':mutation_date' => $date,
                    ':officer_name' => $officer,
                    ':destination_or_source' => $destination,
                    ':notes' => $notes,
                    ':latitude' => $latitude,
                    ':longitude' => $longitude,
                    ':location_name' => $locationName ?: null
                ]);

                $upStmt = $pdo->prepare("UPDATE inventory_items SET stock_current = :new_stock, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $upStmt->execute([':new_stock' => $newStock, ':id' => $itemId]);

                $newId = (int)$pdo->lastInsertId();

                // Pemotongan bahan baku otomatis berdasarkan resep (jika IN dan komoditas adalah produk)
                $deductedSummary = [];
                if ($type === 'IN' && $item['type'] === 'produk' && $autoDeduct) {
                    $recStmt = $pdo->prepare("
                        SELECT r.raw_material_id, r.amount_per_unit, i.name as raw_name, i.unit as raw_unit, i.stock_current as raw_stock
                        FROM product_recipes r
                        JOIN inventory_items i ON r.raw_material_id = i.id
                        WHERE r.product_id = :pid
                    ");
                    $recStmt->execute([':pid' => $itemId]);
                    $recipes = $recStmt->fetchAll();

                    if (!empty($recipes)) {
                        $updRaw = $pdo->prepare("UPDATE inventory_items SET stock_current = MAX(0, stock_current - :qty), updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $insRawMut = $pdo->prepare("
                            INSERT INTO inventory_mutations (item_id, type, quantity, mutation_date, officer_name, destination_or_source, notes)
                            VALUES (:item_id, 'OUT', :quantity, :mdate, :officer, :dest, :notes)
                        ");

                        foreach ($recipes as $r) {
                            $neededQty = round($r['amount_per_unit'] * $quantity, 4);
                            if ($neededQty > 0) {
                                $updRaw->execute([':qty' => $neededQty, ':id' => $r['raw_material_id']]);
                                $insRawMut->execute([
                                    ':item_id' => $r['raw_material_id'],
                                    ':quantity' => $neededQty,
                                    ':mdate' => $date,
                                    ':officer' => $officer,
                                    ':dest' => "Produksi {$item['name']}",
                                    ':notes' => "Potong formula otomatis untuk produksi {$quantity} {$item['unit']} {$item['name']}"
                                ]);
                                $deductedSummary[] = "{$r['raw_name']} (-{$neededQty} {$r['raw_unit']})";
                            }
                        }
                    }
                }

                $pdo->commit();

                if ($type === 'IN' && $item['type'] === 'produk' && !empty($deductedSummary)) {
                    $msg = "Produksi Berhasil!\n\n+{$quantity} {$item['unit']} {$item['name']} (Stok baru: " . number_format($newStock, 0, ',', '.') . " {$item['unit']}).\n\nBahan Baku Terpotong Otomatis:\n• " . implode("\n• ", $deductedSummary);
                } else {
                    $label = ($type === 'IN') ? 'Stok masuk berhasil dicatat' : 'Penyaluran / pemakaian stok berhasil dicatat';
                    $msg = "$label! Sisa stok {$item['name']}: " . number_format($newStock, 0, ',', '.') . " {$item['unit']}.";
                }

                echo json_encode([
                    'success' => true,
                    'message' => $msg,
                    'mutation_id' => $newId,
                    'item_id' => $itemId,
                    'item_name' => $item['name'],
                    'item_unit' => $item['unit'],
                    'new_stock' => $newStock,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location_name' => $locationName,
                    'quantity' => $quantity,
                    'destination' => $destination,
                    'mutation_date' => $date
                ];

                // Auto-sync langsung ke Google Spreadsheet sebelum respon dikirim
                @sync_all_to_google_sheet($pdo);

                echo json_encode($resData);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'message' => 'Gagal mencatat mutasi: ' . $e->getMessage()]);
            }

            break;

        case 'delete_item':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
                exit;
            }
            $del = $pdo->prepare("DELETE FROM inventory_items WHERE id = :id");
            $del->execute([':id' => $id]);

            // Auto-sync langsung ke Google Spreadsheet sebelum respon dikirim
            @sync_all_to_google_sheet($pdo);

            echo json_encode(['success' => true, 'message' => 'Item berhasil dihapus dari sistem!']);
            break;

        case 'update_item':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID item tidak valid']);
                exit;
            }

            $currStmt = $pdo->prepare("SELECT * FROM inventory_items WHERE id = :id");
            $currStmt->execute([':id' => $id]);
            $existing = $currStmt->fetch();
            if (!$existing) {
                echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan']);
                exit;
            }

            $name = trim($_POST['name'] ?? $existing['name']);
            $rawStock = isset($_POST['stock_current']) ? str_replace(',', '.', (string)$_POST['stock_current']) : (string)$existing['stock_current'];
            $stockCurrent = max(0.0, (float)$rawStock);

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Nama item wajib diisi!']);
                exit;
            }

            // Handle Foto Baru jika diunggah atau diisi URL
            $hasNewUpload = !empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK;
            $postedUrl = trim($_POST['image_url'] ?? '');
            $imageUrl = '';

            if ($hasNewUpload) {
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $uploadsDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadsDir)) {
                        @mkdir($uploadsDir, 0777, true);
                    }
                    $filename = 'item_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (@move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadsDir . '/' . $filename)) {
                        $imageUrl = 'uploads/' . $filename;
                    } else {
                        // Fallback ke Base64 jika serverless read-only
                        $raw = @file_get_contents($_FILES['image_file']['tmp_name']);
                        if ($raw) {
                            $mime = @mime_content_type($_FILES['image_file']['tmp_name']) ?: 'image/jpeg';
                            $imageUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
                        }
                    }
                }
            }

            // Jika tidak ada upload file baru, gunakan URL yang diinput atau pertahankan foto lama
            if (empty($imageUrl)) {
                if (!empty($postedUrl)) {
                    $imageUrl = $postedUrl;
                } else {
                    $imageUrl = $existing['image_url'];
                }
            }

            if (!empty($imageUrl)) {
                $imageUrl = save_base64_image_if_possible($imageUrl);
            }

            $upd = $pdo->prepare("
                UPDATE inventory_items SET
                    name = :name,
                    stock_current = :stock_current,
                    image_url = :image_url,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $upd->execute([
                ':id' => $id,
                ':name' => $name,
                ':stock_current' => $stockCurrent,
                ':image_url' => $imageUrl
            ]);

            // Auto-sync langsung ke Google Spreadsheet sebelum respon dikirim
            @sync_all_to_google_sheet($pdo);

            echo json_encode(['success' => true, 'message' => "Data '{$name}' berhasil diperbarui!"]);
            break;

        case 'quick_stock_adjust':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $rawDelta = str_replace(',', '.', trim((string)($_POST['delta'] ?? 0)));
            $delta = (float)$rawDelta;
            $reason = trim($_POST['reason'] ?? 'Penyesuaian stok cepat');

            if (!$itemId || $delta == 0) {
                echo json_encode(['success' => false, 'message' => 'Data penyesuaian tidak valid!']);
                exit;
            }

            try {
                $item = $pdo->prepare("SELECT id, name, unit, stock_current FROM inventory_items WHERE id = :id");
                $item->execute([':id' => $itemId]);
                $it = $item->fetch();

                if (!$it) {
                    echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan!']);
                    exit;
                }

                $newStock = (float)$it['stock_current'] + $delta;
                if ($newStock < 0) {
                    echo json_encode(['success' => false, 'message' => "Stok tidak boleh bernilai negatif! (Sisa: {$it['stock_current']} {$it['unit']})"]);
                    exit;
                }

                $pdo->beginTransaction();
                $up = $pdo->prepare("UPDATE inventory_items SET stock_current = :new_stock, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $up->execute([':new_stock' => $newStock, ':id' => $itemId]);

                $type = ($delta > 0) ? 'IN' : 'OUT';
                $qty = abs($delta);
                $mut = $pdo->prepare("
                    INSERT INTO inventory_mutations (item_id, type, quantity, mutation_date, officer_name, destination_or_source, notes)
                    VALUES (:item_id, :type, :quantity, :mdate, 'Petugas Lapangan iCare NTB', :dest, :notes)
                ");
                $mut->execute([
                    ':item_id' => $itemId,
                    ':type' => $type,
                    ':quantity' => $qty,
                    ':mdate' => date('Y-m-d'),
                    ':dest' => 'Penyesuaian Inventaris Cepat',
                    ':notes' => $reason
                ]);

                $pdo->commit();

                // Auto-sync langsung ke Google Spreadsheet sebelum respon dikirim
                @sync_all_to_google_sheet($pdo);

                echo json_encode([
                    'success' => true,
                    'message' => "Stok {$it['name']} berhasil disesuaikan! Stok baru: " . number_format($newStock, 0, ',', '.') . " {$it['unit']}",
                    'new_stock' => $newStock
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui stok: ' . $e->getMessage()]);
            }

            break;

        case 'get_stats':
            $statsBahanBaku = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN stock_current <= stock_minimum THEN 1 ELSE 0 END) as critical FROM inventory_items WHERE type = 'bahan_baku'")->fetch();
            $statsProduk = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN stock_current <= stock_minimum THEN 1 ELSE 0 END) as critical FROM inventory_items WHERE type = 'produk'")->fetch();

            echo json_encode([
                'success' => true,
                'bahan_baku' => $statsBahanBaku,
                'produk' => $statsProduk
            ]);
            break;

        case 'get_gsheet_info':
            $url = get_gsheet_url();
            echo json_encode([
                'success' => true,
                'is_connected' => !empty($url),
                'url' => $url
            ]);
            break;

        case 'produce_product':
            $productId = (int)($_POST['product_id'] ?? 0);
            $rawProduceQty = str_replace(',', '.', trim((string)($_POST['produce_quantity'] ?? 0)));
            $produceQty = max(0.0, (float)$rawProduceQty);

            if ($productId <= 0 || $produceQty <= 0) {
                echo json_encode(['success' => false, 'message' => 'Pilih produk pakan dan masukkan jumlah produksi yang valid!']);
                exit;
            }

            $prodStmt = $pdo->prepare("SELECT * FROM inventory_items WHERE id = :id AND type = 'produk'");
            $prodStmt->execute([':id' => $productId]);
            $product = $prodStmt->fetch();
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Produk pakan jadi tidak ditemukan!']);
                exit;
            }

            $materials = $_POST['deduct_material_id'] ?? [];
            $quantities = $_POST['deduct_quantity'] ?? [];

            $deductions = [];
            foreach ($materials as $idx => $matId) {
                $mId = (int)$matId;
                $mQty = max(0.0, (float)str_replace(',', '.', $quantities[$idx] ?? 0));
                if ($mId > 0 && $mQty > 0) {
                    $deductions[] = ['id' => $mId, 'quantity' => $mQty];
                }
            }

            try {
                $pdo->beginTransaction();

                $chkStmt = $pdo->prepare("SELECT id, name, unit, stock_current FROM inventory_items WHERE id = :id AND type = 'bahan_baku'");
                $deductedDetails = [];

                foreach ($deductions as $deduct) {
                    $chkStmt->execute([':id' => $deduct['id']]);
                    $bb = $chkStmt->fetch();
                    if (!$bb) {
                        throw new Exception("Bahan baku ID {$deduct['id']} tidak ditemukan.");
                    }
                    if ($bb['stock_current'] < $deduct['quantity']) {
                        throw new Exception("Stok '{$bb['name']}' tidak cukup! Dibutuhkan: {$deduct['quantity']} {$bb['unit']}, Sisa di gudang: {$bb['stock_current']} {$bb['unit']}.");
                    }
                }

                $updBB = $pdo->prepare("UPDATE inventory_items SET stock_current = stock_current - :qty, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $insBBMut = $pdo->prepare("
                    INSERT INTO inventory_mutations (item_id, type, quantity, mutation_date, officer_name, destination_or_source, notes)
                    VALUES (:item_id, 'OUT', :quantity, :mdate, :officer, :dest, :notes)
                ");

                $officer = $_SESSION['admin_user'] ?? 'Petugas Produksi iCare NTB';
                $today = date('Y-m-d');

                foreach ($deductions as $deduct) {
                    $chkStmt->execute([':id' => $deduct['id']]);
                    $bb = $chkStmt->fetch();

                    $updBB->execute([':qty' => $deduct['quantity'], ':id' => $deduct['id']]);
                    $insBBMut->execute([
                        ':item_id' => $deduct['id'],
                        ':quantity' => $deduct['quantity'],
                        ':mdate' => $today,
                        ':officer' => $officer,
                        ':dest' => "Produksi {$product['name']}",
                        ':notes' => "Pemotongan bahan baku untuk produksi {$produceQty} {$product['unit']} {$product['name']}"
                    ]);

                    $deductedDetails[] = "{$bb['name']} (-{$deduct['quantity']} {$bb['unit']})";
                }

                $newProdStock = (float)$product['stock_current'] + $produceQty;
                $updProd = $pdo->prepare("UPDATE inventory_items SET stock_current = :new_stock, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $updProd->execute([':new_stock' => $newProdStock, ':id' => $productId]);

                $insProdMut = $pdo->prepare("
                    INSERT INTO inventory_mutations (item_id, type, quantity, mutation_date, officer_name, destination_or_source, notes)
                    VALUES (:item_id, 'IN', :quantity, :mdate, :officer, :dest, :notes)
                ");
                $insProdMut->execute([
                    ':item_id' => $productId,
                    ':quantity' => $produceQty,
                    ':mdate' => $today,
                    ':officer' => $officer,
                    ':dest' => 'Hasil Pengolahan / Produksi Pabrik',
                    ':notes' => "Produksi {$produceQty} {$product['unit']} {$product['name']} selesai. " . (!empty($deductedDetails) ? "Bahan terpakai: " . implode(', ', $deductedDetails) : '')
                ]);

                $pdo->commit();

                // Auto-sync langsung ke Google Spreadsheet
                @sync_all_to_google_sheet($pdo);

                echo json_encode([
                    'success' => true,
                    'message' => "Produksi {$produceQty} {$product['unit']} '{$product['name']}' berhasil! " . (!empty($deductedDetails) ? "Bahan terpotong: " . implode(', ', $deductedDetails) : ''),
                    'product_id' => $productId,
                    'new_stock' => $newProdStock
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'save_whatsapp':
            $phone = trim($_POST['whatsapp_number'] ?? '');
            $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
            save_app_setting($pdo, 'whatsapp_number', $cleanPhone);
            @sync_all_to_google_sheet($pdo);
            echo json_encode([
                'success' => true,
                'message' => 'Nomor WhatsApp resmi berhasil disimpan!',
                'whatsapp_number' => $cleanPhone
            ]);
            break;

        case 'get_settings':
            $wa = get_app_setting($pdo, 'whatsapp_number', '');
            echo json_encode([
                'success' => true,
                'settings' => [
                    'whatsapp_number' => $wa
                ]
            ]);
            break;

        case 'save_gsheet_url':
            $newUrl = trim($_POST['url'] ?? '');
            if (empty($newUrl)) {
                echo json_encode(['success' => false, 'message' => 'URL Google Apps Script tidak boleh kosong!']);
                exit;
            }
            if (!filter_var($newUrl, FILTER_VALIDATE_URL) || strpos($newUrl, 'script.google.com') === false) {
                echo json_encode(['success' => false, 'message' => 'URL tidak valid! Pastikan berawalan https://script.google.com/macros/s/...']);
                exit;
            }
            save_gsheet_url($newUrl);
            
            // Lakukan tes ping koneksi
            $testRes = send_gsheet_request('GET', ['action' => 'test']);
            echo json_encode([
                'success' => true,
                'message' => 'URL Google Spreadsheet berhasil disimpan!',
                'test_result' => $testRes
            ]);
            break;

        case 'sync_gsheet':
            $res = sync_all_to_google_sheet($pdo);
            echo json_encode($res);
            break;

        case 'pull_gsheet':
            $res = pull_from_google_sheet($pdo);
            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
