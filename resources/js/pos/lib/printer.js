/**
 * Mencetak struk. Pilihan cara cetak (disimpan di HP ini):
 *
 *  - rawbt     : lewat aplikasi gratis RawBT (Android). Cocok untuk printer Bluetooth
 *                biasa (Classic) yang paling banyak dijual. Paling disarankan.
 *  - bluetooth : langsung dari Chrome lewat Bluetooth BLE (printer yang mendukung BLE).
 *  - browser   : jendela cetak browser (printer USB / laptop), juga untuk simpan PDF.
 *  - none      : tidak mencetak (struk dikirim lewat WhatsApp saja).
 */
import { receiptBytes, testPageBytes } from './escpos';

const KEY = 'hermes.printer';

export function loadPrinterSettings() {
    try {
        return { method: 'none', paper: '58', autoPrint: false, ...JSON.parse(localStorage.getItem(KEY) ?? '{}') };
    } catch {
        return { method: 'none', paper: '58', autoPrint: false };
    }
}

export function savePrinterSettings(settings) {
    try {
        localStorage.setItem(KEY, JSON.stringify(settings));
    } catch {
        /* penyimpanan HP penuh / mode pribadi: abaikan */
    }
}

export const bluetoothSupported = () => typeof navigator !== 'undefined' && !!navigator.bluetooth;

function toBase64(bytes) {
    let binary = '';
    bytes.forEach((b) => (binary += String.fromCharCode(b)));
    return btoa(binary);
}

function printViaRawBT(bytes) {
    window.location.href = `intent:base64,${toBase64(bytes)}#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;`;
}

// Layanan BLE yang umum dipakai printer thermal murah.
const BLE_SERVICES = [
    '000018f0-0000-1000-8000-00805f9b34fb',
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
];

let bleCharacteristic = null;

async function connectBluetooth() {
    const device = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: BLE_SERVICES });
    const server = await device.gatt.connect();
    for (const service of await server.getPrimaryServices()) {
        for (const characteristic of await service.getCharacteristics()) {
            if (characteristic.properties.write || characteristic.properties.writeWithoutResponse) {
                device.addEventListener('gattserverdisconnected', () => (bleCharacteristic = null));
                return characteristic;
            }
        }
    }
    throw new Error('Printer ditemukan, tapi tidak bisa dikirimi data. Coba pakai cara RawBT.');
}

async function printViaBluetooth(bytes) {
    bleCharacteristic ??= await connectBluetooth();
    for (let i = 0; i < bytes.length; i += 100) {
        const chunk = bytes.slice(i, i + 100);
        if (bleCharacteristic.properties.writeWithoutResponse) await bleCharacteristic.writeValueWithoutResponse(chunk);
        else await bleCharacteristic.writeValue(chunk);
    }
}

function printViaBrowser(receiptUrl, paper) {
    const sep = receiptUrl.includes('?') ? '&' : '?';
    window.open(`${receiptUrl}${sep}cetak=1&kertas=${paper}`, '_blank', 'noopener');
}

/** Cetak struk sesuai pengaturan. Mengembalikan pesan untuk ditampilkan. */
export async function printReceipt(sale, boot, settings = loadPrinterSettings()) {
    switch (settings.method) {
        case 'rawbt':
            printViaRawBT(receiptBytes(sale, boot, settings.paper));
            return 'Struk dikirim ke aplikasi RawBT.';
        case 'bluetooth':
            await printViaBluetooth(receiptBytes(sale, boot, settings.paper));
            return 'Struk sedang dicetak.';
        case 'browser':
            if (!sale.receipt_url) {
                throw new Error('Printer biasa bisa dipakai setelah transaksi terkirim. Saat offline, pakai printer Bluetooth (RawBT).');
            }
            printViaBrowser(sale.receipt_url, settings.paper);
            return 'Jendela cetak dibuka.';
        default:
            throw new Error('Printer belum diatur. Atur dulu di menu Printer.');
    }
}

export async function printTestPage(settings) {
    if (settings.method === 'rawbt') return printViaRawBT(testPageBytes(settings.paper));
    if (settings.method === 'bluetooth') return printViaBluetooth(testPageBytes(settings.paper));
    if (settings.method === 'browser') return window.print();
}
