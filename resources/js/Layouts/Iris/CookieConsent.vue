<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useCookieConsent, type ConsentCategory, type ConsentChoice } from "@/Composables/useCookieConsent"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCog } from "@fas"
import { faCheck, faChevronDown, faTimes } from "@fal"

library.add(faCog, faTimes, faCheck, faChevronDown)

/* Looks and behaves like the CookieScript banner it replaces (INI-513): a compact dark card
   bottom-left, and "Show details" opens a wide light dialog with a Cookie declaration / About
   cookies tab pair, a switch and a cookie list per category. Opt-in only: nothing is ticked for
   the visitor. */

type CookieRow = { name: string, provider: string, expiration: string, description: string }
type Category = { key: ConsentCategory | "strict", title: string, text: string, cookies: CookieRow[] }

const { consent, isSettingsOpen, load, save, acceptAll, rejectAll } = useCookieConsent()
const isReady = ref(false)
const showDetails = ref(false)
const isDismissed = ref(false)
const hasTicked = ref(false)
const activeTab = ref<"declaration" | "about">("declaration")
const openCookieLists = reactive<Record<string, boolean>>({})
const draft = reactive<ConsentChoice>({ analytics: false, marketing: false, preferences: false })

onMounted(() => {
    load()
    isReady.value = true
})

watch(isSettingsOpen, (open) => {
    if (open) {
        Object.assign(draft, consent.value ?? { analytics: false, marketing: false, preferences: false })
        hasTicked.value = false
        isDismissed.value = false
    }
})

const categories: Category[] = [
    {
        key: "strict",
        title: "Strictly necessary",
        text: "Strictly necessary cookies keep the website working, for example your basket and login. The website cannot work without them.",
        cookies: [
            { name: "PHPSESSID", provider: "{host}", expiration: "Session", description: "Keeps your session between pages, for example your login." },
            { name: "iris_cookie_consent", provider: "{host}", expiration: "6 months", description: "Remembers your cookie choices." },
            { name: "VISITOR_PRIVACY_METADATA", provider: "YouTube (.youtube.com)", expiration: "6 months", description: "Remembers your privacy choices for embedded YouTube videos." },
            { name: "aiku_tsd", provider: "{host}", expiration: "1 year", description: "Cookie associated with system checks." },
            { name: "aiku_lts", provider: "{host}", expiration: "1 year", description: "Cookie associated with system checks." },
            { name: "iris_vua", provider: "{host}", expiration: "1 year", description: "Remembers that you are logged in." },
        ],
    },
    {
        key: "analytics",
        title: "Performance",
        text: "Performance cookies show us how visitors use the website, so we can improve it. They do not identify you.",
        cookies: [
            { name: "_ga", provider: "Google ({domain})", expiration: "1 year 1 month", description: "Google Analytics: tells visits apart with a random number to count visitors and sessions." },
            { name: "_ga_*", provider: "Google ({domain})", expiration: "1 year 1 month", description: "Google Analytics: keeps the state of your visit." },
            { name: "_gid", provider: "Google ({domain})", expiration: "1 day", description: "Google Analytics: counts the pages you view." },
        ],
    },
    {
        key: "marketing",
        title: "Targeting",
        text: "Targeting cookies measure our advertising and show you offers that are relevant to you.",
        cookies: [
            { name: "_gcl_au", provider: "Google ({domain})", expiration: "3 months", description: "Google Ads: measures how well our adverts work." },
            { name: "IDE", provider: "Google (.doubleclick.net)", expiration: "1 year", description: "Google DoubleClick: records the adverts you have seen and how you use the website." },
            { name: "YSC", provider: "YouTube (.youtube.com)", expiration: "Session", description: "YouTube: counts views of embedded videos." },
            { name: "VISITOR_INFO1_LIVE", provider: "YouTube (.youtube.com)", expiration: "6 months", description: "YouTube: remembers your preferences for embedded videos." },
        ],
    },
    {
        key: "preferences",
        title: "Functionality",
        text: "Functionality cookies remember your choices, such as your language.",
        cookies: [
            { name: "_gat_*", provider: "Google ({domain})", expiration: "1 minute", description: "Google Analytics: limits how often data is sent." },
        ],
    },
]

/* Cookie lists come from the CookieScript scan of aw-dropship.eu; the website's own host and
   domain are filled in so they read right on any website. */
const host = typeof window === "undefined" ? "" : window.location.hostname
const providerLabel = (provider: string) => provider.replace("{host}", host).replace("{domain}", "." + host.replace(/^www\./, ""))

const tabs = [
    { key: "declaration", label: "Cookie declaration" },
    { key: "about", label: "About cookies" },
] as const

