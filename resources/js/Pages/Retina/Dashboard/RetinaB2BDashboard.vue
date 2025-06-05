<script setup lang="ts">
const props = defineProps<{
    data: {}
}>()

console.log('RetinaB2BDashboard', props)
</script>

<template>
    <div class="p-8">
        <div>
            <h1 class="text-4xl mb-4">Hello, <span class="font-bold">{{ data?.customer?.contact_name }}</span>!</h1>
            <p>
                Welcome to the <span class="bg-orange-500 px-1 py-0.5 text-white">E-commerce</span> dashboard. Here you can manage your business-to-business operations.
            </p>
        </div>
        
        <!-- resources/js/Pages/Retina/Dashboard/RetinaB2BDashboard.vue
        <pre class="bg-yellow-100">{{ data }}</pre> -->
        
    </div>
</template>
