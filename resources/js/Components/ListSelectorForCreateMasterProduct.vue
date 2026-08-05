<script setup lang="ts">
import { inject, ref, computed, onMounted, onUnmounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from './Elements/Buttons/Button.vue'
import axios from 'axios'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import Pagination from '@/Components/Table/Pagination.vue'
import Image from "@common/Components/Image.vue";
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faExclamationTriangle } from "@fas"
import { faPlus, faTimes, faBoxUp } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faForklift, faTrashAlt } from '@far'
import Toggle from '@/Components/Pure/Toggle.vue'
import FractionDisplay from '@/Components/DataDisplay/FractionDisplay.vue'

library.add(faCheckCircle, faTimes, faBoxUp)

const props = withDefaults(defineProps<{
    modelValue?: Portfolio[]
    routeFetch: routeType
    isLoadingSubmit?: boolean
    isLoadingComponent?: boolean
    withQuantity?: boolean
    label_result?: string
    valueToRefetch?: string
    idxSubmitSuccess?: number
    key_quantity?: string
    head_label?: string
    no_data_label?: string
    tabs?: Array<{ label: string, routeFetch: routeType }>
    is_dropship?:boolean
}>(), {
    key_quantity: 'quantity_selected',
    head_label: 'Selected Products',
    no_data_label: 'No Data Available'
})

const emits = defineEmits<{
    (e: "update:modelValue", val: Portfolio[]): void
    (e: "close"): void
    (e: "after-delete"): void
    (e: "on-select"): void
}>()

interface Portfolio {
    id: number
    name: string
    code: string
    image: any
    gross_weight: string
    price: number
    currency_code: string
    reference?: string
    no_code?: boolean
    no_price?: boolean
    is_recommended?: boolean
}

const layout = inject('layout', layoutStructure)
const locale = inject('locale', null)

const hideStockUnavailable = ref(false)

const isLoadingFetch = ref(false)
const showDialog = ref(false)

const queryPortfolio = ref('')
const list = ref<Portfolio[]>([])
const meta = ref()
const link = ref()

// --- States ---
// committed selections (parent synced)
const committedProducts = ref<Portfolio[]>(props.modelValue || [])
// temporary selections (inside dialog)
const selectedProduct = ref<Portfolio[]>([])

const activeTab = ref(0)

// --- helpers
const openDialog = () => {
    // copy committed -> temp when opening
    selectedProduct.value = [...committedProducts.value]
    showDialog.value = true
}

const confirmSelection = () => {
    committedProducts.value = [...selectedProduct.value]
    emits("update:modelValue", committedProducts.value)
    emits("on-select")
    showDialog.value = false
}

const getPortfoliosList = async (url?: string) => {
    isLoadingFetch.value = true
    try {
        const tabRoute = props.tabs?.[activeTab.value]?.routeFetch || props.routeFetch
        const currentTab = props.tabs?.[activeTab.value]
        const params: Record<string, any> = { ...tabRoute.parameters }

        // ✅ Only append search if tab has "search: true"
        if (currentTab?.search && queryPortfolio.value) {
            params['filter[global]'] = queryPortfolio.value
        }

        const urlToFetch = url || route(tabRoute.name, params)

        const response = await axios.get(urlToFetch)
        list.value = response.data.data
        meta.value = response?.data.meta || null
        link.value = response?.data.links || null
    } catch (e) {
        console.error('Error', e)
        notify({
            title: trans("Something went wrong."),
            text: trans("Error while getting the portfolios list."),
            type: "error"
        })
    } finally {
        isLoadingFetch.value = false
    }
}

const debounceGetPortfoliosList = debounce(() => (getPortfoliosList()), 500)

const compSelectedProduct = computed(() =>
    selectedProduct.value.map((item: Portfolio) => item.id)
)

