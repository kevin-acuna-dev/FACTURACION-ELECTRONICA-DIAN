let currentUser = null;
let createData = null;
let currentInvoiceId = null;
let modalResolve = null;

const Modal = {
    show: function(options) {
        return new Promise((resolve) => {
            modalResolve = resolve;
            const modal = document.getElementById('modal-panel');
            const title = document.getElementById('modal-title-text');
            const message = document.getElementById('modal-message');
            const icon = document.getElementById('modal-icon');
            const errorsDiv = document.getElementById('modal-errors');
            const errorsList = document.getElementById('modal-errors-list');
            const contentDiv = document.getElementById('modal-content');
            const actionsDiv = document.getElementById('modal-actions');
            const confirmBtn = document.getElementById('modal-confirm');
            const cancelBtn = document.getElementById('modal-cancel');
            
            title.textContent = options.title || 'Información';
            message.textContent = options.message || '';
            
            const iconClass = {
                'info': 'fa-info-circle text-blue-400',
                'success': 'fa-check-circle text-green-400',
                'warning': 'fa-exclamation-triangle text-yellow-400',
                'error': 'fa-times-circle text-red-400',
                'question': 'fa-question-circle text-blue-400'
            }[options.type || 'info'] || 'fa-info-circle text-blue-400';
            
            icon.className = `fas ${iconClass} mr-2`;
            
            if (options.errors && options.errors.length > 0) {
                errorsDiv.classList.remove('hidden');
                errorsList.innerHTML = '';
                options.errors.forEach(error => {
                    const li = document.createElement('li');
                    li.textContent = error;
                    errorsList.appendChild(li);
                });
            } else {
                errorsDiv.classList.add('hidden');
            }
            
            if (options.content) {
                contentDiv.innerHTML = options.content;
                contentDiv.classList.remove('hidden');
            } else {
                contentDiv.classList.add('hidden');
            }
            
            if (options.type === 'confirm') {
                actionsDiv.classList.remove('hidden');
                confirmBtn.textContent = options.confirmText || 'Confirmar';
                cancelBtn.textContent = options.cancelText || 'Cancelar';
                confirmBtn.onclick = () => {
                    modal.classList.add('hidden');
                    if (modalResolve) modalResolve(true);
                };
                cancelBtn.onclick = () => {
                    modal.classList.add('hidden');
                    if (modalResolve) modalResolve(false);
                };
            } else {
                actionsDiv.classList.add('hidden');
                confirmBtn.onclick = null;
                cancelBtn.onclick = null;
            }
            
            document.getElementById('modal-close').onclick = () => {
                modal.classList.add('hidden');
                if (modalResolve) modalResolve(false);
            };
            
            modal.classList.remove('hidden');
        });
    },
    
    info: function(message, title = 'Información') {
        return this.show({ type: 'info', title, message });
    },
    
    success: function(message, title = 'Éxito') {
        return this.show({ type: 'success', title, message });
    },
    
    warning: function(message, title = 'Advertencia') {
        return this.show({ type: 'warning', title, message });
    },
    
    error: function(message, title = 'Error', errors = []) {
        return this.show({ type: 'error', title, message, errors });
    },
    
    confirm: function(message, title = 'Confirmar') {
        return this.show({ 
            type: 'confirm', 
            title, 
            message,
            confirmText: 'Confirmar',
            cancelText: 'Cancelar'
        });
    }
};

// Initialize App
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    setupEventListeners();
});

function checkAuth() {
    const token = API.getToken();
    if (token) {
        loadUser();
    } else {
        showLogin();
    }
}

async function loadUser() {
    try {
        const response = await API.getMe();
        currentUser = response.data.user;
        showMainApp();
        loadDashboard();
    } catch (error) {
        console.error('Error loading user:', error);
        showLogin();
    }
}

function showLogin() {
    document.getElementById('login-screen').classList.remove('hidden');
    document.getElementById('main-app').classList.add('hidden');
}

function showMainApp() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('main-app').classList.remove('hidden');
    if (currentUser) {
        document.getElementById('user-name').textContent = currentUser.first_name || currentUser.email;
    }
}

