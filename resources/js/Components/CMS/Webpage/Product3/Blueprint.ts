export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},
		{
			name: "Settings",
			key: ["setting"],
			replaceForm: [
				{
					key: ["product_specs"],
					type: "switch",
					label: "Show Product Specs & Documentations",
					props_data: {},
				},
				{
					key: ["bespoke"],
					type: "switch",
					label: "Bespoke Options",
					props_data: {},
				},
			],
		},
		{
			key: ["bespoke_data"],
			name: "Bespoke Options",
			replaceForm: [
				{
					key: ["title"],
					type: "text",
					label: "Title",
				},
				{
					key: ["text"],
					type: "editorhtml",
					label: "Text",
					props_data: {
						toggle: [
							'fontSize', 'bold', 'italic', 'underline', "fontFamily",
							'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight',
							'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear',
						]
					},
				},
				{
					key: ["link"],
					type: "link",
					label: "Link",
				},
			],
		},
		{
			key: ["delivery_info"],
			name: "Delivery Info",
			replaceForm: [
				{
					key: ["text"],
					type: "editorhtml",
					label: "text",
				},
			],
		},
		{
			name: "Button Add to basket / portofolio",
			key: ["button", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["text"],
					type: "textProperty",
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
				},
			],
		},
		{
			name: "Button login",
			key: ["buttonLogin", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["text"],
					type: "textProperty",
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
				},
			],
		},
	],
}