const selectProduct = (item: Portfolio) => {
    const exists = selectedProduct.value.find(p => p.id === item.id)
    if (exists) {
        selectedProduct.value = selectedProduct.value.filter(p => p.id !== item.id)
    } else {
        const newItem: any = { ...item }
        if (props.withQuantity && !newItem[props.key_quantity]) {
            newItem[props.key_quantity] = 1
        }
        selectedProduct.value = [...selectedProduct.value, newItem]
    }
}


const isAllSelected = computed(() => {
    if (list.value.length === 0) return false
    return list.value.every(item =>
        selectedProduct.value.some(selected => selected.id === item.id)
    )
})

const selectAllProducts = () => {
    if (isAllSelected.value) {
        const currentPageIds = list.value.map(item => item.id)
        selectedProduct.value = selectedProduct.value.filter(
            selected => !currentPageIds.includes(selected.id)
        )
    } else {
        const currentSelectedIds = selectedProduct.value.map(item => item.id)
        const productsToAdd = list.value.filter(
            item => !currentSelectedIds.includes(item.id)
        )
        selectedProduct.value = [...selectedProduct.value, ...productsToAdd]
    }
}

const changeTab = (index: number) => {
    activeTab.value = index
    /* queryPortfolio.value = '' */
    getPortfoliosList()
}

// lifecycle
onMounted(() => getPortfoliosList())
onUnmounted(() => {
    list.value = []
    meta.value = null
    link.value = null
    queryPortfolio.value = ''
})

const clearAll = () => {
    selectedProduct.value = []
}

const deleteFormCommited = (item) => {
    committedProducts.value = committedProducts.value.filter(p => p.id !== item.id); emits('update:modelValue', [...committedProducts.value]), emits('after-delete')
}

const updateProduct = (updated: Portfolio) => {
    committedProducts.value = committedProducts.value.map(p => {
        if (p.id === updated.id) {
            console.log("✅ Match found:", p);

            const merged = {
                ...p,
                ...updated
            };

            console.log("🔹 After merge:", merged);

            return merged;
        }

        return p;
    });

    console.log("🔹 After update (committedProducts):", committedProducts.value);

    emits("update:modelValue", [...committedProducts.value]);
    console.log("📤 Emitted updated modelValue:", [...committedProducts.value]);
};

// Section: per-organisation packed_in — the OS-TU leg of the triangle, editable in place
type OrgPackedIn = { org_stock_id: number; org_code: string; packed_in: number }
const packedInDialogItem = ref<any>(null)
const packedInEdits = ref<OrgPackedIn[]>([])
const isSavingPackedIn = ref(false)

const orgPackedInSummary = (item: any): { label: string; diverges: boolean } | null => {
    const orgs: OrgPackedIn[] = item.packed_in_by_org || []
    if (!orgs.length) {
        return null
    }
    const distinct = [...new Set(orgs.map(org => Number(org.packed_in)))]
    if (distinct.length === 1) {
        return { label: trans('(SKO packed in :packs)', { packs: distinct[0] + 's' }), diverges: false }
    }
    return {
        label: '(' + groupOrgsByValue(orgs, org => Number(org.packed_in) + 's') + ')',
        diverges: true,
    }
}

/** "AW 2s · ES, SK 1s" — orgs sharing a value collapse into one entry. */
const groupOrgsByValue = (orgs: OrgPackedIn[], valueOf: (org: OrgPackedIn) => string): string => {
    const groups = new Map<string, string[]>()
    for (const org of orgs) {
        const value = valueOf(org)
        groups.set(value, [...(groups.get(value) || []), org.org_code])
    }
    return [...groups].map(([value, codes]) => `${codes.join(', ')} ${value}`).join(' · ')
}

/** When warehouses pack differently there is no single pick number: show one per org. */
const orgPicksSummary = (item: any): string | null => {
    const orgs: OrgPackedIn[] = item.packed_in_by_org || []
    const distinct = [...new Set(orgs.map(org => Number(org.packed_in)))]
    if (distinct.length < 2) {
        return null
    }
    const quantity = Number(item[props.key_quantity]) || 1
    return groupOrgsByValue(orgs, org => String(Number((quantity / (Number(org.packed_in) || 1)).toFixed(2))))
}

