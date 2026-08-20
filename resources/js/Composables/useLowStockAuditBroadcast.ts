import { onBeforeUnmount, onMounted, ref } from "vue"

export interface LowStockAuditedEvent {
	org_stock_id: number
	org_stock_slug?: string | null
	org_stock_code: string | null
	warehouse_id: number
	warehouse_slug: string | null
	location_org_stock_id: number
	location_id: number | null
	location_code: string | null
	quantity?: number
	audited_at?: string | null
	is_low_stock_checked?: boolean
}

const START_EVENT = ".low_stock_audited_start"
const DONE_EVENT = ".low_stock_audited"

// A lock is only ever held for as long as one audit takes. If the releasing broadcast is lost
// the controls would stay dead, so every lock expires on its own.
const LOCK_TIMEOUT_MS = 30000

/**
 * An audit made in one tab has to reach the other: the low stock list and the stock controller
 * both show the same counts, so whichever one is not doing the auditing has to be told.
 *
 * The audit announces itself twice, once as it starts and once when it lands. Between the two
 * the location is locked, so nobody counts a shelf someone else is already writing to.
 */
export const useLowStockAuditBroadcast = (handlers: {
	onAudited: (event: LowStockAuditedEvent) => void
	onAuditStart?: (event: LowStockAuditedEvent) => void
}) => {
	const organisation = route().params["organisation"]
	const warehouse = route().params["warehouse"]
	const channelName = `grp.${organisation}.warehouse.${warehouse}.low_stock_audit`

	const lockedLocationIds = ref<number[]>([])
	const lockTimers: Record<number, ReturnType<typeof setTimeout>> = {}

	let channel: any = null

	const isLocked = (locationOrgStockId: number) =>
		lockedLocationIds.value.includes(locationOrgStockId)

	const releaseLock = (locationOrgStockId: number) => {
		lockedLocationIds.value = lockedLocationIds.value.filter((id) => id !== locationOrgStockId)

		if (lockTimers[locationOrgStockId]) {
			clearTimeout(lockTimers[locationOrgStockId])
			delete lockTimers[locationOrgStockId]
		}
	}

	const acquireLock = (locationOrgStockId: number) => {
		if (!isLocked(locationOrgStockId)) {
			lockedLocationIds.value.push(locationOrgStockId)
		}

		if (lockTimers[locationOrgStockId]) {
			clearTimeout(lockTimers[locationOrgStockId])
		}

		lockTimers[locationOrgStockId] = setTimeout(
			() => releaseLock(locationOrgStockId),
			LOCK_TIMEOUT_MS
		)
	}

	const subscribe = () => {
		// Pages outside a warehouse, such as the group level stock page, have nothing to listen to
		if (!window.Echo || !organisation || !warehouse) {
			return
		}

		channel = window.Echo.private(channelName)
			.listen(START_EVENT, (event: LowStockAuditedEvent) => {
				acquireLock(event.location_org_stock_id)
				handlers.onAuditStart?.(event)
			})
			.listen(DONE_EVENT, (event: LowStockAuditedEvent) => {
				releaseLock(event.location_org_stock_id)
				handlers.onAudited(event)
			})
	}

	const unsubscribe = () => {
		Object.keys(lockTimers).forEach((id) => clearTimeout(lockTimers[Number(id)]))

		if (!channel) {
			return
		}

		channel.stopListening(START_EVENT)
		channel.stopListening(DONE_EVENT)
		window.Echo.leave(`private-${channelName}`)
		channel = null
	}

	onMounted(subscribe)
	onBeforeUnmount(unsubscribe)

	return { subscribe, unsubscribe, lockedLocationIds, isLocked }
}
