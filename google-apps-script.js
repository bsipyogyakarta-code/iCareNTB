/**
 * GOOGLE APPS SCRIPT - SINKRONISASI iCare NTB
 * 
 * PANDUAN PEMASANGAN (CUMA 2 MENIT):
 * 1. Buat Google Sheet baru di Google Drive Anda (beri judul: "Database iCare NTB").
 * 2. Klik menu "Extensions" (Ekstensi) > "Apps Script".
 * 3. Hapus semua kode default di Apps Script, lalu COPY dan PASTE seluruh isi script ini.
 * 4. Klik tombol "Deploy" (Terapkan) di pojok kanan atas > pilih "New deployment" (Penerapan baru).
 * 5. Klik ikon gerigi (Select type) > pilih "Web app".
 * 6. Atur:
 *    - Description: "iCare NTB API"
 *    - Execute as: "Me" (email Anda)
 *    - Who has access: "Anyone" (Siapa saja - agar web hosting bisa kirim data)
 * 7. Klik "Deploy" > Berikan izin (Authorize access) akun Google Anda.
 * 8. SALIN "Web app URL" (berawalan https://script.google.com/macros/s/...)
 * 9. Tempelkan URL tersebut ke Dashboard Admin web iCare NTB!
 */

function doGet(e) {
  var action = (e && e.parameter && e.parameter.action) ? e.parameter.action : 'test';
  var ss = SpreadsheetApp.getActiveSpreadsheet();

  if (action === 'test') {
    return createJsonResponse({
      success: true,
      message: 'Koneksi Google Apps Script iCare NTB Berhasil Aktif!',
      spreadsheet_name: ss.getName(),
      timestamp: new Date().toISOString()
    });
  }

  if (action === 'get_all') {
    var items = getItemsFromSheets(ss);
    var mutations = getMutationsFromSheets(ss);
    return createJsonResponse({
      success: true,
      items: items,
      mutations: mutations
    });
  }

  return createJsonResponse({ success: false, message: 'Aksi tidak dikenal: ' + action });
}

function doPost(e) {
  try {
    var contents = e.postData.contents;
    var data = JSON.parse(contents);
    var action = data.action || 'sync_all';
    var ss = SpreadsheetApp.getActiveSpreadsheet();

    if (action === 'sync_all') {
      var items = data.items || [];
      var mutations = data.mutations || [];

      syncItemsSheet(ss, items);
      syncMutationsSheet(ss, mutations);

      return createJsonResponse({
        success: true,
        message: 'Sinkronisasi berhasil! ' + items.length + ' item & ' + mutations.length + ' mutasi tersimpan.',
        synced_at: new Date().toISOString()
      });
    }

    return createJsonResponse({ success: false, message: 'Aksi POST tidak dikenal' });
  } catch (err) {
    return createJsonResponse({ success: false, message: 'Error Server: ' + err.toString() });
  }
}

function syncItemsSheet(ss, items) {
  var bbSheet = getOrCreateSheet(ss, 'Bahan_Baku', '#1E5E3A');
  var prdSheet = getOrCreateSheet(ss, 'Produk_Pakan', '#1E5E3A');

  // Headers
  var headersBB = ['ID', 'Kode', 'Nama Bahan Baku', 'Kategori', 'Stok Saat Ini', 'Satuan', 'Batas Minimal', 'Gudang / Lokasi', 'Harga Satuan (Rp)', 'Spesifikasi Mutu', 'Catatan'];
  var headersPRD = ['ID', 'Kode', 'Nama Produk Pakan', 'Kategori', 'Stok Saat Ini', 'Satuan', 'Batas Minimal', 'Gudang / Lokasi', 'Harga Satuan (Rp)', 'Target / Standar', 'Deskripsi'];

  var rowsBB = [headersBB];
  var rowsPRD = [headersPRD];

  for (var i = 0; i < items.length; i++) {
    var item = items[i];
    var specText = [item.spec_1_label ? item.spec_1_label + ': ' + item.spec_1_value : '',
                    item.spec_2_label ? item.spec_2_label + ': ' + item.spec_2_value : '',
                    item.spec_3_label ? item.spec_3_label + ': ' + item.spec_3_value : ''].filter(Boolean).join(' | ');

    if (item.type === 'bahan_baku') {
      rowsBB.push([
        item.id,
        item.code,
        item.name,
        item.category,
        Number(item.stock_current) || 0,
        item.unit,
        Number(item.stock_minimum) || 0,
        item.storage_location || '-',
        Number(item.price_per_unit) || 0,
        specText || '-',
        item.description || '-'
      ]);
    } else {
      rowsPRD.push([
        item.id,
        item.code,
        item.name,
        item.category,
        Number(item.stock_current) || 0,
        item.unit,
        Number(item.stock_minimum) || 0,
        item.storage_location || '-',
        Number(item.price_per_unit) || 0,
        item.target_stage || specText || '-',
        item.description || '-'
      ]);
    }
  }

  writeAndFormatSheet(bbSheet, rowsBB);
  writeAndFormatSheet(prdSheet, rowsPRD);
}

