<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, provide, ref, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import axios from "axios"
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"
import { getWebsiteDialogComponent } from "@/Composables/useWebsiteDialog"
import { isAnnouncementVisible, isPauseOver, isWithinSchedule, useAnnouncementClock } from "@/Iris/Composables/useAnnouncementVisibility"
import {
    browserDismissalStores,
    isOncePerCustomer,
    pickWebsiteDialog,
    rememberWebsiteDialogDismissal,
    websiteDialogUlidFromHref,
    type DismissalStores,
    type IrisWebsiteDialog,
} from "@/Iris/Composables/useWebsiteDialogVisibility"

library.add(faTimes)

const layout = inject("layout", {} as { iris?: { is_logged_in?: boolean } })
const screenType = inject("screenType", ref("desktop"))
provide("screenType", screenType)

const page = usePage()
const now = useAnnouncementClock()

const isMounted = ref(false)
const browserStores = ref<DismissalStores>({ session: null, local: null })
const customerDismissals = ref<Record<string, string | null> | null>(null)
const isLoggedIn = computed(() => !!layout?.iris?.is_logged_in)
const stores = computed<DismissalStores>(() => ({ ...browserStores.value, customer: customerDismissals.value }))

const fetchCustomerDismissals = async () => {
    try {
        const response = await axios.get(route("iris.json.website_dialog_dismissals.index"))
        customerDismissals.value = response.data?.data ?? {}
    } catch {
        customerDismissals.value = {}
    }
}

const saveCustomerDismissal = (dialog: IrisWebsiteDialog) => {
    customerDismissals.value = { ...(customerDismissals.value ?? {}), [dialog.ulid]: dialog.version ?? null }
    axios.post(route("iris.json.website_dialog_dismissals.store", { websiteDialog: dialog.ulid })).catch(() => null)
}
const closedOnThisPage = ref(new Set<string>())

const currentPath = computed(() => (page.url || "").split(/[?#]/)[0])

const candidate = computed<IrisWebsiteDialog | null>(() => {
    if (!isMounted.value) {
        return null
    }

    const dialogs = (page.props?.website_dialogs ?? []) as IrisWebsiteDialog[]
    const pageKey = currentPath.value
    const isWaitingForCustomerDismissals = isLoggedIn.value && customerDismissals.value === null

    return pickWebsiteDialog(
        dialogs.filter(dialog =>
            !closedOnThisPage.value.has(`${dialog.ulid}|${pageKey}`)
            && !(isWaitingForCustomerDismissals && isOncePerCustomer(dialog))
        ),
        dialog => isAnnouncementVisible(dialog, now.value, isLoggedIn.value, pageKey),
        stores.value
    )
})

const shownDialog = ref<IrisWebsiteDialog | null>(null)
const isOpen = ref(false)
const isOpenedByTrigger = ref(false)
let delayTimer: ReturnType<typeof setTimeout> | null = null

const clearDelayTimer = () => {
    if (delayTimer) {
        clearTimeout(delayTimer)
        delayTimer = null
    }
}

watch(
    () => candidate.value ? `${candidate.value.ulid}|${candidate.value.version}|${currentPath.value}` : null,
    (candidateKey) => {
        if (isOpen.value && isOpenedByTrigger.value) {
            return
        }

        clearDelayTimer()

        if (!candidateKey) {
            isOpen.value = false
            return
        }

        if (isOpen.value && shownDialog.value?.ulid === candidate.value?.ulid) {
            return
        }

        const dialog = candidate.value
        const delayMs = Math.max(0, Number(dialog?.settings?.delay_seconds ?? 0)) * 1000

        delayTimer = setTimeout(() => {
            shownDialog.value = dialog
            isOpenedByTrigger.value = false
            isOpen.value = true
        }, delayMs)
    }
)

const openDialogFromTrigger = (ulid: string): boolean => {
    const dialog = ((page.props?.website_dialogs ?? []) as IrisWebsiteDialog[]).find(websiteDialog =>
        websiteDialog.ulid === ulid
        && !!getWebsiteDialogComponent(websiteDialog.component)
        && isWithinSchedule(websiteDialog, now.value)
        && isPauseOver(websiteDialog, now.value)
    )

    if (!dialog) {
        return false
    }

    clearDelayTimer()
    shownDialog.value = dialog
    isOpenedByTrigger.value = true
    isOpen.value = true

    return true
}

const forgetDialogHash = () => {
    if (websiteDialogUlidFromHref(window.location.hash)) {
        history.replaceState(history.state, "", window.location.pathname + window.location.search)
    }
}

const closeDialog = () => {
    const dialog = shownDialog.value
    const wasOpenedByTrigger = isOpenedByTrigger.value
    isOpen.value = false
    isOpenedByTrigger.value = false
    forgetDialogHash()

    if (!dialog || wasOpenedByTrigger) {
        return
    }

    rememberWebsiteDialogDismissal(dialog, stores.value)

    if (isLoggedIn.value && isOncePerCustomer(dialog)) {
        saveCustomerDismissal(dialog)
    }
    closedOnThisPage.value = new Set(closedOnThisPage.value).add(`${dialog.ulid}|${currentPath.value}`)
}

const onClickPanel = (event: MouseEvent) => {
    if ((event.target as HTMLElement | null)?.closest("[data-website-dialog-action]")) {
        closeDialog()
    }
}

const dialogComponent = computed(() => getWebsiteDialogComponent(shownDialog.value?.component))

const onDocumentClick = (event: MouseEvent) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return
    }

    const trigger = (event.target as Element | null)?.closest?.("[data-website-dialog-target], a[href]")
    const ulid = trigger?.getAttribute("data-website-dialog-target") || websiteDialogUlidFromHref(trigger?.getAttribute("href"))

    if (ulid && openDialogFromTrigger(ulid)) {
        event.preventDefault()
    }
}

const openDialogFromHash = () => {
    const ulid = websiteDialogUlidFromHref(window.location.hash)

    if (ulid) {
        openDialogFromTrigger(ulid)
    }
}

watch([isMounted, isLoggedIn], ([mounted, loggedIn]) => {
    if (!mounted) {
        return
    }

    if (!loggedIn) {
        customerDismissals.value = null
        return
    }

    const hasCustomerDialogs = ((page.props?.website_dialogs ?? []) as IrisWebsiteDialog[]).some(isOncePerCustomer)

    if (hasCustomerDialogs) {
        fetchCustomerDismissals()
    } else {
        customerDismissals.value = {}
    }
})

onMounted(() => {
    browserStores.value = browserDismissalStores()
    isMounted.value = true
    document.addEventListener("click", onDocumentClick)
    window.addEventListener("hashchange", openDialogFromHash)
    openDialogFromHash()
})

onBeforeUnmount(() => {
    clearDelayTimer()
    document.removeEventListener("click", onDocumentClick)
    window.removeEventListener("hashchange", openDialogFromHash)
})
</script>

<template>
    <TransitionRoot appear :show="isOpen && !!dialogComponent" as="template">
        <Dialog as="div" class="relative z-[80]" @close="closeDialog">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black/50" aria-hidden="true" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel class="relative max-w-full" @click="onClickPanel">
                            <component :is="dialogComponent" :dialogData="shownDialog" />

                            <button
                                type="button"
                                class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-600 shadow hover:bg-white hover:text-gray-900"
                                :aria-label="ctrans('Close')"
                                @click="closeDialog"
                            >
                                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                            </button>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
