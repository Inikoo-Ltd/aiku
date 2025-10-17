export default {
	blueprint: [
		{
			name: "Container",
			icon: {
				icon: "fal fa-rectangle-wide",
				tooltip: "Container",
			},
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["text"],
					label: "Text",
					type: "textProperty",
				},
			],
		},
		{
			name: "Title",
			key: ["main_title"],
			icon: {
				icon: "fal fa-text",
				tooltip: "Text",
			},
			replaceForm: [
				{
					key: ["text"],
					label: "Text",
					type: "editorhtml",
				},
			],
		},
		{
			name: "Favourite",
			accordion_key: "favourite",
			key: ["favourite"],
			icon: {
				icon: "fal fa-heart",
				tooltip: "Favourite",
			},
			replaceForm: [
				{
					key: ["text"],
					label: "Text",
					type: "select",
					props_data: {
						placeholder: "Select Text",
						by: "value",
						required: true,
						options: [
							{
								label: "Amount (95)",
								value: "{{ favourites_count }}",
							},
							{
								label: "Amount & label (95 favourites)",
								value: "{{ favourites_count }} favourites",
							},
						],
						defaultValue: "{{ favourites_count }} favourites",
					},
				},
			],
		},
		{
			name: "Profile",
			key: ["profile"],
			icon: {
				icon: "fal fa-user",
				tooltip: "Profile",
			},
			replaceForm: [
				{
					key: ["text"],
					label: "Profile label",
					type: "select",
					props_data: {
						placeholder: "Select profile label",
						by: "value",
						required: true,
						options: [
							{
								label: "Name (Aqordeon)",
								value: "{{ name }}",
							},
							{
								label: "Name & reference (Aqordeon #000001)",
								value: "{{ name }} #{{ reference }}",
							},
							{
								label: "Reference (#000001)",
								value: "#{{ reference }}",
							},
						],
						defaultValue: "{{ name }} #{{ reference }}",
					},
				},
			],
		},
		{
			name: "Button",
			key: ["button"],
			icon: {
				icon: "fal fa-rectangle-wide",
				tooltip: "Button",
			},
			replaceForm: [
				{
					key: ["container", "properties", "background"],
					label: "Background",
					type: "background",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "text"],
					type: "textProperty",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					name: "hover",
					key: ["hover"],
					replaceForm: [
						{
							key: ["container", "properties", "background"],
							label: "Background",
							type: "background",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["container", "properties", "text"],
							type: "textProperty",
							useIn: ["desktop", "tablet", "mobile"],
						},
					],
				},
			],
		},
	],
}