/** Orgs whose packing doesn't divide the quantity — the real partial-pack anomalies. */
const partialPackOrgs = (item: any): string | null => {
    const quantity = Number(item[props.key_quantity]) || 1
    const orgs: OrgPackedIn[] = item.packed_in_by_org || []
    if (!orgs.length) {
        return Number(item.packed_in) > 1 && quantity % Number(item.packed_in) !== 0
            ? String(item.packed_in)
            : null
    }
    const offenders = orgs.filter(org => Number(org.packed_in) > 1 && quantity % Number(org.packed_in) !== 0)
    if (!offenders.length) {
        return null
    }
    return groupOrgsByValue(offenders, org => Number(org.packed_in) + 's')
}

const openPackedInDialog = (item: any) => {
    if (!item.packed_in_by_org?.length) return
    packedInDialogItem.value = item
    packedInEdits.value = item.packed_in_by_org.map((org: OrgPackedIn) => ({ ...org, packed_in: Number(org.packed_in) }))
}

const savePackedIn = async () => {
    const item = packedInDialogItem.value
    if (!item) return
    isSavingPackedIn.value = true
    try {
        for (const [index, edit] of packedInEdits.value.entries()) {
            const original = item.packed_in_by_org[index]
            if (Number(edit.packed_in) === Number(original.packed_in)) continue
            await axios.patch(route('grp.models.org_stock.trade_units.update', { orgStock: edit.org_stock_id }), {
                trade_units: [{ id: item.id, quantity: Number(edit.packed_in) }],
            })
            original.packed_in = Number(edit.packed_in)
        }
        notify({ title: trans('Success'), text: trans('Warehouse packing updated'), type: 'success' })

        const distinct = [...new Set(item.packed_in_by_org.map((org: OrgPackedIn) => Number(org.packed_in)))]
        if (distinct.length === 1) {
            item.packed_in = distinct[0]
            updatePickFractional(item)
        }
        packedInDialogItem.value = null
    } catch (error: any) {
        notify({
            title: trans('Something went wrong.'),
            text: error.response?.data?.message || trans('Could not update warehouse packing'),
            type: 'error',
        })
    } finally {
        isSavingPackedIn.value = false
    }
}

const listData = computed(() => {
    if (hideStockUnavailable.value) {
        return list.value.filter(item => item.stock_available)
    }
    return list.value
})

console.log(route().params)

/** item.packed_in can lag behind the per-org reality; trust packed_in_by_org when it agrees. */
const effectivePackedIn = (item: any): number => {
    const orgs: OrgPackedIn[] = item.packed_in_by_org || []
    const distinct = [...new Set(orgs.map(org => Number(org.packed_in)))]
    if (distinct.length === 1) {
        return distinct[0] || 1
    }
    return Number(item.packed_in) || 1
}

const updatePickFractional = async (item: any) => {
    const packedIn = effectivePackedIn(item)
    await axios.get(route('grp.json.product.get-pick-fractional', {
        numerator: (Number(item.quantity) / packedIn),
        denominator: packedIn,
    })).then((result) => {
        const index = committedProducts.value.findIndex(
            (p: any) => p.id === item.id
        );

        if (index !== -1) {
            committedProducts.value[index].pick_fractional = result.data;
        }
    })
}

const debounceUpdatePickFractional = debounce((item: any) => updatePickFractional(item), 500)


defineExpose({
    updateProduct,
    committedProducts
})



</script>

