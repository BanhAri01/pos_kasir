/**
 * Jumlah / berat dari ketikan pengguna (koma = desimal, titik = ribuan bila ada koma).
 *
 *   num('1,5')      -> 1.5
 *   num('1.245,5')  -> 1245.5
 *   num(25)         -> 25
 *   fmtQty(1245.5)  -> '1245,5'
 */
export function num(value) {
    let s = String(value ?? '').trim();
    if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
    return parseFloat(s) || 0;
}

/** Dibulatkan 3 angka di belakang koma (gram), koma sebagai pemisah desimal. */
export function fmtQty(n) {
    return String(Math.round((Number(n) || 0) * 1000) / 1000).replace('.', ',');
}
