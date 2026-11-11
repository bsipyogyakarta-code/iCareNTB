// assets/js/public.js - Client Script Khusus Publik (Katalog & Peta Distribusi iCare NTB)

let currentPublicDetailItem = null;
let publicDistMap = null;
let publicDistMarkers = [];
let publicDistMarkersMap = {};
let publicLocationsData = [];

document.addEventListener('DOMContentLoaded', () => {
    initPublicDistributionMap();
});

// Modal Helpers
function openModal(modalId) {
    const dialog = document.getElementById(modalId);
    if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
    }
}

function closeModal(modalId) {
    const dialog = document.getElementById(modalId);
    if (dialog && typeof dialog.close === 'function') {
        dialog.close();
    }
}

// Close dialog on backdrop click
document.querySelectorAll('dialog').forEach(dialog => {
    dialog.addEventListener('click', (e) => {
        const rect = dialog.getBoundingClientRect();
        const isInDialog = (
            rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
            rect.left <= e.clientX && e.clientX <= rect.left + rect.width
        );
        if (!isInDialog) {
            dialog.close();
        }
    });
});

// Modal Detail Pakan Jadi (Read-Only Publik)
function openDetailModalPublic(item) {
    if (!item) return;
    currentPublicDetailItem = item;

    const codeEl = document.getElementById('pub-detail-code');
    if (codeEl) codeEl.textContent = item.code || '-';

    const nameEl = document.getElementById('pub-detail-name');
    if (nameEl) nameEl.textContent = item.name || '-';

    const stockEl = document.getElementById('pub-detail-stock');
    if (stockEl) {
        stockEl.textContent = `${(parseFloat(item.stock_current) || 0).toLocaleString('id-ID')} ${item.unit || 'kg'}`;
    }

    const waEl = document.getElementById('pub-detail-wa');
    if (waEl) {
        const waNum = (typeof window.APP_WHATSAPP !== 'undefined' && window.APP_WHATSAPP) ? String(window.APP_WHATSAPP).trim() : '';
        if (waNum) {
            const cleanNum = waNum.replace(/^0/, '62').replace(/[^0-9]/g, '');
            const msg = encodeURIComponent(`Halo Admin iCare NTB, saya ingin konsultasi atau memesan ${item.name || 'Pakan Jadi'} (${item.code || ''}).`);
            waEl.innerHTML = `
                <a href="https://wa.me/${cleanNum}?text=${msg}" target="_blank" class="inline-flex items-center space-x-2 text-emerald-700 hover:text-emerald-800 font-bold hover:underline transition-smooth">
                    <span>${waNum}</span>
                    <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">Chat WA 💬</span>
                </a>
            `;
        } else {
            waEl.textContent = '- (Belum Disetel)';
        }
    }

    const imgEl = document.getElementById('pub-detail-img');
    if (imgEl) {
        imgEl.src = item.image_url || 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';
        imgEl.alt = item.name || 'Foto Pakan';
    }

    openModal('modal-detail-public');
}

// ========================================================
// PETA BESAR SEBARAN DISTRIBUSI DI HALAMAN PUBLIK
// ========================================================

