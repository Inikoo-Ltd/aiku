<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { inject, ref } from 'vue'

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'

import { trans } from 'laravel-vue-i18n'
import Modal from '@/Components/Utils/Modal.vue'
import PureInputWithAddOn from '@/Components/Pure/PureInputWithAddOn.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { notify } from '@kyvg/vue3-notification'


import { faGlobe, faExternalLinkAlt, faUnlink, faUsers } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import axios from 'axios'

library.add(faGlobe, faExternalLinkAlt, faUnlink, faUsers)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    shopify_url: string
    unlinkRoute: routeType
    fetchCustomerRoute: routeType
    tiktokAuth: {
        url: string
        isAuthenticated: boolean
        isAuthenticatedExpired: boolean
        tiktokName: string
        deleteAccountRoute: routeType
    }
    type_manual: {
        url: routeType
        isAuthenticated: boolean
    }
    type_shopify: {
        createRoute: routeType
        connectRoute?: {
            url: string
        }
        isAuthenticated: boolean
        shopify_url: string
    }
    type_woocommerce: {
        connectRoute: routeType
        isConnected: boolean
    }
    total_channels: {
        manual: number
        shopify: number
        woocommerce: number
        tiktok: number
    }
}>()

const layout = inject('layout', layoutStructure)

const isModalOpen = ref<string | boolean>(false)
const websiteInput = ref<string | null>(null)
const isLoading = ref<string | boolean>(false)
const onCreateStoreShopify = () => {
    router[props.type_shopify.createRoute.method || 'post'](
        route(props.type_shopify.createRoute.name, props.type_shopify.createRoute.parameters),
        {
            name: websiteInput.value
        },
        {
            onStart: () => isLoading.value = true,
            onError: (error) => {
                notify({
                    title: trans('Something went wrong'),
                    text: error,
                    type: 'error',
                })
            },
            onSuccess: () => {
                window.open(props.type_shopify.connectRoute?.url + '?shop=' + websiteInput.value + props.type_shopify.shopify_url, '_blank')

                isModalOpen.value = false
                websiteInput.value = null
            },
            onFinish: () => isLoading.value = false
        }
    )
}

