<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import { Link } from '@inertiajs/vue3';

defineProps<{
    data: object
}>()

function customerRoute(customer: any) {
   
}
</script>

<template>
     <Table :resource="data" >
        <template #cell(code)="{ item: customer }">
            <Link :href="customerRoute(customer)" class="primaryLink">
                {{ customer["code"] }}
            </Link>
        </template>
    </Table>
</template>
