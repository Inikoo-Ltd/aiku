<script setup lang="ts">
import { ref, onMounted, inject, watch } from 'vue'
import { FilterMatchMode } from '@primevue/core/api'
import { Button as ButtonPrime, Column, DataTable, Dialog, FileUpload, FloatLabel, IconField, InputIcon, InputNumber, InputText, MultiSelect, Popover, RadioButton, Rating, Select, Skeleton, Tag, Textarea, Toolbar } from 'primevue'
import axios from 'axios'

import PureCheckbox from '../Pure/PureCheckbox.vue'
import Image from '@/Components/Image.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch, faColumns } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '../Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'

import Editor from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'
import { set, get } from 'lodash-es'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { router } from '@inertiajs/vue3'
import ButtonWithLink from '../Elements/Buttons/ButtonWithLink.vue'
import PureInput from '../Pure/PureInput.vue'

library.add(faSearch, faColumns)
// import { useToast } from 'primevue/usetoast'
// import { ProductService } from '@/service/ProductService'

// onMounted(() => {
//     ProductService.getProducts().then((data) => (products.value = data))
// })

const layout = inject('layout', layoutStructure)

const dt = ref()
const selectedProducts = ref()
const filters = ref({
    'global': { value: null, matchMode: FilterMatchMode.CONTAINS },
})
const exportCSV = () => {
    dt.value.exportCSV()
}

const productsList = ref([])
const productsToCompare = ref([])
const isLoadingFetch = ref(false)
onMounted(async () => {

    const parsedQueryIdToArray = Array.isArray(route().params.id)
        ? route().params.id.map(Number)
        : route().params.id
            ? Object.values(route().params.id)
            : []


    try {
        isLoadingFetch.value = true
        const response = await axios.post(
            route(
                'grp.masters.master_shops.show.bulk-edit.selected_list',
                {
                    masterShop: route().params.masterShop
                }
            ),
            { 
                data: parsedQueryIdToArray
            }
        )

        if (response.status !== 200) {
            
        }
        
        console.log('grp.json.cached.product_list', response.data)
        productsList.value = response.data
        productsToCompare.value = response.data
    } catch (error: any) {
        
        console.log('zzzzzzzzzzzzzzz', error)
    } finally {
        isLoadingFetch.value = false
    }
})

const familiesList = ref<{}[] | null>(null)
const fetchFamilies = async (shop_id: number, family_data?: {}) => {
    try {
        const response = await axios.get(
            route(
                'grp.json.master-family.all-master-family',
                {
                    masterShop: route().params.masterShop
                }
            )
        )
        console.log('response', response)

        if (response.status !== 200) {
            
        }

        familiesList.value = response.data?.data

        const families = response.data?.data || []
        if (family_data?.id && !families.find(f => f.id === family_data.id)) {
            families.push(family_data)
        }
        familiesList.value = families
    } catch (error: any) {
        
        console.log('fetchFamilies', error)
    }
}


const rowClass = (xxx: any) => {
    // const isCompared = productsToCompare.value.some((p: any) => p.id === xxx.id)
    // const theProduct = productsToCompare.value.find(prod => prod.id === xxx.id)
    
    // if (theProduct === xxx) {
    //     return ['!bg-yellow-200']
    // } else {
        return []
    // }
}


// Section: multiselect columns selector
const selectedColumns = ref([ 'name', 'image', 'description', 'is_for_sale', 'price', 'units', 'unit', 'gross_weight', 'family_id', ])
const groupedColumnList = ref([
    {
        label: 'General',
        items: [
            { label: 'Name', value: 'name', disabled: true },
            { label: 'Image', value: 'image' },
            { label: 'Description', value: 'description' },
            { label: 'Is For Sale?', value: 'is_for_sale' }
        ]
    },
    {
        label: 'Pricing',
        items: [
            { label: 'Price', value: 'price' },
        ]
    },
    {
        label: 'Uniting',
        items: [
            // { label: 'Units', value: 'units' },
            { label: 'Unit', value: 'unit' }
        ]
    },
    {
        label: 'Shipping',
        items: [
            { label: 'Gross Weight', value: 'gross_weight' },
        ]
    },
    {
        label: 'Ancestor',
        items: [
            { label: 'Family', value: 'family_id' },
        ]
    }
])
watch(selectedColumns, (e) => {  // To avoid 'name' to be unselected
    if (e.includes('name')) {

    } else {
        selectedColumns.value.push('name')
    }
})

