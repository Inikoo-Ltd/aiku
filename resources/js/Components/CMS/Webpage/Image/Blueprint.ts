import { trans } from "laravel-vue-i18n";
export default {
    blueprint: [
      {
        name: "Images",
        key: ["value"],
        replaceForm: [
          {
            key: ["layout_type"],
            label:"Layout",
            type: "layout_type",
          },
          {
            key: ["layout","properties","dimension"],
            label:"Dimension",
            type: "dimension",
            information: trans("Setup all dimension in all image"),
          },
          {
            label: "Images",
            key: ["images"],
            type: "images-property",
          }
        ],
      },
      {
        name: "Properties",
        key: ["container", "properties"],
        replaceForm: [
          {
            key: ["padding"],
            label:"Padding",
            type: "padding",
          },
          {
            key: ["margin"],
            label:"Margin",
            type: "margin",
          },
          {
            key: ["border"],
            label:"Border",
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
    ]
  }