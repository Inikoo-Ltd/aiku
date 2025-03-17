<script setup lang='ts'>
import Password from 'primevue/password';
import { FilterMatchMode } from '@primevue/core/api'
import { onMounted, ref } from 'vue'
import { useLocaleStore } from '@/Stores/locale'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { faSearch, faThLarge, faListUl, faStar as falStar } from '@fal'
import { faStar } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faSearch, faThLarge, faListUl, faStar, falStar)

declare global {
    interface Window {
        sessionToken: string; // or the correct type if it's not a string
    }
}

const props = defineProps<{
    user: {}
    shop: string
    shopUrl: string
    showIntro: boolean
    shops: object
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

// Selected product
const isLoadingSubmit = ref(false)
const selectedProducts = ref([])
const isSelected = (id: number) => {
    return selectedProducts.value.some(item => item.id === id);
}

const onClickGetStarted = (id: number) => {
    isModalGetStarted.value = false

    router[props.routes.get_started.method || 'post'](route(props.routes.get_started.name, props.routes.get_started.parameters), {
        shop: id
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
const openWebsite = (url) => {
    window.open(url, '_blank');
};
</script>

<template>
    <div v-if="props.showIntro" class="relative isolate overflow-hidden px-6 py-8 text-center sm:rounded-3xl sm:px-12">
        <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
            {{ trans(`Let's get started.`) }}
        </h2>
        <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-500">
            It's looks like this is the first time you integrate Shopify, let's have a look what you can do.
        </p>
            <div class="mt-10 flex items-center justify-center gap-x-6">
                <div v-for="shop in props.shops">
                    <div class="flex flex-col p-4 border-gray-300 border rounded">
                        <img class="w-72 h-48 object-cover text-center" v-if="shop.name === 'AW Fulfilment'" src="https://i.ibb.co.com/CxTbCRf/undraw-factory-4d61.png" :alt="`${shop.name}`">
                        <img class="w-72 h-48 object-cover text-center" v-else src="https://i.ibb.co.com/9k4B20qk/undraw-financial-data-r0vs.png" :alt="`${shop.name}`">
                        <Button @click="() => onClickGetStarted(shop.id)" type="tertiary" size="l" :label="`Setup ${shop.name}`" />
                    </div>
                </div>
            </div>
    </div>
    <div v-else-if="props.shop" class="relative isolate overflow-hidden px-6 py-8 text-center sm:rounded-3xl sm:px-12">
        <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
            {{ trans(`Welcome to ${props.shop}!`) }}
        </h2>
        <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-500">
            You can manage your orders and products in our fulfilment website.
        </p>
        <div class="flex justify-center">
            <img class="w-1/2 h-1/2 object-cover" src="https://i.ibb.co.com/CxTbCRf/undraw-factory-4d61.png" :alt="`${props.shop}`">
        </div>
        <div class="mt-10 flex flex-col items-center justify-center gap-x-6">
            <Button @click="openWebsite(props.shopUrl)" type="black" size="l" :label="`Open ${props.shop}`" />
        </div>
    </div>
</template>
