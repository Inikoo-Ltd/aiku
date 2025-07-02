export const blueprint = (productCategory: number) => {
	console.log()
	return {
		blueprint: [
			{
				key: ["price"],
				label: "Price",
				type: "min_max_price",
			},
			{
				key: ["tags"],
				label: "Tags",
				type: "selectquery",
				props_data: {
					mode: "tags",
					valueProp: "id",
					labelProp: "name",
					fetchRoute: {
						name: route().params["organisation"]
							? "grp.json.tags.index"
							: "iris.json.tags.index",
						parameters: route().params["organisation"] ? {} : { productCategory },
					},
				},
			},
			{
				key: ["brands"],
				label: "Brands",
				type: "selectquery",
				props_data: {
					mode: "tags",
					valueProp: "id",
					labelProp: "name",
					fetchRoute: {
						name: route().params["organisation"]
							? "grp.json.brands.index"
							: "iris.json.brands.index",
						parameters: route().params["organisation"] ? {} : { productCategory },
					},
				},
			},
		],
	}
}

export default blueprint
