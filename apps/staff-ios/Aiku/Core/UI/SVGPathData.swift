import SwiftUI

enum SVGPathData {
    static func path(_ data: String) -> Path {
        var reader = SVGPathReader(data)
        var path = Path()
        var current = CGPoint.zero
        var subpathStart = CGPoint.zero
        var lastCubicControl: CGPoint?
        var lastQuadControl: CGPoint?
        var command: UInt8 = 0

        while let next = reader.nextCommand(after: command) {
            command = next
            let relative = command >= UInt8(ascii: "a")
            let origin = relative ? current : .zero
            var cubicControl: CGPoint?
            var quadControl: CGPoint?

            switch command | 0x20 {
            case UInt8(ascii: "m"):
                guard let point = reader.point(from: origin) else { return path }
                path.move(to: point)
                current = point
                subpathStart = point
                command = relative ? UInt8(ascii: "l") : UInt8(ascii: "L")
            case UInt8(ascii: "l"):
                guard let point = reader.point(from: origin) else { return path }
                path.addLine(to: point)
                current = point
            case UInt8(ascii: "h"):
                guard let value = reader.number() else { return path }
                let x = CGFloat(value)
                current = CGPoint(x: relative ? current.x + x : x, y: current.y)
                path.addLine(to: current)
            case UInt8(ascii: "v"):
                guard let value = reader.number() else { return path }
                let y = CGFloat(value)
                current = CGPoint(x: current.x, y: relative ? current.y + y : y)
                path.addLine(to: current)
            case UInt8(ascii: "c"):
                guard let first = reader.point(from: origin), let second = reader.point(from: origin), let end = reader.point(from: origin) else { return path }
                path.addCurve(to: end, control1: first, control2: second)
                cubicControl = second
                current = end
            case UInt8(ascii: "s"):
                guard let second = reader.point(from: origin), let end = reader.point(from: origin) else { return path }
                let first = reflected(lastCubicControl, around: current)
                path.addCurve(to: end, control1: first, control2: second)
                cubicControl = second
                current = end
            case UInt8(ascii: "q"):
                guard let control = reader.point(from: origin), let end = reader.point(from: origin) else { return path }
                path.addQuadCurve(to: end, control: control)
                quadControl = control
                current = end
            case UInt8(ascii: "t"):
                guard let end = reader.point(from: origin) else { return path }
                let control = reflected(lastQuadControl, around: current)
                path.addQuadCurve(to: end, control: control)
                quadControl = control
                current = end
            case UInt8(ascii: "a"):
                guard let rx = reader.number(), let ry = reader.number(), let rotation = reader.number(),
                      let largeArc = reader.flag(), let sweep = reader.flag(), let end = reader.point(from: origin) else { return path }
                addArc(to: &path, from: current, radii: CGSize(width: rx, height: ry), rotation: rotation, largeArc: largeArc, sweep: sweep, end: end)
                current = end
            case UInt8(ascii: "z"):
                path.closeSubpath()
                current = subpathStart
            default:
                return path
            }
            lastCubicControl = cubicControl
            lastQuadControl = quadControl
        }
        return path
    }

    private static func reflected(_ control: CGPoint?, around point: CGPoint) -> CGPoint {
        guard let control else { return point }
        return CGPoint(x: 2 * point.x - control.x, y: 2 * point.y - control.y)
    }

    static func addArc(to path: inout Path, from start: CGPoint, radii: CGSize, rotation: Double, largeArc: Bool, sweep: Bool, end: CGPoint) {
        guard start != end else { return }
        let startX = Double(start.x)
        let startY = Double(start.y)
        let endX = Double(end.x)
        let endY = Double(end.y)
        var rx = abs(Double(radii.width))
        var ry = abs(Double(radii.height))
        guard rx > 0, ry > 0 else {
            path.addLine(to: end)
            return
        }
        let phi: Double = rotation * Double.pi / 180
        let cosPhi: Double = cos(phi)
        let sinPhi: Double = sin(phi)
        let halfDX: Double = (startX - endX) / 2
        let halfDY: Double = (startY - endY) / 2
        let x1: Double = cosPhi * halfDX + sinPhi * halfDY
        let y1: Double = -sinPhi * halfDX + cosPhi * halfDY
        let lambda: Double = (x1 * x1) / (rx * rx) + (y1 * y1) / (ry * ry)
        if lambda > 1 {
            rx *= lambda.squareRoot()
            ry *= lambda.squareRoot()
        }
        let rx2: Double = rx * rx
        let ry2: Double = ry * ry
        let numerator: Double = max(0, rx2 * ry2 - rx2 * y1 * y1 - ry2 * x1 * x1)
        let denominator: Double = rx2 * y1 * y1 + ry2 * x1 * x1
        var coefficient: Double = denominator == 0 ? 0 : (numerator / denominator).squareRoot()
        if largeArc == sweep {
            coefficient = -coefficient
        }
        let centerX1: Double = coefficient * rx * y1 / ry
        let centerY1: Double = -coefficient * ry * x1 / rx
        let centerX: Double = cosPhi * centerX1 - sinPhi * centerY1 + (startX + endX) / 2
        let centerY: Double = sinPhi * centerX1 + cosPhi * centerY1 + (startY + endY) / 2
        let fromU: Double = (x1 - centerX1) / rx
        let fromV: Double = (y1 - centerY1) / ry
        let toU: Double = (-x1 - centerX1) / rx
        let toV: Double = (-y1 - centerY1) / ry
        let startAngle = angle(1, 0, fromU, fromV)
        var sweepAngle = angle(fromU, fromV, toU, toV)
        if !sweep, sweepAngle > 0 {
            sweepAngle -= 2 * Double.pi
        } else if sweep, sweepAngle < 0 {
            sweepAngle += 2 * Double.pi
        }

        let segments = max(1, Int((abs(sweepAngle) / (Double.pi / 2)).rounded(.up)))
        let step: Double = sweepAngle / Double(segments)
        let handle: Double = 4.0 / 3.0 * tan(step / 4)
        func onEllipse(_ u: Double, _ v: Double) -> CGPoint {
            let x: Double = centerX + rx * u * cosPhi - ry * v * sinPhi
            let y: Double = centerY + rx * u * sinPhi + ry * v * cosPhi
            return CGPoint(x: x, y: y)
        }
        var from = startAngle
        for segment in 0..<segments {
            let to = from + step
            let control1 = onEllipse(cos(from) - handle * sin(from), sin(from) + handle * cos(from))
            let control2 = onEllipse(cos(to) + handle * sin(to), sin(to) - handle * cos(to))
            let point = segment == segments - 1 ? end : onEllipse(cos(to), sin(to))
            path.addCurve(to: point, control1: control1, control2: control2)
            from = to
        }
    }

