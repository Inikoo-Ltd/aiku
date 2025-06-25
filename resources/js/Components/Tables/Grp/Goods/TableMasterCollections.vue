<script setup lang="ts">
/*
* Author: Vika Aqordi
* Created on: 18-02-2025-09h-16m
* Github: https://github.com/aqordeon
* Copyright: 2025
*/
import Table from '@/Components/Table/Table.vue'


defineProps<{
    data: {

    }
    tab?: string
}>()



</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
    </Table>
</template>
