<script setup lang='ts'>
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import SelectButton from 'primevue/selectbutton'
import DataView from 'primevue/dataview'
import IconField from 'primevue/iconfield'
import { FilterMatchMode } from '@primevue/core/api'
import { onMounted, ref } from 'vue'
import { useLocaleStore } from '@/Stores/locale'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSearch, faThLarge, faListUl, faStar as falStar } from '@fal'
import { faStar } from '@fas'
import Select from 'primevue/select'
import { library } from '@fortawesome/fontawesome-svg-core'
import { notify } from '@kyvg/vue3-notification'
import Modal from '@/Components/Utils/Modal.vue'
import {capitalize} from "@/Composables/capitalize";
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import error from "@/Pages/Iris/Errors/Error.vue";

library.add(faSearch, faThLarge, faListUl, faStar, falStar)

declare global {
    interface Window {
        sessionToken: string; // or the correct type if it's not a string
    }
}

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes,
    user: {}
    shop: string
    showIntro: boolean
    routes: {
        products: routeType
        store_product: routeType
        get_started: routeType
    },
    products: object
    // token: string
    // token_request: string
}>()

console.log('token', props)
// const xxToken = Object.keys(props.token)[1].match(/login_pupil_([a-f0-9]+)/)?.[1]
const locale = useLocaleStore()

const isModalGetStarted = ref(props.showIntro)

const filters = ref({
    'global': {value: null, matchMode: FilterMatchMode.CONTAINS},
})

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'INSTOCK':
            return 'success';

        case 'LOWSTOCK':
            return 'warn';

        case 'OUTOFSTOCK':
            return 'danger';

        default:
            return null;
    }
}

// Fetch: product
const realProducts = ref([])
onMounted(async () => {

    const xxx = window.Echo.join(`shopify.upload-product.${props.user?.id}`).
    listen('.action-progress', (e) => {
        console.log('xxxxxxxxxxxxxx', e)

    })
    console.log('Websocket:', xxx)


/*    setTimeout(async () => {
        console.log('500 window sessionToken', window.sessionToken)
        try {
            const { data } = await axios.get(route(props.routes.products.name, props.routes.products.parameters),
                {
                    headers: {
                        Authorization: `Bearer ${window.sessionToken}`,
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                }
            )

            realProducts.value = data.data
            // console.log('aaa', realProducts.value)
        } catch (error) {
            console.error('error', error)
        }
    }, 500)*/



})


// Selected product
const isLoadingSubmit = ref(false)
const selectedProducts = ref([])
const isSelected = (id: number) => {
    return selectedProducts.value.some(item => item.id === id);
}
const onSubmitProduct = () => {
    router.post(
        route(props.routes.store_product.name, props.routes.store_product.parameters),
        {
            products: selectedProducts.value.map(sel => sel.id)
        },
        {
            headers: {
                Authorization: `Bearer ${window.sessionToken}`,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            onStart: () => {
                isLoadingSubmit.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans('Success'),
                    text: trans('Successfully add') + ` ${selectedProducts.value.length} ` + trans('products'),
                    type: 'success',
                })
                selectedProducts.value = []
            },
            onError: () => {
                notify({
                    title: trans('Failed'),
                    text: trans('Something went wrong. Try again.'),
                    type: 'error',
                })
            },
            onFinish: () => {
                isLoadingSubmit.value = false
            }
        }
    )
}


const productView = ref('list')
const optionsView = [
    {
        id: 1,
        label: trans('Grid'),
        value: 'grid',
        icon: 'fal fa-th-large'
    },
    {
        id: 2,
        label: trans('List'),
        value: 'list',
        icon: 'fal fa-list-ul'
    }
]


const toggleItem = (id) => {
    const index = selectedProducts.value.findIndex(item => item.id === id);
    if (index !== -1) {
        // If item is found, remove it
        selectedProducts.value.splice(index, 1)
    } else {
        // If item is not found, add it
        selectedProducts.value.push({id: id})
    }
}

