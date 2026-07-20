<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 22 May 2023 10:35:34 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import ShowcaseContactCard from "@/Components/ShowcaseContactCard.vue"
import BoxDisplay from "@/Components/DataDisplay/BoxDisplay.vue"
import { Agent } from '@/types/Grp/Agent'

defineProps<{
    data: {
        contactCard: Agent
        stats: {
            label: string
            count: number
            description?: string
            full?: boolean
            orther_counts: any
        }[]
    }
}>()
</script>

<template>
    <div class="grid text-gray-600 gap-y-6 md:gap-y-0 md:grid-cols-2 px-4 py-6 gap-x-6">
        <!-- Section 1: Contact -->
        <div>
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                {{ trans('Details') }}
            </h3>
            <ShowcaseContactCard :data="data.contactCard" />
        </div>

        <!-- Section 2: Statistics -->
        <div>
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                {{ trans('Statistics') }}
            </h3>
            <BoxDisplay :data="data.stats" />
        </div>
    </div>
</template>
