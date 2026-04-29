<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Tue, 04 Nov 2025 10:00:04 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import { Head, Link } from "@inertiajs/vue3";
    import PageHeading from "@/Components/Headings/PageHeading.vue";
    import Table from "@/Components/Table/Table.vue";
    import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
    import Button from "@/Components/Elements/Buttons/Button.vue";
    import { trans } from "laravel-vue-i18n";
    import { library } from "@fortawesome/fontawesome-svg-core";
    import { faTrash, faPencil } from "@fal";

    library.add(faTrash, faPencil);

    defineProps<{
        title: string;
        pageHead: any;
        data: any;
    }>();

</script>


<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <Table :resource="data">
        <template #cell(action)="{ item }">
            <Link
                v-if="item.slug"
                :href="route('grp.trade_units.tags.edit', { tag: item.slug })"
            >
                <Button type="edit" />
            </Link>
        </template>
    </Table>
</template>