const onChangeDisplay = (type: string) => {
    if (productView.value == type) return
    productView.value = type
}

// View: Grid
const sortKey = ref()
const sortOrder = ref()
const sortField = ref()
const gridSortOptions = ref([
    {label: 'Price High to Low', value: '!price'},
    {label: 'Price Low to High', value: 'price'},
    {label: 'Alphabetically a-z', value: 'name'},
    {label: 'Alphabetically z-a', value: '!name'},
])
const onSortChange = (event) => {
    const value = event.value.value;
    const sortValue = event.value;

    if (value.indexOf('!') === 0) {
        sortOrder.value = -1;
        sortField.value = value.substring(1, value.length);
        sortKey.value = sortValue;
    }
    else {
        sortOrder.value = 1;
        sortField.value = value;
        sortKey.value = sortValue;
    }
}


const onClickGetStarted = () => {
    isModalGetStarted.value = false

    router[props.routes.get_started.method || 'post'](route(props.routes.get_started.name, props.routes.get_started.parameters), {

    }, {
        headers: {
            Authorization: `Bearer ${window.sessionToken}`
        },
        preserveState: true,
        onError: () => {
            console.error('error get started: ', error)
        }
    })
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-8">
        <!-- <pre>{{ props }}</pre> -->
        <!-- <h1>Hello from Vue Dashboard!</h1> -->
        <h4 class="font-bold text-2xl mb-3">Here you can add our Aw-Dropship products automatically to your shop 😲</h4>

        <!-- Select: Grid and List -->
        <div class="flex justify-end gap-x-3 mb-2">
            <SelectButton :modelValue="productView" @update:modelValue="(e: string) => onChangeDisplay(e)" :allowEmpty="false" :options="optionsView" optionValue="value" dataKey="value" aria-labelledby="custom">
                <template #option="{ option }">
                    <FontAwesomeIcon :icon='option.icon' class='' fixed-width aria-hidden='true' />
                </template>
            </SelectButton>

            <Button @click="() => onSubmitProduct()" :key="'buttonSubmit' + isLoadingSubmit" :loading="isLoadingSubmit" label="Add product" icon="fal fa-plus" :disabled="!selectedProducts.length" type="black" />
        </div>

        <div class="bg-stone-100 overflow-hidden rounded-2xl border border-stone-300">
            <DataTable v-if="productView === 'list'" ref="_dt"
                       v-model:selection="selectedProducts"
                       :value="products.data"
                       dataKey="id"
                       selectionMode="multiple"
                       :paginator="true"
                       :rows="20"
                       :filters="filters"
                       scrollable
                       paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                       :rowsPerPageOptions="[5, 10, 20, 40]"
                       currentPageReportTemplate="Showing {first} to {last} of {totalRecords} products">
                <template #header headerStyle="background: #ff0000">
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <IconField>
                            <InputIcon>
                                <FontAwesomeIcon icon='fal fa-search' class='' fixed-width aria-hidden='true' />
                            </InputIcon>
                            <InputText v-model="filters['global'].value" placeholder="Search..." />
                        </IconField>
                    </div>
                </template>

                <Column selectionMode="multiple" style="width: 3rem" :exportable="false" frozen ></Column>

                <Column field="code" header="Code" sortable style="min-width: 12rem"></Column>

                <Column field="name" header="Name" sortable style="min-width: 16rem" frozen ></Column>

                <Column field="price" header="Price" sortable style="min-width: 8rem">
                    <template #body="{ data }">
                        {{ locale.currencyFormat('usd', data.price) }}
                    </template>
                </Column>

                <Column field="state" header="State" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.state.tooltip" />
                    </template>
                </Column>

                <!-- <Column :exportable="false" style="min-width: 12rem">
                    <template #body="slotProps">
                        <div class="flex gap-x-1">
                            <Button type="edit" class="mr-2" @click="editProduct(slotProps.data)" />
                            <Button type="delete" outlined rounded severity="danger" @click="confirmDeleteProduct(slotProps.data)" />
                        </div>
                    </template>
                </Column> -->
            </DataTable>

            <!-- View: Grid -->
            <DataView v-else :value="products" paginator :rows="12" :sortOrder :sortField>
                <template #header>
                    <Select v-model="sortKey" :options="gridSortOptions" optionLabel="label" placeholder="Sort By Price" @change="onSortChange($event)" />
                </template>

                <template #list="{ items }">
                    <div class="p-4 grid grid-cols-12 gap-4">
                        <div v-for="(item, index) in items" :key="index"
                             @click="() => toggleItem(item.id)"
                             class="cursor-pointer h-full border rounded-lg flex flex-col col-span-12 sm:col-span-6 lg:col-span-3"
                             :class="[isSelected(item.id) ? 'bg-stone-200 ring-1 ring-stone-500' : 'hover:bg-stone-100']"
                        >
                            <!-- == {{ isSelected(item.id) }} == -->
                            <div class="relative flex justify-center rounded">
                                <img class="rounded w-full" :src="`https://primefaces.org/cdn/primevue/images/product/gaming-set.jpg`" :alt="item.name" style="max-width: 300px"/>
                                <div class="absolute top-1.5 left-2">
                                    <div class="capitalize text-xs inline-flex items-center gap-x-1 rounded select-none px-1.5 py-0.5 w-fit font-medium bg-emerald-100 hover:bg-emerald-200 border border-emerald-200 text-emerald-500" :theme="13">
                                        {{ item.state }}
                                    </div>
                                </div>
                                <div class="absolute top-1.5 right-2">
                                    <input :checked="isSelected(item.id)" name="checkboxProduct" type="checkbox" class="cursor-pointer h-5 w-5  rounded border-stone-300 text-stone-800 shadow-sm focus:ring-0 focus:outline-none">
                                </div>
                            </div>
                            <div class="py-4 px-6 h-full flex flex-col justify-between">
                                <div class="flex flex-row justify-between items-start gap-2">
                                    <div>
                                        <span class="text-stone-500 text-sm">{{ item.code }}</span>
                                        <div class="text-lg font-medium">{{ item.name }}</div>
                                    </div>
                                </div>

                                <!-- Section: Price -->
                                <div class="flex justify-between mt-6">
                                    <span class="text-2xl font-semibold">${{ item.price }}</span>

                                    <div class="p-1" style="border-radius: 30px">
                                        <div class="flex items-center gap-2 justify-center py-1 px-2" style="border-radius: 30px; box-shadow: 0 1px 2px 0px rgba(0, 0, 0, 0.04), 0px 1px 2px 0px rgba(0, 0, 0, 0.06)">
                                            <span class="font-medium text-sm">{{ item.rating || 0 }}</span>
                                            <!-- <i class="pi pi-star-fill "></i> -->
                                            <FontAwesomeIcon v-if="item.rating > 0" icon='fas fa-star' class='text-yellow-500' fixed-width aria-hidden='true' />
                                            <FontAwesomeIcon v-else icon='fal fa-star' class='text-gray-500' fixed-width aria-hidden='true' />
                                        </div>
                                    </div>
                                    <!-- <div class="flex gap-2">
                                        <Button icon="pi pi-shopping-cart" label="Buy Now" :disabled="item.inventoryStatus === 'OUTOFSTOCK'" class="flex-auto whitespace-nowrap"></Button>
                                        <Button icon="pi pi-heart" outlined></Button>
                                    </div> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>
    </div>

    <Modal :isOpen="isModalGetStarted" width="w-[700px]">
        <div class="relative isolate overflow-hidden px-6 py-8 text-center sm:rounded-3xl sm:px-12">
            <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
                {{ trans(`Let's get started.`) }}
            </h2>
            <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-500">
                It's looks like this is the first time you integrate Shopify, let's have a look what you can do.
            </p>
            <div class="mt-10 flex items-center justify-center gap-x-6">
                <Button @click="() => onClickGetStarted()" type="black" size="l" label="Get started" />
            </div>
        </div>

    </Modal>
</template>
