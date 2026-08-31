<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { debounce } from "lodash-es"
import { faPlus, faTrash } from "@fal"
import { faExclamationTriangle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { routeType } from "@/types/route"

library.add(faPlus, faTrash, faExclamationTriangle)

type Row = { uid: number; id: number | null; quantity: number | string; code?: string; name?: string }
type ProductContext = { code: string; shop_code: string; quantity: number }

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        fetchRoute: routeType
        impactRoute?: routeType
        productsContext?: Record<number, ProductContext[]>
    }
}>()

let nextUid = 0

// A product picks quantity/packed_in packs of this SKU; a remainder means either the
// product's pack size or this SKU's packing is wrong, which is exactly what to show.
const productPick = (productQuantity: number, packedIn: number | string) => {
    const packs = Number(packedIn)
    if (!packs || packs <= 0) return null
    return {
        label: `${productQuantity} / ${packs}`,
        isPartial: productQuantity % packs !== 0,
    }
}

const productsOn = (row: Row) =>
    (row.id ? props.fieldData.productsContext?.[row.id] ?? [] : []).map((product) => ({
        ...product,
        pick: productPick(product.quantity, row.quantity),
    }))

const rows = ref<Row[]>(
    (props.form[props.fieldName] ?? []).map((row: Omit<Row, "uid">) => ({
        uid: nextUid++,
        id: row.id,
        quantity: row.quantity,
        code: row.code,
        name: row.name,
    }))
)

// The backend refuses to re-mean stocked locations until the editor says what the counts
// now mean; those errors arrive under their own keys, not under the field name.
const stockStrategyErrorKeys = ["stock_recount_required", "stock_conversion_preview", "stock_strategy"]
const recountError = computed<string | null>(() => props.form.errors?.stock_recount_required ?? null)
const conversionPreview = computed<string | null>(() => props.form.errors?.stock_conversion_preview ?? null)
const strategyError = computed<string | null>(() => props.form.errors?.stock_strategy ?? null)

// With no arithmetic conversion on offer there is nothing to choose: keeping the counts is
// the only answer, so it is preselected and saving again is the confirmation.
watch([recountError, conversionPreview], ([hasRecountError, hasConversion]) => {
    if (hasRecountError && !hasConversion && "stock_strategy" in props.form && !props.form.stock_strategy) {
        props.form.stock_strategy = "keep"
    }
})

const rowError = (row: Row): string | null => {
    const submittedIndex = rows.value.filter((candidate) => candidate.id).indexOf(row)
    if (submittedIndex < 0) return null
    return (
        props.form.errors?.[`${props.fieldName}.${submittedIndex}.quantity`] ??
        props.form.errors?.[`${props.fieldName}.${submittedIndex}.id`] ??
        null
    )
}

const otherErrors = computed<string[]>(() =>
    Object.entries(props.form.errors ?? {})
        .filter(([key, message]) =>
            message &&
            key !== props.fieldName &&
            !key.startsWith(`${props.fieldName}.`) &&
            !stockStrategyErrorKeys.includes(key)
        )
        .map(([, message]) => String(message))
)

const impact = ref<{ to_be_modified: any[]; to_be_affected: any[] } | null>(null)
const isLoadingImpact = ref(false)

const fetchImpact = debounce(async () => {
    if (!props.fieldData.impactRoute || !props.form.isDirty) {
        impact.value = null
        return
    }

    isLoadingImpact.value = true
    try {
        const response = await axios.get(
            route(props.fieldData.impactRoute.name, props.fieldData.impactRoute.parameters)
        )
        impact.value = response.data
    } catch (error) {
        impact.value = null
    } finally {
        isLoadingImpact.value = false
    }
}, 500)

watch(
    rows,
    (value) => {
        props.form[props.fieldName] = value
            .filter((row) => row.id)
            .map((row) => ({ id: row.id, quantity: Number(row.quantity) || 1 }))
        props.form.clearErrors()
        if ("stock_strategy" in props.form) {
            props.form.stock_strategy = null
        }
        fetchImpact()
    },
    { deep: true }
)

const addRow = () => rows.value.push({ uid: nextUid++, id: null, quantity: 1 })
const removeRow = (index: number) => rows.value.splice(index, 1)
</script>

