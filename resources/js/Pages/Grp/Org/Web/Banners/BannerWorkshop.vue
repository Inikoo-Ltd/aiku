<script setup lang="ts">
import { Head, router, usePage } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { ref, onBeforeMount, watch, onBeforeUnmount, computed, inject, shallowRef } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import BannerWorkshopComponent from '@/Components/Banners/BannerWorkshopComponent.vue'
import { debounce } from "lodash-es"
import { useBannerHash } from "@/Composables/useBannerHash"
import Publish from "@/Components/Utils/Publish.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUser, faUserFriends } from '@fal'
import { faRocketLaunch } from '@far'
import { faAsterisk } from '@fas'
import { faSpinnerThird } from '@fad'
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faAsterisk, faRocketLaunch, faUser, faUserFriends, faSpinnerThird)

const props = defineProps<{
    title: string
    pageHead: any
    banner: any
    imagesUploadRoute: any
    autoSaveRoute: any
    publishRoute: any
    galleryRoute:{
        stock_images : any,
        uploaded_images : any
    }
}>()

inject('layout', layoutStructure)

const isLoading = ref(false)
const comment = ref("")
const loadingState = ref(true)
const isInitial = ref(true)

const routeExit = props.pageHead.actions.find((i:any) => i.style === "exit")

/*
IMPORTANT:
use shallowRef to avoid Vue deep proxy recursion
DO NOT deep clone compiled_layout
*/
const data = shallowRef<any>({})

onBeforeMount(() => {
    loadingState.value = true
    data.value = props.banner?.compiled_layout || {}

    setTimeout(() => {
        isInitial.value = false
        loadingState.value = false
    }, 50)
})


const compCurrentHash = computed(() => {
    try {
        return useBannerHash(data.value)
    } catch {
        return ""
    }
})


const status = ref<null | 'loading' | 'success' | 'error'>(null)
let statusTimeout:any = null

const setStatus = (s:any) => {
    status.value = s
    if (statusTimeout) clearTimeout(statusTimeout)

    if (s === "success" || s === "error") {
        statusTimeout = setTimeout(() => status.value = null, 2500)
    }
}


const patchAutoSave = () => {
    setStatus("loading")

    router.patch(
        route(props.autoSaveRoute.name, props.autoSaveRoute.parameters),
        data.value,
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setStatus("success"),
            onError: () => {
                setStatus("error")
                notify({
                    title: trans("Save failed"),
                    text: trans("Auto save failed"),
                    type: "error"
                })
            }
        }
    )
}

const autoSave = debounce(patchAutoSave, 2500)

watch(
    () => data.value,
    () => {
        if (isInitial.value) return
        autoSave()
    },
    { deep: true }
)


const saveBanner = () => {
    patchAutoSave()
}


const sendDataToServer = () => {
    isLoading.value = true

    const payload:any = {
        ...data.value,
        ...(props.banner.state !== "unpublished" && { comment: comment.value })
    }

    router.patch(
        route(props.publishRoute.name, props.publishRoute.parameters),
        payload,
        {
            onSuccess: () => {
                isLoading.value = false
                router.visit(route(routeExit.route.name, routeExit.route.parameters))
                notify({
                    title: "Success",
                    text: "Banner published",
                    type: "success"
                })
            },
            onError: (err:any) => {
                isLoading.value = false
                notify({
                    title: "Publish failed",
                    text: err,
                    type: "error"
                })
            }
        }
    )
}


const compIsHashSameWithPrevious = computed(() => {
    return compCurrentHash.value === data.value?.published_hash
})

const compIsDataFirstTimeCreated = computed(() => {
    return compCurrentHash.value === "fd186208ae9dab06d40e49141f34bef9"
})

onBeforeUnmount(() => {
    autoSave.cancel()
})

console.log(props.banner?.compiled_layout)
</script>

<template>
<Head :title="capitalize(title)" />
<PageHeading :data="pageHead">
    <template #afterTitle2>
        <!-- {{ status }} -->
        <!-- <ConditionIcon v-if="status" :state="status" class="text-xl" /> -->

        <Button
            vxlse
            v-tooltip="useFormatTime(banner.updated_at, {formatTime: 'hms'})"
            @click="saveBanner"
            type="tertiary"
            :label="trans('Save')"
            :icon="status === 'success' ? 'fal fa-check' : 'fas fa-save'"
            size="sm"
            :loading="status === 'loading'"
        />
    </template>

    <template #other>
        <Publish
            v-if="data?.components?.length"
            v-model="comment"
            :isDataFirstTimeCreated="compIsDataFirstTimeCreated"
            :isHashSame="compIsHashSameWithPrevious"
            :isLoading="isLoading"
            :saveFunction="sendDataToServer"
            :firstPublish="banner.state === 'unpublished'"
        />
    </template>
</PageHeading>

<section>
    <div v-if="loadingState" class="w-full min-h-screen flex justify-center items-center">
        <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin h-12 text-gray-600'/>
    </div>

    <div v-else>
        <BannerWorkshopComponent
            v-model="data"
            :imagesUploadRoute="imagesUploadRoute"
            :banner="banner"
            :galleryRoute="galleryRoute"
        />
    </div>
</section>
</template>
