/** Scan kamera hanya tersedia di browser yang mendukung BarcodeDetector (Chrome Android). */
export function cameraScanSupported() {
    return typeof window !== 'undefined' && 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia;
}

/** Cari barang (atau satuan barang) dari hasil scan. */
export function findByBarcode(products, code) {
    const term = String(code).trim();
    for (const p of products) {
        if (p.barcode === term || p.code === term) return { product: p, unitId: null };
        const unit = (p.units ?? []).find((u) => u.barcode === term);
        if (unit) return { product: p, unitId: unit.id };
    }
    return null;
}
