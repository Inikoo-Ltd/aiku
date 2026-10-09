import { ctrans } from "@/Composables/useTrans"

export type PartnerProductionStage =
	| "backlog"
	| "preparing"
	| "assigned"
	| "producing"
	| "made"
	| "picked_from_stock"
	| "waiting"
	| "handed_over"

export interface PartnerProductionStageBadge {
	label: string
	tooltip: string
	class: string
}

export const usePartnerProductionStages = (
	partnerName: string
): Record<PartnerProductionStage, PartnerProductionStageBadge> => {
	const productionStage = (label: string, tone: string) => ({
		label,
		tooltip: ctrans("Production at :partner: :stage", { partner: partnerName, stage: label }),
		class: tone,
	})

	return {
		backlog: productionStage(ctrans("Backlog"), "bg-gray-100 text-gray-600"),
		preparing: productionStage(ctrans("Preparing"), "bg-amber-100 text-amber-800"),
		assigned: productionStage(ctrans("Assigned"), "bg-amber-100 text-amber-800"),
		producing: productionStage(ctrans("Producing"), "bg-amber-100 text-amber-800"),
		made: productionStage(ctrans("Made"), "bg-emerald-50 text-emerald-700"),
		picked_from_stock: {
			label: ctrans("Picked from stock"),
			tooltip: ctrans(":partner had it in stock, no need to make it", { partner: partnerName }),
			class: "bg-emerald-50 text-emerald-700",
		},
		waiting: {
			label: ctrans("Waiting"),
			tooltip: ctrans("Submitted to :partner, they have not picked or planned it yet", { partner: partnerName }),
			class: "bg-gray-100 text-gray-600",
		},
		handed_over: {
			label: ctrans("Done"),
			tooltip: ctrans(":partner already handed it over", { partner: partnerName }),
			class: "bg-emerald-100 text-emerald-800",
		},
	}
}