    private static func angle(_ ux: Double, _ uy: Double, _ vx: Double, _ vy: Double) -> Double {
        atan2(ux * vy - uy * vx, ux * vx + uy * vy)
    }
}

struct SVGPathReader {
    private let bytes: [UInt8]
    private var index = 0

    init(_ data: String) {
        bytes = Array(data.utf8)
    }

    mutating func nextCommand(after previous: UInt8) -> UInt8? {
        skipSeparators()
        guard index < bytes.count else { return nil }
        let byte = bytes[index]
        if Self.isCommand(byte) {
            index += 1
            return byte
        }
        guard previous != 0, previous | 0x20 != UInt8(ascii: "z"), Self.startsNumber(byte) else { return nil }
        return previous
    }

    mutating func point(from origin: CGPoint) -> CGPoint? {
        guard let x = number(), let y = number() else { return nil }
        return CGPoint(x: origin.x + CGFloat(x), y: origin.y + CGFloat(y))
    }

    mutating func flag() -> Bool? {
        skipSeparators()
        guard index < bytes.count else { return nil }
        let byte = bytes[index]
        guard byte == UInt8(ascii: "0") || byte == UInt8(ascii: "1") else { return nil }
        index += 1
        return byte == UInt8(ascii: "1")
    }

    mutating func number() -> Double? {
        skipSeparators()
        let start = index
        if index < bytes.count, bytes[index] == UInt8(ascii: "-") || bytes[index] == UInt8(ascii: "+") {
            index += 1
        }
        var digits = 0
        while index < bytes.count, Self.isDigit(bytes[index]) {
            index += 1
            digits += 1
        }
        if index < bytes.count, bytes[index] == UInt8(ascii: ".") {
            index += 1
            while index < bytes.count, Self.isDigit(bytes[index]) {
                index += 1
                digits += 1
            }
        }
        guard digits > 0 else {
            index = start
            return nil
        }
        if index < bytes.count, bytes[index] | 0x20 == UInt8(ascii: "e") {
            var exponentEnd = index + 1
            if exponentEnd < bytes.count, bytes[exponentEnd] == UInt8(ascii: "-") || bytes[exponentEnd] == UInt8(ascii: "+") {
                exponentEnd += 1
            }
            if exponentEnd < bytes.count, Self.isDigit(bytes[exponentEnd]) {
                index = exponentEnd
                while index < bytes.count, Self.isDigit(bytes[index]) {
                    index += 1
                }
            }
        }
        return Double(String(decoding: bytes[start..<index], as: UTF8.self))
    }

    private mutating func skipSeparators() {
        while index < bytes.count, bytes[index] == UInt8(ascii: " ") || bytes[index] == UInt8(ascii: ",") || bytes[index] == UInt8(ascii: "\n")
            || bytes[index] == UInt8(ascii: "\t") || bytes[index] == UInt8(ascii: "\r") {
            index += 1
        }
    }

    private static func isDigit(_ byte: UInt8) -> Bool {
        byte >= UInt8(ascii: "0") && byte <= UInt8(ascii: "9")
    }

    private static func startsNumber(_ byte: UInt8) -> Bool {
        isDigit(byte) || byte == UInt8(ascii: "-") || byte == UInt8(ascii: "+") || byte == UInt8(ascii: ".")
    }

    private static func isCommand(_ byte: UInt8) -> Bool {
        "MmLlHhVvCcSsQqTtAaZz".utf8.contains(byte)
    }
}
