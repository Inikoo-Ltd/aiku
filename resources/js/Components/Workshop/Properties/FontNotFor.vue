<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 02 Oct 2026 03:40:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    languages?: string[]
}>()

const languageNames = new Intl.DisplayNames(["en"], { type: "language" })
const names = computed(() => (props.languages ?? []).map((code) => languageNames.of(code) ?? code).join(", "))
</script>

<template>
    <span v-if="languages?.length" class="block font-sans text-[10px] leading-tight text-amber-600"
        v-tooltip="ctrans('This font has no letters for these languages; they would show in another font')">
        {{ ctrans('Not for :languages', { languages: names }) }}
    </span>
</template>