async function initPublicDistributionMap() {
    const mapContainer = document.getElementById('public-distribution-map');
    if (!mapContainer || typeof L === 'undefined') return;

    // Default center ke NTB (antara Lombok & Sumbawa)
    const ntbCenter = [-8.65, 117.0];

    publicDistMap = L.map('public-distribution-map', {
        center: ntbCenter,
        zoom: 9,
        zoomControl: true,
        scrollWheelZoom: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(publicDistMap);

    await loadPublicDistributionLocations();
}

async function loadPublicDistributionLocations() {
    try {
        const res = await fetch('api.php?action=get_locations');
        const data = await res.json();
        if (data.success && Array.isArray(data.locations)) {
            publicLocationsData = data.locations;
            renderPublicDistributionMarkers(publicLocationsData);
            renderPublicDistList(publicLocationsData);
        }
    } catch (e) {
        console.error('Gagal memuat data sebaran distribusi:', e);
    }
}

function renderPublicDistributionMarkers(locations) {
    if (!publicDistMap || !locations) return;

    // Bersihkan marker lama
    publicDistMarkers.forEach(m => m.remove());
    publicDistMarkers = [];
    publicDistMarkersMap = {};

    const bounds = [];
    const countBadge = document.getElementById('pub-map-point-count');
    if (countBadge) {
        countBadge.textContent = `${locations.length} Titik Terdata`;
    }

    const customIcon = L.divIcon({
        className: 'minimap-pin',
        html: '<div class="minimap-pin-pulse"></div><div class="minimap-pin-inner"></div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -12]
    });

    locations.forEach(loc => {
        const lat = parseFloat(loc.latitude);
        const lng = parseFloat(loc.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        bounds.push([lat, lng]);

        const marker = L.marker([lat, lng], { icon: customIcon }).addTo(publicDistMap);

        const imgUrl = loc.image_url || 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';
        const qtyFormatted = (parseFloat(loc.quantity) || 0).toLocaleString('id-ID');

        const popupContent = `
            <div class="p-3.5 max-w-[290px] text-xs font-sans">
                <!-- Location Header -->
                <div class="flex items-center space-x-1.5 text-emerald-800 font-extrabold text-[11px] mb-2 uppercase tracking-wide bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-100">
                    <span>📍</span>
                    <span class="truncate">${escapeHtml(loc.location_name || 'Nusa Tenggara Barat')}</span>
                </div>

                <!-- Product Info Card inside Popup -->
                <div class="flex items-start space-x-2.5 p-2 bg-slate-50 rounded-xl border border-slate-100 mb-2.5">
                    <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(loc.item_name)}" class="w-12 h-12 rounded-lg object-cover shrink-0 border border-slate-200">
                    <div class="min-w-0 flex-1">
                        <span class="text-[9px] font-mono font-bold text-slate-500 block">${escapeHtml(loc.item_code || 'PRODUK')}</span>
                        <h4 class="font-extrabold text-slate-900 text-xs leading-snug line-clamp-2">${escapeHtml(loc.item_name)}</h4>
                        <span class="inline-block mt-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-100/70 px-1.5 py-0.2 rounded">
                            ${escapeHtml(loc.item_category || 'Pakan Jadi')}
                        </span>
                    </div>
                </div>

                <!-- Quantity Disalurkan -->
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-2 rounded-xl text-center shadow-xs mb-2.5">
                    <span class="text-[9px] uppercase font-bold text-emerald-100 block tracking-wider">Volume Pakan Tersalurkan</span>
                    <span class="text-sm font-black text-white">
                        ${qtyFormatted} ${escapeHtml(loc.unit || 'kg')}
                    </span>
                </div>

                <!-- Details: Penerima, Tanggal, Catatan -->
                <div class="space-y-1 text-slate-600 text-[11px] border-t border-slate-100 pt-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Penerima:</span>
                        <strong class="text-slate-800 text-right truncate max-w-[170px]">${escapeHtml(loc.destination_or_source)}</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Tanggal:</span>
                        <span class="font-semibold text-slate-700">${escapeHtml(loc.mutation_date)}</span>
                    </div>
                    ${loc.notes ? `
                    <div class="mt-1 pt-1 border-t border-dashed border-slate-200 text-[10px] text-slate-500 italic">
                        "${escapeHtml(loc.notes)}"
                    </div>` : ''}
                </div>
            </div>
        `;

        marker.bindPopup(popupContent);
        publicDistMarkers.push(marker);
        publicDistMarkersMap[loc.id] = marker;
    });

    if (bounds.length > 0) {
        publicDistMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
    }
}

// Render Interactive Cards in Side Panel
function renderPublicDistList(locations) {
    const container = document.getElementById('pub-dist-list-container');
    if (!container) return;

    if (!locations || locations.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 px-3 text-slate-400 text-xs">
                <span class="text-3xl block mb-2">📍</span>
                <p class="font-bold text-slate-600 text-sm">Belum Ada Titik Distribusi</p>
                <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">Data penyaluran komoditas pakan akan muncul di sini setelah dicatat pada sistem admin.</p>
            </div>
        `;
        const badge = document.getElementById('pub-total-volume-badge');
        if (badge) badge.textContent = 'Total: 0 kg';
        return;
    }

    let totalVolume = 0;
    let html = '';

    locations.forEach(loc => {
        const qty = parseFloat(loc.quantity) || 0;
        totalVolume += qty;
        const imgUrl = loc.image_url || 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';

        html += `
            <div class="p-3 bg-slate-50 hover:bg-emerald-50/70 rounded-2xl border border-slate-100 hover:border-emerald-200 transition-all cursor-pointer group shadow-2xs"
                 onclick="focusMapOnLocation(${loc.id}, ${loc.latitude}, ${loc.longitude})">
                <div class="flex items-start space-x-2.5">
                    <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(loc.item_name)}" 
                         class="w-12 h-12 rounded-xl object-cover shrink-0 border border-slate-200 group-hover:scale-105 transition-transform">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[9px] font-mono font-bold text-slate-400">${escapeHtml(loc.item_code || 'PRODUK')}</span>
                            <span class="text-[10px] font-extrabold text-emerald-800 bg-emerald-100/90 px-1.5 py-0.5 rounded-md">
                                ${qty.toLocaleString('id-ID')} ${escapeHtml(loc.unit || 'kg')}
                            </span>
                        </div>
                        <h4 class="font-extrabold text-slate-900 text-xs leading-snug truncate group-hover:text-emerald-700 transition-colors mt-0.5">
                            ${escapeHtml(loc.item_name)}
                        </h4>
                        <p class="text-[11px] text-slate-600 mt-1 flex items-center space-x-1 truncate">
                            <span>📍</span>
                            <span class="font-semibold text-slate-700">${escapeHtml(loc.location_name || 'NTB')}</span>
                        </p>
                        <div class="flex items-center justify-between mt-1.5 text-[10px] text-slate-400 border-t border-slate-200/60 pt-1">
                            <span class="truncate max-w-[150px]">Penerima: <strong class="text-slate-600">${escapeHtml(loc.destination_or_source)}</strong></span>
                            <span class="shrink-0 text-emerald-700 font-bold group-hover:translate-x-0.5 transition-transform flex items-center space-x-0.5">
                                <span>Lihat di Peta</span>
                                <span>📍</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;

    const badge = document.getElementById('pub-total-volume-badge');
    if (badge) {
        badge.textContent = `Total: ${totalVolume.toLocaleString('id-ID')} kg`;
    }
}

// Focus Map on a specific distribution location
function focusMapOnLocation(id, lat, lng) {
    if (!publicDistMap) return;

    lat = parseFloat(lat);
    lng = parseFloat(lng);
    if (isNaN(lat) || isNaN(lng)) return;

    publicDistMap.flyTo([lat, lng], 13, {
        animate: true,
        duration: 1.0
    });

    const marker = publicDistMarkersMap[id];
    if (marker) {
        setTimeout(() => {
            marker.openPopup();
        }, 600);
    }
}

// Filter Side List and Map Markers by search term
function filterPublicDistList(query) {
    if (!publicLocationsData) return;
    const term = (query || '').toLowerCase().trim();

    if (!term) {
        renderPublicDistributionMarkers(publicLocationsData);
        renderPublicDistList(publicLocationsData);
        return;
    }

    const filtered = publicLocationsData.filter(loc => {
        const name = (loc.item_name || '').toLowerCase();
        const code = (loc.item_code || '').toLowerCase();
        const dest = (loc.destination_or_source || '').toLowerCase();
        const locName = (loc.location_name || '').toLowerCase();
        const cat = (loc.item_category || '').toLowerCase();
        const notes = (loc.notes || '').toLowerCase();

        return name.includes(term) || code.includes(term) || dest.includes(term) || locName.includes(term) || cat.includes(term) || notes.includes(term);
    });

    renderPublicDistributionMarkers(filtered);
    renderPublicDistList(filtered);
}

function fitPublicDistributionMap() {
    if (!publicDistMap) return;
    if (publicLocationsData && publicLocationsData.length > 0) {
        const bounds = publicLocationsData
            .map(l => [parseFloat(l.latitude), parseFloat(l.longitude)])
            .filter(pt => !isNaN(pt[0]) && !isNaN(pt[1]));
        if (bounds.length > 0) {
            publicDistMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
            return;
        }
    }
    publicDistMap.setView([-8.65, 117.0], 9);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
