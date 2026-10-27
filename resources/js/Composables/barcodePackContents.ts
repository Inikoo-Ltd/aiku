/*
    * Author: Vika Aqordi
    * Created on: 2026-10-27 10:47
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/
import { ctransChoice } from "@/Composables/useTrans"

export const barcodePackContents = (barcode: { level: string, quantity?: number | null, packs?: number | null }): string | null => {
	const parts: string[] = []

	if (barcode.level === "carton" && barcode.packs) {
		parts.push(ctransChoice(":count SKO|:count SKOs", Number(barcode.packs)))
	}

	if (barcode.level !== "unit" && barcode.quantity) {
		parts.push(ctransChoice(":count unit|:count units", Number(barcode.quantity)))
	}

	return parts.length ? parts.join(" · ") : null
}
