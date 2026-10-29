import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useEchoGrpPersonal } from "@/Stores/echo-grp-personal"
import type { routeType } from "@/types/route"

export type LinksCheck = { webpage_id: number, is_checking: boolean }

export const useWebpageCheck = (crawlRoute: () => routeType | null, linksCheck: () => LinksCheck | null | undefined) => {
    const echoPersonal = useEchoGrpPersonal()

    const isCrawling = ref(false)
    const isCheckingLinks = computed(() => Boolean(linksCheck()?.is_checking))

    watch(
        () => {
            const webpageId = linksCheck()?.webpage_id

            return webpageId ? echoPersonal.checkedWebpageLinks[webpageId] : undefined
        },
        (result) => {
            if (!result) {
                return
            }

            notify({
                title: ctrans("Links checked"),
                text: result.broken_links
                    ? ctransChoice(":count broken link found|:count broken links found", result.broken_links, { count: result.broken_links })
                    : ctrans("No broken links found"),
                type: result.broken_links ? "warning" : "success",
            })
            router.reload()
        }
    )

    const startCrawl = () => {
        const checkRoute = crawlRoute()
        if (!checkRoute) {
            return
        }

        router.post(route(checkRoute.name, checkRoute.parameters), {}, {
            preserveScroll: true,
            onStart: () => (isCrawling.value = true),
            onSuccess: () => notify({ title: ctrans("Page checked"), text: isCheckingLinks.value ? ctrans("Checking its links now, the page updates when done") : ctrans("The checks show what the page has now"), type: "success" }),
            onError: (errors) => notify({ title: ctrans("Page not checked"), text: Object.values(errors)[0] ?? ctrans("The page could not be checked"), type: "error" }),
            onFinish: () => (isCrawling.value = false),
        })
    }

    return { isCrawling, isCheckingLinks, startCrawl }
}
