<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faExternalLinkAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faCheck, faExternalLinkAlt)

const props = defineProps<{
    data: {}
    shop: string
    shopUrl: string
}>()


</script>

<template>
    <div class="grid-cols-1 md:grid-cols-2 gap-2">


        <div class="relative isolate px-6 pt-14 lg:px-8">

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

        </div>
    </div>
</template>
