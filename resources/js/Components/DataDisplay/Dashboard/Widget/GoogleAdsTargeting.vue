<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { criterionLabel, criterionTypeLabel, criterionTypeOrder, type Criterion } from "@/Composables/googleAdsCriteria"

/**
 * Who an ad group is aimed at and where its ads may appear, grouped by the kind of criterion so a
 * list of forty rows reads as "these audiences, these ages, these placements". Exclusions sit in the
 * same groups, struck through, because "everyone except these" is one decision, not two lists.
 */
const props = defineProps<{
    adGroups: { id: string; name: string | null; status: string | null; targeting: Criterion[] }[]
}>()

const groups = computed(() =>
    props.adGroups
        .filter((group) => group.targeting.length)
        .map((group) => {
            const byType = new Map<string, Criterion[]>()

            group.targeting.forEach((criterion) => {
                const type = criterion.type ?? "OTHER"

                if (!byType.has(type)) byType.set(type, [])
                byType.get(type)?.push(criterion)
            })

            const types = Array.from(byType.entries()).sort(
                ([a], [b]) => (criterionTypeOrder.indexOf(a) + 1 || 99) - (criterionTypeOrder.indexOf(b) + 1 || 99)
            )

            return { ...group, types }
        })
)
</script>

<template>
    <div class="space-y-4">
        <div v-for="group in groups" :key="group.id" class="rounded-lg p-3 ring-1 ring-gray-100">
            <div class="text-xs font-medium text-gray-700">{{ group.name ?? group.id }}</div>

            <dl class="mt-2 space-y-1.5 text-xs">
                <div v-for="[type, criteria] in group.types" :key="type" class="flex flex-wrap gap-x-3 gap-y-1">
                    <dt class="w-36 shrink-0 text-gray-500">{{ criterionTypeLabel(type) }}</dt>
                    <dd class="flex flex-wrap gap-1">
                        <span
                            v-for="criterion in criteria"
                            :key="criterion.id ?? criterion.label ?? ''"
                            class="rounded px-2 py-0.5"
                            :class="[
                                criterion.negative ? 'bg-[#fdeaea] text-[#d03b3b] line-through' : 'bg-gray-100 text-gray-700',
                                criterion.status === 'PAUSED' ? 'opacity-50' : '',
                            ]"
                            :title="criterion.negative ? trans('Excluded') : criterion.status === 'PAUSED' ? trans('Paused') : ''">
                            {{ criterionLabel(criterion) }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</template>
