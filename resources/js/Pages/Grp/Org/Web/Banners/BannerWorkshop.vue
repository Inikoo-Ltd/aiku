<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { ref, watch, computed } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRedo, faUndo, faUser, faUserFriends } from "@fal"
import { faRocketLaunch } from "@far"
import { faAsterisk } from "@fas"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import BannerWorkshopComponent from "@/Components/Banners/BannerWorkshopComponent.vue"
import Publish from "@/Components/Utils/Publish.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useAutoSave } from "@/Composables/useAutoSave"
import { useBannerHistory } from "@/Composables/useBannerHistory"
import { useBannerHash } from "@/Composables/useBannerHash"
import { useFormatTime } from "@/Composables/useFormatTime"
import type { BannerWorkshop, BannerWorkshopResource } from "@/types/BannerWorkshop"
import type { PageHeadingTypes } from "@/types/PageHeading"
import type { routeType } from "@/types/route"

library.add(faAsterisk, faRocketLaunch, faRedo, faUndo, faUser, faUserFriends)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    banner: BannerWorkshopResource
    imagesUploadRoute: routeType
    autoSaveRoute: routeType
    publishRoute: routeType
    galleryRoute: {
        stock_images: routeType
        uploaded_images: routeType
    }
}>()

const data = ref<BannerWorkshop>(props.banner.compiled_layout)

const exitRoute = computed(() => props.pageHead.actions.find((action) => action.style === "exit")?.route)

const { status, saveDebounced, saveNow, cancelPendingSave } = useAutoSave(props.autoSaveRoute, () => data.value)
const { canUndo, canRedo, undo, redo, flushHistory } = useBannerHistory(data)

watch(data, () => saveDebounced(), { deep: true })

const currentHash = computed(() => {
    try {
        return useBannerHash(data.value)
    } catch {
        return ""
    }
})

const isPublishedHashSame = computed(() => currentHash.value === data.value?.published_hash)
const isBannerEmpty = computed(() => !data.value?.components?.length)
const isPublishing = ref(false)
const comment = ref("")

const publishBanner = () => {
    isPublishing.value = true
    flushHistory()
    cancelPendingSave()

    router.patch(
        route(props.publishRoute.name, props.publishRoute.parameters),
        {
            ...data.value,
            published_hash: currentHash.value,
            ...(props.banner.state !== "unpublished" && { comment: comment.value })
        },
        {
            onSuccess: () => {
                isPublishing.value = false
                notify({
                    title: ctrans("Success"),
                    text: ctrans("Banner published"),
                    type: "success"
                })

                if (exitRoute.value) {
                    router.visit(route(exitRoute.value.name, exitRoute.value.parameters))
                }
            },
            onError: (errors) => {
                isPublishing.value = false
                notify({
                    title: ctrans("Publish failed"),
                    text: Object.values(errors).join(", "),
                    type: "error"
                })
            }
        }
    )
}
</script>

<template>
<Head :title="capitalize(title)" />
<PageHeading :data="pageHead">
    <template #afterTitle2>
        <Button
            v-tooltip="ctrans('Undo (Ctrl+Z)')"
            @click="undo"
            type="tertiary"
            icon="fal fa-undo"
            size="sm"
            :disabled="!canUndo"
        />

        <Button
            v-tooltip="ctrans('Redo (Ctrl+Shift+Z)')"
            @click="redo"
            type="tertiary"
            icon="fal fa-redo"
            size="sm"
            :disabled="!canRedo"
        />

        <Button
            v-tooltip="useFormatTime(banner.updated_at, { formatTime: 'hms' })"
            @click="saveNow"
            type="tertiary"
            :label="ctrans('Save')"
            :icon="status === 'success' ? 'fal fa-check' : 'fas fa-save'"
            size="sm"
            :loading="status === 'loading'"
        />
    </template>

    <template #other>
        <Publish
            v-model="comment"
            :isDataFirstTimeCreated="isBannerEmpty"
            :isHashSame="isPublishedHashSame"
            :isLoading="isPublishing"
            :saveFunction="publishBanner"
            :firstPublish="banner.state === 'unpublished'"
        />
    </template>
</PageHeading>

<section>
    <BannerWorkshopComponent
        v-model="data"
        :imagesUploadRoute="imagesUploadRoute"
        :galleryRoute="galleryRoute"
        :ratio="banner.ratio"
    />
</section>
</template>
