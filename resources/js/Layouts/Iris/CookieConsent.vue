<script setup lang="ts">
import { onMounted, reactive, ref, watch } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useCookieConsent, type ConsentChoice } from "@/Composables/useCookieConsent"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCog } from "@fas"
import { faCheck, faTimes } from "@fal"

library.add(faCog, faTimes, faCheck)

const { consent, isSettingsOpen, load, save, acceptAll, rejectAll } = useCookieConsent()
const isReady = ref(false)
const showDetails = ref(false)
const isDismissed = ref(false)
const draft = reactive<ConsentChoice>({ analytics: false, marketing: false, preferences: false })

onMounted(() => {
    load()
    isReady.value = true
})

watch(isSettingsOpen, (open) => {
    if (open) {
        Object.assign(draft, consent.value ?? { analytics: false, marketing: false, preferences: false })
        isDismissed.value = false
    }
})

const categories = [
    { key: "analytics", title: "Performance", text: "Performance cookies show us how visitors use the website, so we can improve it. They do not identify you." },
    { key: "marketing", title: "Targeting", text: "Targeting cookies measure our advertising and show you offers that are relevant to you." },
    { key: "preferences", title: "Functionality", text: "Functionality cookies remember your choices, such as your language." },
] as const

const isOpen = () => (!consent.value || isSettingsOpen.value) && !isDismissed.value

const close = () => {
    isDismissed.value = true
    isSettingsOpen.value = false
}
</script>

<template>
    <template v-if="isReady">
        <div v-if="isOpen()"
            class="fixed bottom-4 left-4 z-[1000] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl bg-[#333] p-8 pt-7 text-white shadow-2xl"
            :class="showDetails ? 'w-[min(40rem,calc(100vw-2rem))]' : 'w-[min(24rem,calc(100vw-2rem))]'"
            role="dialog" aria-live="polite" :aria-label="ctrans('Cookie settings')">
            <button type="button" class="absolute right-4 top-4 text-white/80 hover:text-white" :aria-label="ctrans('Close')" @click="close()">
                <FontAwesomeIcon :icon="['fal', 'times']" class="text-xl" />
            </button>

            <h2 class="pr-6 text-xl font-bold">{{ ctrans("It's time for cookies!") }}</h2>
            <p class="mt-4 text-sm leading-6">
                {{ ctrans("We use cookies to make your interactions with our website more meaningful. They help us better understand how our websites are used, so we can tailor content for you.") }}
            </p>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="flex items-center gap-3 text-sm font-bold uppercase tracking-wider">
                        <span class="flex size-6 items-center justify-center rounded border-2 border-white/30 bg-white/20"><FontAwesomeIcon :icon="['fal', 'check']" /></span>
                        {{ ctrans("Strictly necessary") }}
                    </label>
                    <p v-if="showDetails" class="ml-9 mt-1 text-xs text-white/70">{{ ctrans("Strictly necessary cookies keep the website working, for example your basket and login. The website cannot work without them.") }}</p>
                </div>
                <div v-for="category in categories" :key="category.key">
                    <label class="flex cursor-pointer items-center gap-3 text-sm font-bold uppercase tracking-wider">
                        <input v-model="draft[category.key]" type="checkbox" class="size-6 cursor-pointer rounded border-2 border-white bg-transparent text-[#9dbfdc] focus:ring-0 focus:ring-offset-0" />
                        {{ ctrans(category.title) }}
                    </label>
                    <p v-if="showDetails" class="ml-9 mt-1 text-xs text-white/70">{{ ctrans(category.text) }}</p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" class="w-full rounded-lg bg-[#9dbfdc] py-3 text-sm font-bold uppercase tracking-wider text-white hover:brightness-110" @click="acceptAll()">
                    {{ ctrans("Accept all") }}
                </button>
                <template v-if="showDetails">
                    <button type="button" class="flex-1 rounded-lg border border-white py-3 text-sm font-bold uppercase tracking-wider hover:bg-white/10" @click="rejectAll()">
                        {{ ctrans("Reject all") }}
                    </button>
                    <button type="button" class="flex-1 rounded-lg border border-white py-3 text-sm font-bold uppercase tracking-wider hover:bg-white/10" @click="save({ ...draft })">
                        {{ ctrans("Save and close") }}
                    </button>
                </template>
            </div>
            <button type="button" class="mx-auto mt-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wider hover:text-white/80" @click="showDetails = !showDetails">
                <FontAwesomeIcon :icon="['fas', 'cog']" />
                {{ showDetails ? ctrans("Hide details") : ctrans("Show details") }}
            </button>
        </div>

        <button v-else type="button"
            class="fixed bottom-2 left-2 z-[900] rounded-full bg-[#333] px-3 py-1 text-xs text-white shadow hover:brightness-125"
            @click="isSettingsOpen = true">
            {{ ctrans("Cookie settings") }}
        </button>
    </template>
</template>
