<script setup lang="ts">
import {Head, router} from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import {capitalize} from "@/Composables/capitalize";
import {inject, ref} from "vue";

import {PageHeading as PageHeadingTypes} from "@/types/PageHeading";
import {Tabs as TSTabs} from "@/types/Tabs";
import Button from "@/Components/Elements/Buttons/Button.vue";
import {routeType} from "@/types/route";

import {trans} from "laravel-vue-i18n";
import Modal from "@/Components/Utils/Modal.vue";
import PureInputWithAddOn from "@/Components/Pure/PureInputWithAddOn.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import {notify} from "@kyvg/vue3-notification";


import {faGlobe, faExternalLinkAlt, faUnlink, faUsers} from "@fal";
import {library} from "@fortawesome/fontawesome-svg-core";
import {layoutStructure} from "@/Composables/useLayoutStructure";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import axios from "axios";

library.add(faGlobe, faExternalLinkAlt, faUnlink, faUsers);

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
        createRoute: routeType
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
    type_ebay: {
        connectRoute: routeType
    }
    type_amazon: {
        connectRoute: routeType
    }
    total_channels: {
        manual: number
        shopify: number
        woocommerce: number
        tiktok: number
        ebay: number
        amazon: number
    }
}>();

const layout = inject("layout", layoutStructure);

const isModalOpen = ref<string | boolean>(false);
const websiteInput = ref<string | null>(null);
const isLoading = ref<string | boolean>(false);
const errorShopify = ref('')
const onCreateStoreShopify = async () => {
    errorShopify.value = ''
    isLoading.value = true
    try {
        const data = await axios['post'](
            route(props.type_shopify.createRoute.name, props.type_shopify.createRoute.parameters),
            {
                name: websiteInput.value
            }
        )
        // console.log("dazzta", data)
        isModalOpen.value = false;
        websiteInput.value = null;
        router.reload({
            only: ['total_channels']
        });
        window.open(data.data, "_blank");

    } catch (error) {
        errorShopify.value = error.response?.data?.message
        notify({
            title: trans("Something went wrong"),
            text: error.response?.data?.message,
            type: "error"
        });
    }
    isLoading.value = false

};

// Section: Woocommerce
const isModalWooCommerce = ref<boolean>(false);
const wooCommerceInput = ref({
    name: null as null | string,
    url: null as null | string
});
const onSubmitWoocommerce = async () => {
    try {
        const response = await axios.post(
            route(props.type_woocommerce?.connectRoute?.name, props.type_woocommerce.connectRoute.parameters),
            wooCommerceInput.value);
        isModalWooCommerce.value = false;
        wooCommerceInput.value.name = null;
        wooCommerceInput.value.url = null;

        window.location.href = response.data;
    } catch (error) {
        console.log("error", error);
        notify({
            title: trans("Something went wrong"),
            text: error.response?.data?.message,
            type: "error"
        });
    };
}

// Section: Manual
const isModalManual = ref(false)
const errManual = ref('')
const manualInput = ref({
    name: null as null | string,
    // url: null as null | string
});
const onSubmitManual = async () => {
    isLoading.value = true;
    try {
        const response = await axios.post(
            route(props.type_manual?.createRoute.name, props.type_manual?.createRoute.parameters),
            manualInput.value);
        isModalManual.value = false;
        manualInput.value.name = null;

        // console.log("response", response.data.slug);
        // window.location.href = response.data.slug;
        notify({
            title: trans("Success!"),
            text: trans("Your Manual store has been created."),
            type: "success",
        })
        router.get(
            route('retina.dropshipping.customer_sales_channels.show', {
                customerSalesChannel: response.data.slug
            })
        )
    } catch (error) {
        errManual.value = error.response?.data?.message
        notify({
            title: trans("Something went wrong"),
            text: error.response?.data?.message,
            type: "error"
        });
    };
    isLoading.value = false;
}

