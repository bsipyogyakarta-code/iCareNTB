// assets/js/app.js - Rumah Produksi BRMP DIY (Manajemen Pakan Jadi & Bahan Baku)

let currentDetailItem = null;
let currentProductRecipe = [];
let distributionMap = null;
let distributionMarkers = {};
let distributionLocationsData = [];

// Single Product Map Variables
let singleProductMap = null;
let singleProductMarkers = {};
let currentMapProductItem = null;

// Distribution Form & Result Map Variables
let currentDistributingItem = null;
let distPickerMap = null;
let distPickerMarker = null;
let resultMinimap = null;
let resultMinimapMarkers = [];

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    setupEventHandlers();
    setupProductionListeners();
    initDistributionMinimap();
    setupLocationPresets();
    setupDistributeLocationPresets();
    initProduceFeature();
    initWhatsAppFeature();
});

// Toast Notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast-notification');
    const toastMsg = document.getElementById('toast-message');
    const toastIcon = document.getElementById('toast-icon');

    if (!toast) return;

    toastMsg.textContent = message;
    if (type === 'error') {
        toast.className = 'fixed bottom-5 right-5 z-50 flex items-center p-4 text-white bg-rose-600 rounded-xl shadow-2xl transition-all duration-300 transform translate-y-0 opacity-100';
        toastIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
    } else {
        toast.className = 'fixed bottom-5 right-5 z-50 flex items-center p-4 text-white bg-emerald-600 rounded-xl shadow-2xl transition-all duration-300 transform translate-y-0 opacity-100';
        toastIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
    }

    setTimeout(() => {
        toast.className = 'fixed bottom-5 right-5 z-50 flex items-center p-4 text-white bg-emerald-600 rounded-xl shadow-2xl transition-all duration-300 transform translate-y-10 opacity-0 pointer-events-none';
    }, 4500);
}

// Navigation Tabs for Admin Features
function initTabs() {
    const navButtons = document.querySelectorAll('.feature-tab-btn');
    const sections = {
        'tab-produk': document.getElementById('section-produk'),
        'tab-bahan-baku': document.getElementById('section-bahan-baku'),
        'tab-mutasi': document.getElementById('section-mutasi')
    };

    navButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-target');

            // Reset buttons to transparent/subtle pill
            navButtons.forEach(b => {
                b.classList.remove('bg-[#059669]', 'bg-emerald-600', 'text-white', 'shadow-md');
                b.classList.add('bg-transparent', 'text-slate-600', 'hover:text-emerald-800', 'hover:bg-slate-100/70');
                const badge = b.querySelector('span:last-child');
                if (badge) {
                    badge.classList.remove('bg-white', 'text-emerald-800');
                    badge.classList.add('bg-slate-100', 'text-slate-700');
                }
            });

            // Activate chosen button
            btn.classList.add('bg-[#059669]', 'text-white', 'shadow-md');
            btn.classList.remove('bg-transparent', 'text-slate-600', 'hover:text-emerald-800', 'hover:bg-slate-100/70');
            const activeBadge = btn.querySelector('span:last-child');
            if (activeBadge) {
                activeBadge.classList.add('bg-white', 'text-emerald-800');
                activeBadge.classList.remove('bg-slate-100', 'text-slate-700');
            }

            // Show active section
            Object.keys(sections).forEach(key => {
                if (sections[key]) {
                    if (key === target) {
                        sections[key].classList.remove('hidden');
                    } else {
                        sections[key].classList.add('hidden');
                    }
                }
            });
        });
    });
}

// Copy Color HEX to Clipboard
function copyColorHex(hex, name) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(hex).then(() => {
            showToast(`Warna ${name} (${hex}) berhasil disalin ke clipboard!`);
        }).catch(() => {
            promptCopy(hex);
        });
    } else {
        promptCopy(hex);
    }
}

function promptCopy(text) {
    const tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    showToast(`Kode warna ${text} berhasil disalin!`);
}

// Setup Event Handlers
function setupEventHandlers() {
    // Setup filter for each section
    setupSectionFilters('bahan-baku');
    setupSectionFilters('produk');

    // Form Add Item
    const formAddItem = document.getElementById('form-add-item');
    if (formAddItem) {
        formAddItem.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Kumpulkan tombol submit untuk diberi state loading & disabled
            const submitBtns = formAddItem.querySelectorAll('button[type="submit"], #btn-next-slide');
            const originalContents = [];
            submitBtns.forEach((btn, i) => {
                originalContents[i] = btn.innerHTML;
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                btn.innerHTML = `<span class="inline-block animate-spin mr-1.5">⏳</span> Menyimpan...`;
            });

            const resetButtons = () => {
                submitBtns.forEach((btn, i) => {
                    btn.disabled = false;
                    btn.classList.remove('opacity-75', 'cursor-not-allowed');
                    btn.innerHTML = originalContents[i];
                });
            };

            const formData = new FormData(formAddItem);
            formData.append('action', 'add_item');

            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    closeModal('modal-add-item');
                    formAddItem.reset();
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message || 'Gagal menyimpan item.', 'error');
                    resetButtons();
                }
            } catch (err) {
                showToast('Gagal menghubungi server. Periksa koneksi internet Anda.', 'error');
                resetButtons();
            }
        });
    }

    // Form Standard Mutation
    const formMutation = document.getElementById('form-mutation');
    if (formMutation) {
        formMutation.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(formMutation);
            formData.append('action', 'add_mutation');

            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    closeModal('modal-mutation');
                    formMutation.reset();
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Gagal menghubungi server.', 'error');
            }
        });
    }

    // Form Distribusi Benih/Pakan & Penentuan Lokasi
    const formDistribute = document.getElementById('form-distribute') || document.getElementById('form-distribute-seed');
    if (formDistribute) {
        formDistribute.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = formDistribute.querySelector('button[type="submit"]');
            let originalContent = '';
            if (submitBtn) {
                originalContent = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = '<span class="inline-block animate-spin mr-1.5">⏳</span> Menyimpan & Memetakan...';
            }

            const resetBtn = () => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                    submitBtn.innerHTML = originalContent;
                }
            };

            const formData = new FormData(formDistribute);
            formData.append('action', 'add_mutation');
            formData.append('type', 'OUT');

            // Fallback parameter
            if (!formData.get('item_id')) {
                const itId = document.getElementById('dist-item-id')?.value;
                if (itId) formData.set('item_id', itId);
            }
            if (!formData.get('quantity')) {
                const amt = document.getElementById('dist-amount')?.value || document.getElementById('dist-quantity')?.value;
                if (amt) formData.set('quantity', amt);
            }
            if (!formData.get('destination_or_source')) {
                const rec = document.getElementById('dist-recipient')?.value;
                if (rec) formData.set('destination_or_source', rec);
            }

            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast('Distribusi berhasil dicatat!');
                    closeModal('modal-distribute');
                    formDistribute.reset();

                    // Update live stock di card dashboard jika ada
                    if (data.item_id) {
                        const cardStock = document.getElementById(`card-stock-${data.item_id}`);
                        if (cardStock) {
                            cardStock.textContent = `${Number(data.new_stock).toLocaleString('id-ID')} ${data.item_unit || 'kg'}`;
                        }
                    }

                    // Reload halaman agar semua tabel, log mutasi, dan peta terbarui secara akurat
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    showToast(data.message || 'Gagal mencatat distribusi.', 'error');
                    resetBtn();
                }
            } catch (err) {
                showToast('Gagal menghubungi server. Periksa koneksi internet Anda.', 'error');
                resetBtn();
            }
        });
    }

    // Form Edit Item
    const formEditItem = document.getElementById('form-edit-item');
    if (formEditItem) {
        formEditItem.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(formEditItem);
            formData.append('action', 'update_item');

            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    closeModal('modal-edit-item');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Gagal memperbarui item.', 'error');
            }
        });
    }
}

