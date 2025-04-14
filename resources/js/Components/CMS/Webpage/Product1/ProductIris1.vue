<script setup lang="ts">
import { ref } from "vue";
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/vue";
import {
    faCube,
    faLink,
    faStar,
    faCircle,
    faChevronDown,
    faChevronLeft,
    faChevronRight,
    faHeart,
    faSeedling,
    faHandPaper,
    faFish,
    faMedal,
    faSquare,
} from "@fortawesome/free-solid-svg-icons";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Image from "@/Components/Image.vue";

library.add(
    faCube,
    faLink,
    faStar,
    faCircle,
    faChevronDown,
    faChevronLeft,
    faChevronRight,
    faHeart,
    faSeedling,
    faHandPaper,
    faFish,
    faMedal,
    faSquare
);

const props = defineProps<{
    fieldValue: {}
	theme?: any
}>();

const tableData = [
    {
        label: "Origin",
        value: "Dummy",
    },
    {
        label: "Net weight",
        value: "Dummy",
    },
    {
        label: "Shipping weight",
        value: "Dummy",
    },
    {
        label: "Dimensions",
        value: "Dummy",
    },
    {
        label: "Barcode",
        value: "Dummy",
    },
    {
        label: "MSDS",
        value: "Dummy",
        url: "Dummy",
    },
    {
        label: "MSDS",
        value: "Dummy",
        url: "Dummy",
    },
    {
        label: "MSDS",
        value: "Dummy",
        url: "Dummy",
    },
];

const selectedProduct = ref(0);
const emits = defineEmits(["update:modelValue", "autoSave"]);
const dataProduct = ref({
    images: [
        "https://tailwindui.com/plus/img/ecommerce-images/product-page-01-product-shot-02.jpg",
        "https://tailwindui.com/plus/img/ecommerce-images/product-page-01-product-shot-02.jpg",
        "https://tailwindui.com/plus/img/ecommerce-images/product-page-01-product-shot-02.jpg",
        "https://tailwindui.com/plus/img/ecommerce-images/product-page-01-product-shot-02.jpg",
    ],
});
</script>

