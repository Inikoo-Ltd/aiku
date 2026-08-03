#!/bin/bash

# Script to analyze lines of code per author in app/Actions directory
# Groups results by subfolder (depth 1 only)
# Usage: bash analyze_actions_by_folder.sh [output_file]
# Example: bash analyze_actions_by_folder.sh report.txt

OUTPUT_FILE="${1:-actions_by_folder_report.txt}"
TEMP_DIR="/tmp/actions_analysis_$$"
mkdir -p "$TEMP_DIR"

# Check if app/Actions exists
if [ ! -d "app/Actions" ]; then
    echo "Error: app/Actions directory not found" >&2
    exit 1
fi

echo "Starting analysis of app/Actions directory..."
echo "Grouping by subfolder (depth 1 only)"
echo ""

# Get all PHP files in app/Actions
FILES=$(git ls-files "app/Actions/**/*.php" 2>/dev/null)
TOTAL_FILES=$(echo "$FILES" | wc -l)

if [ $TOTAL_FILES -eq 0 ]; then
    echo "Error: No PHP files found in app/Actions" >&2
    exit 1
fi

echo "Found $TOTAL_FILES PHP files in app/Actions"
echo ""

# Get all folders (depth 1)
FOLDERS=$(echo "$FILES" | cut -d'/' -f3 | sort -u)

# Process each folder
for FOLDER in $FOLDERS; do
    echo "Processing $FOLDER/..."

    # Get all files in this folder
    FOLDER_FILES=$(echo "$FILES" | grep "^app/Actions/$FOLDER/")
    FOLDER_COUNT=$(echo "$FOLDER_FILES" | wc -l)

    # Process each file and extract author lines
    echo "$FOLDER_FILES" | while read FILE; do
        git blame --line-porcelain "$FILE" 2>/dev/null | grep "^author " | sed 's/author //'
    done > "$TEMP_DIR/${FOLDER}.txt"
done

# Generate comprehensive report
{
    echo "======================================================================================================"
    echo "Lines of Code Analysis - app/Actions Directory"
    echo "Grouped by Subfolder (Depth 1)"
    echo "======================================================================================================"
    echo ""

    # Count totals
    TOTAL_FILES=$(echo "$FILES" | wc -l)
    TOTAL_LINES=$(cat "$TEMP_DIR"/*.txt 2>/dev/null | wc -l)
    TOTAL_FOLDERS=$(echo "$FOLDERS" | wc -l)

    echo "Total Folders: $TOTAL_FOLDERS"
    echo "Total Files: $TOTAL_FILES"
    echo "Total Lines: $TOTAL_LINES"
    echo ""

    # Overall stats
    echo "======================================================================================================"
    echo "OVERALL STATS (All folders combined)"
    echo "======================================================================================================"
    echo ""
    printf "%-45s %15s %15s\n" "Author" "Lines" "Percentage"
    echo "------------------------------------------------------------------------------------------------------"

    cat "$TEMP_DIR"/*.txt 2>/dev/null | sort | uniq -c | sort -rn | while read COUNT AUTHOR; do
        PERCENTAGE=$(awk "BEGIN {printf \"%.2f\", ($COUNT / $TOTAL_LINES) * 100}")
        printf "%-45s %15d %14s%%\n" "$AUTHOR" "$COUNT" "$PERCENTAGE"
    done

    echo "------------------------------------------------------------------------------------------------------"
    printf "%-45s %15d %14s%%\n" "TOTAL" "$TOTAL_LINES" "100.00"
    echo ""

    # Per-folder breakdown
    echo "======================================================================================================"
    echo "BREAKDOWN BY FOLDER"
    echo "======================================================================================================"
    echo ""

    for FOLDER in $(echo "$FOLDERS" | sort); do
        FOLDER_TOTAL=$(cat "$TEMP_DIR/${FOLDER}.txt" 2>/dev/null | wc -l)

        echo ""
        echo "📁 $FOLDER/ ($FOLDER_TOTAL lines)"
        echo "------------------------------------------------------------------------------------------------------"
        printf "%-45s %15s %15s\n" "Author" "Lines" "% of Folder"
        echo "------------------------------------------------------------------------------------------------------"

        cat "$TEMP_DIR/${FOLDER}.txt" 2>/dev/null | sort | uniq -c | sort -rn | while read COUNT AUTHOR; do
            PERCENTAGE=$(awk "BEGIN {printf \"%.2f\", ($COUNT / $FOLDER_TOTAL) * 100}")
            printf "%-45s %15d %14s%%\n" "$AUTHOR" "$COUNT" "$PERCENTAGE"
        done

        echo "------------------------------------------------------------------------------------------------------"
        printf "%-45s %15d %14s%%\n" "Subtotal" "$FOLDER_TOTAL" "100.00"
    done

    echo ""
    echo "======================================================================================================"
    echo "Report generated: $(date)"
    echo "======================================================================================================"
} | tee "${OUTPUT_FILE}.formatted"

# Clean up temp directory
rm -rf "$TEMP_DIR"

echo ""
echo "✓ Analysis complete!"
echo "✓ Formatted report saved to: ${OUTPUT_FILE}.formatted"
