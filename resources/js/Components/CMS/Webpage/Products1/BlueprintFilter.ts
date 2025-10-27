export const blueprint = (productCategory: number) => {
	return {
		blueprint: [
			{
				id: 'price_filter',
				key: ["price_range"],
				label: "Price",
				type: "min_max_price",
			},
			{
				id: 'tags_filter',
				key: ["tags"],
				label: "Tags",
				type: "selectquery",
				props_data: {
					mode: "tags",
					valueProp: "id",
					labelProp: "name",
					fetchRoute: {
						name: typeof route === "function" && route().params["organisation"]
							? "grp.json.tags.index"
							: "iris.json.shops.tags.index",
						parameters: typeof route === "function" && route().params["organisation"] ? {} : { productCategory },
					},
				},
			},
			{
				id: 'brands_filter',
				key: ["brands"],
				label: "Brands",
				type: "selectquery",
				props_data: {
					mode: "tags",
					valueProp: "id",
					labelProp: "name",
					fetchRoute: {
						name: typeof route === "function" && route().params["organisation"]
							? "grp.json.brands.index"
							: "iris.json.shops.brands.index",
						parameters: typeof route === "function" && route().params["organisation"] ? {} : { productCategory },
					},
				},
			},
		],
	}
}

export default blueprint