<template>
    <div id="app" class="mx-auto max-w-5xl px-4 py-4 text-gray-600">
        <div class="flex gap-x-10 mb-12">
            <div class="flex-1">
                <div class="font-bold text-2xl">{{ fieldValue.product.name }}</div>

                <div class="mb-1 flex gap-x-10">
                    <div class="text-sm">Product code:{{ fieldValue.product.code }}</div>
                    <div class="flex gap-x-[1px] items-center">
                        <FontAwesomeIcon icon="fas fa-star" class="text-[9px] text-gray-600" />
                        <FontAwesomeIcon icon="fas fa-star" class="text-[9px] text-gray-600" />
                        <FontAwesomeIcon icon="fas fa-star" class="text-[9px] text-gray-600" />
                        <FontAwesomeIcon icon="fas fa-star" class="text-[9px] text-gray-600" />
                        <FontAwesomeIcon icon="fas fa-star" class="text-[9px] text-gray-600" />
                        <span class="ml-1 text-xs">41</span>
                    </div>
                </div>

                <div class="mb-1 flex justify-between">
                    <div>
                        <FontAwesomeIcon icon="fas fa-circle" class="text-sm text-green-600" />
                        <span class="ml-1 text-sm">(41)</span>
                    </div>
                    <div>RRP: £{{fieldValue.product.price}}/Piece</div>
                </div>

                <!-- Images product -->
                <div class="grid grid-cols-5 mb-10 gap-x-2">
                    <div class="flex flex-col gap-y-1.5">
                        <div v-for="(product, idxProduct) in fieldValue.product.images"
                            @click="() => (selectedProduct = idxProduct)" class="aspect-square cursor-pointer"
                            :class="selectedProduct == idxProduct ? 'ring-2 ring-gray-400' : 'hover:ring-1 hover:ring-gray-300'">
                            <Image :imageCover="true" :src="product.source" :alt="`product Image ${idxProduct}`" />
                        </div>
                    </div>

                    <!-- Image large -->
                    <div class="relative col-span-4 aspect-square">
                        <FontAwesomeIcon
                            icon="absolute bottom-2 right-2 text-3xl far fa-heart text-gray-400 hover:text-gray-600 cursor-pointer" />
                        <Image :imageCover="true"  :src="fieldValue.product.images[selectedProduct].source" alt="product Image" />
                        <div v-if="selectedProduct != 0" @click="() => (selectedProduct = selectedProduct - 1)"
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer">
                            <FontAwesomeIcon icon="fas fa-chevron-left" class="text-2xl" />
                        </div>
                        <div v-if="selectedProduct != dataProduct.images.length - 1"
                            @click="() => (selectedProduct = selectedProduct + 1)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer">
                            <FontAwesomeIcon icon="fas fa-chevron-right" class="text-2xl" />
                        </div>
                    </div>
                </div>

                <!-- Section: Description -->
                <div class="space-y-4 mb-6">
                    <div class="text-xs text-gray-500">
                       {{ fieldValue.product.description }}
                    </div>
                </div>

                <!-- <div class="flex gap-x-10 text-gray-400 mb-6">
                    <div class="flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-seedling" class="text-sm" />
                        <div class="text-xs">Vegan</div>
                    </div>
                    <div class="flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-hand-paper" class="text-sm" />
                        <div class="text-xs">Handmade</div>
                    </div>
                    <div class="flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-fish" class="text-sm" />
                        <div class="text-xs">Cruelty Free</div>
                    </div>
                    <div class="flex items-center gap-x-2">
                        <FontAwesomeIcon icon="fas fa-square" class="fa-rotate-by text-sm"
                            style="--fa-rotate-angle: 45deg" />
                        <div class="text-xs">Plastic Free</div>
                    </div>
                </div> -->

                <Disclosure v-if="fieldValue.setting.product_specs" v-slot="{ open }">
                    <DisclosureButton class="cursor-pointer w-full">
                        <div class="mb-2 flex gap-x-4 items-center w-fit cursor-pointer">
                            <div class="font-bold">Product Specification & Documentation</div>
                            <FontAwesomeIcon :icon="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                class="text-gray-400" />
                        </div>
                    </DisclosureButton>
                    <DisclosurePanel class="text-sm text-gray-500">
                        <div class="col-span-2 divide-y divide-gray-300 text-gray-500 text-sm w-fit">
                            <div v-for="row in tableData" class="py-1 grid grid-cols-5 gap-x-4">
                                <div class="col-span-2">
                                    {{ row.label }}
                                </div>
                                <component :is="row.url ? 'a' : 'div'" :href="row.url" class="col-span-3"
                                    :class="[row.url ? 'hover:underline' : '']">
                                    {{ row.value }}
                                </component>
                            </div>
                        </div>
                    </DisclosurePanel>
                </Disclosure>

                <Disclosure v-slot="{ open }" v-if="fieldValue.setting.customer_review" >
                    <DisclosureButton class="cursor-pointer w-full">
                        <div class="mb-2 flex gap-x-4 items-center w-fit cursor-pointer">
                            <div class="font-bold">Customer Reviews</div>
                            <FontAwesomeIcon :icon="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                class="text-gray-400" />
                        </div>
                    </DisclosureButton>
                    <DisclosurePanel class="text-sm text-gray-500">
                       Dummy
                    </DisclosurePanel>
                </Disclosure>
            </div>

            <!-- Column: Right -->
            <div class="flex-none w-1/3"> <!-- Adjust width as needed -->
               <!--  <div v-if="mode.value == 'login' || mode.value == 'member'">
                    <div v-if="mode.value == 'login'" class="mb-2 font-semibold text-2xl">£9.60 (£1.20/Piece)</div>
                    <div v-if="mode.value == 'login' || mode.value == 'member'"
                        class="mb-2 font-semibold text-2xl text-orange-500">
                        £8.00 (£1.00/Piece)
                    </div>
                    <div v-if="mode.value == 'login'" class="mb-2 space-x-2">
                        <FontAwesomeIcon icon="fas fa-medal" class="text-orange-500" />
                        <span class="bg-orange-500 text-white py-0.5 px-1 rounded-md">Member Price</span>
                        <span class="text-xs underline cursor-pointer">Membership Info</span>
                    </div>

                    <div v-if="mode.value == 'login'" class="mb-8">
                        <div class="mb-0.5 text-xs text-gray-500">NOT A MEMBER?</div>
                        <div class="mb-2 text-orange-500 text-xs w-8/12">
                            Order 4 or more outers from this product family to benefit from lower price.
                        </div>
                        <div class="text-xs underline cursor-pointer">Browse Product Family</div>
                    </div>
                </div> -->

                <div  class="p-3 w-full">
                    <div class="flex gap-3">
                        <div
                            class="bg-gray-600 flex-1 text-white rounded px-3 py-1 h-fit flex items-center justify-center">
                            Login
                        </div>
                        <div
                            class="bg-gray-600 flex-1 text-white rounded px-3 py-1 h-fit flex items-center justify-center">
                            Register
                        </div>
                    </div>
                </div>

                <!-- Section: Order now -->
                <div  class="flex gap-x-2 mb-6">
                    <div class="flex items-start gap-x-1">
                        <div class="font-bold text-3xl leading-none cursor-pointer">-</div>
                        <div
                            class="h-8 aspect-square border border-gray-400 flex items-center justify-center tabular-nums text-xl font-bold">
                            1
                        </div>
                        <div class="font-bold text-3xl leading-none cursor-pointer">+</div>
                    </div>
                    <div class="bg-gray-600 text-white rounded px-3 py-1 h-fit w-fit">
                        Order Now
                    </div>
                </div>

                <!-- Section: Buy now pay later, delivery info, return policy -->
                <div v-if="fieldValue.setting.payments_and_policy" class="mb-4">
                    <Disclosure v-slot="{ open }">
                        <DisclosureButton
                            class="flex items-center w-full gap-x-4 border-t border-gray-300 pl-3 font-bold text-gray-600 py-1">
                            Buy Now Pay Later
                            <img src="https://pastpay.com/wp-content/uploads/2023/07/PastPay-logo-dark-edge.png"
                                class="h-3" alt="" />
                            <FontAwesomeIcon :icon="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                class="text-sm text-gray-500" />
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                            If you're unhappy with your purchase for any reason, email us within 90 days and we'll
                            refund you in full, no questions asked.
                        </DisclosurePanel>
                    </Disclosure>

                    <Disclosure v-slot="{ open }">
                        <DisclosureButton
                            class="flex items-center w-full gap-x-4 border-t border-gray-300 pl-3 font-bold text-gray-600 py-1">
                            Delivery Info
                            <FontAwesomeIcon :icon="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                class="text-sm text-gray-500" />
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                            If you're unhappy with your purchase for any reason, email us within 90 days and we'll
                            refund you in full, no questions asked.
                        </DisclosurePanel>
                    </Disclosure>

                    <Disclosure v-slot="{ open }">
                        <DisclosureButton
                            class="flex items-center gap-x-4 border-t w-full border-gray-300 pl-3 font-bold text-gray-600 py-1">
                            Return Policy
                            <FontAwesomeIcon :icon="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                class="text-sm text-gray-500" />
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                            If you're unhappy with your purchase for any reason, email us within 90 days and we'll
                            refund you in full, no questions asked.
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Secure Payments: PayPal, Visa, Mastercard -->
                    <div class="mb-8 mt-4">
                        <div class="pl-3 font-semibold flex items-center gap-x-4">
                            <div class="text-xs mb-3">Secure Payments:</div>
                            <img src="https://FontAwesomeIcon.pinimg.com/736x/21/bc/22/21bc22b0ae1013adeec20aeef47b3369.jpg"
                                alt="" class="h-6" />
                        </div>
                        <div class="mx-auto flex divide-x-2 divide-gray-300 w-fit border-x border-gray-300">
                            <img src="https://e7.pngegg.com/pngimages/292/77/png-clipart-paypal-logo-illustration-paypal-logo-icons-logos-emojis-tech-companies.png"
                                alt="Paypal" class="px-1 h-4" />
                            <img src="https://e7.pngegg.com/pngimages/687/457/png-clipart-visa-credit-card-logo-payment-mastercard-usa-visa-blue-company.png"
                                alt="Visa" class="px-1 h-4" />
                            <img src="https://FontAwesomeIcon.pinimg.com/736x/38/2f/0a/382f0a8cbcec2f9d791702ef4b151443.jpg"
                                alt="Mastercard" class="px-1 h-4" />
                        </div>
                    </div>

                </div>

                <!-- Section: FAQ -->
                <div v-if="fieldValue.setting.faqs" class="mt-4 pl-3">
                    <h2 class="mb-4 text">Frequently Asked Questions (FAQs):</h2>
                    <Disclosure v-slot="{ open }">
                        <DisclosureButton class="cursor-pointer border-b-2 w-full border-gray-300 py-2 pl-1.5">
                            <summary class="flex justify-between font-bold text-sm">
                                <span>How do they come packaged?</span>
                                <FontAwesomeIcon :icon="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                    class="text-sm text-gray-500" />
                            </summary>
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                            If you're unhappy with your purchase for any reason, email us within 90 days and we'll
                            refund you in full, no questions asked.
                        </DisclosurePanel>
                    </Disclosure>

                    <Disclosure v-slot="{ open }">
                        <DisclosureButton class="cursor-pointer border-b-2 w-full border-gray-300 py-2 pl-1.5">
                            <summary class="flex justify-between font-bold text-sm">
                                <span>Do the bath bombs Fizz or Foam?</span>
                                <FontAwesomeIcon :icon="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                    class="text-sm text-gray-500" />
                            </summary>
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                            If you're unhappy with your purchase for any reason, email us within 90 days and we'll
                            refund you in full, no questions asked.
                        </DisclosurePanel>
                    </Disclosure>

                    <Disclosure v-slot="{ open }">
                        <DisclosureButton class="cursor-pointer border-b-2 w-full border-gray-300 py-2 pl-1.5">
                            <summary class="flex justify-between font-bold text-sm">
                                <span>Are they safe for children?</span>
                                <FontAwesomeIcon :icon="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                    class="text-sm text-gray-500" />
                            </summary>
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                            If you're unhappy with your purchase for any reason, email us within 90 days and we'll
                            refund you in full, no questions asked.
                        </DisclosurePanel>
                    </Disclosure>
                </div>
            </div>
        </div>
    </div>
</template>