// Setup Filters for a specific section
function setupSectionFilters(sectionKey) {
    const searchInput = document.getElementById(`search-${sectionKey}`);
    const statusSelect = document.getElementById(`status-${sectionKey}`);
    const pills = document.querySelectorAll(`.cat-pill-${sectionKey}`);

    const applyFilter = (categoryVal = '') => {
        const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const statusVal = statusSelect ? statusSelect.value : '';

        const cards = document.querySelectorAll(`.item-card-${sectionKey}`);
        let visibleCount = 0;

        cards.forEach(card => {
            const name = card.getAttribute('data-name')?.toLowerCase() || '';
            const code = card.getAttribute('data-code')?.toLowerCase() || '';
            const category = card.getAttribute('data-category') || '';
            const isCritical = card.getAttribute('data-critical') === '1';

            let matchSearch = !searchVal || name.includes(searchVal) || code.includes(searchVal) || category.toLowerCase().includes(searchVal);
            let matchCat = !categoryVal || category === categoryVal;
            let matchStatus = true;
            if (statusVal === 'critical') matchStatus = isCritical;
            if (statusVal === 'safe') matchStatus = !isCritical;

            if (matchSearch && matchCat && matchStatus) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        const noResult = document.getElementById(`no-results-${sectionKey}`);
        if (noResult) {
            if (visibleCount === 0) noResult.classList.remove('hidden');
            else noResult.classList.add('hidden');
        }
    };

    if (searchInput) searchInput.addEventListener('input', () => applyFilter(activeCategory[sectionKey] || ''));
    if (statusSelect) statusSelect.addEventListener('change', () => applyFilter(activeCategory[sectionKey] || ''));

    pills.forEach(pill => {
        pill.addEventListener('click', () => {
            const cat = pill.getAttribute('data-category') || '';
            activeCategory[sectionKey] = cat;

            // Highlight pill
            pills.forEach(p => {
                p.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600', 'shadow-xs');
                p.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
            });
            pill.classList.add('bg-emerald-600', 'text-white', 'border-emerald-600', 'shadow-xs');
            pill.classList.remove('bg-white', 'text-slate-600', 'border-slate-200');

            applyFilter(cat);
        });
    });
}

const activeCategory = {
    'bahan-baku': '',
    'produk': ''
};

// Open HTML5 Dialog Modal
function openModal(id) {
    const dialog = document.getElementById(id);
    if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
    }
}

// Close HTML5 Dialog Modal
function closeModal(id) {
    const dialog = document.getElementById(id);
    if (dialog && typeof dialog.close === 'function') {
        dialog.close();
    }
}

// Open Detail Modal
function openDetailModal(item) {
    currentDetailItem = item;

    document.getElementById('detail-code').textContent = item.code;
    document.getElementById('detail-name').textContent = item.name;
    document.getElementById('detail-type').textContent = item.type.toUpperCase().replace('_', ' ');
    document.getElementById('detail-category').textContent = item.category;
    document.getElementById('detail-location').textContent = item.storage_location || 'Gudang Utama iCare NTB';
    document.getElementById('detail-stock').textContent = `${Number(item.stock_current).toLocaleString('id-ID')} ${item.unit}`;
    document.getElementById('detail-min').textContent = `${Number(item.stock_minimum).toLocaleString('id-ID')} ${item.unit}`;
    document.getElementById('detail-price').textContent = `Rp ${Number(item.price_per_unit).toLocaleString('id-ID')}`;

    const detailImg = document.getElementById('detail-img');
    if (detailImg) {
        detailImg.src = item.image_url || 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';
    }

    document.getElementById('detail-desc').textContent = item.description || '-';

    // Specs
    document.getElementById('detail-spec1-label').textContent = item.spec_1_label || 'Parameter 1';
    document.getElementById('detail-spec1-val').textContent = item.spec_1_value || '-';
    document.getElementById('detail-spec2-label').textContent = item.spec_2_label || 'Parameter 2';
    document.getElementById('detail-spec2-val').textContent = item.spec_2_value || '-';
    document.getElementById('detail-spec3-label').textContent = item.spec_3_label || 'Parameter 3';
    document.getElementById('detail-spec3-val').textContent = item.spec_3_value || '-';

    // If it's a finished product, show "Lihat Peta Sebaran Produk" button
    const btnMapDetail = document.getElementById('btn-map-from-detail');
    if (btnMapDetail) {
        if (item.type === 'produk') {
            btnMapDetail.classList.remove('hidden');
        } else {
            btnMapDetail.classList.add('hidden');
        }
    }

    openModal('modal-detail-item');
}

// Quick Mutation Trigger
function openQuickMutation(itemId, itemName, itemUnit, defaultType = 'IN') {
    const select = document.getElementById('mut-item-id');
    const typeSelect = document.getElementById('mut-type');
    const unitLabel = document.getElementById('mut-unit-label');

    if (select) select.value = itemId;
    if (typeSelect) typeSelect.value = defaultType;
    if (unitLabel) unitLabel.textContent = itemUnit;

    openModal('modal-mutation');
}

function triggerMutationFromDetail(type) {
    if (!currentDetailItem) return;
    closeModal('modal-detail-item');
    openQuickMutation(currentDetailItem.id, currentDetailItem.name, currentDetailItem.unit, type);
}

// Quick refresh trigger
function refreshData() {
    showToast('Menyegarkan data inventaris...');
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

// Quick modal open with prefilled type & slide multi-step
let rawMaterialsCache = (typeof initialRawMaterials !== 'undefined' && Array.isArray(initialRawMaterials))
    ? initialRawMaterials
    : [];

function openAddModalWithType(type = 'produk') {
    const form = document.getElementById('form-add-item');
    if (form) form.reset();

    document.getElementById('add-type').value = type;
    document.getElementById('add-image-preview-container')?.classList.add('hidden');
    document.getElementById('add-image-preview')?.removeAttribute('src');

    const modalTitle = document.getElementById('add-modal-title');
    const modalSubtitle = document.getElementById('add-modal-subtitle');
    const modalIconContainer = document.getElementById('add-modal-icon-container');
    const labelNameType = document.getElementById('label-name-type');
    const stepperBar = document.getElementById('add-stepper-bar');
    const btnNextSlide = document.getElementById('btn-next-slide');
    const btnSubmitDirectFeed = document.getElementById('btn-submit-direct-feed');
    const btnSubmitSingle = document.getElementById('btn-submit-single');
    const deductContainer = document.getElementById('deduct-rows-container');

    if (deductContainer) deductContainer.innerHTML = '';
    const emptyNotice = document.getElementById('empty-deduct-notice');
    if (emptyNotice) emptyNotice.classList.remove('hidden');

    goToSlide1();

    if (type === 'produk') {
        if (modalTitle) modalTitle.textContent = 'Tambah Pakan Jadi Baru';
        if (modalSubtitle) modalSubtitle.textContent = 'Informasi Pakan & Stok Awal';
        if (modalIconContainer) modalIconContainer.textContent = '📦';
        if (labelNameType) labelNameType.textContent = 'Pakan Jadi';
        if (stepperBar) stepperBar.classList.remove('hidden');
        if (btnNextSlide) btnNextSlide.classList.remove('hidden');
        if (btnSubmitDirectFeed) btnSubmitDirectFeed.classList.remove('hidden');
        if (btnSubmitSingle) btnSubmitSingle.classList.add('hidden');
    } else {
        if (modalTitle) modalTitle.textContent = 'Tambah Bahan Baku Baru';
        if (modalSubtitle) modalSubtitle.textContent = 'Input stok bahan baku gudang';
        if (modalIconContainer) modalIconContainer.textContent = '🌾';
        if (labelNameType) labelNameType.textContent = 'Bahan Baku';
        if (stepperBar) stepperBar.classList.add('hidden');
        if (btnNextSlide) btnNextSlide.classList.add('hidden');
        if (btnSubmitDirectFeed) btnSubmitDirectFeed.classList.add('hidden');
        if (btnSubmitSingle) btnSubmitSingle.classList.remove('hidden');
    }

    openModal('modal-add-item');
}

function goToSlide1() {
    const s1 = document.getElementById('add-slide-1');
    const s2 = document.getElementById('add-slide-2');
    if (s1) s1.classList.remove('hidden');
    if (s2) s2.classList.add('hidden');

    const dot1 = document.getElementById('step-dot-1');
    const dot2 = document.getElementById('step-dot-2');
    const txt1 = document.getElementById('step-text-1');
    const txt2 = document.getElementById('step-text-2');
    const subtitle = document.getElementById('add-modal-subtitle');

    if (dot1) dot1.className = 'w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center shadow-xs';
    if (txt1) txt1.className = 'text-xs font-bold text-emerald-800';
    if (dot2) dot2.className = 'w-6 h-6 rounded-full bg-slate-200 text-slate-500 font-bold text-xs flex items-center justify-center';
    if (txt2) txt2.className = 'text-xs font-semibold text-slate-400';
    if (subtitle) subtitle.textContent = 'Langkah 1 dari 2: Informasi Pakan';
}

function goToSlide2() {
    const nameInput = document.getElementById('add-name');
    const stockInput = document.getElementById('add-stock');

    if (!nameInput || !nameInput.value.trim()) {
        showToast('Silakan isi Nama Pakan Jadi terlebih dahulu.', 'error');
        nameInput?.focus();
        return;
    }

    if (!stockInput || stockInput.value === '' || parseFloat(stockInput.value) < 0) {
        showToast('Silakan isi Jumlah Berat dengan benar (minimal 0 kg).', 'error');
        stockInput?.focus();
        return;
    }

    const s1 = document.getElementById('add-slide-1');
    const s2 = document.getElementById('add-slide-2');
    if (s1) s1.classList.add('hidden');
    if (s2) s2.classList.remove('hidden');

    const summaryName = document.getElementById('summary-feed-name');
    if (summaryName) summaryName.textContent = nameInput.value.trim();

    const dot1 = document.getElementById('step-dot-1');
    const dot2 = document.getElementById('step-dot-2');
    const txt1 = document.getElementById('step-text-1');
    const txt2 = document.getElementById('step-text-2');
    const subtitle = document.getElementById('add-modal-subtitle');

    if (dot1) dot1.className = 'w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center justify-center';
    if (txt1) txt1.className = 'text-xs font-medium text-slate-500';
    if (dot2) dot2.className = 'w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center shadow-xs';
    if (txt2) txt2.className = 'text-xs font-bold text-emerald-800';
    if (subtitle) subtitle.textContent = 'Langkah 2 dari 2: Pengurangan Bahan Baku (Fleksibel)';
}

function addDeductRow() {
    const container = document.getElementById('deduct-rows-container');
    const emptyNotice = document.getElementById('empty-deduct-notice');
    if (!container) return;

    if (emptyNotice) emptyNotice.classList.add('hidden');

    const row = document.createElement('div');
    row.className = 'deduct-row bg-slate-50/90 p-2.5 rounded-2xl border border-slate-200 flex items-center gap-2 transition-all';

    let optionsHtml = '<option value="">-- Pilih Bahan Baku --</option>';
    if (Array.isArray(rawMaterialsCache)) {
        rawMaterialsCache.forEach(m => {
            optionsHtml += `<option value="${m.id}" data-stock="${m.stock}" data-unit="${m.unit}">🌾 ${escapeHtml(m.name)} (Stok: ${Number(m.stock).toLocaleString('id-ID')} ${m.unit})</option>`;
        });
    }

    row.innerHTML = `
        <div class="flex-1">
            <select name="deduct_material_id[]" required onchange="handleDeductSelect(this)" 
                    class="w-full text-xs font-bold bg-white border border-slate-300 rounded-xl px-2.5 py-2 focus:ring-2 focus:ring-emerald-500">
                ${optionsHtml}
            </select>
        </div>
        <div class="w-32 relative">
            <input type="number" step="0.1" min="0.1" name="deduct_quantity[]" required placeholder="0" 
                   class="w-full text-xs font-black pl-3 pr-9 py-2 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 text-slate-900">
            <span class="deduct-unit-badge absolute right-2.5 top-2 text-[10px] font-bold text-slate-400 uppercase">kg</span>
        </div>
        <button type="button" onclick="removeDeductRow(this)" class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-xl transition-smooth" title="Hapus Baris">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    `;

    container.appendChild(row);
}

function removeDeductRow(btn) {
    const row = btn.closest('.deduct-row');
    if (row) row.remove();

    const container = document.getElementById('deduct-rows-container');
    const emptyNotice = document.getElementById('empty-deduct-notice');
    if (container && container.children.length === 0 && emptyNotice) {
        emptyNotice.classList.remove('hidden');
    }
}

function handleDeductSelect(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const unit = opt ? opt.getAttribute('data-unit') : 'kg';
    const row = selectEl.closest('.deduct-row');
    if (row) {
        const badge = row.querySelector('.deduct-unit-badge');
        if (badge) badge.textContent = unit || 'kg';
    }
}

function compressAndConvertImage(file, maxWidth = 800, quality = 0.8) {
    return new Promise((resolve) => {
        if (!file || !file.type.startsWith('image/')) {
            return resolve('');
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                if (width > maxWidth) {
                    height = Math.round((height * maxWidth) / width);
                    width = maxWidth;
                }
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                const dataUrl = canvas.toDataURL('image/jpeg', quality);
                resolve(dataUrl);
            };
            img.onerror = () => resolve(e.target.result);
            img.src = e.target.result;
        };
        reader.onerror = () => resolve('');
        reader.readAsDataURL(file);
    });
}

async function previewAddImage(input) {
    const container = document.getElementById('add-image-preview-container');
    const preview = document.getElementById('add-image-preview');
    const urlInput = document.getElementById('add-image-url');
    if (!container || !preview) return;

    if (input.files && input.files[0]) {
        const compressed = await compressAndConvertImage(input.files[0], 800, 0.8);
        preview.src = compressed;
        container.classList.remove('hidden');
        if (urlInput) {
            urlInput.value = compressed;
        }
    }
}

function previewAddImageUrl(url) {
    const container = document.getElementById('add-image-preview-container');
    const preview = document.getElementById('add-image-preview');
    if (!container || !preview) return;

    if (url && (url.trim().startsWith('http') || url.trim().startsWith('data:image/'))) {
        preview.src = url.trim();
        container.classList.remove('hidden');
    }
}

async function previewEditImage(input) {
    const preview = document.getElementById('edit-image-preview');
    const urlInput = document.getElementById('edit-image');
    if (!preview) return;
    if (input.files && input.files[0]) {
        const compressed = await compressAndConvertImage(input.files[0], 800, 0.8);
        preview.src = compressed;
        if (urlInput) {
            urlInput.value = compressed;
        }
    }
}

function previewEditImageUrl(url) {
    const preview = document.getElementById('edit-image-preview');
    if (preview && url && (url.trim().startsWith('http') || url.trim().startsWith('data:image/'))) {
        preview.src = url.trim();
    }
}

// ========================================================
// FITUR LOKASI & MINIMAP DISTRIBUSI BENIH BRMP DIY
// ========================================================

function initDistributionMinimap() {
    const mapContainer = document.getElementById('distribution-minimap');
    if (!mapContainer || typeof L === 'undefined') return;

    // Titik pusat Nusa Tenggara Barat (-8.65, 117.0)
    const ntbCenter = [-8.65, 117.0];

    distributionMap = L.map('distribution-minimap', {
        center: ntbCenter,
        zoom: 9,
        zoomControl: true,
        scrollWheelZoom: false
    });

    // OpenStreetMap Standard Tile Layer (Gratis, jernih & tanpa API Key)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(distributionMap);

    loadDistributionLocations();
}

async function loadDistributionLocations() {
    try {
        const res = await fetch('api.php?action=get_locations&type=produk');
        const data = await res.json();
        if (data.success && Array.isArray(data.locations)) {
            distributionLocationsData = data.locations;
            renderMinimapMarkers(distributionLocationsData);
            renderDistributionList(distributionLocationsData);
        }
    } catch (e) {
        console.error('Gagal memuat titik distribusi:', e);
    }
}

function renderMinimapMarkers(locations) {
    if (!distributionMap || !locations) return;

    // Clear existing markers
    Object.values(distributionMarkers).forEach(m => m.remove());
    distributionMarkers = {};

    const bounds = [];

    // Create Minimalist Pin Icon
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

        const marker = L.marker([lat, lng], { icon: customIcon }).addTo(distributionMap);

        // Minimalist Popup Design
        const popupContent = `
            <div class="p-3.5 max-w-[260px] text-xs font-sans">
                <div class="flex items-center space-x-1.5 text-emerald-700 font-extrabold text-[11px] mb-1 uppercase tracking-wider">
                    <span>📍 ${escapeHtml(loc.location_name || 'Nusa Tenggara Barat')}</span>
                </div>
                <h4 class="font-bold text-slate-900 text-sm leading-snug mb-1">${escapeHtml(loc.item_name)}</h4>
                <div class="bg-emerald-50 text-emerald-800 px-2.5 py-1 rounded-lg font-bold text-xs inline-block mb-2 border border-emerald-100">
                    Penyaluran: ${(parseFloat(loc.quantity) || 0).toLocaleString('id-ID')} ${escapeHtml(loc.unit || 'unit')}
                </div>
                <div class="space-y-1 text-slate-600 text-[11px] border-t border-slate-100 pt-2">
                    <div><span class="text-slate-400">Penerima:</span> <strong class="text-slate-800">${escapeHtml(loc.destination_or_source)}</strong></div>
                    <div><span class="text-slate-400">Tanggal:</span> ${loc.mutation_date}</div>
                    ${loc.notes ? `<div class="italic text-slate-500 mt-1 text-[10px] bg-slate-50 p-1.5 rounded">"${escapeHtml(loc.notes)}"</div>` : ''}
                </div>
            </div>
        `;

        marker.bindPopup(popupContent);
        distributionMarkers[loc.id] = marker;
    });

    // Update count badge
    const badge = document.getElementById('label-count-titik');
    if (badge) {
        badge.textContent = `${locations.length} Titik Sebaran`;
    }

    // Auto fit map bounds if we have points
    if (bounds.length > 0) {
        distributionMap.fitBounds(bounds, { padding: [35, 35], maxZoom: 12 });
    }
}

