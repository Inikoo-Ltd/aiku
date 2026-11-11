<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableCompetitors from "@/Components/Tables/Grp/Masters/TableCompetitors.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    masterShopFilter?: string | null
    masterShopOptions: { value: string; label: string; count: number }[]
}>()

const loadingMasterShop = ref<string | null | undefined>(undefined)

const filterByMasterShop = (masterShop: string | null) =>
    router.reload({
        data: { "elements[master_shop]": masterShop ?? undefined, page: 1 },
        preserveScroll: true,
        onStart: () => (loadingMasterShop.value = masterShop),
        onFinish: () => (loadingMasterShop.value = undefined),
    })
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div v-if="masterShopOptions.length > 1" class="mx-4 mt-2 flex flex-wrap items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm">
        <span class="mr-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Master shop") }}</span>
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1 transition duration-200"
            :class="!masterShopFilter ? 'bg-[var(--app-accent)] text-[var(--app-accent-text)] shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
            :disabled="loadingMasterShop !== undefined"
            @click="filterByMasterShop(null)">
            <LoadingIcon v-if="loadingMasterShop === null" />
            {{ ctrans("All") }}
        </button>
        <button
            v-for="option in masterShopOptions"
            :key="option.value"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1 transition duration-200"
            :class="masterShopFilter === option.value ? 'bg-[var(--app-accent)] text-[var(--app-accent-text)] shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
            :disabled="loadingMasterShop !== undefined"
            @click="filterByMasterShop(option.value)">
            <LoadingIcon v-if="loadingMasterShop === option.value" />
            {{ option.label }} <span class="tabular-nums opacity-70">{{ option.count }}</span>
        </button>
    </div>
    <TableCompetitors :data="data" />
</template>
