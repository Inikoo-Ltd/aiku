<script setup lang='ts'>
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import SelectButton from 'primevue/selectbutton'
import DataView from 'primevue/dataview'
import IconField from 'primevue/iconfield'
import Rating from 'primevue/rating'
import { FilterMatchMode } from '@primevue/core/api'
import { onMounted, ref } from 'vue'
import { useLocaleStore } from '@/Stores/locale'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSearch, faThLarge, faListUl, faStar as falStar } from '@fal'
import { faStar } from '@fas'
import Select from 'primevue/select'
import { library } from '@fortawesome/fontawesome-svg-core'
import Image from "@common/Components/Image.vue"
import { notify } from '@kyvg/vue3-notification'
import Modal from '@/Components/Utils/Modal.vue'
library.add(faSearch, faThLarge, faListUl, faStar, falStar)

declare global {
    interface Window {
        sessionToken: string; // or the correct type if it's not a string
    }
}

const props = defineProps<{
    user: {}
    shop: string
    showIntro: boolean
    routes: {
        products: routeType
        store_product: routeType
        get_started: routeType
    }
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

    const xxx = window.Echo.join(`shopify.upload-product.${props.user.id}`).
        listen('.action-progress', (e) => {
            console.log('xxxxxxxxxxxxxx', e)

    })
    console.log('Websocket:', xxx)


    setTimeout(async () => {
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
    }, 500)

    

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
                :value="realProducts"
                dataKey="id"
                selectionMode="multiple"
                :paginator="true"
                :rows="20"
                :filters="filters"
                scrollable
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 20, 40]"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords} products">
            </DataTable>

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
