#
# Author: Raul Perusquia <raul@inikoo.com>
# Created: Tue, 02 May 2023 12:10:46 Malaysia Time, Kuala Lumpur, Malaysia
# Copyright (c) 2023, Raul A Perusquia Flores
#
# test

rm *.bz2
cp ../paso/*_base.sql .
pbzip2 -f *_base.sql &
wait
echo "done 👍"
