import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'

export function useGenerateAIImages({
    customerId,
    onImageGenerated,
}: {
    customerId?: () => string | number | null
    onImageGenerated: (media: any) => void
}) {
    const isGeneratingAI = ref(false)
    const aiGenerateImagesError = ref<string | null>(null)
    const showGenerateProgressModal = ref(false)

    let echoChannel: any = null
    let echoChannelName: string | null = null

    const stopEchoListener = () => {
        if (echoChannelName && window.Echo) {
            window.Echo.leave(echoChannelName)
        }
        echoChannel = null
        echoChannelName = null
    }

    const initEchoListener = (id: string | number): boolean => {
        if (!window.Echo || !id) return false

        stopEchoListener()

        echoChannelName = `retina.image.generation.${id}`
        echoChannel = window.Echo.private(echoChannelName)

        echoChannel.listen('.action-progress', (media: any) => {
            if (!media?.id) return
            onImageGenerated(media)
            showGenerateProgressModal.value = false
            stopEchoListener()
            notify({ title: trans('AI Image Generated'), type: 'success' })
        })

        return true
    }

    const generateAIImages = async ({
        routeName,
        routeParams,
        images,
        prompt,
    }: {
        routeName: string
        routeParams: Record<string, any>
        images: number[]
        prompt: string
    }) => {
        try {
            isGeneratingAI.value = true
            aiGenerateImagesError.value = null

            const id = customerId?.() ?? null
            const usingWebSocket = id ? initEchoListener(id) : false

            if (usingWebSocket) {
                showGenerateProgressModal.value = true
            }

            const res = await axios.post(route(routeName, routeParams), { images, prompt })

            if (!usingWebSocket) {
                const media = res.data?.data
                if (media) {
                    onImageGenerated(media)
                }
                notify({ title: trans('AI Image Generated'), type: 'success' })
            }
        } catch (e) {
            aiGenerateImagesError.value = trans('The OpenAI service is currently unreachable, please try again later.')
            showGenerateProgressModal.value = false
            stopEchoListener()
        } finally {
            isGeneratingAI.value = false
        }
    }

    return {
        isGeneratingAI,
        aiGenerateImagesError,
        showGenerateProgressModal,
        generateAIImages,
        stopEchoListener,
    }
}
