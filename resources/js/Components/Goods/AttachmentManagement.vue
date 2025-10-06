<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faPencil, faUnlink, faUpload, faVideo, faInfoCircle } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Tag from "@/Components/Tag.vue"
import { capitalize } from "lodash"
import { Message } from "primevue"
// Types
import { Image as ImageTS } from "@/types/Image"
import { routeType } from "@/types/route"
import { faStarChristmas } from "@fas"

const props = defineProps<{
    data: {
        editable?: boolean
        id: {}
        images: {}
        bucket_images?: boolean
        images_update_route: routeType
        upload_images_route: routeType
        delete_images_route: routeType
        images_category_box: {
            label: string
            type: string
            column_in_db: string
            url?: string
            images?: ImageTS
            information?: string
            id?: number
        }[]
    }
}>()

// State
console.log(props)

</script>

<template>
   In Progress

</template>

<style scoped>
/* TransitionGroup animations */
.fade-move-enter-active,
.fade-move-leave-active {
    transition: all 0.25s ease;
}

.fade-move-enter-from,
.fade-move-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

.fade-move-move {
    transition: transform 0.25s ease;
}

/* Smooth drag effect */
.dragging {
    opacity: 0.6;
    transform: scale(0.96);
}
</style>
