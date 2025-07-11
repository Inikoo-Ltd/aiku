<script setup lang="ts">
import BackgroundBox from '@/Components/BackgroundBox.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import Icon from '@/Components/Icon.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import CountUp from 'vue-countup-v3'

import { faArrowRight } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link, router } from '@inertiajs/vue3'
import { capitalize, inject, ref } from 'vue'
import { ChannelLogo } from '@/Composables/Icon/ChannelLogoSvg'
import StatsBox from '@/Components/Stats/StatsBox.vue'
import { trans } from 'laravel-vue-i18n'
import { Fieldset } from 'primevue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import Modal from '@/Components/Utils/Modal.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { notify } from '@kyvg/vue3-notification'
library.add(faArrowRight)

const props = defineProps<{
    data: {
        customer: {

        }
        channels: {

        }[]
        stats: {

        }[]
        last_visited_channels: {

        }[]
        shortcut: {
            order: {
                is_show_button: boolean
                manual_data: {
                    icon: string
                    icon_rotation: string
                    value: number
                }
                route_create_order: routeType
            }
        }
    }
}>()

const locale = inject('locale', aikuLocaleStructure)


// Section: Modal Create Order
const isModalCreateOrder = ref(false)
const selectedCustomerClientId = ref(null)
const isLoadingSubmit = ref(false)
const onSubmitCreateOrder = () => {
    // Section: Submit
    router.post(
        route('retina.models.customer-client.order.store', {
            customerClient: selectedCustomerClientId.value
        }),
        {
            data: 'qqq'
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingSubmit.value = true
            },
            onSuccess: () => {
                isModalCreateOrder.value = false
                notify({
                    title: trans("Success"),
                    text: trans("Successfully create the order"),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to create the order. Please try again."),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmit.value = false
            },
        }
    )
}
</script>

<template>
    <div class="relative isolate overflow-hidden">
        <!-- <pre>{{ data }}</pre> -->
        <!-- <svg class="absolute inset-0 -z-10 size-full stroke-gray-200 [mask-image:radial-gradient(100%_100%_at_top_right,white,transparent)]"
            aria-hidden="true">
            <defs>
                <pattern id="0787a7c5-978c-4f66-83c7-11c213f99cb7" width="200" height="200" x="50%" y="-1"
                    patternUnits="userSpaceOnUse">
                    <path d="M.5 200V.5H200" fill="none" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" stroke-width="0" fill="url(#0787a7c5-978c-4f66-83c7-11c213f99cb7)" />
        </svg> -->
        
        <div class="mx-auto px-6 pb-12 pt-10 lg:flex lg:px-14 ">
            <div v-if="data.channels.length" class="w-full lg:shrink-0">
                <div class="mx-auto xmax-w-2xl lg:mx-0 ">
                    <h1 class="mt-10 text-pretty text-5xl font-semibold tracking-tight sm:text-7xl">
                        {{ trans("Your channels summary") }}
                    </h1>
                    <p class="mt-8 text-pretty text-lg xfont-medium text-gray-500 sm:text-xl/8">
                        {{ trans("Have a look at your channels summary.") }}
                    </p>
                </div>
                
                <div class="flex justify-between gap-x-4">
                    <div class="w-full max-w-96 mt-4 xmd:grid grid-cols-1 gap-2 lg:gap-5 xsm:grid-cols-2">
                        <StatsBox
                            v-for="(stat, idxStat) in data.stats"
                            :stat="stat"
                        />
                        <div v-if="data.last_visited_channels?.length" class="overflow-hidden border border-gray-300 rounded-md mt-5 relative">
                            <div class="sticky top-0 z-10 border-y border-b-gray-200 border-t-gray-100 bg-gray-50 px-3 py-1.5 text-sm/6 font-semibold text-gray-900">
                                <h3>{{ trans("Last visited Channels") }}</h3>
                            </div>
                            <ul role="list" class="divide-y divide-gray-100">
                                <li v-for="channel in data.last_visited_channels" xkey="person.email" class="flex gap-x-4 px-3 py-2">
                                    <div v-html="ChannelLogo(channel.platform)" class="flex-grow size-8 overflow-hidden border border-gray-300 rounded-full"></div>
                                    <div class="w-full xflex-shrink-0 justify-between flex items-center">
                                        <div class="min-w-0">
                                            <p class="text-sm/6 font-semibold">
                                                {{ channel.name }}
                                            </p>
                                            <p v-if="channel.baskets_count" class="xmt-1 truncate text-xs/5 text-gray-500">
                                                {{ channel.baskets_count ?? 0 }} in Baskets
                                            </p>
                                        </div>
                                        <ButtonWithLink
                                            xrouteTarget="{
                                                name: 'retina.dropshipping.customer_sales_channels.show',
                                                params: {
                                                    channel: 'shopify'
                                                }
                                            }"
                                            :url="channel.route"
                                            xlabel="View channel"
                                            iconRight="far fa-arrow-right"
                                            xclass="mt-2"
                                            type="transparent"
                                            key="2"
                                        />
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: Shortcut -->
                    <div v-if="
                        data.shortcut.order.is_show_button
                    " class="max-w-64 w-full">
                        <Fieldset :legend="trans('Quick links (Shortcuts)')">
                            <div class="flex flex-col">
                                <Button
                                    v-if="data.shortcut.order.is_show_button"
                                    @click="isModalCreateOrder = true"
                                    :label="trans('Create manual Order')"
                                    full
                                    type="tertiary"
                                    icon="fas fa-plus"
                                />
                            </div>
                        </Fieldset>
                    </div>
                </div>
            </div>

            <div v-else class="mx-auto max-w-2xl lg:mx-0 lg:shrink-0 lg:pt-8">
                <!-- <div class="">
                    <a href="#" class="inline-flex space-x-6">
                        <span class="rounded-full bg-indigo-600/10 px-3 py-1 text-sm/6 font-semibold text-indigo-600 ring-1 ring-inset ring-indigo-600/10">
                            What's new?
                        </span>
                    </a>
                </div> -->

                <h1 class="mt-10 text-pretty text-5xl font-semibold tracking-tight sm:text-7xl">
                    Manage your orders and products
                </h1>
                <p class="mt-8 text-pretty text-lg font-medium text-gray-500 sm:text-xl/8">
                    Control your orders and products with our easy-to-use dashboard. You can manage your orders, products, and customers all in one place.
                </p>
                <div class="mt-10 flex items-center gap-x-6">
                    <ButtonWithLink
                        :routeTarget="{
                            name: 'retina.dropshipping.customer_sales_channels.create'
                        }"
                        label="Get started"
                        iconRight="far fa-arrow-right"
                    />
                </div>
            </div>
            
        </div>

        <!-- Modal: Create order -->
        <Modal :isOpen="isModalCreateOrder" @onClose="isModalCreateOrder = false" closeButton :isClosableInBackground="false" width="max-w-lg w-full">
            <div>
                <div class="text-lg font-semibold mb-4 text-center">
                    {{ trans("Create Manual Order") }}
                </div>

                <div>
                    <div class="mb-4">
                        <div class="text-sm xmb-2">
                            {{ trans("Select Customer Client") }}
                        </div>
                        <PureMultiselectInfiniteScroll
                            v-model="selectedCustomerClientId"
                            :fetchRoute="{
                                name: 'retina.dropshipping.customer_sales_channels.client.index',
                                parameters: {
                                    customerSalesChannel: data.shortcut.order.manual_data.slug,
                                }
                            }"
                            required
                            :disabled="isLoadingSubmit"
                        >
                            <template #singlelabel="{ value }">
                                <div class="w-full text-left pl-4">
                                    {{ value.name}}
                                    <span v-if="value.reference" class="text-sm text-gray-400">
                                        (#{{ value.reference }})
                                    </span>
                                </div>
                            </template>

                            <template #afterlist>
                                <div class="m-2 cursor-auto text-gray-400 text-sm">
                                    {{ trans("Can't find the client?") }}
                                    
                                    <Link
                                        :href="route('retina.dropshipping.customer_sales_channels.client.create', {
                                            customerSalesChannel: data.shortcut.order.manual_data.slug
                                        })"
                                        class="hover:underline hover:text-gray-700 cursor-pointer"
                                    >
                                        {{ trans("Create new client here") }}
                                    </Link>
                                </div>
                            </template>
                        </PureMultiselectInfiniteScroll>
                    </div>

                    <Button 
                        @click="() => onSubmitCreateOrder()"
                        label="Create Order"
                        full
                        :loading="isLoadingSubmit"
                        :disabled="!selectedCustomerClientId"
                    />
                </div>

                <!-- Divider -->
                <div class="relative my-3">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300" />
                    </div>
                    <div class="relative flex justify-center">
                        <span class="bg-white px-2 text-xs text-gray-500">{{ trans("Can't find the client? Create new client") }}</span>
                    </div>
                </div>

                <Link
                    :href="route('retina.dropshipping.customer_sales_channels.client.create', {
                        customerSalesChannel: data.shortcut.order.manual_data.slug
                    })"
                >
                    <Button
                        xclick="() => onSubmitCreateOrder()"
                        label="Create new client"
                        full
                        type="tertiary"
                        xloading="isLoadingSubmit"
                        xdisabled="!selectedCustomerClientId"
                        xiconRight="fal fa-external-link-alt"
                        icon="fas fa-plus"
                    />
                </Link>
            </div>
        </Modal>
    </div>
</template>
