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
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
				},
				{
                    key: ["shadow"],
                    label : "Shadow",
                    type: "shadow",
                },
                {
                    key: ["shadowColor"],
                    label : "Shadow Color",
                    type: "color",
                },
			],
		},
        {
			name: "Navigation",
			key: ["navigation_container", "properties"],
			replaceForm: [
				{
					key: ["text"],
					type: "textProperty",
				},
			],
		},
         {
			name: "Subnavigation",
			key: ["sub_navigation", "properties"],
			replaceForm: [
				{
					key: ["text"],
					type: "textProperty",
				},
			],
		},
        {
			name: "Link Subnavigation",
			key: ["sub_navigation_link", "properties"],
			replaceForm: [
				{
					key: ["text"],
					type: "textProperty",
				},
			],
		},
	],
}
