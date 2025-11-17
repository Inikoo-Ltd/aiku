<script setup lang="ts">
import { withDefaults, ref, onMounted } from "vue"
import Chart from "primevue/chart"
import SectionTable from "@/Components/Table/SectionTable.vue";

// Props definition
const props = defineProps<{
	widget: {}
}>()
</script>

<template>
  
	<SectionTable :data="widget.data" />
  
</template>
