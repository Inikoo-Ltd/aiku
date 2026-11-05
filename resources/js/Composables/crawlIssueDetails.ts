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
        case "noindex_page":
        case "nofollow_page":
            return details.robots_meta ?? ctrans("X-Robots-Tag header")
        case "canonical_to_non_indexable":
            return details.its_canonical
                ? ctrans(":url names :canonical as canonical", { url: pathOfUrl(details.canonical ?? ""), canonical: pathOfUrl(details.its_canonical) })
                : ctrans(":url is noindex", { url: pathOfUrl(details.canonical ?? "") })
        case "sitemap_canonicalised":
            return pathOfUrl(details.canonical ?? "")
        case "soft_404":
            return details.text ?? ""
        case "exact_duplicate":
        case "near_duplicate":
        case "duplicate_h1":
            return ctrans("Same as :count other pages", { count: locale.number(details.duplicates ?? 0) }) + ": " + (details.duplicate_urls ?? []).map(pathOfUrl).join(", ")
        case "low_content":
            return ctrans(":count words", { count: locale.number(details.words ?? 0) })
        case "high_crawl_depth":
            return ctrans(":count clicks from the home page", { count: details.clicks })
        case "title_same_as_h1":
            return details.title ?? ""
        case "url_too_long":
            return ctrans(":length characters", { length: details.length })
        case "sitemap_non_200":
            return details.redirect_to
                ? ctrans(":status to :url", { status: details.status_code ?? "-", url: pathOfUrl(details.redirect_to) })
                : String(details.status_code ?? ctrans("No response"))
        case "in_multiple_sitemaps":
            return ctrans("In :count sitemaps", { count: details.sitemaps })
        case "sitemap_too_large":
            return (details.items ?? []).map((file: { url: string, urls: number, mb: number }) => ctrans(":url: :urls URLs, :mb MB", { url: file.url, urls: locale.number(file.urls), mb: locale.number(file.mb) })).join(", ")
        case "internal_nofollow_links":
        case "non_descriptive_anchor_text":
        case "localhost_links":
        case "unsafe_blank_links":
        case "internal_http_links":
        case "links_with_parameters":
        case "mixed_content":
        case "insecure_forms":
            return examplesText(details, (example) => issueType === "non_descriptive_anchor_text" ? `"${example}"` : formatUrl(issueType)(example))
        case "empty_anchor_text":
            return ctrans(":count links", { count: locale.number(details.count ?? 0) })
        case "images_without_size":
        case "multiple_titles":
        case "multiple_meta_descriptions":
            return ctrans(":count found", { count: locale.number(details.count ?? 0) })
        case "conflicting_canonicals":
        case "head_tags_in_body":
        case "missing_security_headers":
        case "open_graph_incomplete":
        case "pagination_loop":
        case "pagination_not_linked":
            return (details.items ?? []).map(formatUrl(issueType)).join(", ")
        case "broken_external_links":
        case "broken_images":
        case "og_image_broken":
        case "large_images":
        case "pagination_to_broken":
            return resourcesText(issueType, details)
        default:
            return ""
    }
}

const internalLinkIssues = ["internal_nofollow_links", "internal_http_links", "links_with_parameters", "pagination_loop", "pagination_not_linked"]

const formatUrl = (issueType: string) => (value: string) => internalLinkIssues.includes(issueType) && !issueType.startsWith("internal_http") ? pathOfUrl(value) : value

const examplesText = (details: Record<string, any>, format: (example: string) => string) => {
    const locale = useLocaleStore()
    const examples = (details.examples ?? []).map(format).join(", ")
    const more = (details.count ?? 0) - (details.examples ?? []).length

    return more > 0 ? ctrans(":examples and :count more", { examples, count: locale.number(more) }) : examples
}

const resourcesText = (issueType: string, details: Record<string, any>) => {
    const locale = useLocaleStore()
    const items = (details.items ?? []).map((item: { url: string, status_code: number | null, kb: number | null, error: string | null }) => issueType === "large_images"
        ? ctrans(":url (:kb KB)", { url: item.url, kb: locale.number(item.kb ?? 0) })
        : ctrans(":url answers :status", { url: item.url, status: item.status_code ?? item.error ?? "-" }))
    const more = (details.count ?? 0) - items.length

    return more > 0 ? ctrans(":examples and :count more", { examples: items.join(", "), count: locale.number(more) }) : items.join(", ")
}
