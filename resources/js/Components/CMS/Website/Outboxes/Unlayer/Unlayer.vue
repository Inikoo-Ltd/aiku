<script setup lang="ts">
import { onMounted, ref } from "vue";
import { loadScript, getNextEditorId, useUnlayer } from "./script-loader";
import axios from "axios"
import { notify } from "@kyvg/vue3-notification";

const props = withDefaults(defineProps<{
    updateRoute?: routeType;
    loadRoute?: routeType;
    imagesUploadRoute?: routeType
    mailshot?: object
}>(), {});

const emits = defineEmits(['onSaveToServer']);
// get last editor id
const editorId = getNextEditorId();
let editor = null;
const editorRef = ref(null);
const isUnlayerLoading = ref(true)

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpinnerThird } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faSpinnerThird)


// on mount load editor unlayer

const Store = async (update: any, data: any) => {
    if (!props.updateRoute) return;
    try {
        const response = await axios.patch(
            route(props.updateRoute.name, props.updateRoute.parameters),
            { data: update, pagesHtml: { ...data } },
        );
        emits("onSaveToServer", response?.data?.isDirty);
    } catch (error) {
        console.error("Error saving data:", error);
    }
};

const Load = async () => {
    if (!props.loadRoute) return null;
    try {
        const response = await axios.get(
            route(props.loadRoute.name, props.loadRoute.parameters)
        );
        return response?.data?.html?.design || null;
    } catch (error) {
        console.error("Error loading data:", error);
        notify({
            title: "Failed",
            text: "Failed to get data",
            type: "error",
        });
        return null;
    }
}

const setToNewTemplate = (template) => {
    editor.loadDesign(template)
}

const getMergeTagData = () => {
    return axios.get(route('grp.json.mailshot.merge-tags', { id: props.mailshot.id }))
        .then(response => response.data)
        .catch(error => {
            console.error(error);
            return [];
        });
}


onMounted(async () => {
    await loadScript();

    const editorOptions = {
        id: editorId,
        displayMode: "email",
        features: {
            sendTestEmail: true,
        },
        tools: {
            form: { enabled: false },
            menu: { enabled: true },
            divider: { enabled: true },
        },
        fonts: {
            showDefaultFonts: true,
            customFonts: [
                {
                    label: "Comic Sans",
                    value: "'Comic Sans MS', cursive, sans-serif",
                },
                {
                    label: "Lobster Two",
                    value: "'Lobster Two',cursive",
                    url: "https://fonts.googleapis.com/css?family=Lobster+Two:400,700",
                },
                {
                    label: "Roboto",
                    value: "Roboto",
                    url: "https://fonts.googleapis.com/css2?family=Roboto:ital,wght@1,100&display=swap",
                },
                {
                    label: "Montserrat",
                    value: "Montserrat",
                    url: "https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@1,100&display=swap",
                },
            ],
        },
    };

    editor = unlayer.createEditor(editorOptions);

    editor.addEventListener("design:updated", function (updates) {
        editor.exportHtml((data) => {
            Store(updates, data);
        });
    });

    editor.addEventListener("editor:ready", () => {
        isUnlayerLoading.value = false;
    });

    const loadDesignData = await Load();
    if (loadDesignData) {
        editor.loadDesign(loadDesignData);
    } else {
        console.warn("No design data available to load");
    }

    editor.registerCallback("image", async (file, done) => {
        if (!props.imagesUploadRoute) return;
        try {
            const response = await axios.post(
                route(props.imagesUploadRoute.name, props.imagesUploadRoute.parameters),
                { images: file.attachments },
                { headers: { "Content-Type": "multipart/form-data" } }
            );
            response.data.data.forEach((image: any) => {
                done({ progress: 100, url: image.source.original });
            });
        } catch (error) {
            console.error("Error uploading image:", error);
        }
    });

    editor.setBodyValues({
        fontFamily: {
            label: "Helvetica",
            value: "'Helvetica Neue', Helvetica, Arial, sans-serif",
        },
    });

    editorRef.value = editor;
});

defineExpose({
    editor: editor,
    setToNewTemplate: setToNewTemplate,
    ready: isUnlayerLoading
})
console.log(props,'dsad');

</script>

<template>
    <div v-show="isUnlayerLoading" class="mt-32 md:mt-64">
        <!-- <span>Loading...</span> -->
        <FontAwesomeIcon icon='fad fa-spinner-third' class='block mx-auto h-14 animate-spin' fixed-width aria-hidden='true' />
        <div class="text-center mt-2 text-gray-500">Editor is loading...</div>
    </div>
    <div v-show="!isUnlayerLoading" class="unlayer" :id="editorId"></div>
</template>

<style lang="scss">
.unlayer {
    height: calc(100vh - 177px);
    width: 100%;
}
:deep( .unlayer .blockbuilder-branding) {
    @apply hidden
}
</style>
      