// Section: ebay
const onSubmitEbay = async () => {
    const response = await axios.post(
        route(props.type_ebay.connectRoute.name, props.type_ebay.connectRoute.parameters));

    window.location.href = response.data;
};

// Section: amazon
const onSubmitAmazon = async () => {
    const response = await axios.post(
        route(props.type_amazon.connectRoute.name, props.type_amazon.connectRoute.parameters));

    window.location.href = response.data;
};

</script>

<template>

    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"/>
    <div class="px-6">
        <div class="text-xl py-2 w-fit">E-Commerce</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Section: Manual -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <div
                    class="xhover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://aw.aurora.systems/art/aurora_log_v2_orange.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">{{ trans("Manual") }}</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.manual }} {{ trans("Channels") }}</div>
                    </div>
                </div>

                <div class="w-full flex justify-end">
                    <!-- <ButtonWithLink :routeTarget="type_manual?.createRoute" :label="trans('Create')" full/> -->
                    <Button
                        @click="() => isModalManual = true"
                        :label="trans('Create')"
                        full
                    />
                </div>
            </div>

            <!-- Section: Shopify -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <div
                    class="xhover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/64/5968/5968919.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">Shopify</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.shopify }} {{ trans("Channels") }}</div>
                    </div>
                </div>

                <!-- Button: Connect -->
                <div class="relative w-full">
                    <Button @click="() => isModalOpen = 'shopify'" label="Connect" type="primary" full/>
                </div>
            </div>

            <!-- Section: Tiktok -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <div
                    class="xhover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/64/3046/3046126.png" alt="" class="h-12">
                    <div class="flex flex-col">
                        <div class="font-semibold">Tiktok</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.tiktok }} {{ trans("Channels") }}</div>
                    </div>
                </div>

                <div class="w-full flex justify-end">
                    <a target="_blank" class="w-full" :href="tiktokAuth?.url">
                        <Button v-if="layout?.app?.environment === 'local'"
                                :label="tiktokAuth?.isAuthenticatedExpired ? trans('Re-connect') : trans('Connect')"
                                type="primary" full/>
                        <Button v-else :label="trans('Coming soon')" type="tertiary" disabled full/>
                    </a>

                </div>
            </div>

            <!-- Section: Woocommerce -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <div
                    class="truncate xhover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/512/15466/15466279.png"
                         alt="" class="h-12">

                    <div class="flex flex-col">
                        <div class="font-semibold text-lg">Woocommerce</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.woocommerce }} {{
                                trans("Channels")
                            }}
                        </div>
                    </div>
                </div>

                <div class="w-full flex justify-end">
                    <Button
                        v-if="layout?.app?.environment === 'production'"
                        :label="trans('Connect')"
                        type="primary"
                        full
                        @click="() => isModalWooCommerce = true"
                    />

                    <Button v-else :label="trans('Only in Production')" type="tertiary" disabled full />

                </div>
            </div>
            <!-- Section: Ebay -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <div
                    class="xhover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/512/888/888848.png"
                        alt="" class="h-12"
                        :class="layout?.app?.environment === 'production' ? 'grayscale' : ''"
                    >

                    <div class="flex flex-col">
                        <div class="font-semibold">Ebay</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.ebay }} {{ trans("Channels") }}</div>
                    </div>
                </div>

                <div class="w-full flex justify-end">
                    <Button
                        v-if="layout?.app?.environment === 'local' || layout?.app?.environment === 'staging'"
                        :label="trans('Connect')"
                        type="primary"
                        full
                        @click="onSubmitEbay"
                    />

                    <Button v-else :label="trans('Coming soon')" type="tertiary" disabled full />

                </div>
            </div>

            <!-- Section: Amazon -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col justify-between">
                <div
                    class="xhover:text-orange-500 mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
                    <img src="https://cdn-icons-png.flaticon.com/512/14079/14079391.png"
                        alt="" class="h-12 filter"
                        :class="layout?.app?.environment === 'production' ? 'grayscale' : ''"
                    >

                    <div class="flex flex-col">
                        <div class="font-semibold">Amazon</div>
                        <div class="text-xs text-gray-500">{{ total_channels?.amazon ?? 0 }} {{ trans("Channels") }}</div>
                    </div>
                </div>

                <div class="w-full flex justify-end">
                    <Button
                        v-if="layout?.app?.environment === 'local' || layout?.app?.environment === 'staging'"
                        :label="trans('Connect')"
                        type="primary"
                        full
                        @click="onSubmitAmazon"
                    />

                    <Button v-else :label="trans('Coming soon')" type="tertiary" disabled full />

                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Shopify -->
    <Modal :isOpen="!!isModalOpen" @onClose="isModalOpen = false" width="w-[500px]">
        <div class="h-fit">
            <div class="mb-4">
                <div class="text-center font-semibold text-xl">
                    {{ trans("Select your store name") }}
                </div>

                <div class="text-center text-xs text-gray-500">
                    {{ trans("This is the url that your store can be accessed") }}
                </div>
            </div>

            <PureInputWithAddOn
                v-model="websiteInput"
                @update:model-value="() => errorShopify = ''"
                :leftAddOn="{
                    icon: 'fal fa-globe'
                }"
                :rightAddOn="{
                    label: shopify_url
                }"
                @keydown.enter="() => onCreateStoreShopify()"
            />

            <Transition name="slide-to-right">
                <div v-if="errorShopify" class="text-red-500 italic text-sm mt-2">
                    *{{ errorShopify }}
                </div>
            </Transition>

            <Button @click="() => onCreateStoreShopify()" full label="Create" :loading="!!isLoading" class="mt-6"/>
        </div>
    </Modal>

    <!-- Modal: Manual -->
    <Modal :isOpen="isModalManual" @onClose="isModalManual = false" width="w-full max-w-lg">
        <div class="">
            <div class="mb-4">
                <div class="text-center font-semibold text-xl">
                    {{ trans("WooCommerce store detail") }}
                </div>

                <div class="text-center text-xs text-gray-500">
                    {{ trans("Enter your Woocommerce store detail") }}
                </div>
            </div>

            <div class="flex flex-col gap-y-2" :class="errManual ? 'errorShake' : ''">
                <PureInput
                    v-model="manualInput.name"
                    @update:modelValue="() => errManual = ''"
                    :placeholder="trans('Your store name')"
                    :maxLength="28"
                    @onEnter="() => onSubmitManual()"></PureInput>
            </div>
            
            <div v-if="errManual" class="text-red-500 italic text-sm mt-2" >
                *{{ errManual }}
            </div>

            <Button @click="() => onSubmitManual()" full label="Create" :loading="!!isLoading" class="mt-6"/>
        </div>
    </Modal>

    <!-- Modal: Woocommerce -->
    <Modal :isOpen="isModalWooCommerce" @onClose="isModalWooCommerce = false" width="w-full max-w-lg">
        <div class="">
            <div class="mb-4">
                <div class="text-center font-semibold text-xl">
                    {{ trans("WooCommerce store detail") }}
                </div>

                <div class="text-center text-xs text-gray-500">
                    {{ trans("Enter your Woocommerce store detail") }}
                </div>
            </div>

            <div class="flex flex-col gap-y-2">
                <PureInput v-model="wooCommerceInput.name" :placeholder="trans('Your store name')"></PureInput>
                <PureInputWithAddOn v-model="wooCommerceInput.url" :leftAddOn="{
                    icon: 'fal fa-globe'
                }" :placeholder="trans('e.g https://storeurlexample.com')"
                                    @keydown.enter="() => onSubmitWoocommerce()"/>
            </div>

            <Button @click="() => onSubmitWoocommerce()" full label="Create" :loading="!!isLoading" class="mt-6"/>
        </div>
    </Modal>
</template>
