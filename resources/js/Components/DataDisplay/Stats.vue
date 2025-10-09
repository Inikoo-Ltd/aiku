<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 10 Sept 2022 13:07:27 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

defineProps<{
    stats: {
        name: string
        route?: routeType
        stat?: number
    }[]
}>()
const locale = useLocaleStore();

</script>

<template>
    <div>
        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-3 px-4 pt-4">
            <div v-for="item in stats" :key="item.name" class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">{{ item.name }}</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-600">
                    <Link v-if="item.route" :href="route(item.route.name, item.route.parameters)"
                        :class="[`inline-block primaryLink`]">
                    {{ locale.number(item.stat ?? 0) }}
                    </Link>
                    <span v-else>
                        {{ locale.number(item.stat ?? 0) }}
                    </span>
                </dd>
            </div>
        </dl>
    </div>
</template>
