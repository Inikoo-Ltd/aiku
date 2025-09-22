<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal } from "@fal"
import { computed, defineAsyncComponent, ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TradeUnitShowcase from "@/Components/Goods/TradeUnitShowcase.vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import TradeUnitFamiliesShowcase from "@/Components/Goods/TradeUnitFamiliesShowcase.vue"
library.add(faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal)

const isModalUploadOpen = ref(false)
const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: {
        current: string;
        navigation: Navigation
    }
    showcase?: object,
}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        showcase: TradeUnitFamiliesShowcase,
    }
    return components[currentTab.value]

})

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="currentTab === 'attachments'" @click="() => isModalUploadOpen = true" label="Attach"
                icon="upload" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :tag_routes />
</template>
