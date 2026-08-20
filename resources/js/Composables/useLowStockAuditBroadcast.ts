import { onBeforeUnmount, onMounted, ref } from "vue"
import axios from "axios"

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

export interface LowStockAuditLockEvent {
	org_stock_id: number
	location_org_stock_id: number | null
	is_locked: boolean
	source: "list" | "detail" | null
}

const START_EVENT = ".low_stock_audited_start"
const DONE_EVENT = ".low_stock_audited"
const LOCK_EVENT = ".low_stock_audit_lock"

/**
 * An audit made in one view has to reach the other: the low stock list and the stock controller
 * both show the same counts, so whichever one is not counting has to be told.
 *
 * Two things lock: an audit being written (start until done), and someone merely intending to
 * count, which is a quantity being typed in the list or the audit modal being opened. The
 * intent lock is announced by the tab that holds it and released when it lets go.
 */
export const useLowStockAuditBroadcast = (handlers: {
	onAudited: (event: LowStockAuditedEvent) => void
	onAuditStart?: (event: LowStockAuditedEvent) => void
	onLockChanged?: (event: LowStockAuditLockEvent) => void
}) => {
	const organisation = route().params["organisation"]
	const warehouse = route().params["warehouse"]
	const channelName = `grp.${organisation}.warehouse.${warehouse}.low_stock_audit`

	const lockedLocationIds = ref<number[]>([])
	const lockedOrgStockIds = ref<number[]>([])

	// What this tab is holding, so it can be handed back on unmount
	const heldLocks = ref<
		{ org_stock_id: number; location_org_stock_id: number | null; source: "list" | "detail" }[]
	>([])

	let channel: any = null

	const isLocked = (locationOrgStockId: number) =>
		lockedLocationIds.value.includes(locationOrgStockId)

	const isOrgStockLocked = (orgStockId: number) => lockedOrgStockIds.value.includes(orgStockId)

	const release = (orgStockId: number, locationOrgStockId: number | null) => {
		if (locationOrgStockId) {
			lockedLocationIds.value = lockedLocationIds.value.filter(
				(id) => id !== locationOrgStockId
			)

			return
		}

		lockedOrgStockIds.value = lockedOrgStockIds.value.filter((id) => id !== orgStockId)
	}

	const acquire = (orgStockId: number, locationOrgStockId: number | null) => {
		if (locationOrgStockId) {
			if (!isLocked(locationOrgStockId)) {
				lockedLocationIds.value.push(locationOrgStockId)
			}
		} else if (!isOrgStockLocked(orgStockId)) {
			lockedOrgStockIds.value.push(orgStockId)
		}
	}

	/**
	 * Tell the other view that this tab is counting, or has stopped.
	 */
	const announceLock = (payload: {
		org_stock_id: number
		location_org_stock_id?: number | null
		is_locked: boolean
		source: "list" | "detail"
	}) => {
		if (!organisation || !warehouse) {
			return
		}

		const locationOrgStockId = payload.location_org_stock_id ?? null

		heldLocks.value = heldLocks.value.filter(
			(held) =>
				!(
					held.org_stock_id === payload.org_stock_id &&
					held.location_org_stock_id === locationOrgStockId
				)
		)

		if (payload.is_locked) {
			heldLocks.value.push({
				org_stock_id: payload.org_stock_id,
				location_org_stock_id: locationOrgStockId,
				source: payload.source,
			})
		}

		return axios
			.post(route("grp.json.warehouse.low_stock_audit_lock", { warehouse }), {
				org_stock_id: payload.org_stock_id,
				location_org_stock_id: locationOrgStockId,
				is_locked: payload.is_locked,
				source: payload.source,
			})
			.catch((error) => {
				// Not worth interrupting the user over, but a lock that never reaches the other
				// view fails silently otherwise, which is very hard to spot
				console.error("Failed to announce low stock audit lock", error)
			})
	}

	const releaseHeldLocks = () => {
		heldLocks.value.slice().forEach((held) => {
			announceLock({
				org_stock_id: held.org_stock_id,
				location_org_stock_id: held.location_org_stock_id,
				is_locked: false,
				source: held.source,
			})
		})
	}

	const subscribe = () => {
		// Pages outside a warehouse, such as the group level stock page, have nothing to listen to
		if (!window.Echo || !organisation || !warehouse) {
			return
		}

		channel = window.Echo.private(channelName)
			.listen(START_EVENT, (event: LowStockAuditedEvent) => {
				acquire(event.org_stock_id, event.location_org_stock_id)
				handlers.onAuditStart?.(event)
			})
			.listen(DONE_EVENT, (event: LowStockAuditedEvent) => {
				release(event.org_stock_id, event.location_org_stock_id)
				handlers.onAudited(event)
			})
			.listen(LOCK_EVENT, (event: LowStockAuditLockEvent) => {
				if (event.is_locked) {
					acquire(event.org_stock_id, event.location_org_stock_id)
				} else {
					release(event.org_stock_id, event.location_org_stock_id)
				}

				handlers.onLockChanged?.(event)
			})
	}

	const unsubscribe = () => {
		if (!channel) {
			return
		}

		channel.stopListening(START_EVENT)
		channel.stopListening(DONE_EVENT)
		channel.stopListening(LOCK_EVENT)
		window.Echo.leave(`private-${channelName}`)
		channel = null
	}

	onMounted(subscribe)
	onBeforeUnmount(() => {
		releaseHeldLocks()
		unsubscribe()
	})

	return {
		subscribe,
		unsubscribe,
		lockedLocationIds,
		lockedOrgStockIds,
		isLocked,
		isOrgStockLocked,
		announceLock,
		releaseHeldLocks,
	}
}
