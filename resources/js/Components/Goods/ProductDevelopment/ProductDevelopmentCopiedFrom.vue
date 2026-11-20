<!--
  - Author Louis Perez
  - Created on 18-11-2026-09h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCopy } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import type { ChecklistCopiedFrom } from "@/Components/Goods/ProductDevelopment/productDevelopmentChecklist"
library.add(faCopy)

defineProps<{
    copiedFrom: ChecklistCopiedFrom
    forCompliance?: boolean
}>()
</script>

<template>
    <div class="flex items-start gap-2 rounded-md px-3 py-2 text-xs" :class="forCompliance && copiedFrom.includes_compliance ? 'bg-amber-50/70 text-amber-800' : 'bg-gray-50 text-gray-600'">
        <FontAwesomeIcon icon="fal fa-copy" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
        <p class="min-w-0">
            {{ ctrans("Copied from") }}
            <Link :href="route(copiedFrom.route.name, copiedFrom.route.parameters)" class="whitespace-nowrap font-medium underline-offset-2 hover:text-[var(--app-accent)] hover:underline">{{ copiedFrom.code }}</Link><span v-if="copiedFrom.sections.length">: {{ copiedFrom.sections.join(", ") }}</span>.
            <template v-if="forCompliance && copiedFrom.includes_compliance">
                {{ ctrans("Check the copied compliance details still apply to the new packaging, and confirm the pictograms and label marks.") }}
            </template>
        </p>
    </div>
</template>
