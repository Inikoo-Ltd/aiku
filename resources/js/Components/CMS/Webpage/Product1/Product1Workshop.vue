<script setup lang="ts">
import { faCube, faHeadSide, faLink, faSeedling, faHeart } from "@fal"
import { faBox } from "@far"
import { faChevronDown, faCircle, faMedal, faStar } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref } from "vue"
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import ImageProducts from "./ImageProducts.vue"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { useLocaleStore } from '@/Stores/locale'
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"

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

const saveDescriptions = (value : string) => {
    if (cancelToken.value) cancelToken.value()
	router.patch(
		route("grp.models.product.update", { product : props.modelValue.product.id} ),
		{ description: value },
		{
            preserveScroll : false,
			onCancelToken: (token) => {
				cancelToken.value = token.cancel
			},
            onFinish: () => {
				cancelToken.value = null
			},
			onSuccess: (e) => {},
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


const productSpec = "Lorem Ipsum s been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not onheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum."

</script>

<template>
    <div id="app" class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6">
        <div class="grid grid-cols-5 gap-x-10 mb-12">
            <!-- Left Column (3/5) -->
            <div class="col-span-3">
                <div class="flex justify-between mb-6 items-center">
                    <div class="w-[90%]">
                        <h1 class="font-bold text-2xl">{{ modelValue.product.name }}</h1>

                        <div class="flex gap-x-10 text-gray-600 mt-1 mb-1 text-sm">
                            <div>Product code:{{ modelValue.product.code }}</div>
                            <div class="flex items-center gap-[1px]">
                                <FontAwesomeIcon :icon="faStar" class="text-[9px]" v-for="n in 5" :key="n" />
                                <span class="ml-1 text-xs">41</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mb-4">
                            <FontAwesomeIcon :icon="faCircle" class="text-sm" :class="modelValue.product.state == 'active' ? 'text-green-600' : 'text-red-600'" />
                            <span v-if="modelValue.product.stock > 0" class="text-gray-600 text-sm">In Stock ({{ modelValue.product.stock }})</span>
                            <span v-else class="text-gray-600 text-sm">Out Of Stock</span>
                        </div>
                    </div>

                    <div class="h-full flex items-center">
                        <FontAwesomeIcon :icon="faHeart" class="text-2xl cursor-pointer"
                            :class="{ 'text-red-500': isFavorite }" @click="toggleFavorite" />
                    </div>
                </div>

                <!-- Product Images -->
                <div class="py-1 w-full">
                    <ImageProducts :images="modelValue.product.images.data" />
                </div>

                <div class="my-3 text-sm text-gray-500">More Product information</div>

                <!-- Labels Section -->
                <div class="flex gap-x-10 text-gray-400 mb-6">
                    <div class="flex items-center gap-1 text-xs" v-for="label in product.labels" :key="label">
                        <FontAwesomeIcon :icon="faSeedling" class="text-sm" />
                        <span>{{ label }}</span>
                    </div>
                </div>

                <!-- Wrapper container with fixed width -->
                <div class="max-w-md cursor-pointer">
                    <Disclosure v-slot="{ open }">
                        <DisclosureButton
                            class="mb-1 border-b justify-between border-gray-400 font-bold text-gray-800 py-1 flex items-center gap-4 w-full">
                            Product Specification & Documentation
                            <FontAwesomeIcon :icon="faChevronDown"
                                class="text-sm text-gray-500 transform transition-transform duration-200"
                                :class="{ 'rotate-180': open }" />
                        </DisclosureButton>

                        <DisclosurePanel class="text-sm text-gray-600 ">
                            <!-- Kamu bisa isi detail spesifikasi dan dokumentasi di sini -->
                            <p>{{ productSpec }}</p>
                        </DisclosurePanel>
                    </Disclosure>


                    <div class="text-sm text-gray-500 my-3">Frequently Asked Questions (FAQs)</div>

                    <!-- FAQ Items with Disclosure -->
                    <div>
                        <Disclosure v-for="(faq, i) of productFaqs" :key="i" v-slot="{ open }">
                            <DisclosureButton
                                class="w-full py-1 border-b border-gray-400 font-bold text-gray-800 flex justify-between items-center gap-4">
                                {{ faq.question }}
                                <FontAwesomeIcon :icon="faChevronDown"
                                    class="text-sm text-gray-500 transition-transform duration-200"
                                    :class="{ 'rotate-180': open }" />
                            </DisclosureButton>
                            <DisclosurePanel class="text-sm text-gray-600 py-2">
                                <p>{{ faq.answer }}</p>
                            </DisclosurePanel>
                        </Disclosure>
                    </div>


                </div>

                <!-- Customer Reviews -->
                <div class="flex items-center  justfy-between gap-4 font-bold cursor-pointer ">
                    <div>Customer Reviews</div>
                    <div class="flex items-center gap-[1px]">
                        <FontAwesomeIcon :icon="faStar" class="text-[9px] text-gray-600" v-for="n in 5"
                            :key="'star-' + n" />
                        <span class="ml-1 font-normal text-xs">{{ 31 }}</span>
                    </div>
                    <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
                </div>
            </div>

            <!-- Right Column (2/5) -->
            <div class="col-span-2">
                <div class="mb-2 font-semibold text-2xl capitalize">
                    {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.price || 0) }}
                    ({{ modelValue.product.units }}/{{ modelValue.product.unit }})
                </div>
                <div class="mb-2 font-semibold text-2xl text-orange-500 capitalize">
                    {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.price || 0) }}
                    ({{ modelValue.product.units }}/{{ modelValue.product.unit }})
                </div>

                <div class="mb-8 flex items-center gap-2">
                    <FontAwesomeIcon :icon="faMedal" class="text-orange-500" />
                    <span :class="`bg-orange-500 text-white text-sm py-0.5 px-1 rounded`">
                        Member Price
                    </span>
                    <span class="text-xs underline cursor-pointer">Membership Info</span>
                </div>

                <div class="mb-8">
                    <div class="text-xs text-gray-500 mb-1">NOT A MEMBER?</div>
                    <div class="text-orange-500 text-xs w-8/12">Order 4 or more outers from this product family to benefit from lower price.</div>
                </div>

                <!-- Order Now Section -->
                <div class="flex gap-2 mb-6 items-center">
                    <div class="flex items-center gap-1 select-none cursor-pointer">
                        <div class="font-bold text-3xl leading-none" @click="decreaseQuantity">-</div>
                        <div
                            class="h-8 aspect-square border border-gray-400 flex items-center justify-center tabular-nums text-xl font-bold">
                            {{ orderQuantity }}
                        </div>
                        <div class="font-bold text-3xl leading-none" @click="increaseQuantity">+</div>
                    </div>
                    <button class="bg-gray-800 text-white rounded px-3 py-1 w-full h-8 text-center font-semibold">
                        Order Now
                    </button>
                </div>

                <div class="flex items-center text-xs text-gray-500 mb-6">
                    <FontAwesomeIcon :icon="faBox" class="mr-3 text-xl" />
                    <span>Order 4 full carton</span>
                </div>

                <div class="text-xs font-medium text-gray-800 py-3">
                    <EditorV2  v-model="modelValue.product.description" @update:model-value="(e)=>onDescriptionUpdate(e)" />

                    <!-- Buy Now Pay Later, Delivery Info, Return Policy -->
                    <div class="mb-4 space-y-2">
                        <div
                            class="flex justify-between items-center gap-4 font-bold text-gray-800 py-1 cursor-pointer border-gray-800">
                            <div class="flex items-center gap-4">
                                Buy Now Pay Later
                                <img src="https://cdn.prod.website-files.com/6660900e2837ec36d7ab4f69/66cccc3128fa6350a8266f72_PastPay-logo-dark-edge.png"
                                    alt="PastPay" class="h-3" />
                            </div>
                            <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
                        </div>

                        <div
                            class="flex justify-between items-center gap-4 font-bold text-gray-800 py-1 border-t border-gray-400 cursor-pointer">
                            Delivery Info
                            <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
                        </div>

                        <div
                            class="flex justify-between items-center gap-4 font-bold text-gray-800 py-1 border-t border-gray-400 cursor-pointer">
                            Return Policy
                            <FontAwesomeIcon :icon="faChevronDown" class="text-sm text-gray-500" />
                        </div>

                        <div class="flex items-center gap-3 border-t border-gray-400 font-bold text-gray-800 py-2">
                            Secure Payments:
                            <img v-for="logo in product.paymentLogos" :key="logo.alt" :src="logo.src" :alt="logo.alt"
                                class="h-4 px-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
