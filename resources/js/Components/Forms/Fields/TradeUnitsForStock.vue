<script setup lang="ts">
import { ref, watch } from "vue"
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

type Row = { id: number | null; quantity: number | string; code?: string; name?: string }

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        fetchRoute: routeType
        impactRoute?: routeType
        productsContext?: Record<number, { code: string; shop_code: string; quantity: number }[]>
    }
}>()

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

const rows = ref<Row[]>(
    (props.form[props.fieldName] ?? []).map((row: Row) => ({
        id: row.id,
        quantity: row.quantity,
        code: row.code,
        name: row.name,
    }))
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
        props.form.errors[props.fieldName] = null
        fetchImpact()
    },
    { deep: true }
)

const addRow = () => rows.value.push({ id: null, quantity: 1 })
const removeRow = (index: number) => rows.value.splice(index, 1)
</script>

<template>
    <div class="space-y-2">
        <div v-if="rows.length" class="flex gap-2 text-xs font-medium text-gray-500">
            <div class="flex-1">{{ trans("Trade unit") }}</div>
            <div class="w-28">{{ trans("Quantity") }}</div>
            <div class="w-8 shrink-0" aria-hidden="true" />
        </div>

        <div v-for="(row, index) in rows" :key="index" class="flex gap-2 items-start">
            <div class="flex-1">
                <PureMultiselectInfiniteScroll
                    v-model="row.id"
                    :fetchRoute="fieldData.fetchRoute"
                    :initOptions="row.id ? [{ id: row.id, code: row.code, name: row.name }] : []"
                    labelProp="code"
                    labelAdditionalProp="name"
                    valueProp="id"
                    :placeholder="trans('Search trade unit')" />
            </div>
            <div class="w-28">
                <PureInputNumber v-model="row.quantity" :minValue="1" />
            </div>
            <button
                type="button"
                @click.stop.prevent="removeRow(index)"
                class="mt-1 p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md">
                <FontAwesomeIcon :icon="faTrash" class="h-4 w-4" />
            </button>
        </div>

        <!-- Context: products selling these trade units, and what this packing means for their picks -->
        <template v-for="(row, index) in rows" :key="'products-' + index">
            <div v-if="row.id && fieldData.productsContext?.[row.id]?.length"
                class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs">
                <div class="font-medium text-gray-600">
                    {{ trans('Products on') }} {{ row.code ?? trans('this trade unit') }} ({{ fieldData.productsContext[row.id].length }})
                    — {{ trans('pick as trade units / pack of :qty', { qty: row.quantity }) }}
                </div>
                <div class="mt-1 max-h-32 overflow-y-auto" style="scrollbar-width: thin">
                    <div v-for="product in fieldData.productsContext[row.id]" :key="product.shop_code + product.code"
                        class="flex gap-2 items-baseline">
                        <span class="text-gray-700">{{ product.code }}</span>
                        <span class="text-gray-400">{{ product.shop_code }}</span>
                        <span v-if="productPick(product.quantity, row.quantity)"
                            :class="productPick(product.quantity, row.quantity)!.isPartial ? 'text-amber-600 font-medium' : 'text-gray-500'">
                            {{ productPick(product.quantity, row.quantity)!.label }}
                            <FontAwesomeIcon v-if="productPick(product.quantity, row.quantity)!.isPartial"
                                :icon="faExclamationTriangle" class="text-amber-500"
                                v-tooltip="trans('Not a whole number of packs: either this product\'s pack size or this SKU packing is wrong')" />
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <button
            type="button"
            @click.stop.prevent="addRow"
            class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
            <FontAwesomeIcon :icon="faPlus" class="h-3.5 w-3.5" />
            {{ trans("Add trade unit") }}
        </button>

        <p v-if="form.errors[fieldName]" class="text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>

        <div v-if="isLoadingImpact" class="py-2">
            <LoadingIcon />
        </div>

        <div
            v-else-if="impact?.to_be_modified?.length || impact?.to_be_affected?.length"
            class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm">
            <div class="flex items-center gap-2 font-medium text-amber-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" />
                {{ trans("Saving will change picking quantities") }}
            </div>

            <div v-if="impact?.to_be_modified?.length" class="mt-2">
                <div class="text-xs font-medium text-gray-600">
                    {{ trans("Updated automatically") }} ({{ impact.to_be_modified.length }})
                </div>
                <div class="max-h-32 overflow-y-auto" style="scrollbar-width: thin">
                    <div v-for="deliveryNote in impact.to_be_modified" :key="deliveryNote.id">
                        {{ deliveryNote.reference }} — {{ deliveryNote.shop_name }}
                    </div>
                </div>
            </div>

            <div v-if="impact?.to_be_affected?.length" class="mt-2">
                <div class="text-xs font-medium text-red-700">
                    {{ trans("Needs manual action") }} ({{ impact.to_be_affected.length }})
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
