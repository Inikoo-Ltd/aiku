import { ctrans, ctransChoice } from "@/Composables/useTrans"

export const packsLabel = (packs: number, packSize: number | null | undefined): string => {
	if (!packSize) {
		return String(packs)
	}
	const pieces = Math.round(packs * packSize)
	const loose = pieces % packSize
	const whole = (pieces - loose) / packSize

	return loose ? ctrans(':packs packs + :loose loose', { packs: whole, loose }) : ctrans(':packs packs', { packs: whole })
}

export const skoAndCartonPackingLabel = (unitsPerPack: number | string | null | undefined, unitsPerCarton: number | string | null | undefined): string => {
	const unitsPerSko = Number(unitsPerPack) || 1
	const cartonUnits = Number(unitsPerCarton) || 0
	const parts = [ctransChoice(":count unit per SKO|:count units per SKO", unitsPerSko)]

	if (cartonUnits) {
		parts.push(unitsPerSko === 1
			? ctransChoice(":count unit per carton|:count units per carton", cartonUnits)
			: ctransChoice(":count SKO per carton (:units units)|:count SKOs per carton (:units units)", Math.round(cartonUnits / unitsPerSko * 100) / 100, { units: cartonUnits }))
	}

	return parts.join(" · ")
}
