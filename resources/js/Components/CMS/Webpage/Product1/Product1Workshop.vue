<script setup lang="ts">
import { faCube, faLink, faSeedling, faHeart } from "@fal"
import { faBox, faPlus, faVial } from "@far"
import { faChevronDown, faCircle, faStar } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref } from "vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { useLocaleStore } from '@/Stores/locale'
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import ProductContents from "./ProductContents.vue"

import btree from '@/../art/payment_service_providers/btree.svg'
import cash from '@/../art/payment_service_providers/cash.svg'
import checkout from '@/../art/payment_service_providers/checkout.svg'
import hokodo from '@/../art/payment_service_providers/hokodo.svg'
import pastpay from '@/../art/payment_service_providers/pastpay.svg'
import paypal from '@/../art/payment_service_providers/paypal.svg'
import sofort from '@/../art/payment_service_providers/sofort.svg'
import worldpay from '@/../art/payment_service_providers/worldpay.svg'
import xendit from '@/../art/payment_service_providers/xendit.svg'
import bank from '@/../art/payment_service_providers/bank.svg'
import accounts from '@/../art/payment_service_providers/accounts.svg'
import cond from '@/../art/payment_service_providers/cond.svg'

library.add(faCube, faLink)

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
}>()

const locale = useLocaleStore()
const isFavorite = ref(false)
const cancelToken = ref<Function | null>(null)
const product = ref({
    labels: ['Vegan', 'Handmade', 'Cruelty Free', 'Plastic Free'],
})

const toggleFavorite = () => {
    isFavorite.value = !isFavorite.value
}

const debounceTimer = ref(null)
const onDescriptionUpdate = (val) => {
    clearTimeout(debounceTimer.value)
    debounceTimer.value = setTimeout(() => {
        saveDescriptions(val)
    }, 5000)
}

const saveDescriptions = (value: string) => {
    if (cancelToken.value) cancelToken.value()
    router.patch(
        route("grp.models.product.update", { product: props.modelValue.product.id }),
        { description: value },
        {
            preserveScroll: false,
            onCancelToken: (token) => {
                cancelToken.value = token.cancel
            },
            onFinish: () => {
                cancelToken.value = null
            },
            onSuccess: () => { },
            onError: (error) => {
                notify({
                    title: trans('Something went wrong'),
                    text: error.message,
                    type: 'error',
                })
            }
        }
    )
}

const selectImage = (code: string) => {
    if (!code) return null
    switch (code) {
        case 'btree': return btree
        case 'cash': return cash
        case 'checkout': return checkout
        case 'hokodo': return hokodo
        case 'accounts': return accounts
        case 'cond': return cond
        case 'bank': return bank
        case 'pastpay': return pastpay
        case 'paypal': return paypal
        case 'sofort': return sofort
        case 'worldpay': return worldpay
        case 'xendit': return xendit
        default: return null
    }
}
</script>