function renderDistributionList(locations) {
    const listContainer = document.getElementById('distribution-points-list');
    if (!listContainer) return;

    if (!locations || locations.length === 0) {
        listContainer.innerHTML = `
            <div class="text-center py-8 text-slate-400 text-xs">
                Belum ada titik distribusi yang tercatat.
            </div>
        `;
        return;
    }

    let html = '';
    locations.forEach(loc => {
        html += `
            <div onclick="panToLocation(${loc.id}, ${loc.latitude}, ${loc.longitude})"
                 class="p-3 bg-slate-50 hover:bg-emerald-50/80 rounded-2xl border border-slate-200/70 hover:border-emerald-300 transition-all cursor-pointer group text-xs flex items-center justify-between">
                <div class="pr-2">
                    <div class="font-bold text-slate-800 group-hover:text-emerald-800 transition-smooth line-clamp-1">
                        ${escapeHtml(loc.location_name || loc.destination_or_source)}
                    </div>
                    <div class="text-[11px] text-slate-500 mt-0.5">
                        <span class="font-semibold text-emerald-700">${escapeHtml(loc.item_name)}</span> &bull; 
                        <span class="font-bold text-slate-700">${(parseFloat(loc.quantity) || 0).toLocaleString('id-ID')} ${escapeHtml(loc.unit || 'unit')}</span>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">
                        ${loc.mutation_date} &bull; ${escapeHtml(loc.destination_or_source)}
                    </div>
                </div>
                <div class="shrink-0 text-slate-400 group-hover:text-emerald-600 transition-smooth p-1 bg-white rounded-xl shadow-2xs border border-slate-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </div>
            </div>
        `;
    });

    listContainer.innerHTML = html;
}

