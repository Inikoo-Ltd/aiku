export type AlertEvent = "customer_message" | "assigned" | "colleague" | "waiting" | "ticket"
export type AlertWay = "popup" | "desktop" | "sound"
export type AlertEventChoices = Partial<Record<AlertEvent, Partial<Record<AlertWay, boolean>>>>

export const ALERT_EVENT_WAYS: Record<AlertEvent, AlertWay[]> = {
	customer_message: ["popup", "desktop", "sound"],
	assigned: ["popup", "desktop", "sound"],
	colleague: ["popup", "desktop", "sound"],
	waiting: ["desktop", "sound"],
	ticket: ["popup", "desktop", "sound"],
}

export const alertWayAllowed = (
	sharedChoices: AlertEventChoices | null,
	loadedChoices: AlertEventChoices | null | undefined,
	event: AlertEvent,
	way: AlertWay,
): boolean => (sharedChoices ?? loadedChoices)?.[event]?.[way] !== false
