<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'
import PupilFulfilmentDashboard from "@/Pages/Pupil/Dashboard/PupilFulfilmentDashboard.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faExternalLinkAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCheck, faExternalLinkAlt)

const props = defineProps<{
    data: {}
    shop: string
    shopUrl: string
}>()

console.log('data', props.data)

// const openWebsite = (url: string) => {
//     window.open(url);
// };
</script>

<template>
    <div class="xgrid grid-cols-1 md:grid-cols-2 gap-2">
        <!-- {{ shop }}
        <div>
            <PupilFulfilmentDashboard :data :shop />
        </div> -->


        <!-- <div class="relative isolate overflow-hidden px-6 py-8 text-center sm:rounded-3xl sm:px-12">
            <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-500">
                {{ trans("You can manage your orders and products in our fulfilment website.") }}
            </p>

            <div class="flex justify-center">
                <img class="w-full h-1/2 object-cover" src="https://i.ibb.co.com/CxTbCRf/undraw-factory-4d61.png" :alt="`${props.shop}`">
            </div>

            <div class="mt-10 flex flex-col items-center justify-center gap-x-6">
                <a :href="props.shopUrl" target="_blank" rel="noopener noreferrer">
                    <Button aclick="openWebsite(props.shopUrl)" size="l" :label="`Open ${props.shop}`" />
                </a>
            </div>
        </div> -->

        <div class="relative isolate px-6 pt-14 lg:px-8">
            <!-- <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80"
                aria-hidden="true">
                <div class="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-30 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]"
                    style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" />
            </div> -->
            <div class="mx-auto max-w-4xl py-32">
                <div class="hidden sm:mb-8 sm:flex sm:justify-center">
                    <div
                        class="relative rounded-full px-3 py-1 text-sm/6 bg-green-50 text-green-600 ring-1 ring-green-900/10 hover:ring-green-900/20">
                        {{ trans("Your platform has been connected.") }}
                        <FontAwesomeIcon icon="fal fa-check" class="" fixed-width aria-hidden="true" />
                    </div>
                </div>
                <div class="text-center">
                    <h1 class="text-balance text-5xl font-semibold tracking-tight sm:text-7xl">
                        {{ trans("Welcome to :shop", { shop: shop }) }}
                    </h1>
                    <p class="mt-8 text-pretty text-lg font-medium text-gray-500 sm:text-xl/8">
                        Now you can manage your orders and products in our website.
                    </p>
                    <div class="mt-10 flex items-center justify-center gap-x-6">
                        <a :href="props.shopUrl" target="_blank" rel="noopener noreferrer">
                            <Button size="l" icon="fal fa-external-link-alt" :label="trans('Open :shop', {shop: props.shop})" />
                        </a>
                    </div>
                </div>
            </div>
            <!-- <div class="absolute inset-x-0 top-[calc(100%-13rem)] -z-10 transform-gpu overflow-hidden blur-3xl sm:top-[calc(100%-30rem)]"
                aria-hidden="true">
                <div class="relative left-[calc(50%+3rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-30 sm:left-[calc(50%+36rem)] sm:w-[72.1875rem]"
                    style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" />
            </div> -->
        </div>
    </div>
</template>