<template>
    <div class="max-w-2xl space-y-3">
        <div class="rounded-md border border-gray-200">
            <table class="w-full table-fixed text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="rounded-tl-md py-2 pl-3 pr-4 font-medium">{{ ctrans("Trade unit") }}</th>
                        <th class="w-32 py-2 pr-4 font-medium">{{ ctrans("Quantity") }}</th>
                        <th class="w-10 rounded-tr-md py-2 pr-2" aria-hidden="true" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, index) in rows" :key="row.uid">
                        <tr :class="index > 0 ? 'border-t border-gray-200' : ''">
                            <td class="min-w-0 py-2 pl-3 pr-4 align-top">
                                <PureMultiselectInfiniteScroll
                                    v-model="row.id"
                                    :fetchRoute="fieldData.fetchRoute"
                                    :initOptions="row.id ? [{ id: row.id, code: row.code, name: row.name }] : []"
                                    labelProp="code"
                                    labelAdditionalProp="name"
                                    valueProp="id"
                                    :placeholder="trans('Search trade unit')" />
                            </td>
                            <td class="py-2 pr-4 align-top">
                                <PureInputNumber v-model="row.quantity" :minValue="1" />
                                <p v-if="rowError(row)" class="mt-1 text-xs text-red-600">{{ rowError(row) }}</p>
                            </td>
                            <td class="py-2 pr-2 align-top text-right">
                                <button
                                    type="button"
                                    @click.stop.prevent="removeRow(index)"
                                    v-tooltip="ctrans('Remove trade unit')"
                                    class="mt-1 rounded-md p-2 text-red-500 hover:bg-red-50 hover:text-red-700">
                                    <FontAwesomeIcon :icon="faTrash" class="h-4 w-4" fixed-width />
                                </button>
                            </td>
                        </tr>

                        <!-- Context: products selling this trade unit, and what this packing means for their picks -->
                        <tr v-if="productsOn(row).length" class="bg-gray-50/70">
                            <td colspan="3" class="px-3 pb-2 pt-1 text-xs">
                                <div class="font-medium text-gray-600">
                                    {{ ctrans('Products on') }} {{ row.code ?? ctrans('this trade unit') }} ({{ productsOn(row).length }})
                                    — {{ ctrans('pick as trade units / pack of :qty', { qty: row.quantity }) }}
                                </div>
                                <div class="mt-1 max-h-32 overflow-y-auto" style="scrollbar-width: thin">
                                    <div
                                        v-for="product in productsOn(row)"
                                        :key="product.shop_code + product.code"
                                        class="flex items-baseline gap-2 py-0.5">
                                        <span class="text-gray-700">{{ product.code }}</span>
                                        <span class="text-gray-400">{{ product.shop_code }}</span>
                                        <span
                                            v-if="product.pick"
                                            class="ml-auto tabular-nums"
                                            :class="product.pick.isPartial ? 'font-medium text-amber-600' : 'text-gray-500'">
                                            {{ product.pick.label }}
                                            <FontAwesomeIcon
                                                v-if="product.pick.isPartial"
                                                :icon="faExclamationTriangle"
                                                class="text-amber-500"
                                                v-tooltip="ctrans('Not a whole number of packs: either this product\'s pack size or this SKU packing is wrong')" />
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="!rows.length">
                        <td colspan="3" class="py-6 text-center text-sm text-gray-400">
                            {{ ctrans("No trade units yet") }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button
            type="button"
            @click.stop.prevent="addRow"
            class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-800">
            <FontAwesomeIcon :icon="faPlus" class="h-3.5 w-3.5" />
            {{ ctrans("Add trade unit") }}
        </button>

        <!-- Stocked locations: the editor decides what the stored counts mean under the new packing -->
        <div v-if="recountError" class="space-y-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm">
            <div class="flex items-start gap-2 font-medium text-amber-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 shrink-0" />
                <span>{{ recountError }}</span>
            </div>

            <template v-if="conversionPreview">
                <div class="space-y-1.5 text-gray-700">
                    <label class="flex cursor-pointer items-start gap-2">
                        <input
                            type="radio"
                            value="keep"
                            v-model="form.stock_strategy"
                            class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span>
                            <span class="font-medium">{{ ctrans("Keep the counts") }}</span>
                            <span class="block text-xs text-gray-500">
                                {{ ctrans("Numbers stay as they are; the locations get flagged for a physical recount") }}
                            </span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-2">
                        <input
                            type="radio"
                            value="convert"
                            v-model="form.stock_strategy"
                            class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span>
                            <span class="font-medium">{{ ctrans("Convert the counts to the new packing") }}</span>
                            <span class="mt-0.5 block whitespace-pre-line text-xs tabular-nums text-gray-500">{{ conversionPreview }}</span>
                        </span>
                    </label>
                </div>

                <p class="text-xs text-amber-700">{{ ctrans("Choose one, then save again") }}</p>
            </template>

            <template v-else>
                <div class="text-gray-700">
                    <span class="font-medium">{{ ctrans("The counts will be kept") }}</span>
                    <span class="block text-xs text-gray-500">
                        {{ ctrans("These counts cannot be converted arithmetically for this packing, so the numbers stay as they are and the locations get flagged for a physical recount") }}
                    </span>
                </div>

                <p class="text-xs text-amber-700">{{ ctrans("Save again to confirm") }}</p>
            </template>
        </div>

        <p v-if="strategyError" class="text-sm text-red-600">{{ strategyError }}</p>

        <p v-if="form.errors[fieldName]" class="text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>

        <p v-for="(message, index) in otherErrors" :key="index" class="text-sm text-red-600">
            {{ message }}
        </p>

        <div v-if="isLoadingImpact" class="py-2">
            <LoadingIcon />
        </div>

        <div
            v-else-if="impact?.to_be_modified?.length || impact?.to_be_affected?.length"
            class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm">
            <div class="flex items-center gap-2 font-medium text-amber-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" />
                {{ ctrans("Saving will change picking quantities") }}
            </div>

            <div v-if="impact?.to_be_modified?.length" class="mt-2">
                <div class="text-xs font-medium text-gray-600">
                    {{ ctrans("Updated automatically") }} ({{ impact.to_be_modified.length }})
                </div>
                <div class="max-h-32 overflow-y-auto" style="scrollbar-width: thin">
                    <div v-for="deliveryNote in impact.to_be_modified" :key="deliveryNote.id">
                        {{ deliveryNote.reference }} — {{ deliveryNote.shop_name }}
                    </div>
                </div>
            </div>

            <div v-if="impact?.to_be_affected?.length" class="mt-2">
                <div class="text-xs font-medium text-red-700">
                    {{ ctrans("Needs manual action") }} ({{ impact.to_be_affected.length }})
                </div>
                <div class="max-h-32 overflow-y-auto" style="scrollbar-width: thin">
                    <div v-for="deliveryNote in impact.to_be_affected" :key="deliveryNote.id">
                        {{ deliveryNote.reference }} — {{ deliveryNote.shop_name }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
