import SwiftUI

struct FulfilmentProgressSteps: View {
    let steps: [String]
    let currentIndex: Int

    var body: some View {
        HStack(spacing: 3) {
            ForEach(Array(steps.enumerated()), id: \.offset) { index, title in
                Text(title)
                    .font(.aiku(11, .bold))
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
                    .foregroundStyle(foreground(index))
                    .padding(.horizontal, 4)
                    .frame(maxWidth: .infinity, minHeight: 28)
                    .background(background(index), in: Capsule())
            }
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("Progress: \(steps.indices.contains(currentIndex) ? steps[currentIndex] : ""), step \(currentIndex + 1) of \(steps.count)")
    }

    private func foreground(_ index: Int) -> Color {
        if index < currentIndex { return Color.white }
        return index == currentIndex ? AikuColor.onPrimary : AikuColor.text3
    }

    private func background(_ index: Int) -> Color {
        if index < currentIndex { return AikuColor.success }
        return index == currentIndex ? AikuColor.primary : AikuColor.bg
    }
}

struct FulfilmentScanCard: View {
    let title: String
    var detail: String?
    let highlight: String
    var footnote: String?
    var identifier = "fulfilment.scan"
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: 6) {
                HStack(spacing: 8) {
                    AppIcon.scan.image.font(.system(size: 18, weight: .medium))
                    Text(title).font(.aiku(14, .bold))
                }
                if let detail {
                    Text(detail).font(.aiku(13))
                }
                Text(highlight)
                    .font(.aiku(22, .bold))
                    .monospacedDigit()
                    .lineLimit(2)
                    .minimumScaleFactor(0.7)
                if let footnote {
                    Text(footnote).font(.aiku(13))
                }
            }
            .multilineTextAlignment(.leading)
            .foregroundStyle(AikuColor.onScan)
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.scan, in: RoundedRectangle(cornerRadius: 12))
            .contentShape(RoundedRectangle(cornerRadius: 12))
        }
        .buttonStyle(.plain)
        .accessibilityHint("Opens the camera to scan")
        .accessibilityIdentifier(identifier)
    }
}

struct FulfilmentScanPanel: View {
    let title: String
    let detail: String
    let allTitle: String
    let verb: String
    let maximum: Int
    let onAll: () -> Void
    let onAmount: (Int) -> Void
    @State private var amount = 1

    var body: some View {
        Card(padding: 14, spacing: 10) {
            HStack(spacing: 10) {
                ProductThumb(size: 48, label: "Product photo of \(title)")
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text(detail)
                        .font(.aiku(13, .bold))
                        .foregroundStyle(AikuColor.warn)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            if maximum > 0 {
                AikuButton(allTitle, icon: .check, action: onAll)
                    .accessibilityIdentifier("fulfilment.scanPanel.all")
                HStack(spacing: 8) {
                    QuantityStepper(value: $amount, range: 1...max(1, maximum))
                    AikuButton("\(verb) \(amount)", kind: .tertiary, fullWidth: false) {
                        onAmount(amount)
                        amount = 1
                    }
                }
            }
        }
        .onChange(of: maximum) { _, newValue in
            amount = min(amount, max(1, newValue))
        }
    }
}

struct FulfilmentCountLabel: View {
    let done: Int
    let total: Int
    let tone: Tone

    var body: some View {
        HStack(alignment: .firstTextBaseline, spacing: 3) {
            Text("\(done)")
                .font(.aiku(16, .bold))
                .foregroundStyle(tone == .neutral ? AikuColor.text : tone.foreground)
            Text("/ \(total)")
                .font(.aiku(13, .medium))
                .foregroundStyle(AikuColor.text3)
        }
        .monospacedDigit()
        .fixedSize()
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("\(done) of \(total)")
    }
}

struct FulfilmentDoneHero: View {
    let title: String
    let message: String

    var body: some View {
        VStack(spacing: 10) {
            AppIcon.check.image
                .font(.system(size: 32, weight: .semibold))
                .foregroundStyle(AikuColor.success)
                .frame(width: 72, height: 72)
                .background(AikuColor.successSoft, in: Circle())
                .accessibilityHidden(true)
            Text(title)
                .font(.aiku(22, .bold))
                .foregroundStyle(AikuColor.text)
            Text(message)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
        }
        .multilineTextAlignment(.center)
        .padding(.vertical, 24)
        .padding(.horizontal, 16)
        .frame(maxWidth: .infinity)
    }
}

struct FulfilmentNote: View {
    let text: String

    init(_ text: String) {
        self.text = text
    }

    var body: some View {
        HelperText(text)
            .padding(.horizontal, 4)
    }
}

struct FulfilmentCameraScan<Panel: View>: View {
    let kicker: String
    let title: String
    var hint = "Point at the barcode"
    var feedback: String?
    let onClose: () -> Void
    let onScan: () -> Void
    @ViewBuilder var panel: Panel

    init(
        kicker: String,
        title: String,
        hint: String = "Point at the barcode",
        feedback: String? = nil,
        onClose: @escaping () -> Void,
        onScan: @escaping () -> Void,
        @ViewBuilder panel: () -> Panel
    ) {
        self.kicker = kicker
        self.title = title
        self.hint = hint
        self.feedback = feedback
        self.onClose = onClose
        self.onScan = onScan
        self.panel = panel()
    }

    var body: some View {
        CameraScanScreen(kicker: kicker, title: title, hint: hint, onClose: onClose, onUseScanner: onClose, onSimulateScan: onScan) {
            if let feedback {
                ScanFeedbackBanner(tone: .success, title: feedback)
            }
        } panel: {
            panel
            AikuButton("Done", kind: .tertiary, action: onClose)
                .accessibilityIdentifier("fulfilment.scan.done")
        }
    }
}
