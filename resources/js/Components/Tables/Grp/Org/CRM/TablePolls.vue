<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Family } from "@/types/family"
import { routeType } from "@/types/route"
import { remove as loRemove } from 'lodash-es'
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"


const props = defineProps<{
    data: {}
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
    </Table>
</template>
