import SwiftUI

struct LocationsRemoveSheet: View {
    let location: LocationsLocation
    let onRemove: (Set<String>) -> Void
    let onCancel: () -> Void
    @State private var selected: Set<String> = []

    var body: some View {
        SheetScaffold(title: "Remove SKOs from \(location.code)", subtitle: "Choose the SKOs to remove") {
            ForEach(location.slots) { slot in
                RadioCard(
                    slot.skoReference,
                    subtitle: "\(slot.type.title) · \(slot.quantity.locationsGrouped) in location",
                    isSelected: selected.contains(slot.skoReference)
                ) {
                    selected.formSymmetricDifference([slot.skoReference])
                }
                .accessibilityIdentifier("locations.remove.\(slot.skoReference)")
            }
            Text("Stock left in the location is written off when the SKO is removed from it.")
                .font(.aiku(14))
                .foregroundStyle(AikuColor.dangerInk)
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(.horizontal, 14)
                .padding(.vertical, 12)
                .background(AikuColor.dangerSoft, in: RoundedRectangle(cornerRadius: 10))
                .accessibilityAddTraits(.isStaticText)
        } footer: {
            AikuButton(selected.isEmpty ? "Remove SKOs" : "Remove \(LocationsFormat.skos(selected.count))", kind: .danger) {
                onRemove(selected)
            }
            .disabled(selected.isEmpty)
            .accessibilityIdentifier("locations.remove.confirm")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }
}
