import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

export const pathOfUrl = (url: string) => {
    try {
        const parsed = new URL(url)

        return parsed.pathname + parsed.search
    } catch {
        return url
    }
}

export const crawlIssueDetailText = (issueType: string, details: Record<string, any> | null) => {
    const locale = useLocaleStore()
    details ??= {}

    switch (issueType) {
        case "http_4xx":
        case "http_5xx":
        case "linked_redirect":
            return details.is_in_sitemap ? ctrans("Listed in the sitemap") : ""
        case "fetch_failed":
            return details.error ?? ""
        case "redirect_chain":
            return ctrans(":hops hops, ends at :url", { hops: details.hops, url: pathOfUrl(details.final_url ?? "") })
        case "redirect_loop":
            return (details.chain ?? []).map(pathOfUrl).join(" > ")
        case "duplicate_title":
        case "duplicate_meta_description":
            return ctrans("Same as :count other pages", { count: locale.number(details.duplicates ?? 0) })
        case "title_too_long":
        case "title_too_short":
            return ctrans(":length characters: :title", { length: details.length, title: details.title })
        case "meta_description_too_long":
        case "meta_description_too_short":
            return ctrans(":length characters", { length: details.length })
        case "multiple_h1":
            return ctrans(":count h1 headings", { count: details.h1_count })
        case "images_without_alt":
            return ctrans(":count images", { count: details.images })
        case "slow_response":
            return ctrans(":seconds s", { seconds: locale.number(Math.round((details.response_ms ?? 0) / 100) / 10) })
        case "canonical_to_broken":
            return ctrans(":url answers :status", { url: pathOfUrl(details.canonical ?? ""), status: details.status_code ?? "-" })
        case "canonical_to_other_page":
            return pathOfUrl(details.canonical ?? "")
        case "noindex_in_sitemap":
            return details.robots_meta ?? ""
        case "hreflang_invalid_code":
        case "hreflang_conflicting_code":
            return (details.codes ?? []).join(", ")
        case "hreflang_missing_self":
            return ctrans("Lists :count other versions", { count: locale.number(details.alternates ?? 0) })
        case "hreflang_to_broken":
            return (details.alternates ?? []).map((alternate: { url: string, status_code: number | null }) => ctrans(":url answers :status", { url: alternate.url, status: alternate.status_code ?? "-" })).join(", ")
        case "hreflang_missing_return":
            return (details.alternates ?? []).join(", ")
        case "invalid_structured_data":
            return (details.errors ?? []).join(", ")
        default:
            return ""
    }
}
