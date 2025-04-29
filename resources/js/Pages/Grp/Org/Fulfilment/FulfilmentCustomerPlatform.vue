<script setup lang="ts">
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import CustomerShowcase from '@/Components/Showcases/Grp/CustomerShowcase.vue'
import { useTabChange } from '@/Composables/tab-change'
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import { trans } from 'laravel-vue-i18n'
import Fieldset from 'primevue/fieldset'
import { computed, ref } from 'vue'
// import {  } from 'vue'
import type { Component } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShopify } from "@fortawesome/free-brands-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faShopify)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTS
    tabs: {
        current: string
        navigation: {}
    }
    showcase: {
        stats: {
            name: string
            number_orders: number
            number_customer_clients: number
            number_portfolios: number
        }
    }
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: CustomerShowcase,

    }

    return components[currentTab.value]
})
</script>

<template>
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <!-- <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"  /> -->
    <!-- {{ showcase }} -->

    <div class="p-6">
        <div :legend="trans('Statistic')" class="border border-gray-300 rounded-lg w-full sm:max-w-lg">
            <div v-if="route().params.platform === 'shopify'" class="py-3 px-2 flex items-center justify-between gap-x-4 w-full border-b border-gray-900/15">
                <dl v-if="true" class="flex-auto pl-3">
                    <dt class="text-xs text-gray-400">{{ trans("Account name") }}</dt>
                    <dd v-tooltip="trans('Shopify account\'s name')" class="w-fit mt text-xl font-semibold leading-6">
                        {{ showcase.stats.name }}
                    </dd>
                </dl>

                <div class="px-6 text-5xl">
                    <FontAwesomeIcon :icon="faShopify" class="" fixed-width aria-hidden="true" />
                </div>
            </div>

            <!-- Section: Field -->
            <div class="flex flex-col gap-y-2 w-full py-4 px-5">
                <!-- Field: Number orders -->
                <dl v-if="showcase.stats.number_orders > -1" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Orders')" class="flex-none">
                        <FontAwesomeIcon icon="fal fa-shopping-cart" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">{{ showcase.stats.number_orders }}</dd>
                </dl>

                <!-- Field: Number customer clients -->
                <dl v-if="showcase.stats.number_customer_clients > -1" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Customer clients')" class="flex-none">
                        <FontAwesomeIcon icon="fal fa-users" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">{{ showcase.stats.number_customer_clients }}</dd>
                </dl>

                <!-- Field: Number portfolios -->
                <dl v-if="showcase.stats.number_portfolios > -1" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Portfolios')" class="flex-none">
                        <FontAwesomeIcon icon="fal fa-cube" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">{{ showcase.stats.number_portfolios }}</dd>
                </dl>

                <!-- Field: Created at -->
                <dl v-if="data?.customer?.created_at" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Created at')" class="flex-none">
                        <span class="sr-only">Created at</span>
                        <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">
                        <time datetime="2023-01-31">{{ useFormatTime(data?.customer?.created_at) }}</time>
                    </dd>
                </dl>
                <!-- Field: Email -->
                <dl v-if="data?.customer?.email" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Email')" class="flex-none">
                        <span class="sr-only">Email</span>
                        <FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">
                        <a :href="`mailto:${data.customer.email}`">{{ data?.customer?.email }}</a>
                    </dd>
                </dl>
                <!-- Field: Phone -->
                <dl v-if="data?.customer?.phone" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Phone')" class="flex-none">
                        <span class="sr-only">Phone</span>
                        <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">
                        <a :href="`tel:${data.customer.email}`">{{ data?.customer?.phone }}</a>
                    </dd>
                </dl>
                <!-- Field: Address -->
                <dl v-if="data?.customer?.address" class="relative flex items w-full flex-none gap-x-4">
                    <dt v-tooltip="'Address'" class="flex-none">
                        <FontAwesomeIcon icon="fal fa-map-marker-alt" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="w-full text-gray-500">
                        <div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
                            <span class="" v-html="data?.customer?.address.formatted_address" />
                            <div v-if="data.address_management.can_open_address_management"
                                @click="() => isModalAddress = true"
                                class="w-fit pr-4 whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
                                <span>{{ trans("Edit") }}</span>
                            </div>
                        </div>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</template>