function setupEventListeners() {
    // Login
    document.getElementById('login-form').addEventListener('submit', handleLogin);
    document.getElementById('logout-btn').addEventListener('click', handleLogout);

    // Tabs
    document.getElementById('tab-dashboard').addEventListener('click', () => switchTab('dashboard'));
    document.getElementById('tab-invoices').addEventListener('click', () => switchTab('invoices'));
    document.getElementById('tab-create').addEventListener('click', () => switchTab('create'));

    // Invoice creation
    document.getElementById('add-item-btn').addEventListener('click', addInvoiceItem);
    document.getElementById('create-invoice-form').addEventListener('submit', handleCreateInvoice);
    document.getElementById('cancel-invoice-btn').addEventListener('click', () => switchTab('invoices'));

    // Modal
    document.getElementById('close-modal').addEventListener('click', closeInvoiceModal);
    document.getElementById('export-pdf-btn').addEventListener('click', exportToPDF);
    document.getElementById('export-png-btn').addEventListener('click', exportToPNG);
    document.getElementById('send-dian-btn').addEventListener('click', sendToDian);
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    const errorDiv = document.getElementById('login-error');
    const submitBtn = e.target.querySelector('button[type="submit"]');

    try {
        errorDiv.classList.add('hidden');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Iniciando sesión...';
        
        const response = await API.login(email, password);
        await loadUser();
    } catch (error) {
        console.error('Error de login:', error);
        errorDiv.textContent = error.message || 'Error al iniciar sesión';
        errorDiv.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Iniciar Sesión';
    }
}

async function handleLogout() {
    try {
        await API.logout();
    } catch (error) {
        console.error('Logout error:', error);
    }
    showLogin();
}

function switchTab(tab) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-button').forEach(el => {
        el.classList.remove('text-blue-600', 'bg-blue-50', 'border-blue-200');
        el.classList.add('text-gray-600', 'hover:bg-gray-50');
    });

    // Show selected tab
    const tabButton = document.getElementById(`tab-${tab}`);
    tabButton.classList.remove('text-gray-600', 'hover:bg-gray-50');
    tabButton.classList.add('text-blue-600', 'bg-blue-50', 'border', 'border-blue-200');

    const content = document.getElementById(`${tab}-content`);
    if (content) {
        content.classList.remove('hidden');
    }

    // Load content
    if (tab === 'dashboard') {
        loadDashboard();
    } else if (tab === 'invoices') {
        loadInvoices();
    } else if (tab === 'create') {
        loadCreateForm();
    }
}

