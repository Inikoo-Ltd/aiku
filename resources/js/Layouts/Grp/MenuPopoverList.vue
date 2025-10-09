<script setup lang='ts'>
import { router } from "@inertiajs/vue3"
import { MenuItem } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faStoreAlt } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { capitalize } from "@/Composables/capitalize"
import { inject, computed } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { trans } from "laravel-vue-i18n"
library.add(faStoreAlt)

const props = defineProps<{
    icon: string | string[]
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


</script>

<template>
    <div class="px-1 py-1">
        <!-- Show All -->
        <!-- <div @click="() => (router.visit(route(layout.navigation.org[layout.currentParams.organisation][`${navKey}s_index`].route.name, layout.navigation.org[layout.currentParams.organisation][`${navKey}s_index`].route.parameters)), closeMenu())"
            class="flex gap-x-2 items-center pl-2 py-1.5 rounded text-slate-600 hover:bg-slate-200/30 cursor-pointer">
            <FontAwesomeIcon v-if="icon" :icon='icon' class='text-xxs' aria-hidden='true' />
            <span class="text-[9px] leading-none font-semibold">Show all {{ navKey }}s</span>
        </div>
        <hr class="w-11/12 mx-auto border-t border-gray-200 mt-1 mb-1"> -->

        <!-- List -->
        <div class="max-h-52x overflow-y-auto space-y-1.5">
            <template v-for="(showare, idxSH) in sortedShowareList" :key="showare.id || idxSH">
                <!-- {{showare}} -->
                <MenuItem v-if="showare.state != 'closed'" v-slot="{ active }" as="div"
                    @click="() => router.visit(route(showare.route?.name, showare.route?.parameters))" :class="[
                        showare.slug == layout.organisationsState?.[layout.currentParams.organisation]?.[`current${capitalize(navKey)}`] && (navKey == layout.organisationsState?.[layout.currentParams.organisation]?.currentType)
                            ? 'navigationDropdownActive'
                            : 'rounded text-slate-600 hover:bg-slate-200/30 cursor-pointer',
                        'group flex gap-x-2 w-full justify-between items-center px-2 py-2 text-sm',
                    ]">
                <!-- <div class="h-5 rounded-full overflow-hidden ring-1 ring-slate-200 bg-slate-50">
                            <Image v-show="imageSkeleton[idxSH]" :src="item.logo" @onLoadImage="() => imageSkeleton[idxSH] = true"/>
                            <div v-show="!imageSkeleton[idxSH]" class="skeleton w-5 h-5"/>
                        </div> -->
                <div class="font-semibold">{{ showare.label }}</div>
                <FontAwesomeIcon v-if="showare.type === 'b2b'" icon='fal fa-fax' class='text-sm text-gray-400'
                    v-tooltip="trans('E-commerce')" aria-hidden='true' />
                <FontAwesomeIcon v-if="showare.type === 'dropshipping'" icon='fal fa-parachute-box'
                    class='text-sm text-gray-400' v-tooltip="trans('Dropshipping')" aria-hidden='true' />
                <FontAwesomeIcon v-if="showare.type === 'fulfilment'" icon='fal fa-hand-holding-box'
                    class='text-sm text-gray-400' v-tooltip="trans('Fulfilment')" aria-hidden='true' />
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