<template>
    <div>
        <div class="">
            <div class="flex justify-between">
                <div>
                    <h3 class="font-semibold mb-2">{{ trans(props.head_label) }}</h3>
                </div>
                <div>
                    <Button v-if="committedProducts.length" @click="openDialog" :label="'select'" size="xs"
                        type="dashed" :icon="faPlus"></Button>
                </div>
            </div>

            <div v-if="committedProducts.length" class="border rounded-md overflow-hidden">

                <slot name="committed-list" :committedProducts="committedProducts"
                    :deleteFormCommited="deleteFormCommited">
                    <div v-for="item in committedProducts" :key="item.id + '-'"
                        class="flex items-center justify-between gap-4 p-2 border-b last:border-b-0 bg-white hover:bg-gray-50">

                        <!-- Info -->
                        <slot name="info" :data="item" :key="item.id + '-'">
                            <div class="flex items-center gap-3">
                                <Image v-if="item.image" :src="item.image.thumbnail"
                                    class="w-12 h-12 rounded object-cover" />
                                <div>
                                    <div class="font-medium leading-none">{{ item.name }}</div>
                                    <div class="flex justify-beetween mt-1 gap-5">
                                        <div class="flex justify-between mt-1 text-xs text-gray-500">
                                            <span>{{ item.code || '-' }}</span>
                                            <slot name="after-title" :data="item">
                                            </slot>
                                        </div>
                                        <div v-if="item.value" class="text-xs text-gray-500">
                                            {{ locale.currencyFormat(layout.app?.currency?.code, item.value || 0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </slot>

                        <!-- Warning: partial pack, the classic sign one side of the composition is wrong -->
                        <div v-if="partialPackOrgs(item)"
                            v-tooltip="trans('This quantity is not a whole number of warehouse packs of :packed_in. Either this quantity or the SKU packing is wrong, check both before saving.', { packed_in: partialPackOrgs(item) })"
                            class="text-xs text-amber-600 whitespace-nowrap"
                        >
                            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500 mr-1" fixed-width aria-hidden="true" />
                            {{ trans('Partial pack') }}: {{ item[props.key_quantity] || 1 }} / {{ partialPackOrgs(item) }}
                        </div>

                        <!-- Quantity + Delete -->
                        <div class="flex items-center gap-2">
                            <NumberWithButtonSave v-if="withQuantity"
                                :key="item.id + '-' + (item[props.key_quantity] || 1)"
                                :modelValue="item[props.key_quantity]" :bindToTarget="{ min: 1 }"
                                @update:modelValue="(val: number) => { item[props.key_quantity] = val; emits('update:modelValue', [...committedProducts]); debounceUpdatePickFractional(item) }"
                                noUndoButton noSaveButton parentClass="w-min" >
                                <template #suffix>
                                   <div class="text-sm text-gray-700 px-3 font-bold">{{ item.type }}</div>
                                </template>
                                 <template #prefix>
                                <div v-tooltip="trans('The warehouse stores this as packs of :packed_in (SKO). Selling :quantity means the picker takes :quantity of a :packed_in-pack.', { packed_in: Number(item.packed_in) || 1, quantity: item[props.key_quantity] || 1 })"
                                    class="text-sm px-3 text-teal-600 whitespace-nowrap w-full flex items-baseline gap-1">
                                    <span>{{ trans('Picks') }}</span>
                                    <span v-if="orgPicksSummary(item)" class="font-bold">{{ orgPicksSummary(item) }}</span>
                                    <span v-else class="font-bold">
                                        <FractionDisplay v-if="item.pick_fractional" :fractionData="item.pick_fractional" />
                                    </span>
                                    <span>{{ trans('SKO') }}</span>
                                    <span v-if="orgPackedInSummary(item)"
                                        @click.stop.prevent="openPackedInDialog(item)"
                                        v-tooltip="trans('Click to edit how each warehouse packs this SKU')"
                                        class="cursor-pointer underline decoration-dotted underline-offset-2"
                                        :class="orgPackedInSummary(item)!.diverges ? 'text-amber-600' : 'text-teal-600/60'">
                                        {{ orgPackedInSummary(item)!.label }}
                                    </span>
                                    <span v-else class="text-teal-600/60">{{ trans('(SKO packed in :packs)', { packs: (Number(item.packed_in) || 1) + 's' }) }}</span>
                                </div>

                                </template>
                            </NumberWithButtonSave>
                            <button class="text-red-500 hover:text-red-700 px-4"
                                @click="() => deleteFormCommited(item)">
                                <FontAwesomeIcon :icon="faTrashAlt" />
                            </button>
                        </div>
                    </div>
                </slot>

            </div>
            <div v-else>
                <div class="border rounded-md bg-gray-50 p-6 text-center text-gray-500">
                    <p class="font-medium">{{ no_data_label }}</p>
                    <p class="text-sm text-gray-400 mb-4">Please add an item to see content here.</p>
                    <div><Button @click="openDialog" :label="'select'" size="xs" type="dashed" :icon="faPlus"></Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog -->
        <Dialog v-model:visible="showDialog" modal header="Select Products"
            :style="{ width: '80vw', maxWidth: '1200px' }"
            :content-style="{ overflow: 'hidden', paddingLeft: '20px', paddingRight: '20px', }" @hide="$emit('close')">

            <div class="relative isolate">
                <div v-if="isLoadingSubmit"
                    class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                    <LoadingIcon />
                </div>

                <!-- Tabs -->
                <div v-if="tabs?.length" class="flex gap-4 mb-4 border-b">
                <div
                    v-for="(tab, index) in tabs"
                    :key="index"
                    @click="changeTab(index)"
                    class="cursor-pointer px-4 py-2 -mb-px font-medium border-b-2"
                    :class="
                    activeTab === index
                        ? 'text-indigo-600 border-indigo-600'
                        : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300'
                    "
                >
                    {{ trans(tab.label) }}
                </div>

                <div
                    class="ml-auto cursor-pointer px-4 py-2 -mb-px font-medium border-b-2 text-gray-500 border-transparent"
                >
                   {{ trans("Hide out of stock")}}
                   <Toggle v-model="hideStockUnavailable" />
                </div>
                </div>


                <div class="mb-2">
                    <div v-if="tabs?.length && tabs[activeTab]?.search" class="mb-2">
                        <PureInput v-model="queryPortfolio" @update:modelValue="() => debounceGetPortfoliosList()"
                            :placeholder="trans('Input to search')" />
                    </div>

                </div>

                <!-- list + pagination -->
                <div class="h-full  text-base font-normal">
                    <div class="col-span-4 pb-8 md:pb-2 h-fit overflow-auto flex flex-col">

                        <!-- header -->
                        <div class="flex justify-between items-center">
                            <div class="font-semibold text-lg py-1">
                                {{ props.label_result ?? trans("Result") }} ({{ locale?.number(meta?.total || 0) }})
                            </div>
                            <div class="flex gap-2">
                                <div @click="selectAllProducts"
                                    :class="isAllSelected ? 'text-green-400' : 'cursor-pointer text-green-600 hover:text-green-700 hover:underline'">
                                    {{ trans("Select :number products in this page", { number: list.length }) }}
                                </div>
                                <div v-if="compSelectedProduct.length" @click="clearAll"
                                    class="cursor-pointer text-red-400 hover:text-red-600 hover:underline">
                                    {{ trans('Clear :number selections', { number: compSelectedProduct.length }) }}
                                    <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-300 mb-1"></div>

                        <!-- list -->
                        <div class="h-full md:h-[400px] overflow-auto py-2 relative">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 pb-2">
                                <template v-if="!isLoadingFetch">
                                    <template v-if="list.length > 0">
                                        <div v-for="(item, index) in listData" :key="index" @click="selectProduct(item)"
                                            class="relative h-full rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border"
                                            :class="[
                                                compSelectedProduct.includes(item.id)
                                                    ? 'bg-indigo-100 border-indigo-300'
                                                    : 'bg-white hover:bg-gray-200 border-gray-300',
                                            ]">

                                            <FontAwesomeIcon v-if="compSelectedProduct.includes(item.id)"
                                                icon="fas fa-check-circle"
                                                class="bottom-2 right-2 absolute text-green-500" fixed-width
                                                aria-hidden="true" />

                                            <slot name="product" :item="item">
                                                <div class="w-16 h-16 border border-gray-500/20 rounded aspect-square overflow-y-clip text-xxs flex items-center justify-center">
                                                    <Image
                                                        v-if="item.image"
                                                        :src="item.image?.thumbnail"
                                                        imageCover
                                                        :alt="item.name"
                                                    />
                                                    <FontAwesomeIcon v-else v-tooltip="trans('No image')" icon="fal fa-image" class="opacity-70 text-xl" fixed-width aria-hidden="true" />

                                                </div>
                                                
                                                <div class="flex flex-col justify-between w-full">
                                                    <div v-if="!item.no_code" class="font-semibold">
                                                        {{ item.code || 'no code' }}
                                                    </div>
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <div class="leading-none mb-1">{{ item.name || 'noname' }}</div>
                                                    </div>
                                                    <div v-if="item.reference" class="text-xs text-gray-400 italic">
                                                        {{ item.reference }}
                                                    </div>

                                                    <div v-if="!item.no_price && item.price"
                                                        class="text-xs text-gray-x500">
                                                        {{ locale?.currencyFormat(item.currency_code || 'usd',  item.price || 0) }}
                                                    </div>

                                                    
                                                    <div v-tooltip="trans('Packed in :qty', { qty: item.packed_in })" class="w-fit text-xs border border-teal-100 rounded px-2 py-0.5 bg-teal-600 text-white">
                                                        <FontAwesomeIcon icon="fas fa-box-up" class="mr-1" fixed-width aria-hidden="true" />
                                                        {{ item.packed_in }} [{{ item.type }}]
                                                    </div>
                                                </div>
                                            </slot>
                                        </div>
                                    </template>
                                    <div v-else class="text-center text-gray-500 col-span-3">
                                        {{ trans("No Results found") }}
                                    </div>
                                </template>
                                <div v-else v-for="(item, index) in 6" :key="index"
                                    class="rounded cursor-pointer w-full h-20 flex gap-x-2 border skeleton" />
                            </div>
                        </div>

                        <Pagination v-if="meta" :on-click="getPortfoliosList" :has-data="true" :meta="meta"
                            :per-page-options="[]" />
                    </div>
                </div>
            </div>

            <!-- footer -->
            <template #footer>
                <Button type="secondary" @click="showDialog = false" label="Cancel"></Button>
                <Button type="create" @click="confirmSelection" label="Select"></Button>
            </template>
        </Dialog>

        <!-- Dialog: per-organisation warehouse packing (OS-TU) -->
        <Dialog :visible="!!packedInDialogItem" @update:visible="(open: boolean) => { if (!open) packedInDialogItem = null }"
            modal :style="{ width: '26rem' }" :header="trans('Warehouse packing') + (packedInDialogItem ? ` — ${packedInDialogItem.code}` : '')">
            <div class="text-xs text-gray-500 mb-3">
                {{ trans('How each warehouse physically packs this SKU. This is the picking reality: changing it re-syncs product picking and open delivery notes for that organisation only.') }}
            </div>
            <div class="flex flex-col gap-2">
                <div v-for="edit in packedInEdits" :key="edit.org_stock_id" class="flex items-center gap-3">
                    <span class="w-16 font-medium text-gray-700 uppercase">{{ edit.org_code }}</span>
                    <NumberWithButtonSave
                        :modelValue="edit.packed_in" :bindToTarget="{ min: 1 }"
                        @update:modelValue="(val: number) => edit.packed_in = val"
                        noUndoButton noSaveButton parentClass="w-min" />
                    <span class="text-xs text-gray-400">{{ trans('per pack') }}</span>
                </div>
            </div>
            <template #footer>
                <Button type="secondary" :disabled="isSavingPackedIn" @click="packedInDialogItem = null" label="Cancel" />
                <Button type="save" :loading="isSavingPackedIn" @click="savePackedIn" label="Save" />
            </template>
        </Dialog>
    </div>
</template>
