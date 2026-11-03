<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import MultiSelect from "primevue/multiselect"
import { ctrans } from "@/Composables/useTrans"

type WorkArea = { id: number, name: string }

const props = defineProps<{
    workAreas: WorkArea[]
    options: WorkArea[]
    route?: { name: string, parameters: object } | null
}>()

const selected = ref<number[]>(props.workAreas.map(workArea => workArea.id))
const saved = ref<number[]>([...selected.value])
const error = ref<string | null>(null)
const saving = ref(false)

watch(() => props.workAreas, workAreas => {
    selected.value = workAreas.map(workArea => workArea.id)
    saved.value = [...selected.value]
})

function save() {
    if (!props.route) return
    const ids = [...selected.value]
    if ([...ids].sort().join(",") === [...saved.value].sort().join(",")) return

    error.value = null
    saving.value = true
    router.patch(route(props.route.name, props.route.parameters), { work_area_ids: ids }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => saved.value = ids,
        onError: errors => {
            error.value = Object.values(errors)[0] as string
            selected.value = [...saved.value]
        },
        onFinish: () => saving.value = false,
    })
}
</script>

<template>
    <div class="min-w-0">
        <MultiSelect
            v-if="route"
            v-model="selected"
            :options="options"
            optionLabel="name"
            optionValue="id"
            display="chip"
            :placeholder="ctrans('No area')"
            :loading="saving"
            :pt="{ root: { class: 'w-full text-xs' }, label: { class: 'text-xs py-1' } }"
            @change="save" />
        <span v-else-if="workAreas.length" class="flex flex-wrap gap-1">
            <span v-for="workArea in workAreas" :key="workArea.id" class="rounded-full bg-gray-100 px-2 py-px text-xs text-gray-600">{{ workArea.name }}</span>
        </span>
        <span v-else class="text-xs text-gray-400">{{ ctrans("No area") }}</span>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
