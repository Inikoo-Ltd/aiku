<!--
  - Author: Artha <artha@aw-advantage.com>
  - Created: Mon, 08 Jul 2024 14:22:45 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head, router, useForm, usePage} from "@inertiajs/vue3"
import {notify} from "@kyvg/vue3-notification"
import {ref, reactive, onBeforeMount, watch, onBeforeUnmount, computed, inject} from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import {capitalize} from "@/Composables/capitalize"
import {library} from "@fortawesome/fontawesome-svg-core"
import BannerWorkshopComponent from '@/Components/Banners/BannerWorkshopComponent.vue'
import {useLayoutStore} from "@/Stores/layout"
import { cloneDeep , debounce } from "lodash-es"

import {useBannerHash} from "@/Composables/useBannerHash"
import Publish from "@/Components/Utils/Publish.vue"
import { BannerWorkshop, CornersData, SlideWorkshopData } from '@/types/BannerWorkshop'
import { Banner } from '@/types/banner'
import { routeType } from '@/types/route'

import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome"
import {faUser, faUserFriends} from '@fal'
import {faRocketLaunch} from '@far'
import {faAsterisk} from '@fas'
import {faSpinnerThird} from '@fad'
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import ConditionIcon from "@/Components/Utils/ConditionIcon.vue"

library.add(faAsterisk, faRocketLaunch, faUser, faUserFriends, faSpinnerThird);

const props = defineProps<{
    title: string
    pageHead: any
    banner: Banner
    bannerLayout?: BannerWorkshop
    imagesUploadRoute: routeType
    autoSaveRoute: routeType
    publishRoute: routeType
    currentView?: Object
    galleryRoute:{
        stock_images : routeType,
        uploaded_images : routeType
    }
}>()

const layout = inject('layout', layoutStructure)
console.log('props',props)

const user = ref(usePage().props.auth.user)
const isLoading = ref(false)
const comment = ref('')
const loadingState = ref(false)
const isSetData = ref(false)


const routeExit = props.pageHead.actions.find((item) => item.style == "exit")
console.log('cocom', props.banner?.compiled_layout)
const data = reactive(cloneDeep(props.banner?.compiled_layout))
let timeoutId: any

const compCurrentHash = computed(() => {
    return useBannerHash(data)
})

// When click 'Publish'
const sendDataToServer = async () => {
    isLoading.value = true
    const formValues = {
        ...data,
        ...(props.banner.state !== 'unpublished' && {comment: comment.value}),
    }
    const form = useForm(formValues)

    form.patch(
        route(props.publishRoute['name'], props.publishRoute['parameters']), {
            onSuccess: async () => {
                isLoading.value = false
                router.visit(route(routeExit['route']['name'], routeExit['route']['parameters']))
                notify({
                    title: "Success!",
                    type: "success",
                    text: "Banner been updated and published.",
                });
            },
            onError: (errors: any) => {
                console.log(errors)
                notify({
                    title: "Failed to update banner",
                    text: errors,
                    type: "error"
                });
            },
        })
}


const fetchInitialData = async () => {
    try {
        isSetData.value = true
        loadingState.value = true
        Object.assign(data, newData)
    } catch (error) {
        Object.assign(data, cloneDeep(props.banner?.compiled_layout))
    } finally {
        isSetData.value = false
        loadingState.value = false
      /*   if (props.banner.state == 'live') {
            startInterval()
        } */
    }
}


onBeforeMount(fetchInitialData)


const handleKeyDown = () => {
    clearTimeout(timeoutId)
}


const status = ref<null | 'loading' | 'success' | 'error'>(null)
let statusTimeout: ReturnType<typeof setTimeout> | null = null
const setStatus = (newStatus: null | 'loading' | 'success' | 'error') => {
    status.value = newStatus
    if (statusTimeout) clearTimeout(statusTimeout)
    if (newStatus === 'success' || newStatus === 'error') {
        statusTimeout = setTimeout(() => {
            status.value = null
        }, 3000)
    }
}
const saveBanner = () => {
    // Section: Submit
    router.patch(
        route(props.autoSaveRoute.name, props.autoSaveRoute.parameters),
        data,
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                autoSave.cancel()
                // isLoadingSave.value = true
                setStatus('loading')
            },
            onSuccess: () => {
                setStatus('success')
                // notify({
                //     title: trans("Success"),
                //     text: trans("Successfully submit the data"),
                //     type: "success"
                // })
            },
            onError: errors => {
                console.log('errors on save banner', errors)
                setStatus('error')
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to save banner. Try again or contact support."),
                    type: "error"
                })
            },
            onFinish: () => {
                // isLoadingSave.value = false
            },
        }
    )
}

const autoSave = debounce(() => {
    const form = useForm(data);
    console.log('ssss',data)
    form.patch(
        route(props.autoSaveRoute.name, props.autoSaveRoute.parameters), {
            preserveScroll: true,
            onError: (errors: any) => {
                console.log(errors);
            },
        }
    );
}, 3000);

watch(data,autoSave,{deep: true})


const intervalAutoSave = ref(null)


/* const startInterval = () => {
    intervalAutoSave.value = setInterval(() => {
        autoSave();
    }, 600000);
} */

const stopInterval = () => {
    clearInterval(intervalAutoSave.value);
}


const compIsHashSameWithPrevious = computed(() => {
    // Check is current Hash is same with previous Hash (that have been published)
    return compCurrentHash.value == data.published_hash
})

const compIsDataFirstTimeCreated = computed(() => {
    // Check is current Hash is same as initial Hash
    return compCurrentHash.value == "fd186208ae9dab06d40e49141f34bef9"
})


onBeforeUnmount(() => {
    stopInterval()
})

</script>


<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead">
        <template v-if="layout.app.environment === 'local'" #afterTitle2>
            <ConditionIcon v-if="status" :state="status" class="text-xl" />
            <Button
                v-else
                @click="() => saveBanner()"
                type="tertiary"
                :label="trans('Save')"
                icon="fas fa-save"
                size="sm"
                :loading="status === 'loading'"
            />
        </template>

        <template #other="{ dataPageHead: head }">
            <Publish
                v-if="data.components?.length > 0"
                v-model="comment"
                :isDataFirstTimeCreated="compIsDataFirstTimeCreated"
                :isHashSame="compIsHashSameWithPrevious"
                :isLoading="isLoading"
                :saveFunction="sendDataToServer"
                :firstPublish="banner.state == 'unpublished'"
            />
        </template>
    </PageHeading>

    <section>
        <div v-if="loadingState" class="w-full min-h-screen flex justify-center items-center">
            <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin h-12 text-gray-600' aria-hidden='true'/>
        </div>

        <div v-else>
            <BannerWorkshopComponent
                :data="data"
                :imagesUploadRoute="imagesUploadRoute"
                :user="user.username"
                :banner="banner"
                :galleryRoute="galleryRoute"
            />
        </div>

        <!-- <div @click="(e)=>console.log(data)">see data</div> -->
    </section>
</template>
