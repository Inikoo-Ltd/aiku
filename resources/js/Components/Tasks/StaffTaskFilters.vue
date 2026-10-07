<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { Select } from "primevue"
import { ctrans } from "@/Composables/useTrans"

type TaskFilterKey = "organisation" | "assignee" | "department"
type FilterOption = { value: string; label: string }

const props = defineProps<{
    options: Record<TaskFilterKey, FilterOption[]>
    applied: { organisation: string | null; assignee: string; department: string | null }
}>()

const taskFilters: { key: TaskFilterKey; label: string; allLabel: string; clearable: boolean }[] = [
    { key: "organisation", label: ctrans("Organisation"), allLabel: ctrans("Whole group"), clearable: true },
    { key: "assignee", label: ctrans("Assignee"), allLabel: ctrans("All"), clearable: false },
    { key: "department", label: ctrans("Department"), allLabel: ctrans("All"), clearable: true },
]

const isShown = (key: TaskFilterKey) => key !== "organisation" || (props.options.organisation ?? []).length > 1

const applyTaskFilter = (key: TaskFilterKey, value: string | null) => {
    const query: Record<string, any> = {}
    new URLSearchParams(window.location.search).forEach((paramValue, paramKey) => {
        if (!paramKey.startsWith("filter[") && paramKey !== "page") query[paramKey] = paramValue
    })

    const next: Record<string, string | null> = { ...props.applied, [key]: value }
    if (key === "organisation") next.assignee = props.applied.assignee === "me" || props.applied.assignee === "all" ? props.applied.assignee : "me"

    const filter = Object.fromEntries(Object.entries(next).filter(([, filterValue]) => filterValue !== null && filterValue !== ""))

    router.get(window.location.pathname, { ...query, filter }, { preserveScroll: true })
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <template v-for="taskFilter in taskFilters" :key="taskFilter.key">
            <div v-if="isShown(taskFilter.key)" class="flex items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ taskFilter.label }}</span>
                <Select
                    :modelValue="applied[taskFilter.key]"
                    :options="options[taskFilter.key] ?? []"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="taskFilter.allLabel"
                    :showClear="taskFilter.clearable"
                    :filter="(options[taskFilter.key] ?? []).length > 8"
                    size="small"
                    class="min-w-[9rem]"
                    @update:modelValue="(value) => applyTaskFilter(taskFilter.key, value)" />
            </div>
        </template>
        <slot />
    </div>
</template>
