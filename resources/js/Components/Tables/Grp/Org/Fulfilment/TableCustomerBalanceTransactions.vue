<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 25 Jan 2024 11:46:16 Malaysia Time, Bali Office, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { faCheck, faTimes, faTimesCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { faCheckCircle } from "@fas"

library.add(faCheck, faTimes, faCheckCircle, faTimesCircle)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore()

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(amount)="{ item: customer }">
            <div class="text-gray-500">
                {{ useLocaleStore().currencyFormat(customer.currency_code, customer.amount) }}
            </div>
        </template>
        <template #cell(running_amount)="{ item: customer }">
            <div class="text-gray-500">
                {{ useLocaleStore().currencyFormat(customer.currency_code, customer.running_amount) }}
            </div>
        </template>
        <template #cell(date)="{ item: customer }">
            <div class="text-right">
                {{
                    useFormatTime(customer.date, {
                        localeCode: locale.language.code,
                        formatTime: "aiku"
                    })
                }}
            </div>
        </template>
    </Table>
</template>
