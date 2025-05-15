<script setup lang="ts">
import {Head, Link, router} from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import {capitalize} from "@/Composables/capitalize"
import { inject, ref } from 'vue'

import {PageHeading as PageHeadingTypes} from '@/types/PageHeading'
import {Tabs as TSTabs} from '@/types/Tabs'
import Button from '@/Components/Elements/Buttons/Button.vue'
import {routeType} from '@/types/route'

import {trans} from 'laravel-vue-i18n'
import Modal from '@/Components/Utils/Modal.vue'
import PureInputWithAddOn from '@/Components/Pure/PureInputWithAddOn.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import {notify} from '@kyvg/vue3-notification'


import {faGlobe, faExternalLinkAlt, faUnlink, faUsers} from '@fal'
import {library} from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from '@/Composables/useLayoutStructure'

library.add(faGlobe, faExternalLinkAlt, faUnlink, faUsers)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    connectRoute: {
        url: string
    } | null
    createRoute: routeType
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
    aikuConnectRoute: {
        url: string
        isAuthenticated: boolean
    }
    wooRoute: {
        connectRoute: routeType
        isConnected: boolean
    }
}>()

const layout = inject('layout', layoutStructure)

const isModalOpen = ref<string | boolean>(false)
const websiteInput = ref<string | null>(null)
const isLoading = ref<string | boolean>(false)
const onCreateStore = () => {
    router[props.createRoute.method || 'post'](
        route(props.createRoute.name, props.createRoute.parameters),
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
                isModalOpen.value = false
                websiteInput.value = null

                window.open(props.connectRoute?.url, '_blank');
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
const onSubmitWoocommerce = () => {
    router[props.wooRoute.connectRoute.method || 'post'](
        route(props.wooRoute.connectRoute.name, props.wooRoute.connectRoute.parameters),
        woocomInput.value,
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
                isModalWoocom.value = false
                woocomInput.value.name = null
                woocomInput.value.url = null

                // window.open(props.connectRoute?.url, '_blank');
            },
            onFinish: () => isLoading.value = false
        }
    )
}
</script>

<template>

    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"/>
    <!-- <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" /> -->

    <!-- <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" /> -->
    <div class="px-6">
        <div class="text-xl py-2 w-fit">E-Commerce</div>
         <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <Link :href="route('retina.dropshipping.platforms.dashboard', ['manual'])" class="hover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://aw.aurora.systems/art/aurora_log_v2_orange.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">{{ trans("Manual") }}</div>
                        <div class="text-xs opacity-70">({{ trans("Manage product") }})</div>
                    </div>
                </Link>
                <div class="w-full flex justify-end">
                    <Link as="button" v-if="!aikuConnectRoute?.isAuthenticated" class="w-full" :href="aikuConnectRoute?.url" :method="'post'">
                        <Button label="Connect" type="primary" full/>
                    </Link>
                    <div v-else class="relative w-full">
                        <Transition name="spin-to-down">
                            <div class="w-full flex justify-end gap-x-2">
                                <Button :capitalize="false" :label="`Connected`" type="positive" icon="fal fa-check" size="xs" full/>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <Link :href="route('retina.dropshipping.platforms.dashboard', ['shopify'])" class="hover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/256/5968/5968919.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">Shopify</div>
                        <div class="text-xs">({{ trans("Manage product") }})</div>
                    </div>
                </Link>

                <!-- Button: Connect -->
                <div class="relative w-full">
                    <Transition name="spin-to-down">
                        <div v-if="connectRoute?.url" class="w-full flex justify-end gap-x-2">
                            <Link as="button" :href="route(unlinkRoute.name, unlinkRoute.parameters)"
                                  :method="unlinkRoute.method" @start="isLoading = 'unlink'" @error="(error) => notify({
                                    title: trans('Something went wrong.'),
                                    text: trans('Please try again'),
                                    type: 'error',
                                }) " @finish="isLoading = false">
                                <Button :loading="isLoading === 'unlink'" label="Unlink" type="negative"
                                        icon="fal fa-unlink" size="xs"/>
                            </Link>
                            <a target="_blank" :href="connectRoute?.url" class="w-full">
                                <Button label="Open" key="secondary" full iconRight="fal fa-external-link-alt"
                                        size="xs"/>
                            </a>
                            <Link as="button" :href="route(fetchCustomerRoute.name, fetchCustomerRoute.parameters)"
                                  :method="fetchCustomerRoute.method" @start="isLoading = 'fetch-customers'" @error="(error) => notify({
                                    title: trans('Something went wrong.'),
                                    text: trans('Please try again'),
                                    type: 'error',
                                }) " @finish="isLoading = false" @success="() =>
                                    notify({
                                    title: trans('Success fetch customers'),
                                    type: 'success',
                                    }
                                )">
                                <Button :loading="isLoading === 'fetch-customers'" type="positive"
                                        icon="fal fa-users" size="xs"/>
                            </Link>
                        </div>

                        <!-- Button: Create -->
                        <div v-else class="w-full flex justify-end">
                            <Button @click="() => isModalOpen = 'shopify'" label="Create" type="primary" full/>
                        </div>
                    </Transition>
                </div>
            </div>

