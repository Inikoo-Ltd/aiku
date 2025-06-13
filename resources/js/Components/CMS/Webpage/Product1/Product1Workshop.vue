<script setup lang="ts">
import { faCube, faHeadSide, faLink, faSeedling, faHeart } from "@fal"
import { faBox, faPlus, faVial } from "@far"
import { faChevronDown, faCircle, faMedal, faStar } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref } from "vue"
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { useLocaleStore } from '@/Stores/locale'
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { ConfirmPopupStyle } from "primevue"

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
    fieldValue: {}
}>()

const locale = useLocaleStore()
const orderQuantity = ref(0)
const isFavorite = ref(false)
const cancelToken = ref<Function | null>(null)
console.log('poprs', props)
const product = ref({
    labels: ['Vegan', 'Handmade', 'Cruelty Free', 'Plastic Free'],
    images: [
        'https://media.aiku.io/QE3VZW2yBT-RO4qWYuaa3ouc5WtTsBochW4VDw9sKJQ/bG9jYWw6Ly9tZWRpYS85US8wQy82MFIzMEMxSDc0VzMwQzlRL2E1OTg0NGJjLmpwZw.avif',
        'http://media.aiku.io/W2GnpCzQywkUCdpi-VUFxoLjTU7wRTWnjKjoTBr3ELQ/bG9jYWw6Ly9tZWRpYS85Ui8wQy82MFIzMEMxSDc0VzMwQzlSL2U0NWU0Mzc3LnBuZw.avif',
        'https://media.aiku.io/SpnYAGPOMaubSosNwaGX85QdiumKFscavJl76q-9twk/bG9jYWw6Ly9tZWRpYS85Uy8wQy82MFIzMEMxSDc0VzMwQzlTL2E0ZmYxMmJlLmpwZw.avif',
        'https://media.aiku.io/zM4hxmcha55ajZYIraLElQKQslHD8g8OZjqDNoquktg/bG9jYWw6Ly9tZWRpYS9IRy8wQy82MFIzMEMxSDc0VzMwQ0hHLzZiZTc2ZTJkLmpwZw.avif',
        'https://media.aiku.io/QE3VZW2yBT-RO4qWYuaa3ouc5WtTsBochW4VDw9sKJQ/bG9jYWw6Ly9tZWRpYS85US8wQy82MFIzMEMxSDc0VzMwQzlRL2E1OTg0NGJjLmpwZw.avif',
        'http://media.aiku.io/W2GnpCzQywkUCdpi-VUFxoLjTU7wRTWnjKjoTBr3ELQ/bG9jYWw6Ly9tZWRpYS85Ui8wQy82MFIzMEMxSDc0VzMwQzlSL2U0NWU0Mzc3LnBuZw.avif'
    ],
    paymentLogos: [
        { alt: 'Paypal', src: 'https://e7.pngegg.com/pngimages/292/77/png-clipart-paypal-logo-illustration-paypal-logo-icons-logos-emojis-tech-companies.png' },
        { alt: 'Visa', src: 'https://e7.pngegg.com/pngimages/687/457/png-clipart-visa-credit-card-logo-payment-mastercard-usa-visa-blue-company.png' },
        { alt: 'Mastercard', src: 'https://i.pinimg.com/736x/38/2f/0a/382f0a8cbcec2f9d791702ef4b151443.jpg' }
    ]
})

const toggleFavorite = () => {
    isFavorite.value = !isFavorite.value
}

const increaseQuantity = () => {
    orderQuantity.value++
}

const decreaseQuantity = () => {
    if (product.value.orderQuantity > 1) {
        product.value.orderQuantity--
    }
}

