const BLOG_CATEGORY_DISPLAY_NAMES: Record<string, string> = {
	newsletters: "David's Travel Blog",
}

export const getBlogCategoryDisplayName = (
	blogCategory?: string | null,
	fallback?: string
): string | undefined => {
	if (!blogCategory) {
		return fallback
	}

	return BLOG_CATEGORY_DISPLAY_NAMES[blogCategory] ?? fallback
}
