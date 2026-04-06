<!--
  - Author: stewicca
  - Created: 2026-04-06
  - Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPencil, faTrashAlt } from "@far";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";

library.add(faPencil, faTrashAlt);

defineProps<{
    data: {};
    tab?: string;
}>();
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: brand }">
            <Link :href="route('grp.trade_units.brands.edit', brand.slug)" class="primaryLink">
                {{ brand.reference }}
            </Link>
        </template>

        <template #cell(name)="{ item: brand }">
            <span>{{ brand.name }}</span>
        </template>

        <template #cell(number_models)="{ item: brand }">
            <span class="tabular-nums">{{ brand.number_models }}</span>
        </template>

        <template #cell(actions)="{ item: brand }">
            <div class="flex gap-2 justify-end">
                <Link
                    :href="route('grp.trade_units.brands.edit', brand.slug)"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                >
                    <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                </Link>

                <ModalConfirmationDelete
                    :routeDelete="{
                        name: 'grp.models.brand.delete',
                        parameters: [brand.id],
                    }"
                    :title="`Delete brand &quot;${brand.name}&quot;?`"
                    @success="router.reload()"
                >
                    <template #default="{ changeModel }">
                        <button
                            class="text-red-400 hover:text-red-600"
                            @click="changeModel"
                        >
                            <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
                        </button>
                    </template>
                </ModalConfirmationDelete>
            </div>
        </template>
    </Table>
</template>
