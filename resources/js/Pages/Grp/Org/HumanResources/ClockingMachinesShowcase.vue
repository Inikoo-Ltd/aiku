<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 14:00:48 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { trans } from "laravel-vue-i18n"

defineProps<{
    data: {
        slug: string,
        status: string,
        name: string,
        type: string
    },
}>()
</script>

<template>
    <div class="m-4 w-fit ring-1 ring-gray-300 shadow rounded-2xl p-6">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2">
            <dt class="text-gray-500">{{ trans("Name") }}</dt>
            <dd class="font-medium">{{ data.name }}</dd>
            <dt class="text-gray-500">{{ trans("Type") }}</dt>
            <dd class="font-medium">{{ data.type }}</dd>
            <dt class="text-gray-500">{{ trans("Status") }}</dt>
            <dd class="font-medium">{{ data.status }}</dd>
        </dl>
    </div>
</template>
