const API_BASE_URL = window.location.origin;

class API {
    static getToken() {
        return localStorage.getItem('token');
    }

    static setToken(token) {
        localStorage.setItem('token', token);
    }

    static removeToken() {
        localStorage.removeItem('token');
    }

    static async request(endpoint, options = {}) {
        const token = this.getToken();
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                ...options,
                headers
            });

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Respuesta no JSON recibida:', text.substring(0, 200));
                throw new Error('El servidor devolvió una respuesta no válida (no es JSON)');
            }

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Error en la petición');
            }

            return data;
        } catch (error) {
            if (error.message.includes('JSON')) {
                throw error;
            }
            throw new Error(error.message || 'Error en la petición');
        }
    }

    // Auth
    static async login(email, password) {
        const response = await this.request('/api/login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
        if (response.data && response.data.access_token) {
            this.setToken(response.data.access_token);
        }
        return response;
    }

    static async logout() {
        await this.request('/api/logout', { method: 'POST' });
        this.removeToken();
    }

    static async getMe() {
        return await this.request('/api/me');
    }

    // Invoices
    static async getInvoices(filters = {}) {
        const params = new URLSearchParams(filters);
        return await this.request(`/api/invoices?${params}`);
    }

    static async getInvoice(id) {
        return await this.request(`/api/invoices/${id}`);
    }

    static async createInvoice(data) {
        return await this.request('/api/invoices', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    static async sendToDian(id) {
        return await this.request(`/api/invoices/${id}/send-dian`, {
            method: 'POST'
        });
    }

    static async checkStatus(id) {
        return await this.request(`/api/invoices/${id}/status`);
    }

    static async downloadXML(id) {
        const token = this.getToken();
        window.open(`${API_BASE_URL}/api/invoices/${id}/download/xml?token=${token}`, '_blank');
    }

    // Invoice Data
    static async getCreateData() {
        return await this.request('/api/invoices/create/data');
    }

    static async getClients() {
        return await this.request('/api/invoices/clients');
    }

    // Stats
    static async getStats() {
        return await this.request('/api/invoices/stats/summary');
    }

    // Sector Info
    static async getSectorInfo() {
        return await this.request('/api/sector-info');
    }

    // QR Code
    static async getQRCode(id) {
        return await this.request(`/api/invoices/${id}/qr`);
    }
}

