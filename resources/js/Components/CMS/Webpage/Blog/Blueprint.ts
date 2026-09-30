export default {
	blueprint: [
		{
			label: "Author",
			key: ["author"],
			type: "selectquery",
			props_data: {
				mode: "single",
				object: true,
				valueProp: "id",
				labelProp: "name",
				placeholder: "Select a user",
				fetchRoute: {
					name: "grp.json.shop.blog_authors",
					parameters: {
						shop: typeof route === "function" ? route().params["shop"] : null,
					},
				},
			},
			information: "Shown next to the date. Defaults to the person who created the blog.",
		},
		{
			name: "Image",
			key: ["image"],
			replaceForm: [
				{
					key: ["source"],
					label: "Image",
					type: "upload_image",
				},
				{
					key: ["alt"],
					label: "Alternate Text",
					type: "text",
				},
				{
					key: ["properties", "object_fit"],
					label: "Object Image",
					useIn: ["desktop", "tablet", "mobile"],
					type: "select",
					props_data: {
						placeholder: "Object",
						options: [
							{
								label: "contain",
								value: "contain",
							},
							{
								label: "cover",
								value: "cover",
							},
							{
								label: "none",
								value: "none",
							},
							{
								label: "scale-down",
								value: "scale-down",
							},
						],
					},
				},
				{
					key: ["properties", "object_position"],
					label: "Object Position",
					useIn: ["desktop", "tablet", "mobile"],
					type: "select",
					props_data: {
						placeholder: "Object",
						options: [
							{
								label: "Bottom",
								value: "bottom",
							},
							{
								label: "Center",
								value: "center",
							},
							{
								label: "Left",
								value: "left",
							},
							{
								label: "Right",
								value: "right",
							},
							{
								label: "Top",
								value: "top",
							},
							{
								label: "Left Bottom",
								value: "left bottom",
							},
							{
								label: "Left Top",
								value: "left top",
							},
							{
								label: "Right Bottom",
								value: "right bottom",
							},
							{
								label: "Right Top",
								value: "right top",
							},
						],
					},
				},
			],
		},
		{
			name: "Text",
			key: ["properties","text"],
			type: "textProperty",
		},
	],
}
