<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 19 Sep 2023 15:25:16 Malaysia Time, Pantai Lembeng, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import FooterLanguage from '@/Components/Footer/FooterLanguage.vue'
import FooterCurrency from '@/Components/Footer/FooterCurrency.vue'
import { faHeart, faComputerClassic } from '@fas'
import { faDiscord } from '@fortawesome/free-brands-svg-icons'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { computed, inject } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import TimezoneDisplay from './TimezoneDisplay.vue'
import { useFormatTime } from '@/Composables/useFormatTime.js'
import { Link } from '@inertiajs/vue3'


const layout = inject('layout', layoutStructure)

library.add(faHeart, faComputerClassic, faDiscord)

const deploymentTooltip = computed(() => {
    const hash = layout?.app?.last_deployment_hash
    const deployedAt = layout?.app?.last_deployment_at

    return [
        `${layout?.user?.username}@${hash ? hash.slice(0, 7) : '—'}`,
        deployedAt
            ? trans('Deployed') + ' ' + useFormatTime(deployedAt, { formatTime: 'PPpp', timeZone: layout?.user?.timezone })
            : null,
    ].filter(Boolean).join(' · ')
})

</script>

<template>
    <footer class="z-20 fixed w-screen bottom-0 left-0 text-white bg-black transition-all duration-300 ease-in-out" :class="layout?.messagingSidebar?.show ? 'md:pr-56' : (layout?.messagingSidebar?.micro ? 'md:pr-4' : 'md:pr-12')">
        <!-- Helper: Product background (close popup purpose) -->
        <div class="flex justify-between">
            <!-- Left: Logo Section -->
            <div class="pl-4 flex gap-x-4 text-slate-400 text-xs">
                <div class="flex items-center gap-x-1.5">
                    <Link
                        :href="route('grp.deploys')"
                        v-tooltip="deploymentTooltip"
                        class="py-1 font-normal leading-none tabular-nums hover:text-white">
                        {{ layout?.app?.last_deployment_version ?? trans('unreleased') }}
                    </Link>
                    <a href="https://aiku.io/" target="_blank" rel="noopener" aria-label="aiku.io" class="hidden lg:inline">
                        <img class="h-3 select-none inline pl-1 pr-1" src="/art/invader.svg" alt="aiku" />
                    </a>
                    <span class="hidden lg:inline whitespace-nowrap"
                        v-tooltip="trans('With help from the teams in the UK, Spain and Slovakia')">
                        {{ trans('Made with') }}
                        <FontAwesomeIcon icon='fas fa-heart' class="text-pink-500 mx-1" fixed-width aria-hidden='true' />
                        {{ trans('and') }}
                        <FontAwesomeIcon icon='fas fa-computer-classic' class="mx-1" fixed-width aria-hidden='true' /> {{ 'in KL|Bali' }}
                    </span>
                </div>
                
                <div>
                    <TimezoneDisplay />
                </div>
            </div>

            <!-- Right: Tab Section -->
            <div class="flex items-center text-sm mr-4">
                <div id="help-articles" class="h-full">

                </div>
                <div id="clone-from-master-progress" class="h-full">

                </div>
               <!--  <FooterCurrency /> -->
                <FooterLanguage />
            </div>
        </div>
    </footer>
</template>