async function loadDashboard() {
    try {
        const stats = await API.getStats();
        const data = stats.data;
        const total = data.total_invoices || 0;
        const accepted = data.accepted || 0;
        const pending = data.pending || 0;
        const rejected = data.rejected || 0;
        
        // Update main stats
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-accepted').textContent = accepted;
        document.getElementById('stat-pending').textContent = pending;
        document.getElementById('stat-rejected').textContent = rejected;
        
        // Calculate percentages
        const acceptedPercent = total > 0 ? Math.round((accepted / total) * 100) : 0;
        const pendingPercent = total > 0 ? Math.round((pending / total) * 100) : 0;
        const rejectedPercent = total > 0 ? Math.round((rejected / total) * 100) : 0;
        
        // Update percentage displays
        document.getElementById('stat-accepted-percent').textContent = acceptedPercent + '%';
        document.getElementById('stat-pending-percent').textContent = pendingPercent + '%';
        document.getElementById('stat-rejected-percent').textContent = rejectedPercent + '%';
        
        // Update progress bars
        document.querySelector('#stat-accepted-bar > div').style.width = acceptedPercent + '%';
        document.querySelector('#stat-pending-bar > div').style.width = pendingPercent + '%';
        document.querySelector('#stat-rejected-bar > div').style.width = rejectedPercent + '%';
        
        // Load recent activity
        await loadRecentActivity();
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

async function loadRecentActivity() {
    try {
        const response = await API.getInvoices();
        const invoices = response.data || [];
        const recentDiv = document.getElementById('recent-activity');
        
        if (invoices.length === 0) {
            recentDiv.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3 opacity-50"></i>
                    <p>No hay facturas registradas</p>
                </div>
            `;
            return;
        }
        
        // Get last 5 invoices
        const recent = invoices.slice(0, 5);
        
        recentDiv.innerHTML = recent.map(invoice => {
            const statusIcon = invoice.dian_status === 'accepted' ? 'fa-check-circle text-green-600' : 
                              invoice.dian_status === 'rejected' ? 'fa-times-circle text-red-600' : 
                              'fa-clock text-yellow-600';
            const statusBg = invoice.dian_status === 'accepted' ? 'bg-green-50' : 
                           invoice.dian_status === 'rejected' ? 'bg-red-50' : 
                           'bg-yellow-50';
            
            return `
                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-all duration-200 cursor-pointer" onclick="showInvoiceDetail(${invoice.id})">
                    <div class="flex items-center space-x-3 flex-1">
                        <div class="w-10 h-10 ${statusBg} rounded-lg flex items-center justify-center">
                            <i class="fas ${statusIcon}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900 truncate">${invoice.invoice_number || 'N/A'}</div>
                            <div class="text-xs text-gray-500">${formatDate(invoice.issue_date)}</div>
                        </div>
                    </div>
                    <div class="text-right ml-4">
                        <div class="font-bold text-gray-900">$${formatCurrency(invoice.payable_amount || 0)}</div>
                        <div class="text-xs text-gray-500">${getStatusText(invoice.dian_status)}</div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading recent activity:', error);
    }
}

async function loadInvoices() {
    try {
        const response = await API.getInvoices();
        const invoices = response.data || [];
        const listDiv = document.getElementById('invoices-list');
        
        if (invoices.length === 0) {
            listDiv.innerHTML = '<div class="p-6 text-center text-gray-500">No hay facturas</div>';
            return;
        }

        document.getElementById('invoices-count').textContent = invoices.length;
        
        listDiv.innerHTML = invoices.map(invoice => {
            const statusIcon = invoice.dian_status === 'accepted' ? 'fa-check-circle' : 
                              invoice.dian_status === 'rejected' ? 'fa-times-circle' : 
                              'fa-clock';
            const statusColor = invoice.dian_status === 'accepted' ? 'text-green-600' : 
                               invoice.dian_status === 'rejected' ? 'text-red-600' : 
                               'text-yellow-600';
            const statusBg = invoice.dian_status === 'accepted' ? 'bg-green-50 border-green-200' : 
                           invoice.dian_status === 'rejected' ? 'bg-red-50 border-red-200' : 
                           'bg-yellow-50 border-yellow-200';
            
            return `
            <div class="p-5 hover:bg-gray-50 cursor-pointer transition-all duration-200 border-l-4 ${getStatusBorderColor(invoice.dian_status)} group" onclick="showInvoiceDetail(${invoice.id})">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center space-x-4 mb-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg flex items-center justify-center text-white font-bold text-base shadow-sm">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-lg text-gray-900 group-hover:text-blue-600 transition-colors mb-1">${invoice.invoice_number || 'N/A'}</div>
                                    <div class="text-xs text-gray-500 font-mono">${invoice.uuid ? invoice.uuid.substring(0, 24) + '...' : 'Sin CUFE'}</div>
                                </div>
                            </div>
                            <div class="px-3 py-1.5 rounded-lg ${statusBg} border ${getStatusColor(invoice.dian_status)}">
                                <div class="flex items-center space-x-2">
                                    <i class="fas ${statusIcon} ${statusColor} text-sm"></i>
                                    <span class="text-xs font-semibold">${getStatusText(invoice.dian_status)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-calendar-alt text-gray-400 mr-2 text-xs"></i>
                                <span class="font-medium">${formatDate(invoice.issue_date)}</span>
                            </div>
                            ${invoice.buyer ? `
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-user text-gray-400 mr-2 text-xs"></i>
                                <span class="font-medium truncate">${invoice.buyer.first_name || 'Cliente'}</span>
                            </div>
                            ` : ''}
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-boxes text-gray-400 mr-2 text-xs"></i>
                                <span class="font-medium">${(invoice.invoiceDetails || []).length} items</span>
                            </div>
                            ${invoice.uuid ? `
                            <div class="flex items-center text-blue-600">
                                <i class="fas fa-shield-alt text-blue-500 mr-2 text-xs"></i>
                                <span class="font-semibold text-xs">Validada DIAN</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="text-right ml-6 min-w-[140px]">
                        <div class="text-2xl font-bold text-gray-900 mb-1">$${formatCurrency(invoice.payable_amount || 0)}</div>
                        <div class="text-xs text-gray-500 mb-1">Total a Pagar</div>
                        ${invoice.tax_inclusive_amount && invoice.tax_exclusive_amount ? `
                        <div class="text-xs text-gray-400">
                            IVA: $${formatCurrency(invoice.tax_inclusive_amount - invoice.tax_exclusive_amount)}
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        }).join('');
    } catch (error) {
        console.error('Error loading invoices:', error);
    }
}

async function loadCreateForm() {
    try {
        if (!createData) {
            createData = await API.getCreateData();
        }

        const buyerSelect = document.getElementById('invoice-buyer');
        buyerSelect.innerHTML = '<option value="">Seleccionar cliente...</option>';
        (createData.data.clients || []).forEach(client => {
            const option = document.createElement('option');
            option.value = client.id;
            option.textContent = `${client.first_name} - ${client.document_number}`;
            buyerSelect.appendChild(option);
        });

        // Load products and services for items
        window.products = createData.data.products || [];
        window.services = createData.data.services || [];

        // Clear items
        document.getElementById('invoice-items').innerHTML = '';
        addInvoiceItem();
    } catch (error) {
        console.error('Error loading create form:', error);
    }
}

function addInvoiceItem() {
    const itemsDiv = document.getElementById('invoice-items');
    const itemIndex = itemsDiv.children.length;
    
    const itemDiv = document.createElement('div');
    itemDiv.className = 'border border-gray-200 rounded-lg p-5 bg-white hover:border-blue-300 hover:shadow-sm transition-all duration-200';
    itemDiv.innerHTML = `
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
            <h4 class="font-semibold text-gray-900 flex items-center space-x-2">
                <i class="fas fa-cube text-gray-400"></i>
                <span>Item ${itemIndex + 1}</span>
            </h4>
            <button type="button" class="remove-item text-red-600 hover:text-red-700 hover:bg-red-50 font-medium text-sm flex items-center space-x-1 px-2 py-1 rounded transition-all duration-200" data-index="${itemIndex}">
                <i class="fas fa-trash text-xs"></i>
                <span>Eliminar</span>
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Tipo</label>
                <select class="item-type w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white text-gray-900 text-sm" data-index="${itemIndex}">
                    <option value="">Seleccionar...</option>
                    <option value="product">Producto</option>
                    <option value="service">Servicio</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Item</label>
                <select class="item-select w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white text-gray-900 text-sm" data-index="${itemIndex}" required>
                    <option value="">Seleccionar...</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Cantidad</label>
                <input type="number" class="item-quantity w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white text-gray-900 text-sm" 
                       data-index="${itemIndex}" min="1" step="0.01" required placeholder="0.00">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Descuento</label>
                <input type="number" class="item-discount w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white text-gray-900 text-sm" 
                       data-index="${itemIndex}" min="0" step="0.01" value="0" placeholder="0.00">
            </div>
        </div>
        <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200 hidden item-preview" data-index="${itemIndex}">
            <div class="text-sm text-gray-700 space-y-1">
                <div class="flex justify-between">
                    <span class="text-gray-600">Precio unitario:</span>
                    <span class="item-unit-price font-semibold text-gray-900">$0.00</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal línea:</span>
                    <span class="item-line-total font-bold text-gray-900">$0.00</span>
                </div>
            </div>
        </div>
    `;
    
    itemsDiv.appendChild(itemDiv);

    // Setup item type change
    itemDiv.querySelector('.item-type').addEventListener('change', (e) => {
        updateItemSelect(e.target.dataset.index, e.target.value);
    });
    
    // Setup item select change
    itemDiv.querySelector('.item-select').addEventListener('change', (e) => {
        updateItemPreview(e.target.dataset.index);
    });
    
    // Setup quantity and discount changes
    itemDiv.querySelector('.item-quantity').addEventListener('input', (e) => {
        updateItemPreview(e.target.dataset.index);
        updateInvoiceSummary();
    });
    
    itemDiv.querySelector('.item-discount').addEventListener('input', (e) => {
        updateItemPreview(e.target.dataset.index);
        updateInvoiceSummary();
    });

    // Setup remove button
    itemDiv.querySelector('.remove-item').addEventListener('click', () => {
        itemDiv.remove();
        updateInvoiceSummary();
    });
}

function updateItemPreview(index) {
    const select = document.querySelector(`.item-select[data-index="${index}"]`);
    const quantityInput = document.querySelector(`.item-quantity[data-index="${index}"]`);
    const discountInput = document.querySelector(`.item-discount[data-index="${index}"]`);
    const preview = document.querySelector(`.item-preview[data-index="${index}"]`);
    
    if (!select.value || !quantityInput.value) {
        if (preview) preview.classList.add('hidden');
        return;
    }
    
    const selectedOption = select.options[select.selectedIndex];
    const unitPrice = parseFloat(selectedOption.dataset.price || 0);
    const quantity = parseFloat(quantityInput.value || 0);
    const discount = parseFloat(discountInput.value || 0);
    
    const lineSubtotal = (unitPrice * quantity) - discount;
    
    if (preview) {
        preview.querySelector('.item-unit-price').textContent = formatCurrency(unitPrice);
        preview.querySelector('.item-line-total').textContent = formatCurrency(lineSubtotal);
        preview.classList.remove('hidden');
    }
}

function updateInvoiceSummary() {
    const summaryDiv = document.getElementById('invoice-summary');
    let subtotal = 0;
    let tax = 0;
    
    document.querySelectorAll('.item-select').forEach(select => {
        if (select.value) {
            const index = select.dataset.index;
            const quantityInput = document.querySelector(`.item-quantity[data-index="${index}"]`);
            const discountInput = document.querySelector(`.item-discount[data-index="${index}"]`);
            const selectedOption = select.options[select.selectedIndex];
            const unitPrice = parseFloat(selectedOption.dataset.price || 0);
            const quantity = parseFloat(quantityInput.value || 0);
            const discount = parseFloat(discountInput.value || 0);
            
            const lineSubtotal = (unitPrice * quantity) - discount;
            subtotal += lineSubtotal;
            // Asumir 19% de IVA (esto debería calcularse según el régimen)
            tax += lineSubtotal * 0.19;
        }
    });
    
    if (subtotal > 0) {
        summaryDiv.classList.remove('hidden');
        document.getElementById('summary-subtotal').textContent = formatCurrency(subtotal);
        document.getElementById('summary-tax').textContent = formatCurrency(tax);
        document.getElementById('summary-total').textContent = formatCurrency(subtotal + tax);
    } else {
        summaryDiv.classList.add('hidden');
    }
}

function updateItemSelect(index, type) {
    const select = document.querySelector(`.item-select[data-index="${index}"]`);
    select.innerHTML = '<option value="">Seleccionar...</option>';
    
    if (!type) {
        return;
    }
    
    const items = type === 'product' ? window.products : window.services;
    if (items && items.length > 0) {
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = `${item.name} - $${formatCurrency(item.unit_price)}`;
            option.dataset.price = item.unit_price;
            select.appendChild(option);
        });
    } else {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No hay items disponibles';
        option.disabled = true;
        select.appendChild(option);
    }
}

async function handleCreateInvoice(e) {
    e.preventDefault();
    
    const buyerId = document.getElementById('invoice-buyer').value;
    const invoiceType = document.getElementById('invoice-type').value;
    
    const items = [];
    document.querySelectorAll('.item-select').forEach(select => {
        if (select.value) {
            const index = select.dataset.index;
            const typeSelect = document.querySelector(`.item-type[data-index="${index}"]`);
            const quantityInput = document.querySelector(`.item-quantity[data-index="${index}"]`);
            const discountInput = document.querySelector(`.item-discount[data-index="${index}"]`);
            
            items.push({
                type: typeSelect.value,
                id: parseInt(select.value),
                quantity: parseFloat(quantityInput.value),
                discount: parseFloat(discountInput.value || 0)
            });
        }
    });

    if (items.length === 0) {
        await Modal.warning('Debe agregar al menos un item', 'Validación');
        return;
    }

    try {
        const response = await API.createInvoice({
            buyer_id: parseInt(buyerId),
            invoice_type_code: invoiceType,
            items: items
        });

        await Modal.success('Factura creada exitosamente', 'Éxito');
        switchTab('invoices');
        loadInvoices();
    } catch (error) {
        const errors = error.data?.errors || [];
        await Modal.error('Error al crear factura', 'Error', errors.length > 0 ? errors : [error.message]);
    }
}

async function showInvoiceDetail(id) {
    currentInvoiceId = id;
    try {
        const response = await API.getInvoice(id);
        const invoice = response.data;
        
        const modal = document.getElementById('invoice-modal');
        const content = document.getElementById('invoice-detail-content');
        
        // Usar qr_url directamente de la respuesta si está disponible
        let qrUrl = invoice.qr_url || null;
        
        // Si no hay qr_url pero hay uuid, intentar obtenerlo
        if (!qrUrl && invoice.uuid) {
            try {
                const qrResponse = await API.getQRCode(id);
                if (qrResponse.data && qrResponse.data.qr_url) {
                    qrUrl = qrResponse.data.qr_url;
                }
            } catch (error) {
                console.error('Error obteniendo QR:', error);
            }
        }
        
        content.innerHTML = await generateInvoiceHTML(invoice, qrUrl);
        modal.classList.remove('hidden');
        
        // Generar QR si tenemos URL
        if (qrUrl) {
            generateQRCode(qrUrl, 'qr-code-canvas');
        }
    } catch (error) {
        await Modal.error('Error al cargar factura: ' + error.message, 'Error');
    }
}

function generateQRCode(text, canvasId) {
    if (typeof QRCode === 'undefined') {
        console.error('QRCode library not loaded');
        return;
    }
    
    const canvas = document.getElementById(canvasId);
    if (canvas) {
        QRCode.toCanvas(canvas, text, {
            width: 200,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#FFFFFF'
            }
        }, function (error) {
            if (error) console.error('Error generando QR:', error);
        });
    }
}

async function generateInvoiceHTML(invoice, qrUrl = null) {
    const statusColor = getStatusColor(invoice.dian_status);
    const statusText = getStatusText(invoice.dian_status);
    const isAccepted = invoice.dian_status === 'accepted';
    const isPending = invoice.dian_status === 'pending';
    const isRejected = invoice.dian_status === 'rejected';
    
    const company = invoice.user?.company || invoice.company || {};
    const buyer = invoice.buyer || {};
    const user = invoice.user || {};
    
    const subtotal = invoice.tax_exclusive_amount || 0;
    const taxAmount = (invoice.tax_inclusive_amount || 0) - subtotal;
    const total = invoice.payable_amount || 0;
    
    // Usar qr_url si está disponible, sino usar uuid para generar
    const displayQrUrl = qrUrl || invoice.qr_url || (invoice.uuid ? `https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?CUFE=${invoice.uuid}` : null);
    
    return `
        <div id="invoice-print" class="bg-white p-8 border-2 border-gray-800" style="max-width: 210mm; margin: 0 auto;">
            <!-- Encabezado -->
            <div class="border-b-4 border-gray-800 pb-6 mb-6">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex-1">
                        <h1 class="text-4xl font-bold text-gray-900 mb-2">FACTURA ELECTRÓNICA</h1>
                        <div class="text-sm text-gray-700 font-semibold">Número: <span class="font-mono text-lg">${invoice.invoice_number || 'N/A'}</span></div>
                        <div class="text-sm text-gray-700">Fecha de Emisión: <span class="font-semibold">${formatDate(invoice.issue_date)}</span></div>
                        ${invoice.uuid ? `
                        <div class="mt-2 p-2 bg-gray-100 rounded border border-gray-300">
                            <div class="text-xs text-gray-600 font-semibold mb-1">CUFE (Código Único de Factura Electrónica):</div>
                            <div class="text-xs font-mono text-gray-800 break-all">${invoice.uuid}</div>
                        </div>
                        ` : ''}
                        ${invoice.protocol_number ? `
                        <div class="mt-2 p-2 bg-blue-50 rounded border border-blue-300">
                            <div class="text-xs text-blue-700 font-semibold">Número de Protocolo: <span class="font-mono">${invoice.protocol_number}</span></div>
                        </div>
                        ` : ''}
                        ${invoice.validation_date ? `
                        <div class="mt-1 text-xs text-gray-600">Fecha de Validación DIAN: <span class="font-semibold">${formatDate(invoice.validation_date)}</span></div>
                        ` : ''}
                    </div>
                    ${displayQrUrl ? `
                    <div class="ml-4 text-center">
                        <div class="text-xs text-gray-600 font-semibold mb-2">Código QR</div>
                        <canvas id="qr-code-canvas" class="border-2 border-gray-400"></canvas>
                        <div class="text-xs text-gray-500 mt-1">Validación DIAN</div>
                    </div>
                    ` : ''}
                </div>
            </div>
            
            <!-- Estado DIAN -->
            ${isRejected ? `
            <div class="mb-4 p-4 bg-red-50 border-2 border-red-300 rounded-lg flex items-start space-x-3">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl flex-shrink-0 mt-0.5"></i>
                <div>
                    <div class="font-bold text-red-900 text-lg mb-1">FACTURA RECHAZADA POR DIAN</div>
                    <div class="text-sm text-red-700">Esta factura no cumple con los requisitos de la DIAN</div>
                </div>
            </div>
            ` : ''}
            
            ${isAccepted ? `
            <div class="mb-4 p-4 bg-green-50 border-2 border-green-300 rounded-lg flex items-start space-x-3">
                <i class="fas fa-check-circle text-green-600 text-2xl flex-shrink-0 mt-0.5"></i>
                <div>
                    <div class="font-bold text-green-900 text-lg mb-1">FACTURA ACEPTADA POR DIAN</div>
                    <div class="text-sm text-green-700">Esta factura ha sido validada y aceptada por la DIAN</div>
                    ${invoice.validation_date ? `<div class="text-xs text-green-600 mt-1">Fecha de validación: ${formatDate(invoice.validation_date)}</div>` : ''}
                    ${invoice.protocol_number ? `<div class="text-xs text-green-600">Protocolo: ${invoice.protocol_number}</div>` : ''}
                </div>
            </div>
            ` : ''}
            
            ${isPending ? `
            <div class="mb-4 p-4 bg-yellow-50 border-2 border-yellow-300 rounded-lg flex items-start space-x-3">
                <i class="fas fa-clock text-yellow-600 text-2xl flex-shrink-0 mt-0.5"></i>
                <div>
                    <div class="font-bold text-yellow-900 text-lg mb-1">VALIDACIÓN PENDIENTE</div>
                    <div class="text-sm text-yellow-700">Esta factura está siendo procesada por la DIAN</div>
                </div>
            </div>
            ` : ''}

            <!-- Información del Emisor y Cliente -->
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div class="border-2 border-gray-300 p-4 rounded-lg bg-gray-50">
                    <h3 class="font-bold text-gray-900 text-lg mb-3 border-b-2 border-gray-400 pb-2">EMISOR</h3>
                    <div class="text-sm text-gray-800 space-y-1">
                        <div><span class="font-semibold">Razón Social:</span> ${company.business_name || user.first_name || 'N/A'}</div>
                        <div><span class="font-semibold">NIT:</span> ${company.nit || 'N/A'}</div>
                        ${company.trade_name ? `<div><span class="font-semibold">Nombre Comercial:</span> ${company.trade_name}</div>` : ''}
                        ${company.address ? `<div><span class="font-semibold">Dirección:</span> ${company.address}</div>` : ''}
                        ${company.city ? `<div><span class="font-semibold">Ciudad:</span> ${company.city}</div>` : ''}
                        ${company.phone ? `<div><span class="font-semibold">Teléfono:</span> ${company.phone}</div>` : ''}
                        ${company.email ? `<div><span class="font-semibold">Email:</span> ${company.email}</div>` : ''}
                        ${company.tax_regime ? `<div><span class="font-semibold">Régimen:</span> ${company.tax_regime}</div>` : ''}
                    </div>
                </div>
                <div class="border-2 border-gray-300 p-4 rounded-lg bg-gray-50">
                    <h3 class="font-bold text-gray-900 text-lg mb-3 border-b-2 border-gray-400 pb-2">CLIENTE / ADQUIRIENTE</h3>
                    <div class="text-sm text-gray-800 space-y-1">
                        <div><span class="font-semibold">Nombre:</span> ${buyer.first_name || 'N/A'}</div>
                        <div><span class="font-semibold">Tipo Documento:</span> ${buyer.document_type || 'N/A'}</div>
                        <div><span class="font-semibold">Número Documento:</span> ${buyer.document_number || 'N/A'}</div>
                        ${buyer.address ? `<div><span class="font-semibold">Dirección:</span> ${buyer.address}</div>` : ''}
                        ${buyer.phone ? `<div><span class="font-semibold">Teléfono:</span> ${buyer.phone}</div>` : ''}
                        ${buyer.email ? `<div><span class="font-semibold">Email:</span> ${buyer.email}</div>` : ''}
                    </div>
                </div>
            </div>

            <!-- Tabla de Items -->
            <div class="mb-6">
                <h3 class="font-bold text-gray-900 text-lg mb-3">DETALLE DE PRODUCTOS Y/O SERVICIOS</h3>
                <table class="w-full border-collapse border-2 border-gray-800">
                    <thead>
                        <tr class="bg-gray-800 text-white">
                            <th class="border-2 border-gray-800 px-4 py-3 text-left font-bold">Código</th>
                            <th class="border-2 border-gray-800 px-4 py-3 text-left font-bold">Descripción</th>
                            <th class="border-2 border-gray-800 px-4 py-3 text-center font-bold">Cantidad</th>
                            <th class="border-2 border-gray-800 px-4 py-3 text-right font-bold">Precio Unit.</th>
                            <th class="border-2 border-gray-800 px-4 py-3 text-right font-bold">Descuento</th>
                            <th class="border-2 border-gray-800 px-4 py-3 text-right font-bold">IVA</th>
                            <th class="border-2 border-gray-800 px-4 py-3 text-right font-bold">Total Línea</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(invoice.invoiceDetails || []).map((detail, index) => {
                            const lineTax = (detail.total_line_amount || 0) - ((detail.total_line_amount || 0) / 1.19);
                            return `
                            <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                                <td class="border-2 border-gray-400 px-4 py-2 text-sm">${detail.item_id || '-'}</td>
                                <td class="border-2 border-gray-400 px-4 py-2 text-sm">${detail.description || 'N/A'}</td>
                                <td class="border-2 border-gray-400 px-4 py-2 text-center text-sm">${formatCurrency(detail.quantity || 0)}</td>
                                <td class="border-2 border-gray-400 px-4 py-2 text-right text-sm">$${formatCurrency(detail.unit_price || 0)}</td>
                                <td class="border-2 border-gray-400 px-4 py-2 text-right text-sm">$${formatCurrency(detail.discount_amount || 0)}</td>
                                <td class="border-2 border-gray-400 px-4 py-2 text-right text-sm">$${formatCurrency(lineTax)}</td>
                                <td class="border-2 border-gray-400 px-4 py-2 text-right font-semibold text-sm">$${formatCurrency(detail.total_line_amount || 0)}</td>
                            </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>

            <!-- Totales -->
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div class="border-2 border-gray-300 p-4 rounded-lg">
                    <h3 class="font-bold text-gray-900 mb-3">INFORMACIÓN ADICIONAL</h3>
                    <div class="text-sm text-gray-700 space-y-1">
                        <div><span class="font-semibold">Forma de Pago:</span> ${invoice.payment_means_name || 'Contado'}</div>
                        <div><span class="font-semibold">Moneda:</span> ${invoice.document_currency_code || 'COP'}</div>
                        ${invoice.observation ? `<div><span class="font-semibold">Observaciones:</span> ${invoice.observation}</div>` : ''}
                    </div>
                </div>
                <div class="border-2 border-gray-800 p-4 rounded-lg bg-gray-100">
                    <h3 class="font-bold text-gray-900 text-lg mb-3 border-b-2 border-gray-600 pb-2">RESUMEN DE TOTALES</h3>
                    <div class="space-y-2 text-base">
                        <div class="flex justify-between">
                            <span class="font-semibold">Subtotal:</span>
                            <span class="font-bold">$${formatCurrency(subtotal)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-semibold">IVA (19%):</span>
                            <span class="font-bold">$${formatCurrency(taxAmount)}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t-2 border-gray-600 text-xl">
                            <span class="font-bold">TOTAL A PAGAR:</span>
                            <span class="font-bold text-gray-900">$${formatCurrency(total)}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pie de Página -->
            <div class="mt-6 pt-6 border-t-4 border-gray-800">
                <div class="text-center space-y-2">
                    <div class="text-sm text-gray-700">
                        <span class="font-semibold">Estado DIAN:</span> 
                        <span class="px-3 py-1 rounded ${statusColor} font-bold">${statusText}</span>
                    </div>
                    ${invoice.uuid ? `
                    <div class="text-xs text-gray-600 mt-2">
                        Esta factura electrónica cumple con los requisitos establecidos en el Decreto 2242 de 2015
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        Puede verificar la autenticidad de esta factura en: <span class="font-mono">catalogo-vpfe-hab.dian.gov.co</span>
                    </div>
                    ` : `
                    <div class="text-sm text-orange-700 mt-2 font-semibold flex items-center justify-center space-x-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Esta factura aún no ha sido enviada a la DIAN. Use el botón "Enviar a DIAN" para validarla.</span>
                    </div>
                    `}
                </div>
            </div>
        </div>
    `;
}

function closeInvoiceModal() {
    document.getElementById('invoice-modal').classList.add('hidden');
}

async function exportToPDF() {
    const element = document.getElementById('invoice-print');
    const { jsPDF } = window.jspdf;
    
    try {
        const canvas = await html2canvas(element, { scale: 2 });
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
        
        pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`factura-${currentInvoiceId}.pdf`);
    } catch (error) {
        await Modal.error('Error al exportar PDF: ' + error.message, 'Error');
    }
}

async function exportToPNG() {
    const element = document.getElementById('invoice-print');
    
    try {
        const canvas = await html2canvas(element, { scale: 2 });
        const link = document.createElement('a');
        link.download = `factura-${currentInvoiceId}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (error) {
        await Modal.error('Error al exportar PNG: ' + error.message, 'Error');
    }
}

async function sendToDian() {
    if (!currentInvoiceId) return;
    
    const confirmed = await Modal.confirm('¿Está seguro de enviar esta factura a la DIAN para validación?', 'Confirmar Envío');
    if (!confirmed) {
        return;
    }

    const btn = document.getElementById('send-dian-btn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Enviando...';

    try {
        const response = await API.sendToDian(currentInvoiceId);
        
        if (response.success) {
            const status = response.data.status || 'Aceptada';
            const message = response.data.message || 'Factura procesada exitosamente';
            const cufe = response.data.cufe || '';
            
            const successMsg = `Factura enviada a DIAN exitosamente!\n\nEstado: ${status}\n${cufe ? 'CUFE: ' + cufe : ''}\n\n${message}`;
            await Modal.success(successMsg, 'Factura Enviada');
            
            // Recargar el detalle de la factura para ver el nuevo estado
            closeInvoiceModal();
            await showInvoiceDetail(currentInvoiceId);
            loadInvoices();
        } else {
            const errorList = response.data?.errors || [];
            const errorMsg = errorList.length > 0 ? errorList.join('\n') : (response.message || 'Error desconocido');
            const finalErrors = Array.isArray(response.data?.errors) ? response.data.errors : [errorMsg];
            await Modal.error('Error al enviar a DIAN', 'Error de Validación', finalErrors);
        }
    } catch (error) {
        await Modal.error('Error al enviar a DIAN: ' + error.message, 'Error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Utility functions
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-CO');
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function getStatusColor(status) {
    const colors = {
        'accepted': 'bg-green-100 text-green-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'rejected': 'bg-red-100 text-red-800',
        'cancelled': 'bg-gray-100 text-gray-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

function getStatusBorderColor(status) {
    const colors = {
        'accepted': 'border-green-500',
        'pending': 'border-yellow-500',
        'rejected': 'border-red-500',
        'cancelled': 'border-gray-500'
    };
    return colors[status] || 'border-gray-300';
}

function getStatusText(status) {
    const texts = {
        'accepted': 'Aceptada',
        'pending': 'Pendiente',
        'rejected': 'Rechazada',
        'cancelled': 'Cancelada'
    };
    return texts[status] || status || 'Desconocido';
}

