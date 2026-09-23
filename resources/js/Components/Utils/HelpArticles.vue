<script setup lang='ts'>
import Modal from '@/Components/Utils/Modal.vue'
import { ref, onMounted } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExternalLinkAlt, faFilm, faLightbulb } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
library.add(faExternalLinkAlt, faFilm, faLightbulb)

const props = defineProps<{
    articles: {
        label: string
        description: string
        url: string
        type?: 'video' | 'article'
    }[]
}>()

const isOpenHelpArticles = ref(false)
const isMounted = ref(false)
onMounted(() => {
    isMounted.value = true
})
</script>

<template>
    <Teleport v-if="isMounted" to="#help-articles">
        <div @click="isOpenHelpArticles = true"
            class="hover:bg-gray-800 cursor-pointer group inline-flex items-center text-yellow-400 px-3 h-full font-medium ">            <!-- Label: Language -->
            <FontAwesomeIcon icon='fal fa-lightbulb' class='text-xs' fixed-width aria-hidden='true' />
            <div class="ml-1 h-full font-extralight text-xs flex items-center gap-x-1 leading-none">
                {{ trans('Help articles') }}
            </div>

        </div>
    </Teleport>

    <Modal :isOpen="isOpenHelpArticles" closeButton @onClose="isOpenHelpArticles = false">
        <div class="font-bold text-lg text-center">
            {{ trans("Help articles") }}
        </div>
        
        <div class="grid grid-cols-1 gap-x-6 gap-y-1 p-4 lg:grid-cols-2">
            <div v-for="(article, idxArticle) in articles" :key="idxArticle" class="border border-gray-200 group relative flex gap-x-6 rounded-lg p-4 hover:bg-gray-100">
                <div v-if="article.type" class="mt-1 flex size-11 flex-none items-center justify-center rounded-lg border border-gray-300 group-hover:bg-gray-200">
                    <FontAwesomeIcon v-if="article.type == 'video'" icon="fal fa-film" class="size-6 text-gray-400 group-hover:text-indigo-500" fixed-width aria-hidden="true" />
                </div>
                
                <div class="grid">
                    <a :href="article.url" target="_blank" tabindex="-1" class="font-semibold group-hover:xtext-indigo-500">
                        {{ article.label }}
                        <span v-tooltip="trans('Open link')" class="absolute inset-0" />
                    </a>
                    <FontAwesomeIcon icon='fal fa-external-link-alt' class='text-gray-400 group-hover:text-gray-600 absolute top-3 right-3' fixed-width aria-hidden='true' />
                    <p class="mt-1 text-gray-500">{{ article.description }}</p>
                </div>
            </div>
        </div>

        <!-- <div class="w-screen max-w-md flex-auto overflow-hidden rounded-3xl bg-white text-sm/6 shadow-lg ring-1 ring-gray-900/5 lg:max-w-3xl">

            <div class="bg-gray-50 px-8 py-6">
                <div class="flex items-center gap-x-3">
                <h3 class="text-sm/6 font-semibold">Enterprise</h3>
                <p class="rounded-full bg-indigo-600/10 px-2.5 py-1.5 text-xs font-semibold text-indigo-600">New</p>
                </div>
                <p class="mt-2 text-sm/6 text-gray-600">Empower your entire team with even more advanced tools.</p>
            </div>
            </div> -->
    </Modal>
</template>