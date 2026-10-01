<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"


const props = defineProps<{
    data: {}
    tab?: string
}>()

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
    </Table>
</template>
