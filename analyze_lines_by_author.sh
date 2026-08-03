#!/bin/bash

# Script to analyze lines of code per author in git repository
# Usage: bash analyze_lines_by_author.sh [output_file]
# Example: bash analyze_lines_by_author.sh report.txt

OUTPUT_FILE="${1:-lines_by_author_report.txt}"
TEMP_FILE="/tmp/blame_results_$$.txt"

echo "Starting analysis of all files in repository..."
echo "This may take several minutes depending on repository size."
echo ""

# Get total file count
TOTAL_FILES=$(git ls-files | wc -l)
echo "Found $TOTAL_FILES files to process"
echo ""

# Process each file and collect blame data
COUNTER=0
git ls-files | while read file; do
    COUNTER=$((COUNTER + 1))

    # Show progress every 500 files
    if [ $((COUNTER % 500)) -eq 0 ]; then
        echo "Processed $COUNTER/$TOTAL_FILES files..." >&2
    fi

    # Get blame for this file and extract author names
    git blame --line-porcelain "$file" 2>/dev/null | grep "^author " | sed 's/author //'
done > "$TEMP_FILE"

echo ""
echo "Processing results..."

# Aggregate and sort
cat "$TEMP_FILE" | sort | uniq -c | sort -rn > "$OUTPUT_FILE"

# Display and save formatted report
{
    echo "================================================================================"
    echo "Lines of Code by Author - Complete Analysis"
    echo "================================================================================"
    echo ""
    printf "%-50s %15s\n" "Author" "Lines of Code"
    echo "================================================================================"

    while read count author; do
        printf "%-50s %15d\n" "$author" "$count"
    done < "$OUTPUT_FILE"

    echo "================================================================================"
    TOTAL=$(awk '{sum+=$1} END {print sum}' "$OUTPUT_FILE")
    printf "%-50s %15d\n" "TOTAL" "$TOTAL"
    echo "================================================================================"
    echo ""
    echo "Report generated: $(date)"
} | tee "${OUTPUT_FILE}.formatted"

# Clean up temp file
rm "$TEMP_FILE"

echo ""
echo "✓ Analysis complete!"
echo "✓ Raw results saved to: $OUTPUT_FILE"
echo "✓ Formatted report saved to: ${OUTPUT_FILE}.formatted"