const optionalCategories = computed(() => categories.filter((category): category is Category & { key: ConsentCategory } => category.key !== "strict"))

const isOpen = () => (!consent.value || isSettingsOpen.value) && !isDismissed.value

const tick = (key: ConsentCategory) => {
    draft[key] = !draft[key]
    hasTicked.value = true
}

const openDetails = () => {
    activeTab.value = "declaration"
    showDetails.value = true
}

const close = () => {
    showDetails.value = false
    isDismissed.value = true
    isSettingsOpen.value = false
}

const finish = (action: () => void) => {
    action()
    showDetails.value = false
}

const fontFamily = "'Open Sans', Arial, 'Trebuchet MS', 'Segoe UI', Helvetica, sans-serif"
</script>

<template>
    <template v-if="isReady">
        <!-- Compact banner -->
        <div v-if="isOpen() && !showDetails"
            class="fixed bottom-0 left-0 z-[1000] max-h-full w-full overflow-y-auto bg-[#1C1C1C] p-[15px] text-left text-[14px] text-white shadow-[1px_2px_8px_0_rgba(0,0,0,0.35)] min-[441px]:bottom-5 min-[441px]:left-5 min-[441px]:max-h-[85%] min-[441px]:w-[300px] min-[441px]:max-w-[calc(100%-40px)] min-[441px]:rounded-[10px] min-[441px]:px-5"
            :style="{ fontFamily }"
            role="dialog" aria-live="polite" :aria-label="ctrans('Cookie settings')">
            <div class="flex justify-end">
                <button type="button" class="-mt-1 text-[29px] font-thin leading-[13px] text-white opacity-[0.85] hover:opacity-100" :aria-label="ctrans('Close')" @click="close()">×</button>
            </div>

            <h2 class="pt-[5px] text-[18px] font-semibold leading-[1.3] tracking-[0.4px] min-[441px]:pt-0">{{ ctrans("It's time for cookies!") }}</h2>
            <p class="mb-2 mt-1 text-[12px] leading-[1.65] tracking-[0.3px] min-[441px]:mb-[10px]">
                {{ ctrans("We use cookies to make your interactions with our website more meaningful. They help us better understand how our websites are used, so we can tailor content for you.") }}
            </p>

            <div class="mb-[15px] flex flex-wrap min-[441px]:mb-[10px] min-[441px]:block">
                <div class="flex w-1/2 items-center gap-3 py-[7px] min-[441px]:w-full">
                    <span class="flex size-[18px] shrink-0 items-center justify-center rounded-[2px] border-2 border-[#5f6368] bg-[#5f6368] text-[#1C1C1C]">
                        <FontAwesomeIcon :icon="['fal', 'check']" class="text-[12px]" />
                    </span>
                    <span class="text-[12px] font-semibold uppercase tracking-[1px]">{{ ctrans("Strictly necessary") }}</span>
                </div>
                <label v-for="category in optionalCategories" :key="category.key"
                    class="flex w-1/2 cursor-pointer items-center gap-3 py-[7px] min-[441px]:w-full">
                    <input type="checkbox" class="peer sr-only" :checked="draft[category.key]" @change="tick(category.key)" />
                    <span class="flex size-[18px] shrink-0 items-center justify-center rounded-[2px] border-2 border-white peer-checked:border-[#8dbad8] peer-checked:bg-[#8dbad8] peer-focus-visible:ring-2 peer-focus-visible:ring-white/60">
                        <FontAwesomeIcon v-if="draft[category.key]" :icon="['fal', 'check']" class="text-[12px] text-white" />
                    </span>
                    <span class="text-[12px] font-semibold uppercase tracking-[1px]">{{ ctrans(category.title) }}</span>
                </label>
            </div>

            <div class="mb-2 flex min-[441px]:mb-0">
                <button v-if="hasTicked" type="button"
                    class="mx-[5px] mb-[13px] flex-grow rounded-[8px] bg-[#8dbad8] px-[7px] text-[12px] font-bold uppercase leading-[3.2] tracking-[0.4px] text-white transition hover:brightness-105"
                    @click="save({ ...draft })">
                    {{ ctrans("Save and close") }}
                </button>
                <button v-else type="button"
                    class="mx-[5px] mb-[13px] flex-grow rounded-[8px] bg-[#8dbad8] px-[7px] text-[12px] font-bold uppercase leading-[3.2] tracking-[0.4px] text-white transition hover:brightness-105"
                    @click="acceptAll()">
                    {{ ctrans("Accept all") }}
                </button>
            </div>

            <button type="button"
                class="mx-auto flex items-center gap-[5px] py-[2px] text-[12px] font-bold uppercase tracking-[0.4px] text-white opacity-[0.85] hover:opacity-100"
                @click="openDetails()">
                <FontAwesomeIcon :icon="['fas', 'cog']" class="text-[13px]" />
                {{ ctrans("Show details") }}
            </button>
        </div>

        <!-- Details dialog -->
        <div v-else-if="isOpen() && showDetails"
            class="fixed inset-0 z-[1000] overflow-y-auto bg-[rgba(17,17,17,0.5)]"
            :style="{ fontFamily }"
            @click.self="showDetails = false" @keydown.esc="showDetails = false">
            <div class="relative mx-auto flex h-full w-full flex-col bg-[#f3f3f3] text-[14px] leading-[18px] text-[#1C1C1C] min-[441px]:top-[5%] min-[441px]:mb-[3%] min-[441px]:h-auto min-[441px]:max-h-[90vh] min-[441px]:w-[95%] min-[441px]:max-w-[950px] min-[441px]:overflow-hidden min-[441px]:rounded-[15px]"
                role="dialog" aria-modal="true" :aria-label="ctrans('Cookie settings')">
                <div class="flex min-h-[57px] w-full items-center justify-end border-b border-[#CCC] bg-white px-5">
                    <button type="button" class="text-[29px] font-thin leading-none text-[#1C1C1C] opacity-[0.85] hover:opacity-100" :aria-label="ctrans('Close')" @click="showDetails = false">×</button>
                </div>

                <div class="w-full bg-white">
                    <div class="max-h-[140px] overflow-y-auto px-5 py-[13px] min-[441px]:max-h-none min-[441px]:px-[53px] min-[441px]:pb-[30px] min-[441px]:pt-5">
                        <h2 class="text-[24px] font-bold leading-[1.3]">{{ ctrans("It's time for cookies!") }}</h2>
                        <p class="mt-[9px] text-[14px] leading-[1.6]">
                            {{ ctrans("We use cookies to make your interactions with our website more meaningful. They help us better understand how our websites are used, so we can tailor content for you.") }}
                        </p>
                    </div>

                    <div class="flex w-full" role="tablist">
                        <button v-for="tab in tabs" :key="tab.key"
                            type="button" role="tab" :aria-selected="activeTab === tab.key"
                            class="flex w-1/2 items-center justify-center border-t-2 p-[6px] text-center text-[12px] font-bold uppercase leading-[1.6] transition-all duration-300 min-[441px]:px-5 min-[441px]:py-[10px] min-[441px]:text-[16px]"
                            :class="activeTab === tab.key ? 'border-[#8dbad8] bg-[#f3f3f3] text-[#8dbad8]' : 'border-transparent bg-white hover:text-[#8dbad8]'"
                            @click="activeTab = tab.key">
                            {{ ctrans(tab.label) }}
                        </button>
                    </div>
                </div>

                <div class="w-full flex-grow overflow-y-auto bg-[#f3f3f3] px-5 pb-[10px] pt-[5px] min-[441px]:px-[50px]">
                    <template v-if="activeTab === 'declaration'">
                        <div v-for="category in categories" :key="category.key" class="flex flex-col border-b border-[#e2e2e2] pb-[17px] pt-[23px]">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex-[9]">
                                    <div class="mb-[6px] text-[16px] font-bold">{{ ctrans(category.title) }}</div>
                                    <div class="pb-[5px] text-[13px] leading-[1.6]">{{ ctrans(category.text) }}</div>
                                </div>
                                <div class="flex flex-1 justify-end">
                                    <button type="button" role="switch"
                                        :aria-checked="category.key === 'strict' ? true : draft[category.key]"
                                        :aria-label="ctrans(category.title)"
                                        :disabled="category.key === 'strict'"
                                        class="relative flex h-5 w-9 shrink-0 items-center disabled:cursor-default"
                                        @click="category.key !== 'strict' && (draft[category.key] = !draft[category.key])">
                                        <span class="h-[14px] w-full rounded-[7px]"
                                            :class="category.key === 'strict' ? 'bg-[#424242]/[0.12]' : draft[category.key] ? 'bg-[#8dbad8]/30' : 'bg-[#e0e0e0]'" />
                                        <span class="absolute top-0 flex size-5 items-center justify-center rounded-full text-white shadow-[0_2px_1px_-1px_rgba(0,0,0,.2),0_1px_1px_0_rgba(0,0,0,.14),0_1px_3px_0_rgba(0,0,0,.12)] transition-transform duration-75"
                                            :class="[
                                                category.key === 'strict' ? 'translate-x-4 bg-[#424242]/40 shadow-none' : draft[category.key] ? 'translate-x-4 bg-[#8dbad8]' : 'translate-x-0 bg-[#616161]',
                                            ]">
                                            <FontAwesomeIcon v-if="category.key === 'strict' || draft[category.key]" :icon="['fal', 'check']" class="text-[11px]" />
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <button type="button"
                                class="mr-auto mt-[10px] flex items-center gap-2 text-[14px] font-bold uppercase text-[#8dbad8]"
                                :aria-expanded="!!openCookieLists[category.key]"
                                @click="openCookieLists[category.key] = !openCookieLists[category.key]">
                                {{ openCookieLists[category.key] ? ctrans("Hide cookies") : ctrans("Show cookies") }}
                                <FontAwesomeIcon :icon="['fal', 'chevron-down']" class="text-[12px] transition-transform" :class="openCookieLists[category.key] ? 'rotate-180' : ''" />
                            </button>

                            <div v-if="openCookieLists[category.key]" class="mt-3 overflow-x-auto">
                                <table class="w-full border-collapse bg-white text-left text-[13px]">
                                    <thead>
                                        <tr>
                                            <th class="whitespace-nowrap px-2 py-2 font-semibold leading-[1.4]">{{ ctrans("Name") }}</th>
                                            <th class="whitespace-nowrap px-2 py-2 font-semibold leading-[1.4]">{{ ctrans("Provider / Domain") }}</th>
                                            <th class="whitespace-nowrap px-2 py-2 font-semibold leading-[1.4]">{{ ctrans("Expiration") }}</th>
                                            <th class="px-2 py-2 font-semibold leading-[1.4]">{{ ctrans("Description") }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="cookie in category.cookies" :key="cookie.name">
                                            <td class="border-t border-[#e2e2e2] px-2 py-[7px] align-top leading-[1.6]">{{ cookie.name }}</td>
                                            <td class="border-t border-[#e2e2e2] px-2 py-[7px] align-top leading-[1.6]">{{ providerLabel(cookie.provider) }}</td>
                                            <td class="whitespace-nowrap border-t border-[#e2e2e2] px-2 py-[7px] align-top leading-[1.6]">{{ ctrans(cookie.expiration) }}</td>
                                            <td class="min-w-[14rem] border-t border-[#e2e2e2] px-2 py-[7px] align-top leading-[1.6]">{{ ctrans(cookie.description) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <div v-else class="min-h-[181px] py-[23px] text-[13px] leading-[1.6]">
                        {{ ctrans("Cookies are small text files that websites place on your computer. Websites use them to help you navigate efficiently and to perform certain functions. Cookies that the website needs to work are set without your permission. All other cookies need your approval before they are set in the browser. You can change your choice at any time with the Cookie settings button at the bottom of the page.") }}
                    </div>
                </div>

                <div class="w-full border-t border-[#e2e2e2] bg-white">
                    <div class="flex flex-wrap px-[15px] py-[5px]">
                        <button type="button"
                            class="mx-[5px] my-[10px] rounded-[8px] border border-[#4d4d4d] bg-white px-[25px] text-[13px] font-bold uppercase leading-[3] tracking-[0.4px] text-[#4d4d4d] hover:bg-[#f3f3f3] max-[440px]:flex-1"
                            @click="finish(acceptAll)">
                            {{ ctrans("Accept all") }}
                        </button>
                        <button type="button"
                            class="mx-[5px] my-[10px] rounded-[8px] border border-[#4d4d4d] bg-white px-[25px] text-[13px] font-bold uppercase leading-[3] tracking-[0.4px] text-[#4d4d4d] hover:bg-[#f3f3f3] max-[440px]:flex-1 min-[441px]:mr-auto"
                            @click="finish(rejectAll)">
                            {{ ctrans("Reject all") }}
                        </button>
                        <button type="button"
                            class="mx-[5px] my-[10px] rounded-[8px] border border-[#8dbad8] bg-[#8dbad8] px-[25px] text-[13px] font-bold uppercase leading-[3] tracking-[0.4px] text-white hover:brightness-105 max-[440px]:w-full"
                            @click="finish(() => save({ ...draft }))">
                            {{ ctrans("Save and close") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <button v-else-if="!isOpen()" type="button"
            class="fixed bottom-2 left-2 z-[900] rounded-full bg-[#1C1C1C] px-3 py-1 text-xs text-white shadow hover:brightness-125"
            :style="{ fontFamily }"
            @click="isSettingsOpen = true">
            {{ ctrans("Cookie settings") }}
        </button>
    </template>
</template>