// src/data/faqs.ts
const productFaqs = [
    {
        question: 'How do they come packaged?',
        answer: 'These bath bombs come individually wrapped in recyclable packaging for freshness and protection during transit.'
    },
    {
        question: 'Are these bath bombs safe for sensitive skin?',
        answer: 'Yes, they are made with skin-friendly ingredients and free from harsh chemicals, but a patch test is always recommended.'
    },
    {
        question: 'Can I use these bath bombs in a Jacuzzi?',
        answer: 'We do not recommend using bath bombs in Jacuzzis as they may interfere with the jets or filter systems.'
    },
    {
        question: 'What is the shelf life of the bath bombs?',
        answer: 'Our bath bombs have a shelf life of up to 12 months when stored in a cool, dry place.'
    }
]

const debounceTimer = ref(null)
const onDescriptionUpdate = (val) => {
    // Clear previous timer
    clearTimeout(debounceTimer.value)

    // Start new 5-second timer
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
            onSuccess: (e) => { },
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
        case 'btree':
            return btree
        case 'cash':
            return cash
        case 'checkout':
            return checkout
        case 'hokodo':
            return hokodo
        case 'accounts':
            return accounts
        case 'cond':
            return cond
        case 'bank':
            return bank
        case 'pastpay':
            return pastpay
        case 'paypal':
            return paypal
        case 'sofort':
            return sofort
        case 'worldpay':
            return worldpay
        case 'xendit':
            return xendit
        default:
            return null
    }
}


const productSpec = "Lorem Ipsum s been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not onheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum."

console.log(props)
</script>

<template>
    <div id="app" class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6">
        <div class="grid grid-cols-12 gap-x-10 mb-12">
            <!-- Left Column (7/12) -->
            <div class="col-span-7">
                <!-- Informasi Produk -->
                <div class="flex justify-between mb-4 items-start">
                    <div class="w-full">
                        <!-- Product Name -->
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ modelValue.product.name }}
                        </h1>

                        <!-- Code & Rating -->
                        <div class="flex flex-wrap gap-x-10 text-sm font-medium text-gray-600 mt-1 mb-1">
                            <div>
                                Product code: {{ modelValue.product.code }}
                            </div>
                            <div class="flex items-center gap-[1px]">
                                <FontAwesomeIcon :icon="faStar" class="text-[10px] text-yellow-400" v-for="n in 5"
                                    :key="n" />
                                <span class="ml-1 text-xs text-gray-500">41</span>
                            </div>
                        </div>

                        <!-- Stock Status -->
                        <div class="flex items-center gap-2 text-sm text-gray-600 mb-4">
                            <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                :class="modelValue.product.stock === 'active' ? 'text-green-600' : 'text-red-600'" />
                            <span>
                                {{ modelValue.product.stock > 0 ? `In Stock (${modelValue.product.stock})` : 'Out Of Stock' }}
                            </span>
                        </div>
                    </div>


                    <!-- Favorit -->
                    <div class="h-full flex items-start">
                        <FontAwesomeIcon :icon="faHeart" class="text-2xl cursor-pointer"
                            :class="{ 'text-red-500': isFavorite }" @click="toggleFavorite" />
                    </div>
                </div>

                <!-- Gambar Produk -->
                <div class="py-1 w-full">
                    <ImageProducts :images="modelValue.product.images.data" />
                </div>

                <!-- Label -->
                <div class="flex gap-x-10 text-gray-400 mb-6 mt-4">
                    <div class="flex items-center gap-1 text-xs" v-for="label in product.labels" :key="label">
                        <FontAwesomeIcon :icon="faSeedling" class="text-sm" />
                        <span>{{ label }}</span>
                    </div>
                </div>

                <!-- Spesifikasi Produk -->
                <!-- Wrapper -->
