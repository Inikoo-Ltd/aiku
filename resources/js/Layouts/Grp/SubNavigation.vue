<script setup lang='ts'>
import { Link } from '@inertiajs/vue3'
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'

const props = defineProps<{
    navigation: {
        name?: string
        label?: string
        slug: string
        icon: string
        route: routeType
    }
    indexNav: number | string
}>()

const layout = inject('layout', layoutStructure)

</script>

<template>
    <Link
        :href="navigation.route?.name ? route(navigation.route.name, navigation.route.parameters) : '#'"
        class="group flex items-center text-sm rounded-md px-3 py-2 w-fit" :class="[
            indexNav === layout.currentModule
                ? 'subNavActive'
                : 'subNav',
            layout.leftSidebar.show ? 'ml-6' : 'text-indigo-500',
        ]" :aria-current="navigation.slug === layout.currentModule ? 'page' : undefined">
        <div class="flex items-center gap-x-2">
            <FontAwesomeIcon v-if="navigation.icon" aria-hidden="true" class="shrink-0 h-4 w-4" :icon="navigation.icon" />
            <span class="capitalize leading-none whitespace-nowrap">
                {{ navigation.name || navigation.label }}
            </span>
        </div>
    </Link>
</template>
