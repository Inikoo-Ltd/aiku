import SwiftUI

struct GoodsOutPrintSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let close: () -> Void
    @State private var printer: String
    @State private var copies = 1
    private let store = GoodsOutStore.shared

    init(note: GoodsOutDeliveryNote, close: @escaping () -> Void) {
        self.note = note
        self.close = close
        _printer = State(initialValue: GoodsOutStore.shared.printers.first?.id ?? "")
    }

    private var pages: Int { note.lines.count > 4 ? 2 : 1 }

    var body: some View {
        SheetScaffold(title: "Print \(note.reference)", subtitle: "Delivery note PDF · \(pages) \(pages == 1 ? "page" : "pages")") {
            SheetSectionLabel("Printer")
            ForEach(store.printers) { option in
                RadioCard(option.id, subtitle: option.detail, isSelected: printer == option.id) {
                    printer = option.id
                }
            }
            HStack {
                Text("Copies")
                    .font(.aiku(15, .semibold))
                    .foregroundStyle(AikuColor.text)
                Spacer()
                GoodsOutCopiesStepper(value: $copies, range: 1...20)
            }
            .frame(minHeight: 48)
        } footer: {
            AikuButton("Print", icon: .printer) {
                close()
                toasts.show("\(note.reference) sent to \(printer) · \(copies) \(copies == 1 ? "copy" : "copies")", sticky: false)
            }
            .accessibilityIdentifier("goodsOut.printDeliveryNote")
            AikuButton("Open PDF", kind: .tertiary, size: .small) {
                close()
                toasts.show("Delivery note PDF of \(note.reference) opened", tone: .info, sticky: false)
            }
        }
    }
}

private struct GoodsOutCopiesStepper: View {
    @Binding var value: Int
    let range: ClosedRange<Int>

    var body: some View {
        HStack(spacing: 10) {
            Button {
                value = max(range.lowerBound, value - 1)
            } label: {
                Text("−")
                    .font(.aiku(20))
                    .foregroundStyle(value > range.lowerBound ? AikuColor.text2 : AikuColor.faint)
                    .frame(width: 44, height: 44)
                    .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .disabled(value <= range.lowerBound)
            .accessibilityLabel("Fewer copies")
            Text("\(value)")
                .font(.aiku(17, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
                .frame(minWidth: 24)
                .accessibilityLabel("\(value) \(value == 1 ? "copy" : "copies")")
            Button {
                value = min(range.upperBound, value + 1)
            } label: {
                Text("+")
                    .font(.aiku(20))
                    .foregroundStyle(AikuColor.onPrimary)
                    .frame(width: 44, height: 44)
                    .background(AikuColor.primary, in: RoundedRectangle(cornerRadius: 8))
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .disabled(value >= range.upperBound)
            .accessibilityLabel("More copies")
        }
    }
}