function syncMutationsSheet(ss, mutations) {
  var mutSheet = getOrCreateSheet(ss, 'Riwayat_Mutasi', '#1E5E3A');
  var headers = ['ID Mutasi', 'ID Item', 'Tanggal', 'Tipe (IN/OUT)', 'Kode Item', 'Nama Item', 'Jumlah Mutasi', 'Satuan', 'Petugas', 'Tujuan / Sumber', 'Nama Lokasi', 'Latitude', 'Longitude', 'Catatan'];

  var rows = [headers];
  for (var i = 0; i < mutations.length; i++) {
    var m = mutations[i];
    rows.push([
      m.id,
      m.item_id || '',
      m.mutation_date ? String(m.mutation_date).substring(0, 10) : '',
      m.type || 'OUT',
      m.item_code || '-',
      m.item_name || '-',
      Number(m.quantity) || 0,
      m.unit || 'kg',
      m.officer_name || '-',
      m.destination_or_source || '-',
      m.location_name || '',
      (m.latitude !== null && m.latitude !== undefined && m.latitude !== '') ? Number(m.latitude) : '',
      (m.longitude !== null && m.longitude !== undefined && m.longitude !== '') ? Number(m.longitude) : '',
      m.notes || '-'
    ]);
  }

  writeAndFormatSheet(mutSheet, rows);
}

function getMutationsFromSheets(ss) {
  var mutSheet = ss.getSheetByName('Riwayat_Mutasi');
  if (!mutSheet) return [];
  var data = mutSheet.getDataRange().getValues();
  if (data.length <= 1) return [];

  var mutations = [];
  for (var r = 1; r < data.length; r++) {
    var row = data[r];
    if (!row[0]) continue; // ID kosong

    // Cek format kolom (14 kolom standar baru vs 11 kolom lama)
    if (row.length >= 13) {
      mutations.push({
        id: row[0],
        item_id: row[1],
        mutation_date: row[2] ? (row[2] instanceof Date ? Utilities.formatDate(row[2], Session.getScriptTimeZone(), 'yyyy-MM-dd') : String(row[2])) : '',
        type: row[3] || 'OUT',
        item_code: row[4] || '',
        item_name: row[5] || '',
        quantity: Number(row[6]) || 0,
        unit: row[7] || 'kg',
        officer_name: row[8] || '',
        destination_or_source: row[9] || '',
        location_name: row[10] || '',
        latitude: row[11] !== '' ? Number(row[11]) : null,
        longitude: row[12] !== '' ? Number(row[12]) : null,
        notes: row[13] || ''
      });
    } else {
      // Format 11 kolom lama
      mutations.push({
        id: row[0],
        item_id: null,
        mutation_date: row[1] ? (row[1] instanceof Date ? Utilities.formatDate(row[1], Session.getScriptTimeZone(), 'yyyy-MM-dd') : String(row[1])) : '',
        type: row[2] || 'OUT',
        item_code: row[3] || '',
        item_name: row[4] || '',
        quantity: Number(row[5]) || 0,
        unit: row[6] || 'kg',
        officer_name: row[7] || '',
        destination_or_source: row[8] || '',
        location_name: row[9] || '',
        latitude: null,
        longitude: null,
        notes: row[10] || ''
      });
    }
  }
  return mutations;
}

function getItemsFromSheets(ss) {
  var items = [];
  var sheets = [
    { name: 'Bahan_Baku', type: 'bahan_baku' },
    { name: 'Produk_Pakan', type: 'produk' }
  ];

  for (var s = 0; s < sheets.length; s++) {
    var sheet = ss.getSheetByName(sheets[s].name);
    if (!sheet) continue;
    var data = sheet.getDataRange().getValues();
    if (data.length <= 1) continue;

    for (var r = 1; r < data.length; r++) {
      var row = data[r];
      if (!row[1]) continue; // Kode kosong
      items.push({
        id: row[0],
        code: row[1],
        name: row[2],
        category: row[3],
        stock_current: row[4],
        unit: row[5],
        stock_minimum: row[6],
        storage_location: row[7],
        price_per_unit: row[8],
        type: sheets[s].type,
        description: row[10] || ''
      });
    }
  }
  return items;
}

function getOrCreateSheet(ss, name, tabColor) {
  var sheet = ss.getSheetByName(name);
  if (!sheet) {
    sheet = ss.insertSheet(name);
  }
  if (tabColor) {
    sheet.setTabColor(tabColor);
  }
  return sheet;
}

function writeAndFormatSheet(sheet, rows) {
  sheet.clearContents();
  if (rows.length === 0) return;

  var numRows = rows.length;
  var numCols = rows[0].length;
  var range = sheet.getRange(1, 1, numRows, numCols);
  range.setValues(rows);

  // Format Header
  var headerRange = sheet.getRange(1, 1, 1, numCols);
  headerRange.setBackground('#1E5E3A')
             .setFontColor('#FFFFFF')
             .setFontWeight('bold')
             .setHorizontalAlignment('center');
  
  sheet.setFrozenRows(1);
  for (var col = 1; col <= numCols; col++) {
    sheet.autoResizeColumn(col);
  }
}

function createJsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
