<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 23 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"

withDefaults(defineProps<{
    days: number | null
    alertAt?: number
    warnAt?: number
}>(), {
    alertAt: 14,
    warnAt: 30,
})
</script>

<template>
    <span v-if="days !== null && days !== undefined"
        :class="{ 'font-medium text-red-600': days <= alertAt, 'text-amber-600': days > alertAt && days <= warnAt }">
        {{ days === 0 ? ctrans("we run out now") : `${ctrans("we run out in")} ~${days} ${ctrans("days")}` }}
    </span>
</template>
