<template>
    <div class="col-span-2 grid">
        <Switch v-model="isDark"
            :class="[isDark ? 'bg-slate-800' : 'bg-gray-200', 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-1 focus:ring-slate-600 focus:ring-offset-2']">
            <span class="sr-only">Use setting</span>
            <span
                :class="[isDark ? 'translate-x-5  bg-gray-100' : 'translate-x-0 bg-slate-800', 'pointer-events-none relative inline-block h-5 w-5 transform rounded-full shadow ring-0 transition duration-200 ease-in-out']">

                <!-- Light -->
                <span
                    :class="[isDark ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in', 'absolute inset-0 flex h-full w-full items-center justify-center transition-opacity']"
                    aria-hidden="true">
                    <FontAwesomeIcon aria-hidden="true" icon="fa-light fa-moon-stars" class="text-xs text-gray-100" />
                </span>

                <!-- Dark -->
                <span
                    :class="[isDark ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out', 'absolute inset-0 flex h-full w-full items-center justify-center transition-opacity']"
                    aria-hidden="true">
                    <FontAwesomeIcon aria-hidden="true" icon="fa-light fa-lightbulb-on" class="text-xs text-slate-800" />
                </span>
            </span>
        </Switch>
        <div class="text-[8px] leading-none pt-1 text-gray-500 italic">To turn dark mode is on or off.</div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Switch } from '@headlessui/vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMoonStars, faLightbulbOn, faCheck } from '@fal'
library.add(faMoonStars, faLightbulbOn, faCheck)
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
const isDark = ref(false)
</script>