function panToLocation(mutationId, lat, lng) {
    if (!distributionMap) return;

    distributionMap.flyTo([lat, lng], 13, {
        animate: true,
        duration: 1.2
    });

    const marker = distributionMarkers[mutationId];
    if (marker) {
        setTimeout(() => {
            marker.openPopup();
        }, 1300);
    }
}

function focusMinimap() {
    const mapEl = document.getElementById('container-minimap');
    if (mapEl) {
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function focusMinimapByItemId(itemId) {
    const targetLoc = distributionLocationsData.find(l => parseInt(l.item_id) === parseInt(itemId));

    const mapEl = document.getElementById('container-minimap');
    if (mapEl) {
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (targetLoc && targetLoc.latitude && targetLoc.longitude) {
        setTimeout(() => {
            panToLocation(targetLoc.id, targetLoc.latitude, targetLoc.longitude);
        }, 400);
    } else {
        showToast('Belum ada riwayat koordinat sebaran untuk produk ini.', 'error');
    }
}

function triggerMapFromDetail() {
    if (!currentDetailItem) return;
    const item = currentDetailItem;
    closeModal('modal-detail-item');
    openProductMapModal(item);
}

function resetMinimapView() {
    if (!distributionMap) return;
    if (distributionLocationsData && distributionLocationsData.length > 0) {
        const bounds = distributionLocationsData.map(l => [parseFloat(l.latitude), parseFloat(l.longitude)]).filter(pt => !isNaN(pt[0]));
        if (bounds.length > 0) {
            distributionMap.fitBounds(bounds, { padding: [35, 35], maxZoom: 12 });
            return;
        }
    }
    distributionMap.flyTo([-8.65, 117.0], 9);
}

// Preset Wilayah DIY di Modal Mutasi
function setupLocationPresets() {
    const presetSelect = document.getElementById('mut-preset-location');
    const latInput = document.getElementById('mut-lat');
    const lngInput = document.getElementById('mut-lng');
    const locNameInput = document.getElementById('mut-loc-name');
    const destInput = document.getElementById('mut-destination');

    if (!presetSelect) return;

    presetSelect.addEventListener('change', () => {
        const val = presetSelect.value;
        const selectedOpt = presetSelect.options[presetSelect.selectedIndex];

        if (val === 'manual') {
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
            if (locNameInput) locNameInput.focus();
            return;
        }

        if (val && selectedOpt) {
            const lat = selectedOpt.getAttribute('data-lat');
            const lng = selectedOpt.getAttribute('data-lng');

            if (latInput) latInput.value = lat || '';
            if (lngInput) lngInput.value = lng || '';
            if (locNameInput) locNameInput.value = val;

            if (destInput && (!destInput.value || destInput.value.trim() === '')) {
                destInput.value = 'Kelompok Tani Wilayah ' + val;
            }
        }
    });
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

// ========================================================
// PETA SEBARAN KHUSUS PER PRODUK (SINGLE PRODUCT MAP)
// ========================================================

async function openProductMapModal(item) {
    if (!item) return;
    currentMapProductItem = item;

    const pmapImg = document.getElementById('pmap-img');
    if (pmapImg) pmapImg.src = item.image_url || 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';

    document.getElementById('pmap-title').textContent = item.name;
    document.getElementById('pmap-code').textContent = item.code;
    document.getElementById('pmap-badge-type').textContent = item.type.toUpperCase().replace('_', ' ');

    openModal('modal-product-map');

    // Inisialisasi peta Leaflet khusus produk jika belum ada
    if (!singleProductMap && typeof L !== 'undefined') {
        singleProductMap = L.map('product-specific-map', {
            center: [-8.65, 117.0],
            zoom: 9,
            zoomControl: true,
            scrollWheelZoom: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(singleProductMap);
    }

    setTimeout(() => {
        if (singleProductMap) singleProductMap.invalidateSize();
    }, 200);

    // Ambil titik distribusi khusus produk ini
    try {
        const res = await fetch(`api.php?action=get_locations&item_id=${item.id}`);
        const data = await res.json();
        const locations = (data.success && Array.isArray(data.locations)) ? data.locations : [];

        renderSingleProductMap(item, locations);
    } catch (err) {
        console.error('Gagal mengambil titik sebaran produk:', err);
        showToast('Gagal memuat titik distribusi produk.', 'error');
    }
}

function renderSingleProductMap(item, locations) {
    if (!singleProductMap) return;

    // Bersihkan marker lama
    Object.values(singleProductMarkers).forEach(m => m.remove());
    singleProductMarkers = {};

    const contentGrid = document.getElementById('pmap-content-grid');
    const emptyState = document.getElementById('pmap-empty-state');
    const listContainer = document.getElementById('product-specific-list');

    // Hitung statistik produk
    let totalVolume = 0;
    const areas = [];

    locations.forEach(l => {
        totalVolume += (parseFloat(l.quantity) || 0);
        if (l.location_name && !areas.includes(l.location_name)) {
            areas.push(l.location_name);
        }
    });

    document.getElementById('pmap-stat-volume').textContent = `${totalVolume.toLocaleString('id-ID')} ${item.unit}`;
    document.getElementById('pmap-stat-points').textContent = `${locations.length} Titik Penyaluran`;
    document.getElementById('pmap-stat-areas').textContent = areas.length > 0 ? areas.join(', ') : 'Belum Ada Lokasi';

    if (locations.length === 0) {
        if (contentGrid) contentGrid.classList.add('hidden');
        if (emptyState) emptyState.classList.remove('hidden');
        return;
    }

    if (contentGrid) contentGrid.classList.remove('hidden');
    if (emptyState) emptyState.classList.add('hidden');

    const bounds = [];

    // Custom pulse marker
    const customIcon = L.divIcon({
        className: 'minimap-pin',
        html: '<div class="minimap-pin-pulse"></div><div class="minimap-pin-inner"></div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -12]
    });

    let listHtml = '';

    locations.forEach(loc => {
        const lat = parseFloat(loc.latitude);
        const lng = parseFloat(loc.longitude);

        if (isNaN(lat) || isNaN(lng)) return;

        bounds.push([lat, lng]);

        const marker = L.marker([lat, lng], { icon: customIcon }).addTo(singleProductMap);

        const popupContent = `
            <div class="p-3.5 max-w-[260px] text-xs font-sans">
                <div class="flex items-center space-x-1.5 text-emerald-700 font-extrabold text-[11px] mb-1 uppercase tracking-wider">
                    <span>📍 ${escapeHtml(loc.location_name || 'Nusa Tenggara Barat')}</span>
                </div>
                <h4 class="font-bold text-slate-900 text-sm leading-snug mb-1">${escapeHtml(loc.item_name)}</h4>
                <div class="bg-emerald-50 text-emerald-800 px-2.5 py-1 rounded-lg font-bold text-xs inline-block mb-2 border border-emerald-100">
                    Penyaluran: ${(parseFloat(loc.quantity) || 0).toLocaleString('id-ID')} ${escapeHtml(loc.unit || 'unit')}
                </div>
                <div class="space-y-1 text-slate-600 text-[11px] border-t border-slate-100 pt-2">
                    <div><span class="text-slate-400">Penerima:</span> <strong class="text-slate-800">${escapeHtml(loc.destination_or_source)}</strong></div>
                    <div><span class="text-slate-400">Tanggal:</span> ${loc.mutation_date}</div>
                    ${loc.notes ? `<div class="italic text-slate-500 mt-1 text-[10px] bg-slate-50 p-1.5 rounded">"${escapeHtml(loc.notes)}"</div>` : ''}
                </div>
            </div>
        `;

        marker.bindPopup(popupContent);
        singleProductMarkers[loc.id] = marker;

        listHtml += `
            <div onclick="panToSingleProductLocation(${loc.id}, ${lat}, ${lng})"
                 class="p-3 bg-slate-50 hover:bg-emerald-50/80 rounded-2xl border border-slate-200/70 hover:border-emerald-300 transition-all cursor-pointer group text-xs flex items-center justify-between">
                <div class="pr-2">
                    <div class="font-bold text-slate-800 group-hover:text-emerald-800 transition-smooth line-clamp-1">
                        ${escapeHtml(loc.location_name || loc.destination_or_source)}
                    </div>
                    <div class="text-[11px] text-emerald-700 font-bold mt-0.5">
                        ${(parseFloat(loc.quantity) || 0).toLocaleString('id-ID')} ${escapeHtml(loc.unit || 'unit')}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">
                        ${loc.mutation_date} &bull; ${escapeHtml(loc.destination_or_source)}
                    </div>
                </div>
                <div class="shrink-0 text-slate-400 group-hover:text-emerald-600 transition-smooth p-1 bg-white rounded-xl shadow-2xs border border-slate-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </div>
            </div>
        `;
    });

    if (listContainer) listContainer.innerHTML = listHtml;

    if (bounds.length > 0) {
        singleProductMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
    } else {
        singleProductMap.setView([-8.65, 117.0], 9);
    }
}

function panToSingleProductLocation(id, lat, lng) {
    if (!singleProductMap) return;
    singleProductMap.flyTo([lat, lng], 13, { animate: true, duration: 1.0 });

    const marker = singleProductMarkers[id];
    if (marker) {
        setTimeout(() => marker.openPopup(), 1100);
    }
}

function triggerDistributeFromProductMap() {
    if (!currentMapProductItem) return;
    const it = currentMapProductItem;
    closeModal('modal-product-map');
    openQuickMutation(it.id, it.name, it.unit, 'OUT');
}

// Filter per produk pada minimap utama
async function filterMinimapByProduct(productId) {
    if (!productId) {
        loadDistributionLocations();
        return;
    }

    try {
        const res = await fetch(`api.php?action=get_locations&item_id=${productId}`);
        const data = await res.json();
        if (data.success && Array.isArray(data.locations)) {
            renderMinimapMarkers(data.locations);
            renderDistributionList(data.locations);

            const badge = document.getElementById('label-count-titik');
            if (badge) {
                badge.textContent = `${data.locations.length} Titik Produk`;
            }
        }
    } catch (e) {
        console.error('Gagal memfilter minimap per produk:', e);
    }
}

// ========================================================
// FITUR DISTRIBUSI BENIH & TAMPILAN MINIMAP HASIL INPUT
// ========================================================

function openDistributeModal(item) {
    if (!item) return;

    if ((parseFloat(item.stock_current) || 0) <= 0) {
        showToast(`Stok "${item.name}" saat ini kosong (0 ${item.unit || 'kg'}). Silakan tambah stok terlebih dahulu sebelum menyalurkan.`, 'error');
        return;
    }

    currentDistributingItem = item;

    const itemIdInput = document.getElementById('dist-item-id');
    if (itemIdInput) itemIdInput.value = item.id;

    const itemNameEl = document.getElementById('dist-item-name');
    if (itemNameEl) itemNameEl.textContent = item.name;

    const itemCodeEl = document.getElementById('dist-item-code');
    if (itemCodeEl) itemCodeEl.textContent = item.code;

    const unitLabelEl = document.getElementById('dist-unit-label');
    if (unitLabelEl) unitLabelEl.textContent = item.unit || 'unit';

    const stockDisplayEl = document.getElementById('dist-item-stock') || document.getElementById('dist-stock-display');
    if (stockDisplayEl) {
        stockDisplayEl.textContent = `${(parseFloat(item.stock_current) || 0).toLocaleString('id-ID')} ${item.unit || 'unit'}`;
    }

    const qtyInput = document.getElementById('dist-amount') || document.getElementById('dist-quantity');
    if (qtyInput) {
        qtyInput.max = item.stock_current;
        qtyInput.value = '';
    }

    const imgEl = document.getElementById('dist-item-img');
    if (imgEl) {
        imgEl.src = item.image_url || 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';
    }

    openModal('modal-distribute');
    setTimeout(() => {
        initDistPickerMap();
    }, 200);
}

function triggerDistributeFromDetail() {
    if (!currentDetailItem) return;
    const it = currentDetailItem;
    closeModal('modal-detail-item');
    openDistributeModal(it);
}

function initDistPickerMap() {
    const container = document.getElementById('dist-picker-map');
    if (!container || typeof L === 'undefined') return;

    if (!distPickerMap) {
        distPickerMap = L.map('dist-picker-map', {
            center: [-8.65, 117.0],
            zoom: 9,
            zoomControl: true,
            scrollWheelZoom: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(distPickerMap);

        // Event Klik pada peta untuk mengambil titik manual
        distPickerMap.on('click', (e) => {
            const lat = parseFloat(e.latlng.lat.toFixed(4));
            const lng = parseFloat(e.latlng.lng.toFixed(4));

            document.getElementById('dist-lat').value = lat;
            document.getElementById('dist-lng').value = lng;

            updatePickerMarker(lat, lng);

            const locNameInput = document.getElementById('dist-location-name');
            if (locNameInput && (!locNameInput.value || locNameInput.value.startsWith('Titik:'))) {
                locNameInput.value = `Titik: ${lat}, ${lng}`;
            }
        });
    }

    setTimeout(() => {
        if (distPickerMap) distPickerMap.invalidateSize();
    }, 200);
}

function setDistCoords(lat, lng, locName = '') {
    const latInput = document.getElementById('dist-lat');
    const lngInput = document.getElementById('dist-lng');
    const locNameInput = document.getElementById('dist-location-name');
    const recipientInput = document.getElementById('dist-recipient');

    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;
    if (locNameInput && locName) locNameInput.value = locName;
    if (recipientInput && (!recipientInput.value || recipientInput.value.trim() === '')) {
        recipientInput.value = 'Kelompok Tani Wilayah ' + locName;
    }

    initDistPickerMap();
    updatePickerMarker(lat, lng);
    if (distPickerMap) {
        distPickerMap.setView([lat, lng], 13);
    }
}

async function searchLocationGeo() {
    const query = document.getElementById('dist-location-search')?.value?.trim();
    const statusEl = document.getElementById('dist-search-status');
    const btn = document.getElementById('btn-search-loc');

    if (!query) return;

    if (statusEl) {
        statusEl.className = 'text-[11px] text-amber-600 font-semibold block';
        statusEl.textContent = `Sedang mencari titik lokasi "${query}"...`;
    }
    if (btn) btn.disabled = true;

    try {
        const endpoint = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id`;
        const res = await fetch(endpoint, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (data && data.length > 0) {
            const result = data[0];
            const lat = parseFloat(parseFloat(result.lat).toFixed(4));
            const lng = parseFloat(parseFloat(result.lon).toFixed(4));
            const displayName = result.display_name.split(',').slice(0, 3).join(', ');

            setDistCoords(lat, lng, displayName);

            if (statusEl) {
                statusEl.className = 'text-[11px] text-emerald-600 font-semibold block';
                statusEl.textContent = `✓ Ditemukan: ${displayName} (${lat}, ${lng})`;
            }
        } else {
            if (statusEl) {
                statusEl.className = 'text-[11px] text-rose-600 font-semibold block';
                statusEl.textContent = `Lokasi "${query}" tidak ditemukan. Silakan klik langsung pada peta.`;
            }
        }
    } catch (err) {
        console.error('Geocoding error:', err);
        if (statusEl) {
            statusEl.className = 'text-[11px] text-rose-600 font-semibold block';
            statusEl.textContent = 'Gagal mencari lokasi. Silakan klik langsung pada peta.';
        }
    } finally {
        if (btn) btn.disabled = false;
    }
}

function updatePickerMarker(lat, lng) {
    if (!distPickerMap) return;

    const customIcon = L.divIcon({
        className: 'minimap-pin',
        html: '<div class="minimap-pin-pulse"></div><div class="minimap-pin-inner"></div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -12]
    });

    if (distPickerMarker) {
        distPickerMarker.setLatLng([lat, lng]);
    } else {
        distPickerMarker = L.marker([lat, lng], { icon: customIcon }).addTo(distPickerMap);
    }

    distPickerMap.panTo([lat, lng]);
}

function setupDistributeLocationPresets() {
    const presetSelect = document.getElementById('dist-preset-select');
    if (!presetSelect) return;

    presetSelect.addEventListener('change', () => {
        const selectedOpt = presetSelect.options[presetSelect.selectedIndex];
        if (!selectedOpt || !presetSelect.value) return;

        const lat = parseFloat(selectedOpt.getAttribute('data-lat'));
        const lng = parseFloat(selectedOpt.getAttribute('data-lng'));
        const val = presetSelect.value;

        if (!isNaN(lat) && !isNaN(lng)) {
            document.getElementById('dist-lat').value = lat;
            document.getElementById('dist-lng').value = lng;
            document.getElementById('dist-location-name').value = val;

            const destInput = document.getElementById('dist-destination');
            if (destInput && (!destInput.value || destInput.value.trim() === '')) {
                destInput.value = 'Kelompok Tani Wilayah ' + val;
            }

            updatePickerMarker(lat, lng);
        }
    });
}

// Menampilkan minimap hasil distribusi setelah input manual
async function showDistributionResultMap(data, item) {
    if (!data) return;

    const itemName = data.item_name || (item ? item.name : 'Produk/Benih');
    const unit = data.item_unit || (item ? item.unit : 'Unit');

    document.getElementById('res-item-name').textContent = itemName;
    document.getElementById('res-destination').textContent = data.destination || '-';
    document.getElementById('res-location-name').textContent = data.location_name || 'Nusa Tenggara Barat';
    document.getElementById('res-quantity-display').textContent = `${Number(data.quantity).toLocaleString('id-ID')} ${unit}`;
    document.getElementById('res-remaining-stock').textContent = `${Number(data.new_stock).toLocaleString('id-ID')} ${unit}`;

    openModal('modal-distribution-result-map');

    const lat = parseFloat(data.latitude);
    const lng = parseFloat(data.longitude);

    if (isNaN(lat) || isNaN(lng)) return;

    const mapContainer = document.getElementById('result-minimap');
    if (!mapContainer || typeof L === 'undefined') return;

    // Bersihkan atau buat peta
    if (!resultMinimap) {
        resultMinimap = L.map('result-minimap', {
            center: [lat, lng],
            zoom: 13,
            zoomControl: true,
            scrollWheelZoom: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(resultMinimap);
    } else {
        resultMinimapMarkers.forEach(m => m.remove());
        resultMinimapMarkers = [];
    }

    setTimeout(() => {
        if (resultMinimap) {
            resultMinimap.invalidateSize();
            resultMinimap.setView([lat, lng], 13);
        }
    }, 200);

    // Marker Baru (Highlight berdenyut)
    const newIcon = L.divIcon({
        className: 'minimap-pin',
        html: '<div class="minimap-pin-pulse"></div><div class="minimap-pin-inner" style="background-color:#047857; width:16px; height:16px;"></div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -14]
    });

    const newMarker = L.marker([lat, lng], { icon: newIcon }).addTo(resultMinimap);
    resultMinimapMarkers.push(newMarker);

    const popupHtml = `
        <div class="p-3 text-xs font-sans">
            <div class="text-[10px] font-black tracking-wider text-emerald-700 uppercase mb-1">
                📍 Titik Penyaluran Baru
            </div>
            <h4 class="font-bold text-slate-900 text-sm leading-snug mb-1">${escapeHtml(itemName)}</h4>
            <div class="bg-emerald-50 text-emerald-800 font-bold px-2 py-0.5 rounded text-xs mb-1.5 inline-block border border-emerald-100">
                Penyaluran: ${Number(data.quantity).toLocaleString('id-ID')} ${escapeHtml(unit)}
            </div>
            <div class="text-slate-600 text-[11px] space-y-0.5 border-t border-slate-100 pt-1.5">
                <div><span class="text-slate-400">Penerima:</span> <strong>${escapeHtml(data.destination)}</strong></div>
                <div><span class="text-slate-400">Lokasi:</span> ${escapeHtml(data.location_name)}</div>
                <div><span class="text-slate-400">Tanggal:</span> ${data.mutation_date}</div>
            </div>
        </div>
    `;

    newMarker.bindPopup(popupHtml);
    setTimeout(() => {
        newMarker.openPopup();
    }, 450);

    // Ambil juga titik-titik sebelumnya dari produk ini untuk memperlihatkan sebaran lengkapnya
    if (data.item_id) {
        try {
            const res = await fetch(`api.php?action=get_locations&item_id=${data.item_id}`);
            const locData = await res.json();
            if (locData.success && Array.isArray(locData.locations)) {
                const prevIcon = L.divIcon({
                    className: 'minimap-pin',
                    html: '<div class="minimap-pin-inner" style="background-color:#059669; opacity:0.85;"></div>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                    popupAnchor: [0, -10]
                });

                locData.locations.forEach(prevLoc => {
                    const pLat = parseFloat(prevLoc.latitude);
                    const pLng = parseFloat(prevLoc.longitude);
                    if (prevLoc.id != data.mutation_id && !isNaN(pLat) && !isNaN(pLng)) {
                        const m = L.marker([pLat, pLng], { icon: prevIcon }).addTo(resultMinimap);
                        m.bindPopup(`
                            <div class="p-2.5 text-xs">
                                <div class="font-bold text-slate-800">${escapeHtml(prevLoc.item_name)}</div>
                                <div class="text-emerald-700 font-semibold">${(parseFloat(prevLoc.quantity) || 0).toLocaleString('id-ID')} ${escapeHtml(prevLoc.unit || 'unit')}</div>
                                <div class="text-slate-500 text-[11px]">${escapeHtml(prevLoc.destination_or_source)} &bull; ${prevLoc.mutation_date}</div>
                            </div>
                        `);
                        resultMinimapMarkers.push(m);
                    }
                });
            }
        } catch (e) {
            console.error('Gagal mengambil titik sebelumnya:', e);
        }
    }
}

// ========================================================
// OPERASI DINAMIS & FLEKSIBEL (EDIT, HAPUS, QUICK ADJUST, RESEP)
// ========================================================

let availableRawMaterials = [];

// Buka Modal Edit Item (Sederhana: Nama, Berat, Foto)
function openEditModal(item) {
    if (!item) return;

    const idEl = document.getElementById('edit-id');
    const typeEl = document.getElementById('edit-type');
    const nameEl = document.getElementById('edit-name');
    const stockEl = document.getElementById('edit-stock-current');
    const unitLabel = document.getElementById('edit-unit-label');
    const imgUrlEl = document.getElementById('edit-image');
    const imgPreview = document.getElementById('edit-image-preview');
    const fileInput = document.getElementById('edit-image-file');

    if (idEl) idEl.value = item.id;
    if (typeEl) typeEl.value = item.type || 'produk';
    if (nameEl) nameEl.value = item.name || '';
    if (stockEl) stockEl.value = item.stock_current || 0;
    if (unitLabel) unitLabel.textContent = item.unit || 'kg';
    if (imgUrlEl) imgUrlEl.value = item.image_url || '';
    if (imgPreview) imgPreview.src = item.image_url || 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';
    if (fileInput) fileInput.value = '';

    openModal('modal-edit-item');
}

function triggerEditFromDetail() {
    if (!currentDetailItem) return;
    const it = currentDetailItem;
    closeModal('modal-detail-item');
    openEditModal(it);
}

// Konfirmasi & Hapus Item
async function confirmDeleteItem(id, name) {
    if (!confirm(`Apakah Anda yakin ingin menghapus item "${name}" dari inventaris? Tindakan ini tidak dapat dibatalkan.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'delete_item');
        formData.append('id', id);

        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            showToast(data.message);
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast('Gagal menghapus item.', 'error');
    }
}

function confirmDeleteItemFromEdit() {
    const id = document.getElementById('edit-id').value;
    const name = document.getElementById('edit-name').value;
    closeModal('modal-edit-item');
    confirmDeleteItem(id, name);
}

function triggerDeleteFromDetail() {
    if (!currentDetailItem) return;
    const id = currentDetailItem.id;
    const name = currentDetailItem.name;
    closeModal('modal-detail-item');
    confirmDeleteItem(id, name);
}

// Penyesuaian Cepat Stok (+ / - Langsung di Kartu)
async function quickStockAdjust(itemId, delta, reason) {
    try {
        const formData = new FormData();
        formData.append('action', 'quick_stock_adjust');
        formData.append('item_id', itemId);
        formData.append('delta', delta);
        formData.append('reason', reason);

        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            showToast(data.message);
            const stockSpan = document.getElementById(`card-stock-${itemId}`);
            if (stockSpan && typeof data.new_stock !== 'undefined') {
                const parts = stockSpan.textContent.split(' ');
                const unit = parts.slice(1).join(' ') || '';
                stockSpan.textContent = `${Number(data.new_stock).toLocaleString('id-ID')} ${unit}`;
            }
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Gagal menyesuaikan stok.', 'error');
    }
}

// Google Sheets Integration Handlers
async function saveGsheetUrl(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-gsheet');
    const input = document.getElementById('input-gsheet-url');
    const url = input.value.trim();

    if (!url) {
        showToast('Masukkan URL Web App Google Apps Script!', 'error');
        return;
    }

    const origText = btn.innerHTML;
    btn.innerHTML = 'Menyimpan & Tes Koneksi...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'save_gsheet_url');
        formData.append('url', url);

        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            showToast(data.message);
            const statusBox = document.getElementById('gsheet-status-box');
            const statusText = document.getElementById('gsheet-status-text');
            if (statusBox && statusText) {
                statusBox.className = 'p-3.5 rounded-2xl border bg-emerald-50 border-emerald-200 flex items-center justify-between';
                statusText.className = 'text-xs font-bold text-emerald-900';
                statusText.textContent = 'Terhubung ke Google Apps Script';
            }
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast('Koneksi ke server gagal.', 'error');
    } finally {
        btn.innerHTML = origText;
        btn.disabled = false;
    }
}

async function syncGsheetNow() {
    const btn = document.getElementById('btn-sync-gsheet');
    const origText = btn.innerHTML;
    btn.innerHTML = '<span>⏳</span><span>Sedang Mengirim ke Google Sheet...</span>';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'sync_gsheet');

        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Sinkronisasi ke Google Spreadsheet berhasil!');
        } else {
            showToast(data.message || 'Gagal sinkronisasi ke Google Sheet.', 'error');
        }
    } catch (err) {
        showToast('Terjadi kesalahan saat sinkronisasi.', 'error');
    } finally {
        btn.innerHTML = origText;
        btn.disabled = false;
    }
}

async function pullGsheetNow() {
    if (!confirm('Apakah Anda yakin ingin menarik data dari Google Spreadsheet? Data lokal akan diperbarui mengikuti data di Google Spreadsheet.')) {
        return;
    }

    const btn = document.getElementById('btn-pull-gsheet');
    const origText = btn.innerHTML;
    btn.innerHTML = '<span>⏳</span><span>Menarik Data dari Sheet...</span>';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'pull_gsheet');

        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Data berhasil dimuat dari Google Sheet!');
            setTimeout(() => {
                location.reload();
            }, 1200);
        } else {
            showToast(data.message || 'Gagal menarik data dari Google Sheet.', 'error');
        }
    } catch (err) {
        showToast('Terjadi kesalahan saat menarik data.', 'error');
    } finally {
        btn.innerHTML = origText;
        btn.disabled = false;
    }
}

