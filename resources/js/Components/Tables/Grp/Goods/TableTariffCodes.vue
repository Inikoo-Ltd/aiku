<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { ref } from "vue"
import Table from "@/Components/Table/Table.vue"
import { trans } from "laravel-vue-i18n"

defineProps<{
    data: object
    canEdit: boolean
}>()

const saving = ref<Record<number, boolean>>({})

function saveName(tariffCode: { id: number; name: string | null }, event: Event) {
    const name = (event.target as HTMLInputElement).value.trim() || null
    if (name === (tariffCode.name ?? null)) return
    saving.value[tariffCode.id] = true
    router.patch(
        route("grp.models.tariff_code.update", tariffCode.id),
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (tariffCode.name = name),
            onFinish: () => (saving.value[tariffCode.id] = false),
        }
    )
}
</script>

<template>
    <Table :resource="data" class="mt-5">
        <template #cell(description)="{ item }">
            <span class="text-gray-500 line-clamp-2" :title="item.description">{{ item.description }}</span>
        </template>
        <template #cell(name)="{ item }">
            <input
                v-if="canEdit"
                type="text"
                :value="item.name"
                :placeholder="trans('Add name')"
                :disabled="saving[item.id]"
                class="w-full rounded border-gray-300 text-sm py-1 disabled:opacity-50"
                @change="saveName(item, $event)"
                @keyup.enter="($event.target as HTMLInputElement).blur()"
            />
            <span v-else>{{ item.name }}</span>
        </template>
    </Table>
</template>
