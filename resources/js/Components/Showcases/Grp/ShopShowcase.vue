<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Dashboard from '@/Components/DataDisplay/Dashboard/DashboardOld.vue'



const props = defineProps<{
    data: {
        dashboard_stats: {}
    }
    tab: string
}>()


</script>


<template>
    <div>
        <Dashboard class="p-4"
            :dashboard="data.dashboard_stats"
        />
    </div>
</template>