// ========================================================
// FITUR PRODUKSI & TAMBAH STOK PAKAN JADI (POTONG BAHAN BAKU)
// ========================================================

function openProduceModal() {
    const form = document.getElementById('form-produce-product');
    if (form) form.reset();
    updateProduceTotalDeducted();
    openModal('modal-produce-product');
}

function openProduceModalForProduct(item) {
    if (!item) return;
    openProduceModal();
    const sel = document.getElementById('produce-product-id');
    if (sel) {
        sel.value = item.id;
    }
}

function updateProduceTotalDeducted() {
    let total = 0;
    document.querySelectorAll('.produce-deduct-input').forEach(input => {
        const val = parseFloat(input.value) || 0;
        total += val;
    });
    const totalEl = document.getElementById('produce-total-deducted');
    if (totalEl) {
        totalEl.textContent = `${total.toLocaleString('id-ID')} kg`;
    }
}

function initProduceFeature() {
    // Listener kalkulator pemotongan bahan baku realtime
    document.querySelectorAll('.produce-deduct-input').forEach(input => {
        input.addEventListener('input', () => {
            const max = parseFloat(input.getAttribute('data-max')) || 0;
            const val = parseFloat(input.value) || 0;
            if (val > max) {
                input.classList.add('border-rose-500', 'text-rose-600');
                showToast(`Jumlah bahan melebihi stok yang tersedia (${max} kg)!`, 'error');
            } else {
                input.classList.remove('border-rose-500', 'text-rose-600');
            }
            updateProduceTotalDeducted();
        });
    });

    const form = document.getElementById('form-produce-product');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('btn-submit-produce');
            let origHtml = '';
            if (submitBtn) {
                origHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = '<span class="inline-block animate-spin mr-1.5">⏳</span> Menyimpan & Memotong Bahan...';
            }

            const resetBtn = () => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                    submitBtn.innerHTML = origHtml;
                }
            };

            const formData = new FormData(form);
            formData.append('action', 'produce_product');

            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message || 'Produksi berhasil disimpan!');
                    closeModal('modal-produce-product');
                    form.reset();

                    // Update live stock di card produk jika ada
                    if (data.product_id) {
                        const cardStock = document.getElementById(`card-stock-${data.product_id}`);
                        if (cardStock) {
                            cardStock.textContent = `${Number(data.new_stock).toLocaleString('id-ID')} kg`;
                        }
                    }

                    // Reload halaman agar seluruh tabel stok bahan baku dan log mutasi terbarui
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    showToast(data.message || 'Gagal memproses produksi.', 'error');
                    resetBtn();
                }
            } catch (err) {
                showToast('Gagal menghubungi server.', 'error');
                resetBtn();
            }
        });
    }
}

// ========================================================
// FITUR PENGATURAN WHATSAPP LAYANAN PUBLIK
// ========================================================

function initWhatsAppFeature() {
    const form = document.getElementById('form-whatsapp');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('btn-submit-wa');
            let origHtml = '';
            if (submitBtn) {
                origHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>⏳ Menyimpan...</span>';
            }

            const formData = new FormData(form);
            formData.append('action', 'save_whatsapp');

            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message || 'Nomor WhatsApp berhasil disimpan!');
                    closeModal('modal-whatsapp');
                    const display = document.getElementById('header-wa-display');
                    if (display) {
                        display.textContent = data.whatsapp_number || 'Set WA';
                    }
                } else {
                    showToast(data.message || 'Gagal menyimpan nomor WhatsApp.', 'error');
                }
            } catch (err) {
                showToast('Gagal menghubungi server.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                }
            }
        });
    }
}



