<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from '@/types/route'
import Dialog from 'primevue/dialog'
import { faMinus, faPencil, faPlus } from '@fal'
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureInput from '@/Components/Pure/PureInput.vue'
import { trans } from 'laravel-vue-i18n'
import axios from 'axios'
import { ulid } from 'ulid'
import AttachmentManagement from '@/Components/Goods/AttachmentManagement.vue'


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
}>()



// Current tab state
const currentTab = ref(props.tabs.current)
const isOpenModalEditProducts = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const form = useForm({
    rrp: 0,
    price: 0,
    unit: ''
})

// Component mapping per tab
const component = computed(() => {
    const mapping: Record<string, any> = {
        index: TableProducts,
        sales: TableProducts,
        attachments: AttachmentManagement
    }
    return mapping[currentTab.value]
})

// Selected products logic
const selectedProductsId = ref<Record<string, boolean>>({})
const compSelectedProductsId = computed(() =>
    Object.keys(selectedProductsId.value).filter(key => selectedProductsId.value[key])
)


const loadingSave = ref(false)
const rowErrors = ref<Record<string, any>>({}) // store errors keyed by productId
const key = ref(ulid())

//loop save
/* const onSaveEditBulkProduct = async () => {
    loadingSave.value = true
    rowErrors.value = {} // reset

    try {
        // Run all axios requests in parallel
        await Promise.all(
            compSelectedProductsId.value.map(async (productId) => {
                try {
                    await axios.patch(
                        route("grp.models.product.update", { product: productId }),
                        {
                            price: form.price,
                            rrp: form.rrp,
                            unit: form.unit
                        }
                    )
                } catch (err: any) {
                    // Save error for this productId
                    rowErrors.value[productId] =
                        err.response?.data?.errors ?? err.message
                }
            })
        )

        // Close modal only if no errors
        if (Object.keys(rowErrors.value).length === 0) {
            isOpenModalEditProducts.value = false
            router.reload({ preserveScroll: true })
            key.value = ulid()
        } else {
            console.warn("Some products failed to update", rowErrors.value)
        }
    } catch (error) {
        console.error("Unexpected bulk save failure", error)
    } finally {
        loadingSave.value = false
    }
} */


const onSaveEditBulkProduct = async () => {
    loadingSave.value = true
    rowErrors.value = {} // reset

    try {
        // Payload sekali request
        const payload: Record<string, any> = {}
        compSelectedProductsId.value.forEach((productId) => {
            payload[productId] = {
                price: form.price,
                rrp: form.rrp,
                unit: form.unit,
            }
        })

        await router.patch(
            route("grp.models.product.bulk_update"),
            payload,
            {
                preserveScroll: true,
                onError: (errors) => {
                    rowErrors.value = errors
                },
                onSuccess: () => {
                    isOpenModalEditProducts.value = false
                    key.value = ulid()
                },
            }
        )
    } catch (error) {
        console.error("Unexpected bulk save failure", error)
    } finally {
        loadingSave.value = false
    }
}

const onCancelEditBulkProduct = () => {
    isOpenModalEditProducts.value = false
    rowErrors.value = {}
    selectedProductsId.value = {}
    router.reload({ preserveScroll: true })
}
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="compSelectedProductsId.length > 0" @click="() => isOpenModalEditProducts = true"
                type="tertiary" :icon="faPencil" label="Edit Products" />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="props.tabs.navigation" @update:tab="handleTabUpdate" />

    <component :is="component" :key="currentTab + key" :tab="currentTab" :data="props[currentTab]" 
        :isCheckboxProducts="props.editable_table" :editable_table="props.editable_table"
        @selectedRow="(productsId: Record<string, boolean>) => selectedProductsId = productsId" />

    <!-- PrimeVue Dialog Modal -->
    <Dialog :header="trans('Edit Selected Products')" v-model:visible="isOpenModalEditProducts" :modal="true"
        :closable="true" :style="{ width: '500px' }">
        <div class="px-2 space-y-4">
            <!-- Form fields -->
            <form class="space-y-3">
                <!-- Grid for Price & RRP -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm" for="price">Price</label>
                        <InputNumber v-model="form.price" mode="currency" :currency="currencies?.code" :step="0.25" showButtons
                            button-layout="horizontal" inputClass="w-full text-xs">
                            <template #incrementbuttonicon>
                                <FontAwesomeIcon :icon="faPlus" />
                            </template>
                            <template #decrementbuttonicon>
                                <FontAwesomeIcon :icon="faMinus" />
                            </template>
                        </InputNumber>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm" for="rrp">RRP</label>
                        <InputNumber v-model="form.rrp" mode="currency" :currency="currencies?.code" :step="0.25" showButtons
                            button-layout="horizontal" inputClass="w-full text-xs">
                            <template #incrementbuttonicon>
                                <FontAwesomeIcon :icon="faPlus" />
                            </template>
                            <template #decrementbuttonicon>
                                <FontAwesomeIcon :icon="faMinus" />
                            </template>
                        </InputNumber>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm" for="unit">Unit</label>
                    <PureInput v-model="form.unit" />
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <Button type="tertiary" label="Cancel" @click="onCancelEditBulkProduct" />
                    <Button type="save" @click="onSaveEditBulkProduct" :loading="loadingSave"/>
                </div>
            </form>
        </div>
    </Dialog>


</template>