// Section: Woocommerce
const isModalWoocom = ref<boolean>(false)
const woocomInput = ref({
    name: null as null | string,
    url: null as null | string,
})
const onSubmitWoocommerce = async () => {
    const method = props.type_woocommerce.connectRoute.method
    const response = await axios.post(
        route(props.type_woocommerce.connectRoute.name, props.type_woocommerce.connectRoute.parameters),
        woocomInput.value)
    isModalWoocom.value = false
    woocomInput.value.name = null
    woocomInput.value.url = null

    window.location.href = response.data
}
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <!-- <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" /> -->

    <!-- <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" /> -->
    <div class="px-6">
        <div class="text-xl py-2 w-fit">E-Commerce</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Section: Manual -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <Link :href="route('retina.dropshipping.platforms.dashboard', ['manual'])"
                    class="hover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://aw.aurora.systems/art/aurora_log_v2_orange.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">{{ trans("Manual") }}</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.manual }} {{ trans('Channels') }}</div>
                    </div>
                </Link>

                <div class="w-full flex justify-end">
                    <!-- <Link v-if="!type_manual?.isAuthenticated" as="button" class="w-full"
                        :href="type_manual?.url" :method="'post'">
                        <Button label="Connect" type="primary" full />
                    </Link>
                    <div v-else class="relative w-full">
                        <Button :capitalize="false" :label="`Connected`" disabled type="positive" icon="fal fa-check"
                            size="xs" full />
                    </div> -->
                    <ButtonWithLink :routeTarget="type_manual?.url" :label="trans('Create')" full />
                </div>
            </div>

            <!-- Section: Shopify -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <Link :href="route('retina.dropshipping.platforms.dashboard', ['shopify'])"
                    class="hover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/64/5968/5968919.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">Shopify</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.shopify }} {{ trans('Channels') }}</div>
                    </div>
                </Link>

                <!-- Button: Connect -->
                <div class="relative w-full">
                    <Button @click="() => isModalOpen = 'shopify'" label="Connect" type="primary" full />

                    <!-- <Transition name="spin-to-down">
                        <div v-if="type_shopify?.connectRoute?.url" class="w-full flex justify-end gap-x-2">
                            <Link as="button" :href="route(unlinkRoute.name, unlinkRoute.parameters)"
                                :method="unlinkRoute.method" @start="isLoading = 'unlink'" @error="(error) => notify({
                                    title: trans('Something went wrong.'),
                                    text: trans('Please try again'),
                                    type: 'error',
                                })" @finish="isLoading = false">
                                <Button :loading="isLoading === 'unlink'" label="Unlink" type="negative"
                                    icon="fal fa-unlink" size="xs" />
                            </Link>

                            <a target="_blank" :href="type_shopify?.connectRoute?.url" class="w-full">
                                <Button label="Open" key="secondary" full iconRight="fal fa-external-link-alt"
                                    size="xs" />
                            </a>

                            <Link as="button" :href="route(fetchCustomerRoute.name, fetchCustomerRoute.parameters)"
                                :method="fetchCustomerRoute.method" @start="isLoading = 'fetch-customers'" @error="(error) => notify({
                                    title: trans('Something went wrong.'),
                                    text: trans('Please try again'),
                                    type: 'error',
                                })" @finish="isLoading = false" @success="() =>
                                    notify({
                                        title: trans('Success fetch customers'),
                                        type: 'success',
                                    }
                                    )">
                                <Button :loading="isLoading === 'fetch-customers'" type="positive" icon="fal fa-users"
                                    size="xs" />
                            </Link>
                        </div>

                        <div v-else class="w-full flex justify-end">
                            <Button @click="() => isModalOpen = 'shopify'" label="Connect" type="primary" full />
                        </div>
                    </Transition> -->
                </div>
            </div>

            <!-- Section: Tiktok -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <Link :href="route('retina.dropshipping.platforms.dashboard', ['tiktok'])"
                    class="hover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/64/3046/3046126.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">Tiktok</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.tiktok }} {{ trans('Channels') }}</div>
                    </div>
                </Link>

                <div class="w-full flex justify-end">
                    <a target="_blank" class="w-full" :href="tiktokAuth?.url">
                        <Button v-if="layout?.app?.environment === 'local'"
                            :label="tiktokAuth?.isAuthenticatedExpired ? trans('Re-connect') : trans('Connect')"
                            type="primary" full />
                        <Button v-else :label="trans('Coming soon')" type="tertiary" disabled full />
                    </a>

                    <!-- <div v-else class="relative w-full">
                        <Transition name="spin-to-down">
                            <div class="w-full flex justify-end gap-x-2">
                                <Link as="button"
                                    :href="route(tiktokAuth?.deleteAccountRoute?.name, tiktokAuth?.deleteAccountRoute?.parameters)"
                                    :method="tiktokAuth?.deleteAccountRoute?.method"
                                    @start="isLoading = 'unlink-tiktok'"
                                    @error="(error) => notify({
                                        title: trans('Something went wrong.'),
                                        text: trans('Please try again'),
                                        type: 'error',
                                    })"
                                    @finish="isLoading = false">
                                    <Button :loading="isLoading === 'unlink-tiktok'" label="Unlink" type="negative"
                                        icon="fal fa-unlink" size="xs" full />
                                </Link>
                                <Button :label="`Connected`" type="positive" icon="fal fa-check" size="xs" full />
                            </div>
                        </Transition>
                    </div> -->
                </div>
            </div>

            <!-- Section: Woocommerce -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <Link :href="route('retina.dropshipping.platforms.dashboard', ['woocommerce'])"
                    class="hover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://e7.pngegg.com/pngimages/490/140/png-clipart-computer-icons-e-commerce-woocommerce-wordpress-social-media-icon-bar-link-purple-violet-thumbnail.png"
                        alt="" class="h-12">

                    <div class="flex flex-col">
                        <div class="font-semibold">Woocommerce</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.woocommerce }} {{ trans('Channels') }}</div>
                    </div>
                </Link>

                <div class="w-full flex justify-end">
                    <Button
                        v-if="layout?.app?.environment === 'local'"
                        :label="trans('Connect')"
                        type="primary"
                        full
                        @click="() => isModalWoocom = true"
                    />

                    <Button v-else :label="trans('Coming soon')" type="tertiary" disabled full />

                    <!-- <a v-if="false" target="_blank" class="w-full" :href="tiktokAuth?.url">
                        <Button
                            v-if="layout?.app?.environment === 'local'"
                            :label="tiktokAuth?.isAuthenticatedExpired ? trans('Re-connect') : trans('Connect')"
                            type="primary"
                            full
                            @click="() => isModalWoocom = true"
                        />
                        <Button v-else :label="trans('Coming soon')" type="tertiary" disabled full />
                    </a>

                    <div v-else class="relative w-full">
                        <Transition name="spin-to-down">
                            <div class="w-full flex justify-end gap-x-2">
                                <Link as="button" :href="route(tiktokAuth?.deleteAccountRoute?.name, tiktokAuth?.deleteAccountRoute?.parameters)"
                                      :method="tiktokAuth?.deleteAccountRoute?.method" @start="isLoading = 'unlink-tiktok'" @error="(error) => notify({
                                    title: trans('Something went wrong.'),
                                    text: trans('Please try again'),
                                    type: 'error',
                                }) " @finish="isLoading = false">
                                    <Button :loading="isLoading === 'unlink-tiktok'" label="Unlink" type="negative"
                                            icon="fal fa-unlink" size="xs" full/>
                                </Link>
                                <Button :label="`Connected`" type="positive" icon="fal fa-check" size="xs" full/>
                            </div>
                        </Transition>
                    </div> -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Shopify -->
    <Modal :isOpen="!!isModalOpen" @onClose="isModalOpen = false" width="w-[500px]">
        <div class="h-40">
            <div class="mb-4">
                <div class="text-center font-semibold text-xl">
                    {{ trans('Select your store name') }}
                </div>

                <div class="text-center text-xs text-gray-500">
                    {{ trans('This is the url that your store can be accessed') }}
                </div>
            </div>

            <PureInputWithAddOn v-model="websiteInput" :leftAddOn="{
                icon: 'fal fa-globe'
            }" :rightAddOn="{
                    label: shopify_url
                }" @keydown.enter="() => onCreateStoreShopify()" />

            <Button @click="() => onCreateStoreShopify()" full label="Create" :loading="!!isLoading" class="mt-6" />
        </div>
    </Modal>

    <!-- Modal: Woocommerce -->
    <Modal :isOpen="isModalWoocom" @onClose="isModalWoocom = false" width="w-full max-w-lg">
        <div class="">
            <div class="mb-4">
                <div class="text-center font-semibold text-xl">
                    {{ trans('Woomerce store detail') }}
                </div>

                <div class="text-center text-xs text-gray-500">
                    {{ trans('Enter your Woocommerce store detail') }}
                </div>
            </div>

            <div class="flex flex-col gap-y-2">
                <PureInput v-model="woocomInput.name" :placeholder="trans('Your store name')"></PureInput>
                <PureInputWithAddOn v-model="woocomInput.url" :leftAddOn="{
                    icon: 'fal fa-globe'
                }" :placeholder="trans('e.g https://storeurlexample.com')"
                    @keydown.enter="() => onSubmitWoocommerce()" />
            </div>

            <Button @click="() => onSubmitWoocommerce()" full label="Create" :loading="!!isLoading" class="mt-6" />
        </div>
    </Modal>
</template>