// Section: Submit data
const isLoadingSave = ref(false)
const onSave = async () => {
    console.log('productsList.value', productsList.value)
    try {
        isLoadingSave.value = true
        const response = await axios.post(
            route(
                'grp.masters.master_shops.show.bulk-edit.update',
                {
                    masterShop: route().params.masterShop
                }
            ),
            { data: productsList.value }
        )
        if (response.status !== 200) {
            
        }

        console.log('Response axios:', response.data)

        notify({
            title: trans("Successfully updated selected products!"),
            text: trans("Changes might take some times before being applied fully. Please wait a few seconds"),
            type: "success",
        })
    } catch (error: any) {
        // console.log('error axios', error)
        const errorBagUnique = error.response.data.errors ? new Set(Object.values(error.response.data.errors).flat()) : [];
        notify({
            title: trans("Something went wrong"),
            data: {
                html: errorBagUnique ? [...errorBagUnique].join('<br>') : trans("Please try again or contact administrator"),
            },
            type: 'error',
            duration: 5000,
        })
    } finally {
        isLoadingSave.value = false
    }
}


// Section: Description edit
const selectedRowToEdit = ref(null)
const _popoverDescription = ref(null)
const toggleDescription = (event) => {
    _popoverDescription.value?.toggle(event);
}
</script>


