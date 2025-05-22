<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faUnlink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import { ref } from 'vue'
import EmptyState from '../Utils/EmptyState.vue'
import Image from '../Image.vue'
import { Image as ImageTS } from '@/types/Image'
library.add(faUnlink)

const props = defineProps<{
    data: {
        department: {

        }
        families: {
            id: number
            name: string
            slug: string
            image: ImageTS
            code: string
        }[]
        routes: {
            detach_family: routeType
        }
    }
}>()

// Section: detach family
const isLoadingDelete = ref<string[]>([])
const onDetachFamily = (slug: string) => {
    router.delete(
        route(props.data.routes.detach_family.name, {
            ...props.data.routes.detach_family.parameters,
            family: slug
        }),
        {
            onStart: () => {
                isLoadingDelete.value.push(slug)
            },
            onFinish: () => {
                isLoadingDelete.value = isLoadingDelete.value.filter(item => item !== slug)
            },
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: "Family has been unlinked",
                    type: "success",
                })
            }
        }
    )
}
</script>

<template>
    <div class="">
        <div v-if="data.families?.length" class="mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:max-w-7xl lg:px-8">
            <h2 class="text-xl font-bold">{{ trans("Family list") }} ({{ data.families?.length }})</h2>

            <di class="mt-8 grid grid-cols-1 gap-y-12 sm:grid-cols-2 sm:gap-x-8 lg:grid-cols-4 xl:gap-x-8">
                <div v-for="family in data.families" :key="family.id" class="flex flex-col justify-between">
                    <div class="relative">
                        
                        <div class="relative border border-gray-100 shadow h-72 w-full overflow-hidden rounded-lg">
                            <Image :src="family.image" :alt="family.name" class="object-cover h-full" imageCover />
                        </div>

                        <div class="relative mt-4">
                            <h3 class="font-bold">{{ family.name }}</h3>
                            <!-- <p class="mt-1 text-sm text-gray-500">{{ family.code }}</p> -->
                        </div>

                        <div class="absolute inset-x-0 top-0 flex h-72 items-end justify-end overflow-hidden rounded-lg p-4">
                            <div aria-hidden="true"
                                class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-black opacity-30" />
                            <p class="relative text-lg font-semibold text-white">{{ family.code }}</p>
                        </div>
                    </div>

                    <div class="mt-6 ">
                        <Button @click="onDetachFamily(family.slug)" label="Unlink" type="negative" full icon="fal fa-unlink"
                            :loading="isLoadingDelete.includes(family.slug)"
                            class="w-full"
                        >
                        </Button>
                    </div>
                </div>
            </di>
        </div>
        <div v-else class="mx-auto max-w-2xl px-4 py-4 sm:px-6 lg:max-w-7xl lg:px-8 text-center">
            <EmptyState
                :data="{
                    title: 'No families',
                    description: 'This subdepartment has no families'
                }"
            />
        </div>
    </div>
</template>