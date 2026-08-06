import { ref } from "vue"

/*
 * Each field on the composition page owns its own Inertia form, so the price fields
 * cannot see the trade-units form going dirty. The trade-units field publishes its
 * pending units change here and the price fields read it.
 */
export const pendingCompositionUnits = ref<{ from: number; to: number } | null>(null)