<template>
    <div>
        <div class="card">
            <!-- <Toolbar class="">
                <template #start>
                    <ButtonPrime label="Delete" icon="pi pi-trash" severity="danger" variant="outlined"
                        :disabled="!selectedProducts || !selectedProducts.length" />
                </template>

                <template #end>
                    <FileUpload mode="basic" accept="image/*" :maxFileSize="1000000" label="Import" customUpload
                        chooseLabel="Import" class="mr-2" auto :chooseButtonProps="{ severity: 'secondary' }" />
                    <ButtonPrime label="Export" icon="pi pi-upload" severity="secondary" @click="exportCSV($event)" />
                </template>
            </Toolbar> -->

            <DataTable
                ref="dt"
                v-model:selection="selectedProducts"
                :value="productsList"
                dataKey="id"
                :paginator="true"
                :loading="isLoadingFetch"
                :rows="10"
                :rowClass="rowClass"
                :filters="filters"
                scrollable 
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25]"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords} products"
            >
                <template #header>
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <div class="flex gap-x-2">
                            <ButtonWithLink
                                v-if="layout.currentQuery?.from"
                                :url="layout.currentQuery?.from"
                                label="Go back"
                                type="cancel"
                                size="lg"
                            />
                            <IconField>
                                <InputIcon>
                                    <FontAwesomeIcon icon="fal fa-search" class="" fixed-width aria-hidden="true" />
                                </InputIcon>
                                <InputText v-model="filters['global'].value" placeholder="Search..." />
                            </IconField>
                        </div>

                        <div class="w-fit flex">
                            <div class="flex items-center justify-end">
                                <FloatLabel class="w-full md:w-52" variant="on">
                                    <MultiSelect
                                        v-model="selectedColumns"
                                        :options="groupedColumnList"
                                        optionLabel="label"
                                        optionValue="value"
                                        optionDisabled="disabled"
                                        filter
                                        class="w-full"
                                        optionGroupLabel="label"
                                        optionGroupChildren="items"
                                        display="comma"
                                        :maxSelectedLabels="2"
                                        placeholder="Select Columns"
                                        selectedItemsLabel="{0} columns"
                                    >
                                        <template #optiongroup="slotProps">
                                            <div class="flex items-center">
                                                <div>{{ slotProps.option.label }}</div>
                                            </div>
                                        </template>
                                    </MultiSelect>
                                    <label for="on_label">{{ trans("Selected columns") }}</label>
                                </FloatLabel>
                            </div>

                            <div class="h-full border-l border-gray-300 py-3 ml-3.5 pl-6 ">
                                <Button
                                    @click="() => onSave()"
                                    label="Save"
                                    icon="fas fa-save"
                                    size="lg"
                                    :loading="isLoadingSave"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column> -->

                <!-- Column: Name -->
                <Column v-if="selectedColumns.includes('name')" field="name" header="Name" frozen sortable style="min-width: 20rem" >
                    <template #body="slotProps">
                        <div class="text-xs italic opacity-70">
                            {{ slotProps.data.code }}
                        </div>
                        <div class="bg-white font-bold">
                            <PureInput
                                v-model="slotProps.data.name"
                                class="mt-1"
                            />
                        </div>
                    </template>
                </Column>

                <!-- Column: Image -->
                <Column v-if="selectedColumns.includes('image')" header="Image" xstyle="min-width: 10rem">
                    <template #body="slotProps">
                        <div class=" h-12 aspect-square overflow-hidden">
                            <Image
                                :src="slotProps.data.web_images.main.thumbnail"
                                
                            />
                        </div>
                    </template>
                </Column>

                <!-- Column: Description -->
                <Column v-if="selectedColumns.includes('description')" field="description" header="Description" style="min-width: 20rem">
                    <template #body="slotProps">
                        <div @click="(e) => (toggleDescription(e), selectedRowToEdit = slotProps.data)" class="h-16 overflow-hidden rounded border border-gray-300 px-2 py-1 text-gray-600">
                            <span v-if="slotProps.data.description" v-html="slotProps.data.description"></span>
                            <span v-else class="text-gray-400 italic">No description</span>
                        </div>
                        
                    </template>
                </Column>

                <!-- Column: Is For Sale -->
                <Column v-if="selectedColumns.includes('is_for_sale')" field="is_for_sale" header="Is For Sale" style="white-space: nowrap">
                    <template #body="slotProps">
                        <div class="flex justify-center">
                            <PureCheckbox
                                :modelValue="slotProps.data?.not_for_sale_from_trade_unit ? false : slotProps.data.is_for_sale"
                                @update:model-value="(e) => slotProps.data?.not_for_sale_from_trade_unit ? false : slotProps.data.is_for_sale = e"
                                fluid
                                :disabled="slotProps.data?.not_for_sale_from_trade_unit"
                                v-tooltip="slotProps.data?.not_for_sale_from_trade_unit ? 'Not editable, not for sale from trade unit' : ''"
                            />
                        </div>
                    </template>
                </Column>

                <!-- Column: Price -->
                <Column v-if="selectedColumns.includes('price')" field="price" header="Price" sortable style="min-width: 8rem">
                    <template #body="slotProps">
                        <InputNumber
                            v-model="slotProps.data.price"
                            @input="(e) => slotProps.data.price = e?.value ?? 0"
                            inputId="currency-us"
                            mode="currency"
                            :currency="'eur'"
                            inputClass="text-right"
                            :maxFractionDigits="2"
                            locale="en-US"
                            :min="0"
                            fluid
                        />
                    </template>
                </Column>
                
                <!-- Column: RPP -->
                <!-- <Column v-if="selectedColumns.includes('rrp')" field="rrp" header="RRP" sortable style="min-width: 8rem">
                    <template #body="slotProps">
                        <InputNumber
                            v-model="slotProps.data.rrp"
                            @input="(e) => slotProps.data.rrp = e?.value ?? 0"
                            inputId="currency-us"
                            mode="currency"
                            :currency="'eur'"
                            inputClass="text-right"
                            :maxFractionDigits="2"
                            locale="en-US"
                            :min="0"
                            fluid
                        />
                    </template>
                </Column> -->
                
                <!-- Column: Unit price -->
                <!-- <Column v-if="selectedColumns.includes('unit_price')" field="unit_price" header="Unit price" sortable style="min-width: 8rem">
                    <template #body="slotProps">
                        <InputNumber
                            v-model="slotProps.data.unit_price"
                            @input="(e) => slotProps.data.unit_price = e?.value ?? 0"
                            inputId="currency-us"
                            mode="currency"
                            :currency="'eur'"
                            inputClass="text-right"
                            :maxFractionDigits="2"
                            locale="en-US"
                            :min="0"
                            fluid
                        />
                    </template>
                </Column> -->

                <!-- Column: Gross Weight -->
                <Column v-if="selectedColumns.includes('gross_weight')" field="gross_weight" header="Gross Weight" sortable style="white-space: nowrap">
                    <template #body="slotProps">
                        <InputNumber
                            v-model="slotProps.data.gross_weight"
                            @input="(e) => slotProps.data.gross_weight = e?.value ?? 0"
                            :maxFractionDigits="2"
                            locale="en-US"
                            :min="0"
                            suffix=" gr"
                            inputClass="text-right"
                            fluid
                        />
                    </template>
                </Column>

                <!-- Column: Units -->
                <Column v-if="selectedColumns.includes('units')" field="units" header="Units" style="min-width: 6rem">
                    <template #body="slotProps">
                        <InputNumber
                            v-model="slotProps.data.units"
                            @input="(e) => slotProps.data.units = e?.value ?? 0"
                            :maxFractionDigits="2"
                            locale="en-US"
                            :min="0"
                            inputClass="text-right"
                            fluid
                        />
                    </template>
                </Column>

                <!-- Column: Unit -->
                <Column v-if="selectedColumns.includes('unit')" field="unit" header="Unit" style="min-width: 8rem">
                    <template #body="slotProps">
                        <InputText
                            v-model="slotProps.data.unit"
                            :maxFractionDigits="2"
                            locale="en-US"
                            :min="0"
                            inputClass="text-right"
                            fluid
                        />
                    </template>
                </Column>
                

                <!-- Column: Family -->
                <Column v-if="selectedColumns.includes('family_id')" field="family_id" header="Family" style="min-width: 10rem">
                    <template #body="{ data }">
                        <div class="w-full md:w-64">
                            <PureMultiselectInfiniteScroll
                                v-model="data.master_family_id"
                                :fetch-route="{
                                    name: 'grp.json.master-family.all-master-family',
                                    parameters: {
                                        masterShop: route().params.masterShop
                                    }
                                }"
                                :initOptions="data.master_family_data ? [data.master_family_data] : undefined"
                                :placeholder="trans('Select a family')"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <!-- <pre>{{ productsList }}</pre> -->

            <Popover ref="_popoverDescription">
                <div class="flex flex-col w-[40rem]">
                    <div class="mb-4 font-bold text-xl text-center text-balance">
                        {{ selectedRowToEdit?.name }}
                    </div>

                    <div class="text-sm italic text-gray-500">
                        {{ trans("Description") }}: 
                    </div>
                    
                    <Editor :modelValue="get(selectedRowToEdit, ['description'], '')" @update:modelValue="(e) => set(selectedRowToEdit, ['description'], e)" xuploadImageRoute="props.uploadRoutes">
                        <template #editor-content="{ editor }">
                            <div
                            class="editor-wrapper border-2 border-gray-300 rounded-lg p-3 shadow-xs transition-all duration-200 focus-within:border-blue-400"
                            :style="{ minHeight: `70px` }"
                            >
                                <EditorContent :editor="editor" class="editor-content" />
                            </div>
                        </template>
                    </Editor>
                </div>
            </Popover>
            
        </div>
    </div>
</template>

<style scoped lang="scss">
:deep(.p-datatable-header) {
    @apply py-0
}
</style>