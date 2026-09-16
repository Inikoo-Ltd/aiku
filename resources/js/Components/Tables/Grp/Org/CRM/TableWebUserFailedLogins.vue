<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import { trans } from 'laravel-vue-i18n'

defineProps<{
    data: object
    tab?: string
}>();
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(failed_at)="{ item: failedLogin }">
            {{ failedLogin.failed_at ? useFormatTime(failedLogin.failed_at) : trans('never') }}
        </template>
    </Table>
</template>
