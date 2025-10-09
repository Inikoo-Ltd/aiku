<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"


const props = defineProps<{
    data: {}
    tab?: string
}>()

console.log("Data:", props.data)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
    </Table>
</template>