<template>
    <!-- Desktop Layout -->
    <div id="app" class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 hidden sm:block">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <div class="col-span-7">
                <div class="flex justify-between mb-4 items-start">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-gray-900">{{ modelValue.product.name }}</h1>
                        <div class="flex flex-wrap gap-x-10 text-sm font-medium text-gray-600 mt-1 mb-1">
                            <div>Product code: {{ modelValue.product.code }}</div>
                            <div class="flex items-center gap-[1px]">
                                <FontAwesomeIcon :icon="faStar" class="text-[10px] text-yellow-400" v-for="n in 5"
                                    :key="n" />
                                <span class="ml-1 text-xs text-gray-500">41</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600 mb-4">
                            <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                :class="modelValue.product.stock === 'active' ? 'text-green-600' : 'text-red-600'" />
                            <span>{{ modelValue.product.stock > 0 ? `In Stock (${modelValue.product.stock})` : 'Out Of Stock' }}</span>
                        </div>
                    </div>
                    <div class="h-full flex items-start">
                        <FontAwesomeIcon :icon="faHeart" class="text-2xl cursor-pointer"
                            :class="{ 'text-red-500': isFavorite }" @click="toggleFavorite" />
                    </div>
                </div>
                <div class="py-1 w-full">
                    <ImageProducts :images="modelValue.product.images.data" />
                </div>
                <div class="flex gap-x-10 text-gray-400 mb-6 mt-4">
                    <div class="flex items-center gap-1 text-xs" v-for="label in product.labels" :key="label">
                        <FontAwesomeIcon :icon="faSeedling" class="text-sm" />
                        <span>{{ label }}</span>
                    </div>
                </div>
            </div>
            <div class="col-span-5 self-start">
                <div class="flex items-end border-b pb-3 mb-3">
                    <div class="text-gray-900 font-semibold text-5xl capitalize leading-none flex-grow min-w-0">
                        {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.price || 0) }}
                        <span class="text-sm text-gray-500 ml-2 whitespace-nowrap">({{ modelValue.product.units }}/{{
                            modelValue.product.unit }})</span>
                    </div>
                    <div class="text-xs text-gray-400 font-semibold text-right whitespace-nowrap pl-4">
                        <span>RRP: {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.rrp ||
                            0) }}</span>
                        <span>/{{ modelValue.product.unit }}</span>
                    </div>
                </div>
                <div class="flex gap-2 mb-6">
                    <button
                        class="flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 text-white rounded px-4 py-2 text-sm font-semibold w-[90%] transition">
                        <FontAwesomeIcon :icon="faPlus" class="text-base" />
                        Add to Portfolio
                    </button>
                    <button v-tooltip="'Buy sample'"
                        class="flex items-center justify-center border border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white rounded p-2 text-sm font-semibold w-[10%] transition">
                        <FontAwesomeIcon :icon="faVial" class="text-sm" />
                    </button>
                </div>
                <div class="flex items-center text-sm text-medium text-gray-500 mb-6">
                    <FontAwesomeIcon :icon="faBox" class="mr-3 text-xl" />
                    <span>Order 4 full carton</span>
                </div>
                <div class="text-xs font-medium text-gray-800 py-3">
                    <EditorV2 v-model="modelValue.product.description"
                        @update:model-value="(e) => onDescriptionUpdate(e)" />
                </div>
                <div class="mb-4 space-y-2">
                    <div
                        class="flex justify-between items-center gap-4 font-bold text-gray-800 py-1 border-gray-400 cursor-pointer">
                        Delivery Info
                        <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
                    </div>
                    <div
                        class="flex justify-between items-center gap-4 font-bold text-gray-800 py-1 border-t border-gray-400 cursor-pointer">
                        Return Policy
                        <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
                    </div>
                    <div class="items-center gap-3 border-t border-gray-400 font-bold text-gray-800 py-2">
                        Secure Payments:
                        <div class="flex flex-wrap items-center gap-6 border-gray-400 font-bold text-gray-800 py-2">
                            <img v-for="logo in modelValue.product.service_providers" :key="logo.code"
                                v-tooltip="logo.code" :src="selectImage(logo.code)" :alt="logo.code" class="h-4 px-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- <ProductContents :product="props.modelValue.product" /> -->
    </div>

    <!-- Mobile Layout -->
    <div class="block sm:hidden px-4 py-6 text-gray-800">
        <h2 class="text-xl font-bold mb-2">{{ modelValue.product.name }}</h2>
        <ImageProducts :images="modelValue.product.images.data" />
        <div class="flex justify-between items-start gap-4 mt-4">
            <!-- Price + Unit Info -->
            <div>
                <div class="text-lg font-semibold">
                    {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.price || 0) }}
                    <span class="text-xs text-gray-500 ml-1">
                        ({{ modelValue.product.units }}/{{ modelValue.product.unit }})
                    </span>
                </div>
                <div class="text-xs text-gray-400 font-semibold mt-1">
                    RRP: {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.rrp || 0) }}
                </div>
            </div>

            <!-- Favorite Icon -->
            <div class="mt-1">
                <FontAwesomeIcon :icon="faHeart" class="text-xl cursor-pointer transition-colors duration-300"
                    :class="{ 'text-red-500': isFavorite, 'text-gray-400 hover:text-red-500': !isFavorite }"
                    @click="toggleFavorite" />
            </div>
        </div>


        <div class="flex flex-wrap gap-2 mt-4">
            <div v-for="label in product.labels" :key="label" class="text-xs flex items-center gap-1 text-gray-500">
                <FontAwesomeIcon :icon="faSeedling" class="text-sm" />
                <span>{{ label }}</span>
            </div>
        </div>
        <div class="mt-6 flex flex-col gap-2">
            <button
                class="flex items-center justify-center gap-2 bg-gray-800 text-white rounded px-4 py-2 text-sm font-semibold transition w-full">
                <FontAwesomeIcon :icon="faPlus" />
                Add to Portfolio
            </button>
            <button
                class="flex items-center justify-center border border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white rounded p-2 text-sm font-semibold transition w-full">
                <FontAwesomeIcon :icon="faVial" />
                Buy Sample
            </button>
        </div>
        <div class="text-xs font-medium py-3">
            <EditorV2 v-model="modelValue.product.description" @update:model-value="(e) => onDescriptionUpdate(e)" />
        </div>
        <div class="mt-4">
            <div class="text-sm font-semibold mb-2">Secure Payments:</div>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in modelValue.product.service_providers" :key="logo.code" v-tooltip="logo.code"
                    :src="selectImage(logo.code)" :alt="logo.code" class="h-4" />
            </div>
        </div>

        <!-- <ProductContents :product="props.modelValue.product" /> -->
    </div>

</template>
