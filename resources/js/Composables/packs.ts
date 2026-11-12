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

export interface Countable {
	unit?: string | null
	pack_size?: number | null
	unit_name?: string | null
	piece_name?: string | null
}

const isPackStep = (step: Countable): boolean => !!step.pack_size && !step.unit

const formatNumber = (quantity: number): string => Number(quantity).toLocaleString(undefined, { maximumFractionDigits: 3 })

export const pieceWord = (step: Countable): string => step.piece_name || ctrans("pieces")

export const unitWord = (step: Countable): string => {
	if (step.unit) {
		return step.unit
	}
	if (step.pack_size) {
		return ctrans(":name of :size", { name: step.unit_name || ctrans("packs"), size: step.pack_size })
	}

	return step.unit_name || pieceWord(step)
}

export const packCount = (packs: number, packSize: number | null | undefined): string => {
	if (!packSize) {
		return formatNumber(packs)
	}
	const pieces = Math.round(packs * packSize)
	const loose = pieces % packSize
	const whole = (pieces - loose) / packSize

	return loose ? ctrans(":packs + :loose loose", { packs: formatNumber(whole), loose }) : formatNumber(whole)
}

export const countLabel = (quantity: number, step: Countable): string =>
	`${isPackStep(step) ? packCount(quantity, step.pack_size) : formatNumber(quantity)} ${unitWord(step)}`

export const progressLabel = (made: number, required: number, step: Countable): string => {
	if (!isPackStep(step)) {
		return `${formatNumber(made)} / ${formatNumber(required)} ${unitWord(step)}`
	}

	return `${packCount(made, step.pack_size)} / ${packCount(required, step.pack_size)} ${unitWord(step)} (${formatNumber(Math.round(required * (step.pack_size ?? 1)))} ${pieceWord(step)})`
}
