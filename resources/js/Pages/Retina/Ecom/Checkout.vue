<script setup lang="ts">
import { faArrowLeft, faCreditCardFront, faUniversity } from "@fal"
import CheckoutSummary from "./CheckoutSummary.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { faPaypal } from "@fortawesome/free-brands-svg-icons"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref } from "vue"
import { data } from "autoprefixer";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n"
library.add(faCreditCardFront)

const props = defineProps<{
    order: {},
    paymentMethods:[]

}>()

const currentTab = ref(0)
const tabs = [
    { label: 'Credit card', icon: faCreditCardFront },
    { label: 'Paypal', icon: faPaypal },
    { label: 'Bank transfer', icon: faUniversity },
]
</script>

<template>
    <div v-if="!order" class="text-center text-gray-500 text-2xl pt-6">
        {{ trans("Your basket is empty") }}
    </div>

    <div v-else class="w-full px-4 mt-8">
        <div class="px-4 text-xl">
            <span class="text-gray-500">Order number</span> <span class="font-bold">#GB550706</span>
        </div>
        
        <!-- <pre>{{ data }}</pre> -->
        <CheckoutSummary :summary="data"></CheckoutSummary>

        <!-- <div class="border border-t border-orange-300"></div> -->

        <!-- Section: Tabs -->
        <div class="mx-10 border border-gray-300 rounded">
            <div class="max-w-lg">
                <div class="grid grid-cols-1 sm:hidden">
                    <!-- Use an "onChange" listener to redirect the user to the selected tab URL. -->
                    <select aria-label="Select a tab" class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-2 pl-3 pr-8 text-base  outline outline-1 -outline-offset-1 outline-gray-300 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600">
                        <option v-for="(tab, tabIdx) in tabs" :key="tabIdx" :selected="currentTab === tabIdx">
                            <FontAwesomeIcon :icon="tab.icon" class="" fixed-width aria-hidden="true" />
                            {{ tab.label }}
                        </option>
                    </select>
                    <ChevronDownIcon class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end fill-gray-500" aria-hidden="true" />
                </div>
                
                <div class="hidden sm:block">
                    <nav class="isolate flex divide-x divide-gray-200 rounded-lg shadow" aria-label="Tabs">
                        <div
                            v-for="(tab, tabIdx) in tabs"
                            @click="currentTab = tabIdx"
                            :key="tabIdx"
                            :class="[currentTab === tabIdx ? '' : 'text-gray-500 hover:text-gray-700', tabIdx === 0 ? 'rounded-l-lg' : '', tabIdx === tabs.length - 1 ? 'rounded-r-lg' : '']"
                            class="cursor-pointer group relative min-w-0 flex-1 overflow-hidden bg-white px-4 py-4 text-center text-sm font-medium hover:bg-gray-100 focus:z-10"
            
                        >
                            <FontAwesomeIcon :icon="tab.icon" class="mr-1" fixed-width aria-hidden="true" />
                            <span>{{ tab.label }}</span>
                            <span aria-hidden="true" :class="[currentTab === tabIdx ? 'bg-indigo-500' : 'bg-transparent', 'absolute inset-x-0 bottom-0 h-0.5']" />
                        </div>
                    </nav>
                </div>
            </div>

            <div class="py-10 w-full text-center">
                {{ tabs[currentTab].label }}
            </div>
        </div>

        <div class="flex justify-end gap-x-4 mt-4 px-4">
            
            <ButtonWithLink
                :icon="faArrowLeft"
                type="tertiary"
                label="Back to basket"
                :routeTarget="{
                    name: 'retina.ecom.basket.show'
                }"
            />
        </div>
    </div>
</template>