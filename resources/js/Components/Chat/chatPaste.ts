import { Fragment, Slice, type Mark, type Schema } from "@tiptap/pm/model"

export const plainTextSlice = (text: string, schema: Schema, marks: readonly Mark[] = []): Slice =>
    new Slice(Fragment.from(
        text.replace(/\r\n?/g, "\n").split("\n")
            .map((line) => schema.nodes.paragraph.create(null, line ? schema.text(line, marks) : null)),
    ), 1, 1)

export const keepBlankLines = (html: string): string =>
    html.replace(/<div\b[^>]*>\s*<br\s*\/?>\s*<\/div>/gi, "<p><br></p>")
