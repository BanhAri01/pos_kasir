/**
 * Pembuat perintah printer thermal ESC/POS (58mm = 32 huruf, 80mm = 48 huruf per baris).
 */
import { formatNumber } from '@/composables/useRupiah';

const ESC = 0x1b;
const GS = 0x1d;

class Builder {
    constructor(paper = '58') {
        this.width = paper === '80' ? 48 : 32;
        this.bytes = [ESC, 0x40]; // reset printer
    }

    raw(...b) {
        this.bytes.push(...b);
        return this;
    }

    text(str = '') {
        // Printer thermal murah hanya mengenal huruf ASCII; huruf lain diganti.
        const clean = String(str).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\x20-\x7e]/g, '?');
        for (const ch of clean) this.bytes.push(ch.charCodeAt(0));
        return this;
    }

    line(str = '') {
        return this.text(str).raw(0x0a);
    }

    align(where) {
        return this.raw(ESC, 0x61, { left: 0, center: 1, right: 2 }[where]);
    }

    bold(on) {
        return this.raw(ESC, 0x45, on ? 1 : 0);
    }

    big(on) {
        return this.raw(GS, 0x21, on ? 0x11 : 0x00);
    }

    divider() {
        return this.line('-'.repeat(this.width));
    }

    /** Teks kiri & kanan dalam satu baris. */
    pair(left, right) {
        const space = this.width - String(right).length;
        const l = String(left).slice(0, Math.max(1, space - 1));
        return this.line(l + ' '.repeat(Math.max(1, space - l.length)) + right);
    }

    /** Teks panjang dipotong per baris. */
    wrap(str) {
        const words = String(str).split(' ');
        let current = '';
        for (const word of words) {
            if ((current + ' ' + word).trim().length > this.width) {
                this.line(current);
                current = word;
            } else {
                current = (current + ' ' + word).trim();
            }
        }
        if (current) this.line(current);
        return this;
    }

    feedAndCut() {
        return this.raw(0x0a, 0x0a, 0x0a, GS, 0x56, 0x42, 0x00);
    }

    build() {
        return new Uint8Array(this.bytes);
    }
}

/** Struk penjualan. `sale` = data dari API (SaleResource), `boot` = data awal kasir. */
export function receiptBytes(sale, boot, paper = '58') {
    const b = new Builder(paper);
    const n = formatNumber;

    b.align('center').bold(true).big(true).wrap(boot.tenant.name).big(false).bold(false);
    if (boot.outlet.name !== boot.tenant.name) b.wrap(boot.outlet.name);
    if (boot.outlet.address) b.wrap(boot.outlet.address);
    if (boot.outlet.receipt_header) b.wrap(boot.outlet.receipt_header);
    b.align('left').divider();
    b.pair('No', sale.number);
    b.pair('Tanggal', `${sale.date} ${sale.time}`);
    if (sale.cashier) b.pair('Kasir', sale.cashier);
    if (sale.customer?.name) b.pair('Pelanggan', sale.customer.name);
    if (sale.status === 'void') b.align('center').bold(true).line('*** DIBATALKAN ***').bold(false).align('left');
    b.divider();

    for (const item of sale.items) {
        b.wrap(item.name);
        b.pair(`  ${item.qty}${item.unit ? ' ' + item.unit : ''} x ${n(item.unit_price)}`, n(item.subtotal + item.discount_amount));
        if (item.discount_amount > 0) b.pair('  Diskon', `-${n(item.discount_amount)}`);
        if (item.note) b.wrap(`  * ${item.note}`);
    }

    b.divider();
    b.pair('Subtotal', n(sale.subtotal));
    if (sale.discount_amount > 0) b.pair('Diskon', `-${n(sale.discount_amount)}`);
    if (sale.service_charge_amount > 0) b.pair('Biaya layanan', n(sale.service_charge_amount));
    if (sale.tax_amount > 0) b.pair(boot.outlet.tax_inclusive ? 'Pajak (termasuk)' : 'Pajak', n(sale.tax_amount));
    b.bold(true).pair('TOTAL', `Rp${n(sale.total)}`).bold(false);
    for (const p of sale.payments) {
        b.pair(p.name, n(p.amount + (p.type === 'cash' ? sale.change_amount : 0)));
    }
    if (sale.change_amount > 0) b.pair('Kembalian', n(sale.change_amount));
    b.divider().align('center');
    b.wrap(boot.outlet.receipt_footer || 'Terima kasih sudah berbelanja!');
    return b.feedAndCut().build();
}

export function testPageBytes(paper = '58') {
    return new Builder(paper)
        .align('center')
        .bold(true)
        .big(true)
        .line('HERMES POS')
        .big(false)
        .bold(false)
        .line('Tes printer berhasil!')
        .line(paper === '80' ? 'Kertas 80mm' : 'Kertas 58mm')
        .divider()
        .feedAndCut()
        .build();
}
