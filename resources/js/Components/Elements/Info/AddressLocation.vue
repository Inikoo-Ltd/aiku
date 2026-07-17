<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 15 May 2023 13:12:47 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { capitalize } from "@/Composables/capitalize"

const props = withDefaults(defineProps<{
    data: string[]
    use_flag?: boolean
}>(), {
    use_flag: true
})

const countryCode = props.data?.[0]
const countryName = props.data?.[1]
const addressLocation = props.data?.[2] ?? ''

let flag = null
if (countryCode) {
    flag = '/flags/' + countryCode.toLowerCase() + '.png'
}

</script>

<template>
    <span class="inline-flex items-center">
        <img
            v-if="flag && use_flag"
            class="mr-1 h-[1em] w-auto shrink-0"
            :src="flag"
            :alt="countryCode"
            :title="capitalize(countryName)"
        />
        <span>{{ addressLocation }}</span>
    </span>
</template>


