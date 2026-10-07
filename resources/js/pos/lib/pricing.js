/**
 * Harga satu baris: SAMA dengan app/Modules/Pos/Services/PriceResolver.php.
 *
 *   1. Harga dasar  = harga barang (sudah termasuk harga khusus outlet dari server)
 *   2. Satuan lain  : harga & konversi satuan (mis. 1 dus = 40 pcs)
 *   3. Harga khusus : untuk tipe pelanggan / mulai jumlah tertentu (grosir). Dipilih yang PALING MURAH.
 *   4. Promo        : potongan promo yang berlaku hari ini, dipilih yang PALING BESAR (varian ikut modelnya)
 *   5. Tambahan     : + harga setiap pilihan
 */
export function resolvePrice(product, { qty = 1, unitId = null, modifierIds = [], priceLevelId = null, promotions = [], date = null } = {}) {
    let price = product.price;
    let conversion = 1;
    let unitName = product.unit;
    let resolvedUnitId = null;

    const unit = unitId ? (product.units ?? []).find((u) => u.id === unitId) : null;
    if (unit) {
        price = unit.price;
        conversion = unit.conversion;
        unitName = unit.name;
        resolvedUnitId = unit.id;
    }

    const q = Number(String(qty).replace(',', '.')) || 0;
    const candidates = (product.prices ?? []).filter(
        (t) => (t.unit_id ?? null) === resolvedUnitId && (t.level_id === null || t.level_id === priceLevelId) && q >= t.min_qty,
    );
    if (candidates.length) {
        price = Math.min(price, ...candidates.map((t) => t.price));
    }

    const promo = bestPromotion(product, price, promotions, date ?? todayIn());
    if (promo) price -= promo.discount;

    const modifiers = [];
    for (const group of product.modifier_groups ?? []) {
        let picked = group.options.filter((o) => modifierIds.includes(o.id));
        if (group.selection === 'single') picked = picked.slice(0, 1);
        for (const option of picked) {
            modifiers.push({ id: option.id, group: group.name, name: option.name, price_delta: option.price_delta });
            price += option.price_delta;
        }
    }

    return { unitPrice: Math.max(0, price), conversion, unitName, unitId: resolvedUnitId, modifiers, promo };
}

/** Tanggal hari ini (YYYY-MM-DD) di zona waktu usaha. */
export function todayIn(timeZone = undefined) {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}

/** Promo dengan potongan terbesar untuk barang ini. SAMA dengan Promotion::appliesTo() & discountFor(). */
export function bestPromotion(product, price, promotions, date) {
    let best = null;
    for (const p of promotions) {
        if (date < p.starts_on || date > p.ends_on) continue;
        const applies =
            p.scope === 'categories' ? (p.category_ids ?? []).includes(product.category_id)
            : p.scope === 'products' ? (p.product_ids ?? []).some((id) => id === product.id || id === product.parent_id)
            : true;
        if (!applies) continue;
        const discount = p.type === 'percent' ? Math.min(price, Math.floor((price * p.value) / 10000)) : Math.min(price, p.value);
        if (discount > (best?.discount ?? 0)) best = { name: p.name, discount };
    }
    return best;
}

/** Pilihan wajib yang belum dipilih (untuk pesan "Pilih ukuran dulu"). */
export function missingRequiredGroup(product, modifierIds = []) {
    return (product.modifier_groups ?? []).find((g) => g.is_required && !g.options.some((o) => modifierIds.includes(o.id))) ?? null;
}
