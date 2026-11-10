import SwiftUI

struct FulfilmentIncidentSheet: View {
    let title: String
    let subtitle: String
    let kinds: [FulfilmentIncidentKind]
    let helper: String
    let confirmTitle: (FulfilmentIncidentKind) -> String
    let onSave: (FulfilmentIncidentKind, String) -> Void
    let onCancel: () -> Void
    @State private var kind: FulfilmentIncidentKind = .damaged
    @State private var note = ""

    var body: some View {
        SheetScaffold(title: title, subtitle: subtitle) {
            ForEach(kinds, id: \.self) { option in
                RadioCard(option.title, subtitle: option.explanation, isSelected: kind == option) {
                    kind = option
                }
            }
            AikuTextArea("What happened", text: $note, placeholder: "Forklift hit the corner, 3 boxes crushed")
            HelperText(helper)
        } footer: {
            AikuButton(confirmTitle(kind), kind: .danger) {
                onSave(kind, note)
            }
            .accessibilityIdentifier("fulfilment.incident.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }
}
