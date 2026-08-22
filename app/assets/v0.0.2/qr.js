import { verify } from './api.js';
import { getToken, clearToken } from './state.js';

const cfg = window.__APP_CONFIG__ || {};
const API_URL = cfg.apiUrl || '/api';

async function fetchQr(payload, size, options, retry) {
    let token = getToken();
    if (!token) {
        await verify();
        token = getToken();
    }
    if (!token) throw new Error('احراز هویت QR در دسترس نیست');

    const body = {
        d: String(payload || ''),
        s: Number(size || 320),
        style: options?.style === 'fancy' ? 'fancy' : 'plain',
    };
    if (options?.cur) body.cur = String(options.cur).slice(0, 16);
    if (options?.net) body.net = String(options.net).slice(0, 16);
    if (options?.bg === false) body.bg = false;

    const response = await fetch(`${API_URL}/qr.php`, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'image/png',
        },
        body: JSON.stringify(body),
    });

    if ((response.status === 401 || response.status === 403) && retry) {
        clearToken();
        await verify();
        return fetchQr(payload, size, options, false);
    }
    if (!response.ok) {
        throw new Error(response.status === 429 ? 'درخواست‌های QR بیش از حد مجاز است' : 'ساخت QR ناموفق بود');
    }
    const contentType = String(response.headers.get('Content-Type') || '').toLowerCase();
    if (!contentType.startsWith('image/png')) throw new Error('پاسخ QR نامعتبر بود');
    const blob = await response.blob();
    if (blob.size < 1 || blob.size > 8 * 1024 * 1024) throw new Error('اندازه QR نامعتبر بود');
    return URL.createObjectURL(blob);
}

function revokeObjectUrl(value) {
    if (typeof value === 'string' && value.startsWith('blob:')) {
        try { URL.revokeObjectURL(value); } catch (_) {}
    }
}

export function releaseQrImage(img) {
    if (!img) return;
    img.__redfoxQrRequestId = Number(img.__redfoxQrRequestId || 0) + 1;
    revokeObjectUrl(img.__redfoxQrObjectUrl);
    img.__redfoxQrObjectUrl = null;
    img.removeAttribute('src');
    delete img.dataset.qrLoading;
    img.removeAttribute('aria-busy');
}

export async function setQrImage(img, payload, size = 320, options = {}) {
    if (!img || !payload) return;
    if (img.dataset.qrLoading === '1') return;
    const requestId = Number(img.__redfoxQrRequestId || 0) + 1;
    img.__redfoxQrRequestId = requestId;
    img.dataset.qrLoading = '1';
    img.setAttribute('aria-busy', 'true');
    try {
        const objectUrl = await fetchQr(payload, size, options, true);
        if (img.__redfoxQrRequestId !== requestId || !img.isConnected) {
            revokeObjectUrl(objectUrl);
            return;
        }
        revokeObjectUrl(img.__redfoxQrObjectUrl);
        img.__redfoxQrObjectUrl = objectUrl;
        img.src = objectUrl;
        img.dataset.qrLoading = '0';
        img.removeAttribute('aria-busy');
    } catch (error) {
        if (img.__redfoxQrRequestId === requestId) {
            img.dataset.qrLoading = '0';
            img.removeAttribute('aria-busy');
        }
        throw error;
    }
}
