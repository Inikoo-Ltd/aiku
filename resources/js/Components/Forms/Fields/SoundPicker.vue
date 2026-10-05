<script setup lang='ts'>
import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlay } from '@fas'
import { faCheck, faChevronDown, faVolumeUp, faVolumeMute } from '@fal'
import { ctrans } from '@/Composables/useTrans'

library.add(faPlay, faCheck, faChevronDown, faVolumeUp, faVolumeMute)

const props = defineProps<{
    sound: string
    muted: boolean
    sounds: readonly string[]
    labels: Record<string, string>
    emojis: Record<string, string>
    label: string
    dimmed?: boolean
}>()

const emits = defineEmits<{
    (e: 'update:sound', sound: string): void
    (e: 'update:muted', muted: boolean): void
    (e: 'play'): void
}>()
</script>

<template>
    <div class="flex shrink-0 items-center gap-x-2">
        <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-md border transition-colors"
            :class="muted ? 'border-red-200 bg-red-50 text-red-500 hover:bg-red-100' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
            :aria-pressed="muted"
            :aria-label="muted ? ctrans('Unmute') : ctrans('Mute')"
            v-tooltip="muted ? ctrans('Muted, click to unmute') : ctrans('Mute')"
            @click="emits('update:muted', !muted)">
            <FontAwesomeIcon :icon="muted ? 'fal fa-volume-mute' : 'fal fa-volume-up'" fixed-width aria-hidden="true" />
        </button>

        <div class="flex items-center rounded-md border border-gray-300 bg-white transition-opacity" :class="dimmed || muted ? 'opacity-40' : ''">
            <Listbox :modelValue="sound" @update:modelValue="(picked: string) => { emits('update:sound', picked); emits('play') }">
                <div class="relative">
                    <ListboxButton
                        :aria-label="ctrans('Sound for :type', { type: label })"
                        class="flex w-44 items-center gap-x-2 py-1.5 pl-3 pr-2 text-left text-sm text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <span aria-hidden="true">{{ emojis[sound] }}</span>
                        <span class="flex-1 truncate">{{ labels[sound] }}</span>
                        <FontAwesomeIcon icon="fal fa-chevron-down" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                    </ListboxButton>
                    <transition leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                        <ListboxOptions class="absolute right-0 z-20 mt-1 w-56 overflow-hidden rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-none">
                            <ListboxOption v-for="option in sounds" :key="option" :value="option" v-slot="{ active, selected }">
                                <li class="flex cursor-pointer items-center gap-x-3 px-3 py-2"
                                    :class="[active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700', selected ? 'font-semibold' : '']">
                                    <span class="text-base" aria-hidden="true">{{ emojis[option] }}</span>
                                    <span class="flex-1">{{ labels[option] }}</span>
                                    <FontAwesomeIcon v-if="selected" icon="fal fa-check" class="text-indigo-600" fixed-width aria-hidden="true" />
                                </li>
                            </ListboxOption>
                        </ListboxOptions>
                    </transition>
                </div>
            </Listbox>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center border-l border-gray-300 text-gray-400 hover:bg-gray-50 hover:text-indigo-600"
                :aria-label="ctrans('Play')"
                v-tooltip="ctrans('Play')"
                @click="emits('play')">
                <FontAwesomeIcon icon="fas fa-play" class="text-[10px]" fixed-width aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
