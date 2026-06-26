<script setup lang='ts'>
import DataTable from 'primevue/datatable'
import SelectButton from 'primevue/selectbutton'
import { FilterMatchMode } from '@primevue/core/api'
import { onMounted, ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSearch, faThLarge, faListUl, faStar as falStar } from '@fal'
import { faStar } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { notify } from '@kyvg/vue3-notification'
import Modal from '@/Components/Utils/Modal.vue'
import error from "@iris/Pages/Errors/Error.vue"
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


const isModalGetStarted = ref(props.showIntro)

const filters = ref({
    'global': {value: null, matchMode: FilterMatchMode.CONTAINS},
})



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

const onChangeDisplay = (type: string) => {
    if (productView.value == type) return
    productView.value = type
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
