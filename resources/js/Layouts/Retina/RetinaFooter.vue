<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 18 Feb 2024 06:43:19 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, Ref } from 'vue'
import FooterLanguage from '@/Components/Footer/FooterLanguage.vue'
import { usePage } from "@inertiajs/vue3"
import Image from "@/Components/Image.vue"
import { faHeart, faComputerClassic } from '@fas'
import { faDiscord } from '@fortawesome/free-brands-svg-icons'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { useLayoutStore } from '@/Stores/retinaLayout'

const layout = useLayoutStore()

const isTabActive: Ref<boolean | string> = ref(false)
const logoSrc = usePage().props.art?.footer_logo

library.add(faHeart, faComputerClassic, faDiscord)

</script>

<template>
    <footer class="z-20 fixed w-screen bottom-0 left-0  text-white bg-black">
        <div class="flex justify-between">
            <!-- Left: Logo Section -->
            <div class="pl-4 flex items-center gap-x-1.5 py-1">
                <Image class="h-4 select-none hidden md:inline" :src="logoSrc" alt="T-aiku" />
                <span class="text-slate-400	text-xs hidden md:inline">
                    {{ trans('Made with') }}
                    <FontAwesomeIcon icon='fas fa-heart' class="text-red-500 mx-1" aria-hidden='true' />
                    {{ trans('and') }}
                    <FontAwesomeIcon icon='fas fa-computer-classic' class="mx-1" aria-hidden='true' /> {{ 'in KL|Bali' }}
                </span>
            </div>

            <!-- <div class="pl-4 flex items-center gap-x-1.5">
                <a href="https://discord.gg/C7bCmMaTxP" target="_blank">
                    <span class="text-slate-400	 text-xs">
                        <FontAwesomeIcon :icon="['fab', 'discord']" class="text-white mx-1" aria-hidden='true' />
                        <span class="hidden sm:inline">{{ trans("Join our community") }}</span> <span
                            class="hidden lg:inline">{{ trans('announcements/feedback/wishlists') }}</span>
                    </span>
                </a>
            </div> -->

            <!-- Right: Tab Section -->
            <div class="flex items-end text-sm">
                <div v-if="layout.app.environment === 'local'" class="bg-yellow-500 text-gray-700 h-full flex items-center justify-center px-2 mr-10">
                    Local
                </div>
                <FooterLanguage :isTabActive="isTabActive" @isTabActive="(value: any) => isTabActive = value" />
            </div>
        </div>
    </footer>
</template>



