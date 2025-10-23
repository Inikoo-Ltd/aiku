<script setup lang="ts">
const props = defineProps<{
    interval: string
    data: any
}>()
</script>

<template>
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 px-4">
        <div>
            <div>
                {{ data.visitors?.[interval].formatted_value ?? 0 }}
                <span>Visitors</span>
            </div>
            <div>
                {{ data.registrations?.[interval].formatted_value ?? 0 }}
                <span>New Customers</span>
            </div>
        </div>
        <div>
            <div>
                {{ data.customers_invoiced?.[interval].formatted_value ?? 0 }}
                <span>Last Orders</span>
            </div>
        </div>
    </div>
</template>
