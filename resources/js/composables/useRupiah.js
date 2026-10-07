/**
 * Format uang Rupiah. Uang selalu berupa bilangan bulat (rupiah), tidak pernah desimal.
 *
 *   formatRupiah(125000)        -> "Rp125.000"
 *   formatRupiah(-5000)         -> "-Rp5.000"
 *   parseRupiah("Rp 12.500")    -> 12500
 */
const formatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

export function formatRupiah(amount) {
    const value = Math.round(Number(amount) || 0);
    // Intl memberi spasi tak-putus "Rp 125.000"; dirapatkan agar konsisten di semua HP.
    return formatter.format(value).replace(/\s/g, '');
}

/** Hanya angka tanpa "Rp": 125.000 */
export function formatNumber(amount) {
    return new Intl.NumberFormat('id-ID').format(Math.round(Number(amount) || 0));
}

export function parseRupiah(text) {
    const negative = String(text).trim().startsWith('-');
    const digits = String(text).replace(/\D/g, '');
    const value = digits ? parseInt(digits, 10) : 0;
    return negative ? -value : value;
}
