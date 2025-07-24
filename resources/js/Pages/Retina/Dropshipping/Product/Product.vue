<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, ref, onMounted } from 'vue'
import type { Component } from 'vue'
import axios from 'axios'
import ButtonAddPortfolio from "@/Components/Iris/Products/ButtonAddPortfolio.vue"

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import ProductShowcase from '@/Components/Retina/Storage/Dropshipping/ProductShowcase.vue'
import { notify } from '@kyvg/vue3-notification'

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        showcase: ProductShowcase
        // products: TableProducts
    }

    return components[currentTab.value]

})

const productHasPortfolio = ref({
    isLoading: false,
    list: []
});


const fetchProductHasPortfolio = async () => {
    console.log("Fetching product portfolio for channels...");
    productHasPortfolio.value.isLoading = true;
    try {
        const apiUrl = route('retina.json.dropshipping.product.channels_list', { product: props.showcase.product.data.id })

        if (!apiUrl) {
            throw new Error("Invalid model_type or missing route configuration");
        }

        const response = await axios.get(apiUrl);
        console.log("Product portfolio response:", response);
        productHasPortfolio.value.list = response.data || [];
    } catch (error) {
        console.error(error);
        notify({
            title: "Error",
            text: "Failed to load product portfolio.",
            type: "error"
        });
    } finally {
        productHasPortfolio.value.isLoading = false;
    }
};

onMounted(() => {
    if (route().current() === "retina.catalogue.products.show") {
        fetchProductHasPortfolio();
    }
});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-portofolio>
            <ButtonAddPortfolio :product="props.showcase.product.data" :productHasPortfolio="productHasPortfolio.list"
                :routeToAllPortfolios="{ name: 'retina.models.portfolio.store_to_all_channels', parameters: null }"
                :routeToSpecificChannel="{ name: 'retina.models.portfolio.store_to_multi_channels', parameters: null }" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component 
        :is="component" 
        :data="props[currentTab as keyof typeof props]" 
        :tab="currentTab" 
        :productHasPortfolio="productHasPortfolio"
    />
</template>
