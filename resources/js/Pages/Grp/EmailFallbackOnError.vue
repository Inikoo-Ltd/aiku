<!--
* Author: Vika Aqordi
* Created on: 2026-05-05 16:07
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'

import BlankLayout from '@/Layouts/BlankLayout.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { faFrown } from '@fal'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faFrown)
defineOptions({ layout: BlankLayout })


const isModalOpen = ref(true)
</script>

<template>

    <Head title="Blank blank blank" />
    <div>
        <Modal :isOpen="isModalOpen" aonClose="isModalNeedToRefresh = false" width="w-full max-w-lg">
            <div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
                <div class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full">
                    <div>
                        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                            <FontAwesomeIcon :icon="faFrown" class="text-red-500 text-2xl" fixed-width
                                aria-hidden="true" />
                        </div>

                        <div class="mt-3 text-center sm:mt-5">
                            <div as="h3" class="font-semibold text-2xl">
                                {{ ctrans("Incorrect URL Detected") }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                {{ ctrans("Please make sure to copy and paste the full URL") }}
                            </div>
                        </div>
                    </div>

                    <!-- <div class="mt-5 sm:mt-6 flex flex-col gap-4">
                        <Button @click="() => onRefreshPage()" :label="ctrans('Refresh page')" full :loading="isLoadingRefreshPage" />
                        <Button @click="() => isModalNeedToRefresh = false" :label="ctrans('Dismiss')" full type="tertiary" />
                    </div> -->
                </div>
            </div>
        </Modal>
    </div>

</template>
