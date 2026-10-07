/**
 * Rumus total belanja: SAMA PERSIS dengan app/Modules/Pos/Services/SaleCalculator.php.
 * Kalau salah satu diubah, ubah juga yang lain (ada test di keduanya).
 *
 * Semua uang bilangan bulat rupiah. Persen dalam basis point (1000 = 10%).
 */

/** Harga x jumlah (jumlah boleh desimal, mis. 2,5 kg), dibulatkan ke rupiah. */
export function lineMoney(unitPrice, qty) {
    // Jumlah dihitung per seribu (3 angka desimal, sama seperti server) dengan bilangan bulat,
    // supaya tidak ada galat float. Contoh: 3.333 x 1,5 = 4.999.500 / 1000 = 4.999,5 -> 5.000.
    const qtyThousandths = Math.round(parseFloat(String(qty).replace(',', '.')) * 1000);
    return Math.round((Math.round(Number(unitPrice)) * qtyThousandths) / 1000);
}

/**
 * @param {Array<{unit_price:number, qty:number|string, discount_amount?:number}>} lines
 * @param {{discountType?:string|null, discountValue?:number, serviceChargeBp?:number, taxBp?:number, taxInclusive?:boolean}} options
 */
export function calculate(lines, options = {}) {
    const { discountType = null, discountValue = 0, serviceChargeBp = 0, taxBp = 0, taxInclusive = false } = options;

    let subtotal = 0;
    const computed = lines.map((line) => {
        const gross = lineMoney(line.unit_price, line.qty);
        const discount = Math.min(Math.max(0, Math.round(line.discount_amount ?? 0)), gross);
        const lineSubtotal = gross - discount;
        subtotal += lineSubtotal;
        return { gross, discount, subtotal: lineSubtotal };
    });

    let discountAmount = 0;
    if (discountType === 'percent') {
        discountAmount = Math.round((subtotal * Math.min(10000, Math.max(0, discountValue))) / 10000);
    } else if (discountType === 'amount') {
        discountAmount = Math.min(Math.max(0, discountValue), subtotal);
    }

    const base = subtotal - discountAmount;
    const service = Math.round((base * Math.max(0, serviceChargeBp)) / 10000);
    const taxable = base + service;

    let tax;
    let total;
    if (taxInclusive) {
        tax = taxBp > 0 ? Math.round((taxable * taxBp) / (10000 + taxBp)) : 0;
        total = taxable;
    } else {
        tax = Math.round((taxable * Math.max(0, taxBp)) / 10000);
        total = taxable + tax;
    }

    return { lines: computed, subtotal, discountAmount, serviceChargeAmount: service, taxAmount: tax, total };
}

/**
 * Tombol uang cepat untuk pembayaran tunai: Uang Pas + pecahan yang masuk akal.
 * Contoh total 37.500 -> [37.500, 40.000, 50.000, 100.000]
 */
export function quickCashOptions(total) {
    if (total <= 0) return [0];
    const options = new Set([total]);
    for (const step of [1000, 5000, 10000]) {
        const rounded = Math.ceil(total / step) * step;
        if (rounded > total) options.add(rounded);
    }
    for (const note of [10000, 20000, 50000, 100000]) {
        if (note > total) options.add(note);
    }
    return [...options].sort((a, b) => a - b).slice(0, 5);
}
