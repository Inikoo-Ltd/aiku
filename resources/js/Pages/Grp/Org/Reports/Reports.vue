<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 11:37:50 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faExclamationCircle } from "@fortawesome/free-solid-svg-icons";
import { capitalize } from "@/Composables/capitalize";
import { ref, onMounted, onUnmounted } from "vue";
import {
    faFileExport,
    faFileInvoice,
} from "@fal";
import { faAngleDown, faAngleUp } from "@far";
import Dashboard from "@/Components/DataDisplay/Dashboard/DashboardOld.vue";

defineProps<{
    title: string
    pageHead: any
    dashboard_stats: {}
}>();

library.add(
    faExclamationCircle,
    faAngleDown,
    faAngleUp,
    faFileExport,
    faFileInvoice,
);

const isFilterVisible = ref(false);

const handleClickOutside = (event: Event) => {
    const target = event.target as HTMLElement;
    if (!target.closest(".search-container")) {
        isFilterVisible.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="grid grid-cols-12 gap-4 p-4">
        <div class="col-span-12">
            <Dashboard :dashboard="dashboard_stats" />
        </div>
    </div>
</template>
