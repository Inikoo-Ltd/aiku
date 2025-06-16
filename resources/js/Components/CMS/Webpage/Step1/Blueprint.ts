import TimelineBlueprint from "./StepBlueprint"

export default {
	blueprint: [
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["padding"],
					label: "Padding",
					type: "padding",
					props_data: {},
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					props_data: {},
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
					props_data: {},
					useIn : ["desktop", "tablet", "mobile"],
				},
			],
		},
		{
			name: "Timeline",
			key: ["timeline"],
			replaceForm: [
				{
					key: ["bullet"],
					name: "Bullet",
					replaceForm: [
						{
							key: ["type"],
							label: "Type",
							type: "select",
							props_data: {
								placeholder: "Select Text",
								by: "value",
								required: true,
								options: [
									{
										label: "Number",
										value: "number",
									},
									{
										label: "Icon",
										value: "icon",
									},
								],
								defaultValue: "number",
							},
						},
						{
							key: ["properties", "background"],
							label: "Background",
							type: "background",
						},
						{
							key: ["properties", "text"],
							type: "textProperty",
						},
					
					],
				},
				{
					key: ["line"],
					name: "Line",
					replaceForm: [
						{
							key: ["properties","background"],
							label: "Background",
							type: "background",
						},
					],
				},
				{
					key: ["timeline_data"],
					name: "Timelines",
					type: "array-data",
					props_data: {
						blueprint: TimelineBlueprint.blueprint,
						order_name: "timeline",
						can_drag: true,
						can_delete: true,
						can_add: true,
						new_value_data: {
							Text: "New Title",
							icon: ["fal", "info-circle"],
						},
					},
				},
			],
		},
	],
}
