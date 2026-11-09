import { onMounted, onUnmounted, reactive } from "vue"

export interface ProductAvailabilityUpdate {
    product_id: number
    available_quantity: number | null
    is_on_demand: boolean
    status: string | null
}

// ponytail: a product event only follows a movement that changes the floored product quantity (not location moves, part packs, on-demand),
// and it can land before the movement event; the timeout, just above the queue's p95 wait, clears those spinners
const PENDING_TIMEOUT_MS = 12000

export function useProductAvailabilityLive(onUpdate: (update: ProductAvailabilityUpdate) => void) {
    const pendingProductIds = reactive(new Set<number>())
    const pendingTimers = new Map<number, ReturnType<typeof setTimeout>>()
    const organisation = route().params?.organisation as string | undefined

    const clearPending = (productId: number) => {
        clearTimeout(pendingTimers.get(productId))
        pendingTimers.delete(productId)
        pendingProductIds.delete(productId)
    }

    const onAvailability = (update: ProductAvailabilityUpdate) => {
        clearPending(update.product_id)
        onUpdate(update)
    }

    const onStockMovement = (event: { affected_data?: { product_ids?: number[] } }) => {
        for (const productId of event.affected_data?.product_ids ?? []) {
            clearPending(productId)
            pendingProductIds.add(productId)
            pendingTimers.set(productId, setTimeout(() => clearPending(productId), PENDING_TIMEOUT_MS))
        }
    }

    onMounted(() => {
        if (!organisation || !window.Echo) {
            return
        }
        window.Echo.private(`grp.${organisation}.product_availability`).listen(".product_availability", onAvailability)
        window.Echo.private(`grp.${organisation}.stock_movement`).listen(".stock_update", onStockMovement)
    })

    onUnmounted(() => {
        pendingTimers.forEach(timer => clearTimeout(timer))
        if (!organisation || !window.Echo) {
            return
        }
        window.Echo.private(`grp.${organisation}.product_availability`).stopListening(".product_availability", onAvailability)
        window.Echo.private(`grp.${organisation}.stock_movement`).stopListening(".stock_update", onStockMovement)
    })

    return { pendingProductIds }
}
