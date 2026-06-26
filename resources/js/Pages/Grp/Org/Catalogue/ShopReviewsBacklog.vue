<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TabsBox from "@/Components/Navigation/TabsBox.vue"
import TableReviews from "@/Components/Shop/Reviews/TableReviews.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClock, faReply, faBroadcastTower, faTimesCircle, faTasksAlt } from "@fal"

library.add(faClock, faReply, faBroadcastTower, faTimesCircle, faTasksAlt)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    waiting?: Record<string, any>
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead" />

    <KeepAlive>
        <TabsBox :tabs_box="tabs.navigation" :current="currentTab" @update:tab="handleTabUpdate" />
    </KeepAlive>

    <TableReviews v-if="props[currentTab]" :data="props[currentTab]" :tab="currentTab" />

    <div v-else class="px-6 py-16 text-center text-sm text-gray-400">
        {{ trans("Coming soon") }}
    </div>
</template>
