<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 06 Feb 2025 21:46:44 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { inject } from "vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
library.add(faCheck, faTimes)


const props = defineProps<{
    data: {}
    tab?: string
}>()

const locale = inject('locale', {})
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(amount)="{ item }">
            {{ locale.currencyFormat(item.currency_code, item.amount) }}
        </template>

        <template #cell(status)="{ item }">
            <FontAwesomeIcon v-if="item.status === 'success'" v-tooltip="trans('Success')" icon="fal fa-check"
                class="text-green-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-if="item.status === 'fail'" v-tooltip="trans('Failed')" icon="fal fa-times"
                class="text-red-500" fixed-width aria-hidden="true" />
        </template>
    </Table>
</template>
