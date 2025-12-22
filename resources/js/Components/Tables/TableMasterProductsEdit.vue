<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { FilterMatchMode } from '@primevue/core/api'
import { Button, Column, DataTable, Dialog, FileUpload, IconField, InputIcon, InputNumber, InputText, RadioButton, Rating, Select, Tag, Textarea, Toolbar } from 'primevue'
import axios from 'axios'
import PureTextarea from '../Pure/PureTextarea.vue'
import PureCheckbox from '../Pure/PureCheckbox.vue'
import Image from '@/Components/Image.vue'
// import { useToast } from 'primevue/usetoast'
// import { ProductService } from '@/service/ProductService'

// onMounted(() => {
//     ProductService.getProducts().then((data) => (products.value = data))
// })

// const toast = useToast()
const dt = ref()
const productsList = ref([])
const productDialog = ref(false)
const deleteProductDialog = ref(false)
const deleteProductsDialog = ref(false)
const product = ref({})
const selectedProducts = ref()
const filters = ref({
    'global': { value: null, matchMode: FilterMatchMode.CONTAINS },
})
const submitted = ref(false)
const openNew = () => {
    product.value = {}
    submitted.value = false
    productDialog.value = true
}
const findIndexById = (id) => {
    let index = -1
    for (let i = 0; i < productsList.value.length; i++) {
        if (productsList.value[i].id === id) {
            index = i
            break
        }
    }

    return index
}
const createId = () => {
    let id = ''
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    for (var i = 0; i < 5; i++) {
        id += chars.charAt(Math.floor(Math.random() * chars.length))
    }
    return id
}
const exportCSV = () => {
    dt.value.exportCSV()
}
const confirmDeleteSelected = () => {
    deleteProductsDialog.value = true
}

onMounted(async () => {
    try {
        const response = await axios.post(
            route(
                'grp.json.cached.product_list',
                {
                    cacheKey: 'zzz'
                }
            ),
            { data: [
                5445,
                50550
            ] }
        )

        if (response.status !== 200) {
            
        }

        console.log('grp.json.cached.product_list', response.data)
        productsList.value = response.data
    } catch (error: any) {
        
        console.log('zzzzzzzzzzzzzzz', error)
    }
})

const familiesList = ref<{}[] | null>(null)
const fetchFamilies = async (shop_id: number, family_data?: {}) => {
    try {
        const response = await axios.get(
            route(
                'grp.json.shop.families',
                {
                    shop: shop_id
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
</script>


<template>
    <div>
        <div class="card">
            <Toolbar class="mb-6">
                <template #start>
                    <Button label="New" icon="pi pi-plus" class="mr-2" @click="openNew" />
                    <Button label="Delete" icon="pi pi-trash" severity="danger" variant="outlined"
                        @click="confirmDeleteSelected" :disabled="!selectedProducts || !selectedProducts.length" />
                </template>

                <template #end>
                    <FileUpload mode="basic" accept="image/*" :maxFileSize="1000000" label="Import" customUpload
                        chooseLabel="Import" class="mr-2" auto :chooseButtonProps="{ severity: 'secondary' }" />
                    <Button label="Export" icon="pi pi-upload" severity="secondary" @click="exportCSV($event)" />
                </template>
            </Toolbar>

            <DataTable
                ref="dt"
                v-model:selection="selectedProducts"
                :value="productsList"
                dataKey="id"
                :paginator="true"
                :rows="10"
                :filters="filters"
                scrollable 
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25]"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords} products"
            >
                <template #header>
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <h4 class="m-0">Manage Products</h4>
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText v-model="filters['global'].value" placeholder="Search..." />
                        </IconField>
                    </div>
                </template>

                <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>

                <!-- Column: Name -->
                <Column field="name" header="Name" frozen sortable style="min-width: 16rem" >
                    <template #body="slotProps">
                        <div class="text-xs italic opacity-70">
                            {{ slotProps.data.code }}
                        </div>
                        <div class="bg-white font-bold">
                            {{ slotProps.data.name }}
                        </div>
                    </template>
                </Column>

                <!-- Column: Image -->
                <Column header="Image">
                    <template #body="slotProps">
                        <Image
                            :src="slotProps.data.web_images.main.thumbnail"
                            width="50"
                            height="50"
                        />
                    </template>
                </Column>
                <!-- Column: Description -->
                <Column field="description" header="Description" style="min-width: 20rem">
                    <template #body="slotProps">
                        <PureTextarea
                            v-model="slotProps.data.text"
                            inputId="currency-us"
                            fluid
                        />
                    </template>
                </Column>

                <!-- Column: Is For Sale -->
                <Column field="is_for_sale" header="Is For Sale" style="white-space: nowrap">
                    <template #body="slotProps">
                        <div class="flex justify-center">
                            <PureCheckbox
                                v-model="slotProps.data.is_for_sale"
                                fluid
                            />
                        </div>
                    </template>
                </Column>

                <!-- Column: Price -->
                <Column field="price" header="Price" sortable style="min-width: 8rem">
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
                <Column field="rrp" header="RRP" sortable style="min-width: 8rem">
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
                </Column>
                
                <!-- Column: Unit price -->
                <Column field="unit_price" header="Unit price" sortable style="min-width: 8rem">
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
                </Column>

                <!-- Column: Gross Weight -->
                <Column field="gross_weight" header="Gross Weight" sortable style="white-space: nowrap">
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
                <Column field="units" header="Units" style="min-width: 6rem">
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
                <Column field="unit" header="Unit" style="min-width: 8rem">
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
                <Column field="family_id" header="Family" sortable style="min-width: 10rem">
                    <template #body="{ data }">
                        <Select
                            v-model="data.family_id"
                            :options="familiesList?.length ? familiesList : [data.family_data]"
                            filter
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select a Family"
                            class="w-full md:w-56"
                            @show="() => fetchFamilies(data.shop_id, data.family_data)"
                            @hide="() => familiesList = null"
                        >
                            <template #value="slotProps">
                                <div v-if="slotProps.value" class="flex items-center">
                                    <!-- <img :alt="slotProps.value.label" src="https://primefaces.org/cdn/primevue/images/flag/flag_placeholder.png" :class="`mr-2 flag flag-${slotProps.value?.code?.toLowerCase()}`" style="width: 18px" /> -->
                                    <div>{{ (familiesList?.length ? familiesList : [data.family_data]).find(family => family.id === slotProps.value)?.name }}</div>
                                </div>

                                <span v-else>
                                    {{ slotProps.placeholder }}
                                </span>
                            </template>
                            
                            <template #option="slotProps">
                                <div class="flex items-center">
                                    <div>{{ (familiesList?.length ? familiesList : [data.family_data]).find(family => family.id === slotProps.option.id)?.name }}</div>
                                </div>
                            </template>
                        </Select>
                    </template>
                </Column>
            </DataTable>
            <pre>{{ productsList }}</pre>

        </div>
    </div>
</template>
