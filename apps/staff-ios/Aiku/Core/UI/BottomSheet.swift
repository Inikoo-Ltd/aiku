import SwiftUI

struct BottomSheet<Content: View>: View {
    let title: String
    var systemImage: String?
    var context: String?
    var primaryTitle: String?
    var isPrimaryLoading = false
    var primaryAction: (() -> Void)?
    @ViewBuilder let content: Content

    @State private var measuredHeight: CGFloat = 0

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            header
            content
            if let primaryTitle, let primaryAction {
                ActionButton(title: primaryTitle, isLoading: isPrimaryLoading, action: primaryAction)
            }
        }
        .padding(.horizontal, 20)
        .padding(.top, 28)
        .padding(.bottom, 20)
        .fixedSize(horizontal: false, vertical: true)
        .onGeometryChange(for: CGFloat.self) { proxy in
            proxy.size.height
        } action: { height in
            measuredHeight = height
        }
        .frame(maxHeight: .infinity, alignment: .top)
        .presentationDetents(measuredHeight > 0 ? [.height(measuredHeight)] : [.medium])
        .presentationDragIndicator(.visible)
        .presentationCornerRadius(16)
        .presentationBackground(AikuColor.surface)
    }

    private var header: some View {
        HStack(spacing: 10) {
            if let systemImage {
                Image(systemName: systemImage)
                    .font(.system(size: 20, weight: .medium))
                    .foregroundStyle(AikuColor.indigo)
                    .accessibilityHidden(true)
            }
            Text(title)
                .font(.system(size: 18, weight: .bold))
                .foregroundStyle(AikuColor.textPrimary)
                .accessibilityAddTraits(.isHeader)
            Spacer(minLength: 8)
            if let context {
                Text(context)
                    .font(.footnote)
                    .foregroundStyle(AikuColor.textSecondary)
            }
        }
    }
}

struct SheetTextField: View {
    let label: String
    @Binding var text: String
    var placeholder = ""

    @FocusState private var isFocused: Bool

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(label)
                .font(.subheadline.weight(.medium))
                .foregroundStyle(AikuColor.textBody)
            TextField(placeholder, text: $text)
                .focused($isFocused)
                .font(.body)
                .foregroundStyle(AikuColor.textPrimary)
                .textInputAutocapitalization(.characters)
                .autocorrectionDisabled()
                .padding(.horizontal, 12)
                .frame(height: 48)
                .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 6))
                .overlay(RoundedRectangle(cornerRadius: 6).stroke(isFocused ? AikuColor.indigo : AikuColor.borderStrong))
                .background {
                    if isFocused {
                        RoundedRectangle(cornerRadius: 6).stroke(AikuColor.indigo.opacity(0.25), lineWidth: 6)
                    }
                }
                .accessibilityLabel(label)
        }
    }
}
