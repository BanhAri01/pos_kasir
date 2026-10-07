import { formatRupiah } from '@/composables/useRupiah';

/** Ubah no HP ke format 628xx untuk link wa.me. */
export function normalizePhone(phone) {
    let digits = String(phone ?? '').replace(/\D/g, '');
    if (digits.startsWith('62')) digits = digits.slice(2);
    else if (digits.startsWith('0')) digits = digits.slice(1);
    return digits ? `62${digits}` : '';
}

/**
 * Link WhatsApp berisi ucapan terima kasih + link struk digital.
 * Kalau transaksi belum terkirim ke server (offline), rincian barang ditulis langsung.
 */
export function whatsappReceiptUrl(sale, boot, phone) {
    const lines = [`Terima kasih sudah berbelanja di *${boot.tenant.name}* 🙏`, '', `No. Nota: ${sale.number}`];

    if (sale.receipt_url) {
        lines.push(`Total: *${formatRupiah(sale.total)}*`, '', `Lihat struk: ${sale.receipt_url}`);
    } else {
        for (const item of sale.items) lines.push(`${item.qty} x ${item.name} = ${formatRupiah(item.subtotal)}`);
        lines.push('', `Total: *${formatRupiah(sale.total)}*`);
        if (sale.change_amount > 0) lines.push(`Kembalian: ${formatRupiah(sale.change_amount)}`);
    }

    return `https://wa.me/${normalizePhone(phone)}?text=${encodeURIComponent(lines.join('\n'))}`;
}
