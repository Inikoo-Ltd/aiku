<script setup lang='ts'>
import { router } from "@inertiajs/vue3"
import { MenuItem } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faStoreAlt } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { capitalize } from "@/Composables/capitalize"
import { inject, computed, nextTick, watch } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { trans } from "laravel-vue-i18n"
library.add(faStoreAlt)

const props = defineProps<{
    // icon: string | string[]
    navKey: string  // shop | warehouse
    closeMenu: () => void
}>()

const layout = inject('layout', layoutStructure)

// Computed property untuk mengurutkan data berdasarkan alphabet
const sortedShowareList = computed(() => {
    const originalData = layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.[`authorised_${props.navKey}s`] ||
        layout.agents.data.find(agent => agent.slug == layout.currentParams.organisation)?.[`authorised_${props.navKey}s`]

    if (!originalData || !Array.isArray(originalData)) return []

    // Sort berdasarkan label secara alphabetical
    return [...originalData].sort((a, b) => {
        const labelA = (a.label || '').toLowerCase()
        const labelB = (b.label || '').toLowerCase()
        return labelA.localeCompare(labelB, 'en', { numeric: true, sensitivity: 'base' })
    })
})


// Section: to automatically scroll to the active item in the dropdown when it changes
const scrollToActiveItem = () => {
    nextTick(() => {
        const activeItem = document.querySelector('.navigationDropdownActive') as HTMLElement
        if (activeItem) {
            const container = activeItem.closest('.max-h-96') as HTMLElement
            const itemRect = activeItem.getBoundingClientRect()
            const containerRect = container.getBoundingClientRect()

            // Calculate the scroll position needed to center the item in the container
            const itemOffset = itemRect.top - containerRect.top
            const centerOffset = (containerRect.height - itemRect.height) / 2

            // Check if the active item is out of view and calculate the new scrollTop to center it
            if (itemOffset + itemRect.height > containerRect.height) {
                // If the item is below the center, scroll to it
                container.scrollTop = itemOffset - centerOffset
            } else if (itemOffset < 0) {
                // If the item is above the center, scroll to it
                container.scrollTop = itemOffset - centerOffset
            }
        }
    })
}
watch(() => layout.value?.organisationsState, () => {
    scrollToActiveItem()
}, { immediate: true })

</script>

<template>
    <div class="px-1 xpy-1">

        <!-- List -->
        <div class="max-h-96 overflow-y-auto space-y-1.5 pr-1">
            <template v-for="(showare, idxSH) in sortedShowareList" :key="showare.id || idxSH">
                <!-- {{showare}} -->
                <MenuItem v-if="showare.state != 'closed'" v-slot="{ active }" as="div"
                    @click="() => router.visit(route(showare.route?.name, showare.route?.parameters))" :class="[
                        showare.slug == layout.organisationsState?.[layout.currentParams.organisation]?.[`current${capitalize(navKey)}`] && (navKey == layout.organisationsState?.[layout.currentParams.organisation]?.currentType)
                            ? 'navigationDropdownActive'
                            : 'rounded text-slate-600 hover:bg-slate-200/30 cursor-pointer',
                        'group flex gap-x-2 w-full justify-between items-center px-2 py-1.5 text-sm',
                    ]">
                <div class="flex flex-col font-semibold">
                    <div>{{ showare.label }}</div>
                    <div v-if="showare.website_domain" v-tooltip="trans('Website domain')" class="w-fit opacity-60 italic text-xs">
                        {{ showare.website_domain }}
                    </div>
                </div>
                <FontAwesomeIcon v-if="showare.type === 'b2b'" icon='fal fa-fax' fixed-width class='text-sm text-gray-400'
                    v-tooltip="trans('E-commerce')" aria-hidden='true' />
                <FontAwesomeIcon v-if="showare.type === 'dropshipping'" icon='fal fa-parachute-box'
                    fixed-width class='text-sm text-gray-400' v-tooltip="trans('Dropshipping')" aria-hidden='true' />
                <FontAwesomeIcon v-if="showare.type === 'fulfilment'" icon='fal fa-hand-holding-box'
                    fixed-width class='text-sm text-gray-400' v-tooltip="trans('Fulfilment')" aria-hidden='true' />
                <FontAwesomeIcon v-if="showare.type === 'external'" icon='fal fa-store'
                    fixed-width class='text-sm text-gray-400' v-tooltip="trans('External Shop')" aria-hidden='true' />
                </MenuItem>
            </template>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.navigationDropdownActive {
    @apply rounded-r cursor-pointer;
    border-left: v-bind('`3px solid ${layout.app.theme[0]}`');
    background-color: v-bind('`${layout.app.theme[0]}22`');
    color: v-bind('`${layout.app.theme[0]}`');
}
</style>