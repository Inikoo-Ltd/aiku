<script setup lang="ts">
import Skeleton from 'primevue/skeleton'
import Image from '@/Components/Image.vue'
import { Link } from '@inertiajs/vue3'
import UnderConstruction from '../../Iris/Pages/Disclosure/UnderConstruction.vue'

const props = defineProps<{
    query: string
}>()
</script>

<template>
    <div class="">
        <UnderConstruction />
    </div>
</template>