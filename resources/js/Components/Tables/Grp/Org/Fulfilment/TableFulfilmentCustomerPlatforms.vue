<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'

defineProps<{
    data: object
}>()
</script>

<template>
     <Table :resource="data" >
        
    </Table>
</template>
