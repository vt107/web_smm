import Alpine from 'alpinejs';
import QRCode from 'qrcode';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

/**
 * Gọi JSON API nội bộ (/ajax/*) bằng session + CSRF.
 * Lỗi → throw Error (message tiếng Việt, .errors nếu lỗi validate).
 */
async function api(method, url, data) {
    const options = {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
    };

    if (data instanceof FormData) {
        options.body = data;
    } else if (data !== undefined) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);

    if (response.status === 401 || response.status === 419) {
        window.location.href = '/login';
        throw new Error('Phiên đăng nhập đã hết hạn');
    }

    const body = response.headers.get('content-type')?.includes('json') ? await response.json() : null;

    if (!response.ok) {
        const error = new Error(body?.message || 'Có lỗi xảy ra, vui lòng thử lại');
        error.errors = body?.errors || {};
        throw error;
    }

    return body;
}

api.get = (url, params = {}) => {
    const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined));
    return api('GET', query.toString() ? `${url}?${query}` : url);
};
api.post = (url, data = {}) => api('POST', url, data);
api.put = (url, data = {}) => api('PUT', url, data);

window.api = api;

/** Thông báo nổi góc màn hình. type: success | error */
window.toast = (message, type = 'success') => window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));

/** Quy đổi USD → tiền tệ hiển thị (chỉ để ước tính trên trang đặt đơn; số chính xác do server tính). */
window.formatMoney = (usd, currency) => {
    const factor = 10 ** currency.decimals;
    const value = Math.ceil(Number(usd) * Number(currency.rate) * factor - 1e-9) / factor;
    const locale = currency.code === 'VND' ? 'vi-VN' : 'en-US';
    const text = value.toLocaleString(locale, {
        minimumFractionDigits: Math.min(2, currency.decimals),
        maximumFractionDigits: currency.decimals,
    });
    return currency.symbol_position === 'after' ? `${text} ${currency.symbol}` : `${currency.symbol}${text}`;
};

window.copyText = async (text) => {
    await navigator.clipboard.writeText(String(text));
    window.toast('Đã sao chép');
};

window.drawQr = (canvas, text) => QRCode.toCanvas(canvas, String(text), { width: 200, margin: 1 });

// Số dư + cấp bậc trên header; gọi $store.me.refresh() sau khi đặt đơn / nạp tiền.
Alpine.store('me', {
    data: null,
    async refresh() {
        this.data = await api.get('/ajax/me');
    },
});

window.Alpine = Alpine;
Alpine.start();
