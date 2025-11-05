<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Tue, 04 Nov 2025 10:00:04 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import { Head } from "@inertiajs/vue3";
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
        pageHeading: [];
        data: any;
    }>();

    function editRoute(tag: any) {
        return route('grp.org.tags.edit', [route().params.organisation, tag.slug]);
    }
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHeading" />
    <Table :resource="data" :name="title">
        <template #cell(action)="{ item }">
            <div class="flex items-center gap-2">
                <a :href="editRoute(item)">
                    <Button
                        v-tooltip="trans('Edit Tag')"
                        type="secondary"
                        icon="fal fa-pencil"
                        size="s"
                    />
                </a>
                <ModalConfirmationDelete
                    :routeDelete="{ name: 'grp.org.tags.delete', parameters: [route().params.organisation, item.id] }"
                    :title="trans('Are you sure you want to delete this tag?')"
                    :noLabel="trans('Delete')"
                    noIcon="fal fa-trash"
                    isFullLoading
                >
                    <template #default="{ isOpenModal, changeModel }">
                        <Button
                            v-tooltip="trans('Delete Tag')"
                            @click="() => changeModel()"
                            type="negative"
                            icon="fal fa-trash"
                            size="s"
                        />
                    </template>
                </ModalConfirmationDelete>
            </div>
        </template>
    </Table>
</template>
