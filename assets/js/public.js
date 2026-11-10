// assets/js/public.js - Client Script Khusus Publik (Katalog Pakan Jadi BRMP DIY)

let currentPublicDetailItem = null;

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
