import { ctrans } from "@/Composables/useTrans"

export const seoFieldHelp = (fieldKey: string | null | undefined): string | undefined => {
    switch (fieldKey) {
        case "state":
            return ctrans("Online shows the page to customers. Closed hides it and sends visitors to the webpage you choose instead.")
        case "keywords":
            return ctrans("The 2 or 3 searches this page should be found for, chosen by the SEO team. The meta title, meta description and top description should each contain one of them.")
        case "title":
            return ctrans("The clickable headline Google shows in search results and the text on the browser tab. Aim for 20 to 60 characters, counting the website's prefix and suffix, with a targeted keyword near the start.")
        case "description":
            return ctrans("The short text under the headline in Google results. It is what makes people click. Aim for 70 to 160 characters and include a targeted keyword.")
        case "url":
            return ctrans("The address of the page after the domain. Use lowercase words joined by hyphens, never underscores. Avoid changing it once the page is live, because links and Google results point to the old address.")
        case "breadcrumb_label":
            return ctrans("The short name of this page in the path shown above it, such as Home › Department › Family. Keep it to 40 characters.")
        case "product_name":
            return ctrans("The name customers see on the page and in the shop. Google also shows it for the product in results.")
        case "desc_title":
            return ctrans("The heading shown above the description on the page.")
        case "top_desc":
            return ctrans("The main text customers read at the top of the page. Google and AI assistants read it too, so mention a targeted keyword in the first sentence.")
        case "extra_desc":
            return ctrans("Longer text further down the page, for details such as uses, sizes and care. Useful text written for this page helps it rank.")
        case "faq":
            return ctrans("Questions and answers shown on the page. Real customer questions help Google and AI assistants answer searches with this page.")
        case "seo_image":
            return ctrans("The image shown when the page is shared on social media or in chat apps.")
        case "indexing":
            return ctrans("Whether search engines may list this page (index) and follow its links (follow). Leave both on unless the page must stay out of Google.")
        case "structured_data":
            return ctrans("Code that tells Google exactly what the page is about. The system already writes Product and ProductGroup, so only add extra types here, such as BreadcrumbList or FAQPage.")
        case "price":
            return ctrans("Follow master uses the master price converted to the shop currency. Customise sets a price for this shop only.")
        default:
            return undefined
    }
}
