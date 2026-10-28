<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { ref } from "vue"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import InputText from "primevue/inputtext"

defineProps<{
    data: object
    canEdit: boolean
}>()

const saving = ref<Record<number, boolean>>({})

type NameField = "name" | "name_sk" | "name_es"

function saveName(tariffCode: Record<NameField, string | null> & { id: number }, field: NameField, event: Event) {
    const value = (event.target as HTMLInputElement).value.trim() || null
    if (value === (tariffCode[field] ?? null)) return
    saving.value[tariffCode.id] = true
    router.patch(
        route("grp.models.tariff_code.update", tariffCode.id),
        { [field]: value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (tariffCode[field] = value),
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
        <template v-for="field in (['name', 'name_sk', 'name_es'] as const)" :key="field" #[`cell(${field})`]="{ item }">
            <InputText
                v-if="canEdit"
                :modelValue="item[field]"
                :placeholder="field === 'name' ? ctrans('Add name') : item.name ?? ''"
                :disabled="saving[item.id]"
                size="small"
                fluid
                @change="saveName(item, field, $event)"
                @keyup.enter="($event.target as HTMLInputElement).blur()"
            />
            <span v-else>{{ item[field] }}</span>
        </template>
    </Table>
</template>
