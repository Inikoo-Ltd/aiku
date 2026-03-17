import re

with open('resources/js/Pages/Retina/Dropshipping/RetinaOrders.vue', 'r') as f:
    content = f.read()

# Replace #other
search_other = """    <PageHeading :data="pageHead">
        <template v-if="is_show_button_create_order" #other>
            <div>
                <Button
                @click="() => isModalCreateOrder = true"
                    label="Create Order"
                    iconRight="fal fa-arrow-right"
                />
            </div>
        </template>
    </PageHeading>"""

replace_other = """    <PageHeading :data="pageHead">
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
    </PageHeading>"""

content = content.replace(search_other, replace_other)

# script append
script_append = """

// Section: Bulk Invoices Download
const isModalBulkInvoices = ref(false)
const bulkInvoicesStartDate = ref(new Date().toISOString().split('T')[0])
const bulkInvoicesEndDate = ref(new Date().toISOString().split('T')[0])
const isDownloadingBulk = ref(false)

const downloadBulkInvoices = () => {
    let salesChannelSlug = null;
    
    if (props.customer_sales_channel && props.customer_sales_channel.slug) {
        salesChannelSlug = props.customer_sales_channel.slug;
    } else {
        salesChannelSlug = route().params.customerSalesChannel;
    }

    if (!salesChannelSlug) {
        notify({ title: trans("Error"), text: trans("Sales Channel not found in context."), type: "error" });
        return;
    }

    isDownloadingBulk.value = true;
    const url = route('retina.dropshipping.customer_sales_channels.orders.bulk_invoices_pdf', {
        customerSalesChannel: salesChannelSlug,
        start_date: bulkInvoicesStartDate.value,
        end_date: bulkInvoicesEndDate.value
    });
    
    window.open(url, '_blank');
    
    setTimeout(() => {
        isDownloadingBulk.value = false;
        isModalBulkInvoices.value = false;
    }, 1500);
}

</script>"""

content = content.replace('</script>', script_append)

modal_append = """

    <!-- Modal: Bulk Invoices Export -->
    <Modal :isOpen="isModalBulkInvoices" @onClose="isModalBulkInvoices = false" closeButton :isClosableInBackground="true" width="max-w-md w-full">
        <div>
            <div class="text-lg font-semibold mb-4 text-center">
                {{ trans("Download Invoices (PDF)") }}
            </div>
            
            <div class="mb-4 text-sm text-gray-600 text-center">
                {{ trans("Select a date range to download combined invoices.") }}
            </div>

            <div class="flex flex-col gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ trans("Start Date") }}</label>
                    <input type="date" v-model="bulkInvoicesStartDate" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ trans("End Date") }}</label>
                    <input type="date" v-model="bulkInvoicesEndDate" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            </div>

            <Button 
                @click="downloadBulkInvoices"
                :label="trans('Download Combined PDF')"
                full
                iconLeft="fal fa-file-pdf"
                :loading="isDownloadingBulk"
            />
        </div>
    </Modal>
</template>"""

content = re.sub(r'</template>\s*$', modal_append, content)

with open('resources/js/Pages/Retina/Dropshipping/RetinaOrders.vue', 'w') as f:
    f.write(content)
