import { trans } from "laravel-vue-i18n"

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
			information: trans('This will apply for all the navigation items'),
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
		{
			name: "Hover",
			information: trans('This will apply when the user hover over the navigation item'),
			key: ["hover", "container", "properties"],
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
					label: "Transition Duration (in ms)",
					key: ["transition", "duration"],
					type: "number",
					props_data: {
						suffix: "ms",
					}
				},
			],
		}
	],
}
