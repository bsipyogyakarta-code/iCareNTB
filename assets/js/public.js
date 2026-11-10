// assets/js/public.js - Client Script Khusus Publik (Katalog & Peta Distribusi iCare NTB)

let currentPublicDetailItem = null;
let publicDistMap = null;
let publicDistMarkers = [];
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
        // Dikosongkan dulu sesuai permintaan
        waEl.textContent = '-';
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

        const popupContent = `
            <div class="p-3.5 max-w-[280px] text-xs font-sans">
                <div class="flex items-center space-x-1.5 text-emerald-700 font-extrabold text-[11px] mb-1 uppercase tracking-wider">
                    <span>📍 ${escapeHtml(loc.location_name || 'Nusa Tenggara Barat')}</span>
                </div>
                <h4 class="font-extrabold text-slate-900 text-sm leading-snug mb-1">${escapeHtml(loc.item_name)}</h4>
                <div class="bg-emerald-50 text-emerald-800 px-2.5 py-1 rounded-lg font-black text-xs inline-block mb-2 border border-emerald-100">
                    Disalurkan: ${(parseFloat(loc.quantity) || 0).toLocaleString('id-ID')} ${escapeHtml(loc.unit || 'kg')}
                </div>
                <div class="space-y-1 text-slate-600 text-[11px] border-t border-slate-100 pt-2">
                    <div><span class="text-slate-400">Penerima:</span> <strong class="text-slate-800">${escapeHtml(loc.destination_or_source)}</strong></div>
                    <div><span class="text-slate-400">Tanggal:</span> <span class="font-semibold text-slate-700">${loc.mutation_date}</span></div>
                    ${loc.notes ? `<div class="italic text-slate-500 mt-1 text-[10px] bg-slate-50 p-1.5 rounded-lg border border-slate-100">"${escapeHtml(loc.notes)}"</div>` : ''}
                </div>
            </div>
        `;

        marker.bindPopup(popupContent);
        publicDistMarkers.push(marker);
    });

    if (bounds.length > 0) {
        publicDistMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
    }
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
