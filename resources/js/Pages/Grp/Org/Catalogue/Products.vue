<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { computed, inject, ref } from "vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from '@/types/route'
import Dialog from 'primevue/dialog'
import { faMinus, faPlus } from '@fal'
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureInput from '@/Components/Pure/PureInput.vue'
import { trans } from 'laravel-vue-i18n'
import axios from 'axios'
import { ulid } from 'ulid'
import AttachmentManagement from '@/Components/Goods/AttachmentManagement.vue'
import { faSave as fadSave } from '@fad'
import { faSave as falSave, faInfoCircle } from '@fal'
import { faAsterisk, faQuestion } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPencil } from '@far'

library.add(fadSave, faQuestion, falSave, faInfoCircle, faAsterisk)

const props = defineProps<{
    pageHead: PageHeadingTypes
    editable_table: boolean
    title: string
    currencies?: any
    tabs: {
        current: string
        navigation: Record<string, string>
    },
    data: Record<string, any>
    index?: Record<string, any>
    sales?: Record<string, any>
    routes: {
        families_route: routeType
        submit_route: routeType
    }
    is_orphan_products?: boolean
    attachments?: Record<string, any>
    shop_id?: number
}>()

const layout = inject<string>('layout')
const currentTab = ref(props.tabs.current)
const isOpenModalEditProducts = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const key = ref(ulid())

/* Bulk form fields */
const form = ref({
    price: null,
    rrp: null,
    unit: ''
})

/* Track field state */
const formDirty = ref({ price: false, rrp: false, unit: false })
const formProcessing = ref({ price: false, rrp: false, unit: false })

/* Component mapping */
const component = computed(() => {
    const mapping: Record<string, any> = {
        index: TableProducts,
        sales: TableProducts,
        attachments: AttachmentManagement
    }
    return mapping[currentTab.value]
})

const selectedProductsId = ref<Record<string, boolean>>({})
const compSelectedProductsId = computed(() =>
    Object.keys(selectedProductsId.value).filter(id => selectedProductsId.value[id])
)

const loadingField = ref<string | null>(null)
const rowErrors = ref<Record<string, any>>({})


const onSaveEditBulkProduct = async (field: string, value: any) => {
    formProcessing.value[field] = true
    loadingField.value = field
    rowErrors.value = {}

    try {
        const payload = compSelectedProductsId.value.map(productId => ({
            id: productId,
            [field]: value,
        }))

        await router.patch(
            route("grp.models.product.bulk_update", { shop: props.shop_id }),
            { products: payload },
            {
                preserveScroll: true,
                onError: (errors) => (rowErrors.value = errors),
                onSuccess: () => {
                    isOpenModalEditProducts.value = false
                    key.value = ulid()
                    selectedProductsId.value = {}
                }
            }
        )
    } catch (err) {
        console.error("Bulk edit failed:", err)
    } finally {
        loadingField.value = null
        formProcessing.value[field] = false
        formDirty.value[field] = false
        key.value = ulid()
    }
}


const savePrice = () => onSaveEditBulkProduct("price", form.value.price)
const saveRrp = () => onSaveEditBulkProduct("rrp", form.value.rrp)
const saveUnit = () => onSaveEditBulkProduct("unit", form.value.unit)

