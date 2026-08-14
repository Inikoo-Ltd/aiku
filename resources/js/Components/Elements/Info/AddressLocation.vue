<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 15 May 2023 13:12:47 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { capitalize } from "@/Composables/capitalize"

const props = withDefaults(defineProps<{
    data: string[]
    use_flag?: boolean
}>(), {
    use_flag: true
})

const countryCode = computed(() => props.data?.[0])
const countryName = computed(() => props.data?.[1])
const addressLocation = computed(() => props.data?.[2] ?? '')

const flag = computed(() => countryCode.value ? '/flags/' + countryCode.value.toLowerCase() + '.png' : null)

</script>

<template>
    <span class="inline-flex items-center">
        <img
            v-if="flag && use_flag"
            class="mr-1 h-[1em] w-auto shrink-0"
            :src="flag"
            :alt="countryCode"
            @error="($event.target as HTMLImageElement).style.display = 'none'"
            :title="capitalize(countryName)"
        />
        <span>{{ addressLocation }}</span>
    </span>
</template>


