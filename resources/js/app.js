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
        throw new Error('Your session has expired');
    }

    const body = response.headers.get('content-type')?.includes('json') ? await response.json() : null;

    if (!response.ok) {
        const error = new Error(body?.message || 'Something went wrong, please try again');
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

/** Toast notification. type: success | error */
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
    window.toast('Copied to clipboard');
};

window.drawQr = (canvas, text) => QRCode.toCanvas(canvas, String(text), { width: 200, margin: 1 });

// Số dư + cấp bậc trên header; gọi $store.me.refresh() sau khi đặt đơn / nạp tiền.
Alpine.store('me', {
    data: null,
    async refresh() {
        this.data = await api.get('/ajax/me');
    },
});

/**
 * Giao diện sáng / tối / theo hệ thống. Lựa chọn lưu ở localStorage (tiện ích riêng từng trình duyệt);
 * class .dark được gắn sớm bằng script inline trong <head> để không bị nháy màu khi tải trang.
 */
Alpine.data('themeSwitcher', () => ({
    theme: readStorage('theme') || 'system',
    options: [
        { value: 'light', label: 'Light', icon: 'sun' },
        { value: 'dark', label: 'Dark', icon: 'moon' },
        { value: 'system', label: 'System', icon: 'computer' },
    ],
    init() {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => this.apply());
    },
    set(value) {
        this.theme = value;
        try {
            localStorage.setItem('theme', value);
        } catch {
            // Trình duyệt chặn storage: vẫn đổi giao diện cho trang hiện tại.
        }
        this.apply();
    },
    apply() {
        const dark = this.theme === 'dark' || (this.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    },
}));

/**
 * Chọn ngôn ngữ bằng Google Translate: lưu cookie googtrans rồi tải lại trang.
 * Script của Google chỉ được nạp khi đang chọn ngôn ngữ khác tiếng Anh (xem layouts/base).
 */
Alpine.data('languageSwitcher', () => ({
    languages: [
        ['en', 'English'], ['vi', 'Tiếng Việt'], ['zh-CN', '中文'], ['es', 'Español'], ['pt', 'Português'],
        ['fr', 'Français'], ['de', 'Deutsch'], ['ru', 'Русский'], ['ar', 'العربية'], ['hi', 'हिन्दी'],
        ['id', 'Bahasa Indonesia'], ['th', 'ไทย'], ['tr', 'Türkçe'], ['ja', '日本語'], ['ko', '한국어'],
    ],
    current: currentLanguage(),
    change(code) {
        const expires = code === 'en' ? 'Thu, 01 Jan 1970 00:00:00 GMT' : 'Fri, 31 Dec 9999 23:59:59 GMT';
        const value = code === 'en' ? '' : `/en/${code}`;
        // Google đọc cookie ở cả domain hiện tại lẫn domain cha, nên ghi/xoá cả hai.
        document.cookie = `googtrans=${value}; expires=${expires}; path=/`;
        document.cookie = `googtrans=${value}; expires=${expires}; path=/; domain=${location.hostname}`;
        window.location.reload();
    },
}));

function currentLanguage() {
    const match = document.cookie.match(/(?:^|;\s*)googtrans=\/en\/([^;]+)/);
    return match ? decodeURIComponent(match[1]) : 'en';
}

function readStorage(key) {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

window.Alpine = Alpine;
Alpine.start();
