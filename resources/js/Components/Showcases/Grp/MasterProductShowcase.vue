<script setup lang="ts">
import {routeType} from '@/types/route';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faImage} from "@far";
import {faInfoCircle} from "@fas";
import Image from "@/Components/Image.vue";
import Message from "primevue/message";
import {Link} from "@inertiajs/vue3";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import {library} from "@fortawesome/fontawesome-svg-core";
import {trans} from "laravel-vue-i18n";
import {ref} from "vue";
import {faAlbum, faAlbumCollection} from "@fal";

library.add(faAlbumCollection);
</script>

<template>
    <div class="px-4 pb-8 m-5">
        idk what to put
    </div>
</template>