const onCancelEditBulkProduct = () => {
    isOpenModalEditProducts.value = false
    rowErrors.value = {}
    key.value = ulid()
}
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #other>
            <Button
                v-if="compSelectedProductsId.length > 0 && editable_table"
                @click="() => isOpenModalEditProducts = true"
                type="tertiary"
                :icon="faPencil"
                label="Edit Products"
            />
        </template>
    </PageHeading>

    <Tabs
        :current="currentTab"
        :navigation="props.tabs.navigation"
        @update:tab="handleTabUpdate"
    />

    <component
        :is="component"
        :key="currentTab + key"
        :tab="currentTab"
        :data="props[currentTab]"
        :isCheckboxProducts="props.editable_table"
        :editable_table="props.editable_table"
        :selectedProductsId="selectedProductsId"
        @selectedRow="(ids) => selectedProductsId = { ...selectedProductsId, ...ids }"
    />

    <!-- MODAL -->
    <Dialog
        :header="trans('Edit Selected Products')"
        v-model:visible="isOpenModalEditProducts"
        modal
        closable
        :style="{ width: '500px' }"
    >
        <div class="px-2 space-y-6">

            <!-- PRICE -->
            <div>
                <label class="text-sm">Price/outer</label>
                <div class="flex gap-2 items-center mt-1">
                    <InputNumber
                        v-model="form.price"
                        @input="formDirty.price = true"
                        mode="currency"
                        :currency="currencies?.code ?? layout?.group?.currency?.code"
                        :step="0.25"
                        showButtons
                        button-layout="horizontal"
                        inputClass="text-xs"
                    >
                        <template #incrementbuttonicon>
                            <FontAwesomeIcon :icon="faPlus" />
                        </template>
                        <template #decrementbuttonicon>
                            <FontAwesomeIcon :icon="faMinus" />
                        </template>
                    </InputNumber>

                    <!-- SAVE BUTTON -->
                    <button
                        class="h-9 align-bottom text-center"
                        :disabled="formProcessing.price || !formDirty.price"
                        @click="savePrice"
                    >
                        <template v-if="formDirty.price">
                            <FontAwesomeIcon
                                v-if="formProcessing.price"
                                icon="fad fa-spinner-third"
                                class="text-2xl animate-spin"
                                fixed-width
                            />
                            <FontAwesomeIcon
                                v-else
                                icon="fad fa-save"
                                class="h-8"
                                :style="{ '--fa-secondary-color': 'rgb(0,255,4)' }"
                            />
                        </template>

                        <FontAwesomeIcon
                            v-else
                            icon="fal fa-save"
                            class="h-8 text-gray-300"
                        />
                    </button>
                </div>
            </div>

            <!-- RRP -->
            <div>
                <label class="text-sm">RRP/unit</label>
                <div class="flex gap-2 items-center mt-1">
                    <InputNumber
                        v-model="form.rrp"
                        @input="formDirty.rrp = true"
                        mode="currency"
                        :currency="currencies?.code ?? layout?.group?.currency?.code"
                        :step="0.25"
                        showButtons
                        button-layout="horizontal"
                        inputClass="text-xs"
                    >
                        <template #incrementbuttonicon>
                            <FontAwesomeIcon :icon="faPlus" />
                        </template>
                        <template #decrementbuttonicon>
                            <FontAwesomeIcon :icon="faMinus" />
                        </template>
                    </InputNumber>

                    <!-- SAVE BUTTON -->
                    <button
                        class="h-9 align-bottom text-center"
                        :disabled="formProcessing.rrp || !formDirty.rrp"
                        @click="saveRrp"
                    >
                        <template v-if="formDirty.rrp">
                            <FontAwesomeIcon
                                v-if="formProcessing.rrp"
                                icon="fad fa-spinner-third"
                                class="text-2xl animate-spin"
                                fixed-width
                            />
                            <FontAwesomeIcon
                                v-else
                                icon="fad fa-save"
                                class="h-8"
                                :style="{ '--fa-secondary-color': 'rgb(0,255,4)' }"
                            />
                        </template>

                        <FontAwesomeIcon
                            v-else
                            icon="fal fa-save"
                            class="h-8 text-gray-300"
                        />
                    </button>
                </div>
            </div>

            <!-- UNIT -->
            <div>
                <label class="text-sm">Unit label</label>
                <div class="flex gap-2 items-center mt-1">
                    <PureInput
                        v-model="form.unit"
                        @input="formDirty.unit = true"
                    />

                    <!-- SAVE BUTTON -->
                    <button
                        class="h-9 align-bottom text-center"
                        :disabled="formProcessing.unit || !formDirty.unit"
                        @click="saveUnit"
                    >
                        <template v-if="formDirty.unit">
                            <FontAwesomeIcon
                                v-if="formProcessing.unit"
                                icon="fad fa-spinner-third"
                                class="text-2xl animate-spin"
                                fixed-width
                            />
                            <FontAwesomeIcon
                                v-else
                                icon="fad fa-save"
                                class="h-8"
                                :style="{ '--fa-secondary-color': 'rgb(0,255,4)' }"
                            />
                        </template>

                        <FontAwesomeIcon
                            v-else
                            icon="fal fa-save"
                            class="h-8 text-gray-300"
                        />
                    </button>
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <Button type="tertiary" label="Close" @click="onCancelEditBulkProduct" />
            </div>
        </div>
    </Dialog>
</template>
