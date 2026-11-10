import SwiftUI

struct DispatchReportProblemSheet: View {
    let onClose: () -> Void
    @Environment(ToastCenter.self) private var toasts
    @State private var kind: DispatchProblemKind = .unknownBarcode
    @State private var note = ""
    @State private var hasPhoto = false

    init(onClose: @escaping () -> Void) {
        self.onClose = onClose
    }

    var body: some View {
        SheetScaffold(title: "Report a problem", subtitle: "Goes to your supervisor or the inventory admin") {
            FlowLayout(spacing: 8, lineSpacing: 8) {
                ForEach(DispatchProblemKind.allCases, id: \.self) { option in
                    kindChip(option)
                }
            }
            AikuTextField("What happened?", text: $note, placeholder: "Scanned 5012345678900 at B-06-02 · not found")
            VStack(alignment: .leading, spacing: 6) {
                Text("Added automatically")
                    .font(.aiku(12, .bold))
                    .foregroundStyle(AikuColor.text2)
                Text("Screen, location, last scan, time, app version")
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
            }
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.surface2, in: RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.border))
            Button {
                hasPhoto.toggle()
            } label: {
                HStack(spacing: 10) {
                    (hasPhoto ? AppIcon.checkCircle : AppIcon.camera).image.font(.system(size: 18))
                    Text(hasPhoto ? "Photo added" : "Add photo").font(.aiku(14))
                }
                .foregroundStyle(hasPhoto ? AikuColor.success : AikuColor.text3)
                .padding(12)
                .frame(maxWidth: .infinity, minHeight: 44, alignment: .leading)
                .overlay(
                    RoundedRectangle(cornerRadius: 8)
                        .strokeBorder(AikuColor.borderStrong, style: StrokeStyle(lineWidth: 1, dash: [4, 4]))
                )
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("dispatch.report.photo")
        } footer: {
            AikuButton("Send report") {
                DispatchStore.shared.submit(DispatchProblemReport(kind: kind, note: note, hasPhoto: hasPhoto, context: "Picking"))
                onClose()
                toasts.show("Report sent")
            }
            .accessibilityIdentifier("dispatch.report.send")
            AikuButton("Cancel", kind: .tertiary, size: .small, action: onClose)
        }
    }

    private func kindChip(_ option: DispatchProblemKind) -> some View {
        let isSelected = option == kind
        return Button {
            kind = option
        } label: {
            Text(option.title)
                .font(.aiku(14, .semibold))
                .foregroundStyle(isSelected ? AikuColor.ink : AikuColor.text2)
                .padding(.horizontal, 14)
                .frame(minHeight: 44)
                .background(isSelected ? AikuColor.soft : AikuColor.surface, in: Capsule())
                .overlay(Capsule().strokeBorder(isSelected ? AikuColor.primary : AikuColor.borderStrong, lineWidth: isSelected ? 2 : 1))
                .contentShape(Capsule())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}
