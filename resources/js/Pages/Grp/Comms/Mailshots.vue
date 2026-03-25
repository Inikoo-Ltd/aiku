<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faInboxIn, faDumpsterFire, faThumbsDown } from "@fal";
import { ref, onMounted, onUnmounted, watch } from "vue";

library.add(faInboxIn, faDumpsterFire, faThumbsDown);

const props = defineProps<{
    data: any
    title: string
    pageHead: PageHeadingTypes
    groupId: number
}>();

const tableData = ref(props.data);

watch(
    () => props.data,
    (val) => {
        tableData.value = val;
    },
    { deep: false }
);

onMounted(() => {
    if (!props.groupId || !(window as any).Echo) {
        return;
    }

    ;(window as any).Echo
        .private(`grp.${props.groupId}.mailshots`)
        .listen(".mailshot.stats.updated", (e: any) => {
            const mailshotId = e.mailshot_id ?? e.data?.mailshot_id;
            if (!mailshotId) {
                return;
            }

            const summary = e.summary ?? e.data?.summary;
            if (!summary) {
                return;
            }

            const rows = (tableData.value as any)?.data ?? [];
            const row = rows.find((r: any) => r.id === mailshotId);

            if (!row) {
                return;
            }

            row.number_deliveries_success = summary.number_deliveries_success;
            row.number_try_send_success = summary.number_try_send_success;
            row.delivered = summary.delivered;
            row.hard_bounce = summary.hard_bounce;
            row.soft_bounce = summary.soft_bounce;
            row.opened = summary.opened;
            row.clicked = summary.clicked;
            row.spam = summary.spam;
            row.unsubscribed = summary.unsubscribed;
        });
});

onUnmounted(() => {
    if (!props.groupId || !(window as any).Echo) {
        return;
    }

    ;(window as any).Echo
        .private(`grp.${props.groupId}.mailshots`)
        .stopListening(".mailshot.stats.updated");
});
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableMailshots :data="tableData" />
</template>
