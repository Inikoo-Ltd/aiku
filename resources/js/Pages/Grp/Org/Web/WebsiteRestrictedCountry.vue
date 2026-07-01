<!--
  - Author: stewicca <wiccaalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBan, faList } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Table from "@/Components/Table/Table.vue"
import { ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faBan, faList)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: object
    }
    restricted_countries?: object
    logs?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const flagUrl = (code: string) => `/flags/${code.toLowerCase()}.png`
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <Table
        v-if="currentTab === 'restricted_countries'"
        :resource="restricted_countries"
        :name="currentTab"
        class="mt-5"
    >
        <template #cell(country)="{ item }">
            <div class="flex items-center gap-2">
                <img
                    :src="flagUrl(item.country)"
                    :alt="item.country"
                    class="h-4 w-auto rounded-[2px] shrink-0"
                    loading="lazy"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <span>{{ item.country }}</span>
            </div>
        </template>
    </Table>

    <Table
        v-else-if="currentTab === 'logs'"
        :resource="logs"
        :name="currentTab"
        class="mt-5"
    >
        <template #cell(country)="{ item }">
            <div class="flex items-center gap-2">
                <img
                    :src="flagUrl(item.country)"
                    :alt="item.country"
                    class="h-4 w-auto rounded-[2px] shrink-0"
                    loading="lazy"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <span>{{ item.country }}</span>
            </div>
        </template>
        <template #cell(status)="{ item }">
            <span
                class="relative flex h-3 w-3"
                :title="item.was_blocked ? trans('Blocked') : trans('Allowed')"
            >
                <span
                    v-if="item.was_blocked"
                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
                />
                <span
                    class="relative inline-flex h-3 w-3 rounded-full"
                    :class="item.was_blocked ? 'bg-red-500' : 'bg-green-500'"
                />
            </span>
        </template>
    </Table>
</template>
