export const blueprint = (data?: {}, id: number) => {
	const banner = data?.layout?.web_blocks?.find((item) => item.id == id) ?? null
	const banner_data = banner?.web_block?.layout?.data?.fieldValue ?? null

	return {
		blueprint: [
			{
				label: "# Id ",
				key: ["id"],
				type: "text",
				information: "id selector is used to select one unique element!",
			},
			{
				key: ["banner_responsive"],
				label: "Banner",
				type: "select_banner",
				defaultValue: {
					id: banner_data?.banner_id,
					slug: banner_data?.banner_slug,
				},
				useIn: ["desktop", "tablet", "mobile"],
				props_data: {
					fetchRoute: {
						name: "grp.org.shops.show.web.banners.index",
						parameters:
							typeof route === "function" && route().params["organisation"]
								? {
										organisation: route().params["organisation"],
										shop: route().params["shop"],
										website: route().params["website"],
									}
								: {},
					},
				},
			},
			{
				key: ["banner_dimension",'properties','dimension'],
				label: "Banner dimension",
				type: "dimension",
				useIn: ["desktop", "tablet", "mobile"],
			},
			{
				name: "Layout",
				key: ["container", "properties"],
				replaceForm: [
					{
						key: ["padding"],
						label: "Padding",
						type: "padding",
						useIn: ["desktop", "tablet", "mobile"],
					},
					{
						key: ["margin"],
						label: "Margin",
						type: "margin",
						useIn: ["desktop", "tablet", "mobile"],
					},
					{
						key: ["border"],
						label: "Border",
						type: "border",
						useIn: ["desktop", "tablet", "mobile"],
					},
					{
						key: ["shadow"],
						label: "Shadow",
						type: "shadow",
						useIn: ["desktop", "tablet", "mobile"],
					},
					{
						key: ["shadowColor"],
						label: "Shadow Color",
						type: "color",
						useIn: ["desktop", "tablet", "mobile"],
					},
				],
			},
		],
	}
}