<div class="max-w-md w-full space-y-4">

    <!-- Spesifikasi Produk -->
    <Disclosure v-slot="{ open }">
        <DisclosureButton
            class="w-full mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center">
            Product Specification & Documentation
            <FontAwesomeIcon
                :icon="faChevronDown"
                class="text-sm text-gray-500 transform transition-transform duration-200"
                :class="{ 'rotate-180': open }"
            />
        </DisclosureButton>
        <DisclosurePanel class="text-sm text-gray-600">
            <p>{{ productSpec }}</p>
        </DisclosurePanel>
    </Disclosure>

    <!-- FAQ -->
    <div>
        <div class="text-sm text-gray-500 mb-1 font-semibold">Frequently Asked Questions (FAQs)</div>
        <div class="space-y-2">
            <Disclosure v-for="(faq, i) of productFaqs" :key="i" v-slot="{ open }">
                <DisclosureButton
                    class="w-full py-1 border-b border-gray-400 font-bold text-gray-800 flex justify-between items-center">
                    {{ faq.question }}
                    <FontAwesomeIcon
                        :icon="faChevronDown"
                        class="text-sm text-gray-500 transform transition-transform duration-200"
                        :class="{ 'rotate-180': open }"
                    />
                </DisclosureButton>
                <DisclosurePanel class="text-sm text-gray-600 py-2">
                    <p>{{ faq.answer }}</p>
                </DisclosurePanel>
            </Disclosure>
        </div>
    </div>

    <!-- Review -->
    <div class="flex items-center justify-between font-bold cursor-pointer border-t pt-4 border-gray-300">
        <div>Customer Reviews</div>
        <div class="flex items-center gap-[1px]">
            <FontAwesomeIcon
                :icon="faStar"
                class="text-[9px] text-gray-600"
                v-for="n in 5"
                :key="'star-' + n"
            />
            <span class="ml-1 font-normal text-xs">{{ 31 }}</span>
        </div>
        <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
    </div>

</div>
</div>


            <!-- Right Column (5/12) -->
            <div class="col-span-5 self-start">
                <!-- Harga -->
                <div class="flex items-end border-b pb-3 mb-3">
                    <!-- Harga Saat Ini -->
                    <div class="text-gray-900 font-semibold text-5xl capitalize leading-none flex-grow min-w-0">
                        {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.price || 0) }}
                        <span class="text-sm text-gray-500 ml-2 whitespace-nowrap">
                            ({{ modelValue.product.units }}/{{ modelValue.product.unit }})
                        </span>
                    </div>

                    <!-- Harga RRP -->
                    <div class="text-xs text-gray-400 font-semibold text-right whitespace-nowrap pl-4">
                        <span>RRP: {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.rrp ||
                            0) }}</span>
                        <span>/{{ modelValue.product.unit }}</span>
                    </div>
                </div>



                <div class="flex gap-2 mb-6">
                    <!-- Add to Portfolio (90%) -->
                    <button
                        class="flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 text-white rounded px-4 py-2 text-sm font-semibold w-[90%] transition">
                        <FontAwesomeIcon :icon="faPlus" class="text-base" />
                        Add to Portfolio
                    </button>

                    <!-- Buy a Sample (10%) -->
                    <button v-tooltip="'Buy  sample'"
                        class="flex items-center justify-center border border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white rounded p-2 text-sm font-semibold w-[10%] transition">
                        <FontAwesomeIcon :icon="faVial" class="text-sm" />
                    </button>
                </div>



                <!-- Keterangan -->
                <div class="flex items-center text-sm text-medium text-gray-500 mb-6">
                    <FontAwesomeIcon :icon="faBox" class="mr-3 text-xl" />
                    <span>Order 4 full carton</span>
                </div>

                <!-- Deskripsi -->
                <div class="text-xs font-medium text-gray-800 py-3">
                    <EditorV2 v-model="modelValue.product.description"
                        @update:model-value="(e) => onDescriptionUpdate(e)" />
                </div>

                <!-- Informasi Tambahan -->
                <div class="mb-4 space-y-2">
                    <div
                        class="flex justify-between items-center gap-4 font-bold text-gray-800 py-1  border-gray-400 cursor-pointer">
                        Delivery Info
                        <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
                    </div>

                    <div
                        class="flex justify-between items-center gap-4 font-bold text-gray-800 py-1 border-t border-gray-400 cursor-pointer">
                        Return Policy
                        <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
                    </div>

                    <!-- Logo Pembayaran -->
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

    </div>
</template>
