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
        pageHeading: any;
        data: any;
    }>();

    const getEditRoute = ({ slug }: { slug: string }) => {
        switch (route().current()) {
            case ('grp.org.shops.show.crm.self_filled_tags.index'):
                return route('grp.org.shops.show.crm.self_filled_tags.edit', { ...route().params, tag: slug });
            case ('grp.org.shops.show.crm.internal_tags.index'):
                return route('grp.org.shops.show.crm.internal_tags.edit', { ...route().params, tag: slug });
            default:
                return '';
        }
    }

    const getDeleteRoute = ({ id }: { id: number }) => {
        switch (route().current()) {
            case ('grp.org.shops.show.crm.self_filled_tags.index'):
                return {
                    name: 'grp.org.shops.show.crm.self_filled_tags.delete',
                    parameters: {
                        ...route().params,
                        tag: id
                    }
                }
            case ('grp.org.shops.show.crm.internal_tags.index'):
            return {
                name: 'grp.org.shops.show.crm.internal_tags.delete',
                parameters: {
                    ...route().params,
                    tag: id
                }
            }
            default:
                return undefined;
        }
    }
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHeading" />
    <Table :resource="data">
        <template #cell(action)="{ item }">
            <div class="flex items-center gap-2">
                 <Link :href="getEditRoute(item)">
                    <Button
                        v-tooltip="trans('Edit Tag')"
                        type="secondary"
                        icon="fal fa-pencil"
                        size="s"
                    />
                </Link>
                <ModalConfirmationDelete
                    :routeDelete="getDeleteRoute(item)"
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
