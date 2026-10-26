<!--
  - Author Louis Perez
  - Created on 26-10-2026-17h-40m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShoppingCart, faCheckCircle, faClock, faCheck, faUndo, faTimes } from "@fal"
library.add(faShoppingCart, faCheckCircle, faClock, faCheck, faUndo, faTimes)

interface Row {
    trade_unit_id: number
    code: string
    name: string
    route: routeType
    skus: string[]
    required: number
    approved: boolean
    status: string
    purchase: { confirmed_at: string | null, confirmed_by: string | null, note: string | null } | null
    can_confirm: boolean
    can_undo: boolean
}

const props = defineProps<{
    data: {
        rows: Row[]
        totals: { components: number, approved: number, confirmed: number }
        route: routeType
    }
}>()

const formatQuantity = (quantity: number) => Number(quantity.toFixed(3)).toLocaleString()
const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : ""

const confirmDialog = ref<{ row: Row, note: string } | null>(null)
const working = ref<number | null>(null)
const errors = ref<Record<string, string>>({})

function send(row: Row, confirmed: boolean, note: string | null) {
    router.patch(route(props.data.route.name, props.data.route.parameters), { trade_unit_id: row.trade_unit_id, confirmed, note }, {
        preserveScroll: true,
        only: ["buyers_list", "recentActivity"],
        onStart: () => working.value = row.trade_unit_id,
        onFinish: () => working.value = null,
        onSuccess: () => { confirmDialog.value = null; errors.value = {} },
        onError: (validationErrors) => errors.value = validationErrors,
    })
}
</script>

<template>
    <section v-if="data" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <header class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-gray-100 px-4 py-3">
            <FontAwesomeIcon icon="fal fa-shopping-cart" class="text-[--app-accent]" fixed-width aria-hidden="true" />
            <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("Buyer's list") }}</h3>
            <span class="text-xs text-gray-500">{{ ctrans(":approved of :total approved for purchasing, :confirmed confirmed bought", { approved: String(data.totals.approved), total: String(data.totals.components), confirmed: String(data.totals.confirmed) }) }}</span>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ ctrans("Component") }}</th>
                        <th class="px-2 py-2">{{ ctrans("For") }}</th>
                        <th class="px-2 py-2 text-right">{{ ctrans("Needed") }}</th>
                        <th class="px-2 py-2">{{ ctrans("Approval") }}</th>
                        <th class="px-2 py-2">{{ ctrans("Purchase") }}</th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in data.rows" :key="row.trade_unit_id">
                        <td class="px-4 py-2">
                            <a :href="route(row.route.name, row.route.parameters)" class="whitespace-nowrap font-medium text-gray-900 hover:text-[--app-accent]">{{ row.code }}</a>
                            <div class="text-xs text-gray-500">{{ row.name }}</div>
                        </td>
                        <td class="px-2 py-2 text-xs text-gray-600">{{ row.skus.join(", ") }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ formatQuantity(row.required) }}</td>
                        <td class="px-2 py-2">
                            <span class="inline-flex items-center gap-1 whitespace-nowrap text-xs" :class="row.approved ? 'text-green-700' : 'text-amber-700'">
                                <FontAwesomeIcon :icon="row.approved ? 'fal fa-check-circle' : 'fal fa-clock'" fixed-width aria-hidden="true" />
                                {{ row.status }}
                            </span>
                        </td>
                        <td class="px-2 py-2 text-xs">
                            <template v-if="row.purchase">
                                <span class="font-medium text-green-700">{{ ctrans("Bought") }}</span>
                                <span class="text-gray-500"> · {{ ctrans(":name, :date", { name: row.purchase.confirmed_by ?? "", date: formatDate(row.purchase.confirmed_at) }) }}</span>
                                <div v-if="row.purchase.note" class="text-gray-700">{{ row.purchase.note }}</div>
                            </template>
                            <span v-else class="text-gray-400">{{ row.approved ? ctrans("Not confirmed yet") : ctrans("Waiting for approval") }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Button v-if="row.can_undo" type="tertiary" size="xs" :label="ctrans('Undo')" :loading="working === row.trade_unit_id" @click="send(row, false, null)">
                                <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-orange-500" fixed-width aria-hidden="true" /></template>
                            </Button>
                            <Button v-else-if="row.can_confirm" type="tertiary" size="xs" :label="ctrans('Confirm bought')" @click="confirmDialog = { row, note: '' }">
                                <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!data.rows.length">
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No components yet. Add them per SKU in Formulation & samples.") }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog :visible="!!confirmDialog" modal :header="confirmDialog ? ctrans('Confirm :code is bought', { code: confirmDialog.row.code }) : ''" :style="{ width: '28rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (confirmDialog = null)">
            <div v-if="confirmDialog" class="space-y-2 text-sm">
                <p class="text-gray-600">{{ ctrans(":quantity needed for :skus.", { quantity: formatQuantity(confirmDialog.row.required), skus: confirmDialog.row.skus.join(", ") }) }}</p>
                <label class="block text-xs text-gray-500">{{ ctrans("Note (optional)") }}</label>
                <InputText v-model="confirmDialog.note" maxlength="255" class="w-full" :placeholder="ctrans('e.g. PO 1234, 600 ordered from Glassworks')" />
                <p v-if="Object.keys(errors).length" class="text-xs text-red-600">{{ Object.values(errors)[0] }}</p>
            </div>
            <template #footer>
                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="confirmDialog = null">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Confirm bought')" :loading="working === confirmDialog?.row.trade_unit_id" @click="confirmDialog && send(confirmDialog.row, true, confirmDialog.note || null)">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>
    </section>
</template>
