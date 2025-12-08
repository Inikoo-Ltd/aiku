#!/bin/bash

# Check if the first argument is provided
if [ -z "$1" ]; then
    echo "Usage: $0 <filename>"
    exit 1
fi

# Directory of the .js files
cd private/fa/pro-$1-svg-icons


first_letter=$(echo $1 | cut -c1)
prefix="fa"
fixed_string="${prefix}${first_letter} ${prefix}"
new_file_name="${prefix}${first_letter}_list.js"


# Output directory path
output_dir="../../../IconList"
echo "Output directory: $output_dir"

# Ensure the output directory exists
mkdir -p "$output_dir"

# Start of the JS file
echo "export const zzz = [" > "$output_dir/$new_file_name"

# Loop through all .js files that start with 'fa' in the current directory
for file in ${prefix}*.js; do
    # Extract the filename without extension
    basename="${file%.*}"  # Strip the extension
    rest_of_name="${basename#$prefix}"  # Get the rest of the name after 'fa'

    # Convert camelCase to kebab-case for the rest of the name
    kebab_case=$(echo "$rest_of_name" | sed -r 's/([a-z0-9])([A-Z])/\1-\2/g' | tr '[:upper:]' '[:lower:]')

    # Append to the JS file with the new format
    echo "  '$fixed_string-$kebab_case'," >> "$output_dir/$new_file_name"
done

# Close the array in the JS file
echo "]" >> "$output_dir/$new_file_name"
