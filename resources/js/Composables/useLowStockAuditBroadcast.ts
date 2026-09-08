import { onBeforeUnmount, onMounted, ref } from "vue"
import axios from "axios"
import { ulid } from "ulid"

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

	// Identifies this tab to the server: nothing else may hand back what it holds
	const holderToken = ulid()

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

	const announceLock = async (payload: {
		org_stock_id: number
		location_org_stock_id?: number | null
		is_locked: boolean
		source: "list" | "detail"
	}): Promise<boolean> => {
		if (!organisation || !warehouse) {
			return false
		}

		const locationOrgStockId = payload.location_org_stock_id ?? null

		try {
			const { data } = await axios.post(
				route("grp.json.warehouse.low_stock_audit_lock", { warehouse }),
				{
					org_stock_id: payload.org_stock_id,
					location_org_stock_id: locationOrgStockId,
					is_locked: payload.is_locked,
					source: payload.source,
					holder: holderToken,
				}
			)

			const granted = data?.granted === true

			heldLocks.value = heldLocks.value.filter(
				(held) =>
					!(
						held.org_stock_id === payload.org_stock_id &&
						held.location_org_stock_id === locationOrgStockId
					)
			)

			if (payload.is_locked && granted) {
				heldLocks.value.push({
					org_stock_id: payload.org_stock_id,
					location_org_stock_id: locationOrgStockId,
					source: payload.source,
				})
			}

			if (payload.is_locked && !granted) {
				acquire(payload.org_stock_id, null)
			}

			return payload.is_locked ? granted : false
		} catch (error) {
			console.error("Failed to announce low stock audit lock", error)

			return false
		}
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

	const releaseOnUnload = () => {
		heldLocks.value.forEach((held) => {
			navigator.sendBeacon?.(
				route("grp.json.warehouse.low_stock_audit_lock", { warehouse }),
				new Blob(
					[
						JSON.stringify({
							org_stock_id: held.org_stock_id,
							location_org_stock_id: held.location_org_stock_id,
							is_locked: false,
							source: held.source,
							holder: holderToken,
						}),
					],
					{ type: "application/json" }
				)
			)
		})
	}

	onMounted(() => {
		subscribe()
		window.addEventListener("beforeunload", releaseOnUnload)
	})

	onBeforeUnmount(() => {
		window.removeEventListener("beforeunload", releaseOnUnload)
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
