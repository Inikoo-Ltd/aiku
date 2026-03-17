<?php // using python script to replace multiline is easier
$content = file_get_contents('resources/js/Pages/Retina/Dropshipping/RetinaOrders.vue');

$search = <<<EOT
    <PageHeading :data="pageHead">
        <template v-if="is_show_button_create_order" #other>
            <div>
                <Button
                @click="() => isModalCreateOrder = true"
                    label="Create Order"
                    iconRight="fal fa-arrow-right"
                />
            </div>
        </template>
    </PageHeading>
EOT;

$replace = <<<EOT
    <PageHeading :data="pageHead">
        <template #other>
            <div class="flex gap-2 items-center">
                <Button
                    @click="isModalBulkInvoices = true"
                    :label="trans('Export Invoices (PDF)')"
                    icon="fal fa-file-pdf"
                    type="secondary"
                />
                <Button
                    v-if="is_show_button_create_order"
                    @click="() => isModalCreateOrder = true"
                    :label="trans('Create Order')"
                    iconRight="fal fa-arrow-right"
                />
            </div>
        </template>
    </PageHeading>
EOT;

file_put_contents('resources/js/Pages/Retina/Dropshipping/RetinaOrders.vue', str_replace($search, $replace, $content));
