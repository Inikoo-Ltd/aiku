import { onBeforeUnmount, onMounted } from "vue"

export interface LowStockAuditedEvent {
	org_stock_id: number
	org_stock_slug: string | null
	org_stock_code: string | null
	warehouse_id: number
	warehouse_slug: string | null
	location_org_stock_id: number
	location_id: number | null
	location_code: string | null
	quantity: number
	audited_at: string | null
	is_low_stock_checked: boolean
}

const EVENT = ".low_stock_audited"

/**
 * An audit made in one tab has to reach the other: the low stock list and the stock controller
 * both show the same counts, so whichever one is not doing the auditing has to be told.
 */
export const useLowStockAuditBroadcast = (onAudited: (event: LowStockAuditedEvent) => void) => {
	const organisation = route().params["organisation"]
	const warehouse = route().params["warehouse"]
	const channelName = `grp.${organisation}.warehouse.${warehouse}.low_stock_audit`

	let channel: any = null

	const subscribe = () => {
		// Pages outside a warehouse, such as the group level stock page, have nothing to listen to
		if (!window.Echo || !organisation || !warehouse) {
			return
		}

		channel = window.Echo.private(channelName).listen(EVENT, (event: LowStockAuditedEvent) => {
			onAudited(event)
		})
	}

	const unsubscribe = () => {
		if (!channel) {
			return
		}

		channel.stopListening(EVENT)
		window.Echo.leave(`private-${channelName}`)
		channel = null
	}

	onMounted(subscribe)
	onBeforeUnmount(unsubscribe)

	return { subscribe, unsubscribe }
}