<!--            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <div class="mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQfkm8ggJ5zlVCHbmIzc9oTvtAiwMG4q3ROWA&s" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">WooCommerce</div>
                        <div class="text-xs">({{ trans("Manage product") }})</div>
                    </div>
                </div>
                <div class="w-full flex justify-end">
                    <Button @click="() => isModalOpen = 'woocommerce'" label="Create" type="primary" full/>
                </div>
            </div>-->

            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <Link :href="route('retina.dropshipping.platforms.dashboard', ['tiktok'])" class="hover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/512/3046/3046126.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">Tiktok</div>
                        <div class="text-xs">({{ trans("Manage product") }})</div>
                    </div>
                </Link>

                <div class="w-full flex justify-end">
                    <a v-if="!tiktokAuth?.isAuthenticated" target="_blank" class="w-full" :href="tiktokAuth?.url">
                        <Button v-if="layout?.app?.environment === 'local'" :label="tiktokAuth?.isAuthenticatedExpired ? trans('Re-connect') : trans('Connect')" type="primary" full />
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
                    </div>
                </div>
            </div>

            <!-- Channel: Woocommerce -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <Link :href="route('retina.dropshipping.platforms.dashboard', ['tiktok'])" class="hover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://e7.pngegg.com/pngimages/490/140/png-clipart-computer-icons-e-commerce-woocommerce-wordpress-social-media-icon-bar-link-purple-violet-thumbnail.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">Woocommerce</div>
                        <div class="text-xs">({{ trans("Manage product") }})</div>
                    </div>
                </Link>

                <div class="w-full flex justify-end">
                    <Button
                        v-if="layout?.app?.environment === 'local'"
                        :label="wooRoute?.isConnected ? trans('Success') : trans('Connect')"
                        type="primary"
                        full
                        @click="() => isModalWoocom = true"
                    />
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
                }" @keydown.enter="() => onCreateStore()"/>

            <Button @click="() => onCreateStore()" full label="Create" :loading="!!isLoading" class="mt-6"/>
        </div>
    </Modal>

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
                <PureInput
                    v-model="woocomInput.name"
                    :placeholder="trans('Your store name')"
                ></PureInput>
                <PureInputWithAddOn v-model="woocomInput.url" :leftAddOn="{
                        icon: 'fal fa-globe'
                    }" :rightAddOn="{
                        label: '.woocommerce.com'
                    }" @keydown.enter="() => onSubmitWoocommerce()"/>
            </div>

            <Button @click="() => onSubmitWoocommerce()" full label="Create" :loading="!!isLoading" class="mt-6"/>
        </div>
    </Modal>
</template>
