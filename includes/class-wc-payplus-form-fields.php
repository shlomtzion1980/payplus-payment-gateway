<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles and process WC PayPlus Orders Data.
 *
 */
class WC_PayPlus_Form_Fields
{
    public $formFields;

    /**
     * @param WP_Admin_Bar $admin_bar
     * @return void
     */
    public static function adminBarMenu($admin_bar)
    {

        $admin_bar->add_menu(array(
            'id' => 'PayPlus-toolbar',
            'title' => __('PayPlus Gateway', 'payplus-payment-gateway'),
            'href' => get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway",
            'meta' => array(
                'title' => __('PayPlus Gateway', 'payplus-payment-gateway'),
                'target' => '_blank',
            ),
        ));
        $admin_bar->add_menu(array(
            'id' => 'payPlus-toolbar-sub',
            'parent' => 'PayPlus-toolbar',
            'title' => __('PayPlus Invoice+', 'payplus-payment-gateway'),
            'href' => get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=payplus-invoice",
            'meta' => array(
                'title' => __('PayPlus Invoice+', 'payplus-payment-gateway'),
                'target' => '_blank',
                'class' => 'my_menu_item_class',
            ),
        ));
    }

    /**
     * @return void
     */
    public static function getGateway()
    {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway'));
        exit;
    }

    /**
     * @return void
     */
    public static function addAdminPageMenu()
    {
        global $submenu;
        $parent_slug = 'payplus-payment-gateway';
        $payplus_payment_gateway_settings = get_option('woocommerce_payplus-payment-gateway_settings');
        $isPayPlus = boolval(isset($payplus_payment_gateway_settings['enabled']) && $payplus_payment_gateway_settings['enabled'] === 'yes');
        $showOrdersButton = boolval($isPayPlus && isset($payplus_payment_gateway_settings['payplus_orders_check_button']) && $payplus_payment_gateway_settings['payplus_orders_check_button'] === 'yes');
        $showSubGatewaysOnSide = boolval(isset($payplus_payment_gateway_settings['payplus_show_sub_gateways_side_menu']) && $payplus_payment_gateway_settings['payplus_show_sub_gateways_side_menu'] === 'yes');

        add_menu_page(
            __('PayPlus Gateway', 'payplus-payment-gateway'),
            __('PayPlus Gateway', 'payplus-payment-gateway'),
            "administrator",
            'payplus-payment-gateway',
            ['WC_PayPlus_Form_Fields', 'getGateway'],
            PAYPLUS_PLUGIN_URL_ASSETS_IMAGES . "payplus-icon.svg"
        );
        add_submenu_page(
            'payplus-payment-gateway', //Page Title
            __('PayPlus Invoice+', 'payplus-payment-gateway'),
            __('PayPlus Invoice+', 'payplus-payment-gateway'),
            'administrator', //Capability
            'admin.php?page=wc-settings&tab=checkout&section=payplus-invoice' //Page slug
        );
        if ($showSubGatewaysOnSide) {
            add_submenu_page(
                'payplus-payment-gateway',
                __('bit', 'payplus-payment-gateway'),
                __('bit', 'payplus-payment-gateway'),
                'administrator', //Capability
                'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-bit'
            );
            add_submenu_page(
                'payplus-payment-gateway', //Page Title
                __('Google Pay', 'payplus-payment-gateway'),
                __('Google Pay', 'payplus-payment-gateway'),
                'administrator', //Capability
                'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-googlepay' //Page slug
            );
            add_submenu_page(
                'payplus-payment-gateway', //Page Title
                __('Apple Pay', 'payplus-payment-gateway'),
                __('Apple Pay', 'payplus-payment-gateway'),
                'administrator', //Capability
                'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-applepay' //Page slug
            );
            add_submenu_page(
                'payplus-payment-gateway', //Page Title
                __('MULTIPASS', 'payplus-payment-gateway'),
                __('MULTIPASS', 'payplus-payment-gateway'),
                'administrator', //Capability
                'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-multipass' //Page slug
            );
            add_submenu_page(
                'payplus-payment-gateway', //Page Title
                __('PayPal', 'payplus-payment-gateway'),
                __('PayPal', 'payplus-payment-gateway'),
                'administrator', //Capability
                'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-paypal' //Page slug
            );
            add_submenu_page(
                'payplus-payment-gateway', //Page Title
                __('Tav zahav', 'payplus-payment-gateway'),
                __('Tav Zahav', 'payplus-payment-gateway'),
                'administrator', //Capability
                'admin.php?page=wc-settings&tab=checkout&section=payplus-payment-gateway-tavzahav' //Page slug
            );
        }
        if ($showOrdersButton) {
            add_submenu_page(
                'payplus-payment-gateway', //Page Title
                __('Run PayPlus Orders Reports/Validator', 'payplus-payment-gateway'),
                __('Run PayPlus Orders Reports/Validator', 'payplus-payment-gateway'),
                'administrator', //Capability
                'runPayPlusOrdersChecker', //Page slug
                [__CLASS__, 'runPayPlusOrdersChecker']
            );
        }
    }

    public static function runPayPlusOrdersChecker()
    {
        if (current_user_can('edit_shop_orders')) {
            $nonce = wp_create_nonce('payPlusOrderChecker');
?>
            <div class="wrap">
                <h1>PayPlus Orders Reports/Validator</h1>
                <!-- <p>Click the button below to run the PayPlus Orders Validator.</p>
                <p>
                    This will check all orders created within the last day are in "pending", "failed" or "cancelled" status and
                    contain "payplus_page_request_uid". It verifies the PayPlus IPN Process and sets the correct status if needded.
                </p> -->
                <?php
                $payPlusSettings = get_option('woocommerce_payplus-payment-gateway_settings');
                $enableDevMode = isset($payPlusSettings['enable_dev_mode']) && $payPlusSettings['enable_dev_mode'] === 'yes';
                $enableOrdersTable = isset($payPlusSettings['enable_orders_table']) && $payPlusSettings['enable_orders_table'] === 'yes';

                if ($enableDevMode && $enableOrdersTable) {
                    $orders_count_by_month = array();
                    $current_year = gmdate('Y');
                    $selected_year = isset($_POST['year']) ? intval($_POST['year']) : $current_year;
                    $selected_month = isset($_POST['month']) ? intval($_POST['month']) : gmdate('m');
                ?>
                    <h2>Orders by Month - Table select - Current month displayed: <?php echo esc_html(gmdate('F', mktime(0, 0, 0, $selected_month, 10))); ?></h2>
                    <form method="post" action="" id="selctedYearForm">
                        <label for="year">Choose Year:</label>
                        <select name="year" id="year">
                            <?php for ($i = $current_year; $i >= $current_year - 5; $i--) : ?>
                                <option value="<?php echo esc_attr($i); ?>" <?php selected($selected_year, $i); ?>>
                                    <?php echo esc_html($i); ?></option>
                            <?php endfor; ?>
                        </select>
                        <label for="month">Choose Month:</label>
                        <select name="month" id="month">
                            <?php for ($i = 1; $i <= 12; $i++) : ?>
                                <option value="<?php echo esc_attr($i); ?>" <?php selected($selected_month, $i); ?>>
                                    <?php echo esc_html(gmdate('F', mktime(0, 0, 0, $i, 10))); ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit">Submit</button>
                    </form>
                    <?php
                    $current_year = $selected_year;
                    $month = isset($_POST['month']) ? intval($_POST['month']) : $selected_month;
                    $start_date = gmdate('Y-m-01 00:00:00', strtotime("$current_year-$month-01"));
                    $end_date = gmdate('Y-m-t 23:59:59', strtotime("$current_year-$month-01"));
                    $args = array(
                        'date_created' => $start_date . '...' . $end_date,
                        'return'       => 'ids',
                        'limit'        => -1,
                    );
                    $orders = wc_get_orders($args);
                    $orders_count_by_month[$month] = array();

                    foreach ($orders as $order_id) {
                        $order = wc_get_order($order_id);
                        $status = $order->get_status();
                        if (!isset($orders_count_by_month[$month][$status])) {
                            $orders_count_by_month[$month][$status] = array();
                        }
                        $orders_count_by_month[$month][$status][] = $order_id;
                    }

                    echo '<pre>';
                    echo '<style>
                    table#pp_all_orders {
                        width: 90%;
                        border-collapse: collapse;
                    }
                    th, td {
                        border: 1px solid #ddd;
                        padding: 8px;
                        text-align: left;
                    }
                    th {
                        background-color: #f2f2f2;
                    }
                    ul.pp_orders {
                        list-style-type: none;
                        padding: 0;
                        margin: 0;
                        display: flex;
                        flex-wrap: wrap;
                    }
                    li {
                        margin-right: 10px;
                    }
                </style>';
                    echo '<table id="pp_all_orders">';
                    echo '<tr><th>Month</th><th>Pending</th><th>Cancelled</th><th>Failed</th><th>Completed</th><th>Processing</th><th>On-Hold</th></tr>';
                    foreach ($orders_count_by_month as $month => $statuses) {
                        echo '<tr>';
                        echo '<td>' . esc_html(gmdate('F', mktime(0, 0, 0, $month, 10))) . '</td>';
                        foreach (['pending', 'cancelled', 'failed', 'completed', 'processing', 'on-hold'] as $status) {
                            echo '<td>';
                            if (isset($statuses[$status])) {
                                echo '<ul class="pp_orders">';
                                echo '<li><input type="checkbox" class="select-all" data-status="' . esc_attr($status) . '"> Select All</li>';
                                foreach ($statuses[$status] as $order_id) {
                                    echo '<li><input type="checkbox" name="order_ids[]" value="' . esc_attr($order_id) . '" class="order-checkbox-' . esc_attr($status) . '"> ' . esc_html($order_id) . '</li>';
                                }
                                echo '</ul>';
                            } else {
                                echo '0';
                            }
                            echo '</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '</pre>';
                    echo '<div id="selected-orders-summary"></div>';
                    echo '<script>
                    document.querySelectorAll(".select-all").forEach(function(selectAllCheckbox) {
                        selectAllCheckbox.addEventListener("change", function() {
                            var status = this.getAttribute("data-status");
                            var checkboxes = this.closest("td").querySelectorAll(".order-checkbox-" + status);
                            checkboxes.forEach(function(checkbox) {
                                checkbox.checked = selectAllCheckbox.checked;
                            });
                            updateSelectedOrdersSummary();
                        });
                    });

                    document.querySelectorAll("input[name=\'order_ids[]\']").forEach(function(orderCheckbox) {
                        orderCheckbox.addEventListener("change", updateSelectedOrdersSummary);
                    });

                    function updateSelectedOrdersSummary() {
                        var summary = {};
                        var selectedOrderIds = [];
                        document.querySelectorAll("input[name=\'order_ids[]\']:checked").forEach(function(checkbox) {
                            if (selectedOrderIds.length < 100) {
                                var month = checkbox.closest("tr").querySelector("td:first-child").textContent;
                                var status = checkbox.closest("ul").querySelector(".select-all").getAttribute("data-status");
                                if (!summary[month]) {
                                    summary[month] = {};
                                }
                                if (!summary[month][status]) {
                                    summary[month][status] = [];
                                }
                                summary[month][status].push(checkbox.value);
                                selectedOrderIds.push(checkbox.value);
                            } else {
                                checkbox.checked = false;
                            }
                        });

                        var summaryDiv = document.getElementById("selected-orders-summary");
                        summaryDiv.innerHTML = "<h3>Selected Orders Summary</h3>";
                        for (var month in summary) {
                            summaryDiv.innerHTML += "<p><strong>" + month + ":</strong></p>";
                            for (var status in summary[month]) {
                                summaryDiv.innerHTML += "<p>" + status + ": " + summary[month][status].join(", ") + "</p>";
                            }
                        }
                        if (selectedOrderIds.length >= 100) {
                            summaryDiv.innerHTML += "<p><strong style=\'color: red;\'>Total Selected Orders: " + selectedOrderIds.length + "/100</strong></p>";
                        } else {
                            summaryDiv.innerHTML += "<p><strong>Total Selected Orders: " + selectedOrderIds.length + "/100</strong></p>";
                        }

                        // Update the order_numbers textarea
                        var orderNumbersTextarea = document.querySelector("textarea[name=\'order_numbers\']");
                        orderNumbersTextarea.value = selectedOrderIds.join(", ");

                        // Hide orderFilters and timeFilters if any orders are selected
                        var orderFilters = document.getElementById("orderFilters");
                        var timeFilters = document.getElementById("timeFilters");
                        var orderNumbers = document.getElementById("orderNumbers");
                        if (selectedOrderIds.length > 0) {
                            orderFilters.style.display = "none";
                            timeFilters.style.display = "none";
                            orderNumbers.style.display = "flex";
                        } else {
                            orderFilters.style.display = "flex";
                            timeFilters.style.display = "flex";
                            orderNumbers.style.display = "flex";
                        }
                    }
                </script>';
                }
                if ($enableDevMode) {
                    ?>
                    <form id="reportsForm" method="post" action=""
                        style="display: flex;width: 20%;flex-direction: column;flex-wrap: wrap;">
                        <span id="timeFilters" style="display: flex;flex-direction: column;">
                            <h2>Orders by Month - Filter select</h2>
                            <select name="month">
                                <?php for ($i = 1; $i <= 12; $i++) : ?>
                                    <option value="<?php echo esc_attr($i); ?>">
                                        <?php echo esc_html(gmdate('F', mktime(0, 0, 0, $i, 10))); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <label for="year">Year</label>
                            <select name="year">
                                <?php
                                $currentYear = gmdate('Y');
                                for ($i = $currentYear; $i >= $currentYear - 5; $i--) : ?>
                                    <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                                <?php endfor; ?>
                            </select>
                            <label for="take">How many?</label>
                            <select name="take" id="take">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <label for="offset">Start from (Offset of 0 to 100)</label>
                            <input type="number" name="offset" value="0" min="0" />
                        </span>
                        <div class="checkBoxes" style="display: flex;flex-direction: column;padding: 10px;">
                            <span id="orderFilters" style="display: flex;flex-direction: column;">
                                <h4>Filters:</h4>
                                <span style="margin-right: 10px;">
                                    <input type="radio" name="orderStatus" value="pendingOnly">
                                    <label for="pendingOnly">Pending Only</label>
                                </span>
                                <span style="margin-right: 10px;">
                                    <input type="radio" name="orderStatus" value="cancelledOnly">
                                    <label for="cancelledOnly">Cancelled Only</label>
                                </span>
                                <span style="margin-right: 10px;">
                                    <input type="radio" name="orderStatus" value="failedOnly">
                                    <label for="failedOnly">Failed Only</label>
                                </span>
                                <span style="margin-right: 10px;">
                                    <input type="radio" name="orderStatus" value="allStatuses">
                                    <label for="orderStatus">All statuses</label>
                                </span>
                            </span>
                            <div id="orderNumbers" style="display: flex;flex-direction: column;">
                                <h4>(Optional - Overrides the filters) Enter order ids comma sepearated: </h4>
                                <textarea name="order_numbers" placeholder="Enter order numbers, separated by commas"></textarea>
                            </div>
                            <h4>Actions:</h4>
                            <span style="margin-right: 10px;">
                                <input type="checkbox" name="forceInvoice" value="true">
                                <label for="forceInvoice">Create Invoice<br>(Create default doc - Ignore order status)</label>
                            </span>
                            <span style="margin-right: 10px;">
                                <input type="checkbox" name="getInvoice" value="true">
                                <label for="getInvoice">Get Invoices (Instead of IPN!)</label>
                            </span>
                            <span style="margin-right: 10px;">
                                <input type="checkbox" name="forceAll" value="true">
                                <label for="forceAll">Force IPN (Run IPN even if response exists)</label>
                            </span>
                            <span style="margin-right: 10px;">
                                <input type="checkbox" name="reportOnly" value="true" checked>
                                <label for="reportOnly">Report Only (will not make any changes)</label>
                            </span>
                        </div><button name="verifyPayPlusOrders" value="<?php echo esc_attr($nonce); ?>">Run PayPlus orders
                            verifier</button>
                    </form> <?php } else { ?>
                    <form method="post" action="">
                        <button name="verifyPayPlusOrders" value="<?php echo esc_attr($nonce); ?>">Run PayPlus orders verifier</button>
                    </form> <?php } ?>
            </div>
<?php
            if (isset($_POST['verifyPayPlusOrders'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $nonce = sanitize_text_field(wp_unslash($_POST['verifyPayPlusOrders'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                echo '<pre>';
                echo "Running PayPlus Order checker...\n";
                if (wp_verify_nonce($nonce, 'payPlusOrderChecker')) {
                    // Process the form data here
                    $month = isset($_POST['month']) ? intval($_POST['month']) : gmdate('m');
                    $year = isset($_POST['year']) ? intval($_POST['year']) : gmdate('Y');
                    $take = isset($_POST['take']) ? intval($_POST['take']) : 10;
                    $offset = isset($_POST['offset']) ? intval(wp_unslash($_POST['offset'])) : 0;
                    $orderStatus = isset($_POST['orderStatus']) ? sanitize_text_field(wp_unslash($_POST['orderStatus'])) : false;
                    $getInvoice = isset($_POST['getInvoice']) ? filter_var(wp_unslash($_POST['getInvoice']), FILTER_VALIDATE_BOOLEAN) : false;
                    $forceInvoice = isset($_POST['forceInvoice']) ? filter_var(wp_unslash($_POST['forceInvoice']), FILTER_VALIDATE_BOOLEAN) : false;
                    $forceAll = isset($_POST['forceAll']) ? filter_var(wp_unslash($_POST['forceAll']), FILTER_VALIDATE_BOOLEAN) : false;
                    $reportOnly = isset($_POST['reportOnly']) ? filter_var(wp_unslash($_POST['reportOnly']), FILTER_VALIDATE_BOOLEAN) : false;
                    // $allStatuses = isset($_POST['allStatuses']) ? filter_var(wp_unslash($_POST['allStatuses']), FILTER_VALIDATE_BOOLEAN) : false;
                    $failedOnly = $orderStatus === 'failedOnly';
                    $allStatuses = $orderStatus === 'allStatuses';
                    $cancelledOnly = $orderStatus === 'cancelledOnly';
                    $pendingOnly = $orderStatus === 'pendingOnly';
                    $status = !$allStatuses ? ['pending', 'cancelled', 'failed'] : ['pending', 'cancelled', 'failed', 'completed', 'processing', 'on-hold'];
                    $status = $failedOnly ? 'failed' : $status;
                    $status = $cancelledOnly ? 'cancelled' : $status;
                    $status = $pendingOnly ? 'pending' : $status;

                    $current_time = current_time('Y-m-d H:i:s');

                    if (isset($_POST['month'])) {
                        // Get start and end dates for the given month
                        $start_date = gmdate('Y-m-01 00:00:00', strtotime("$year-$month-01"));
                        $end_date = gmdate('Y-m-t 23:59:59', strtotime("$year-$month-01")); // Last day of the month

                        $dateOrDates = $start_date . '...' . $end_date;
                    } else {
                        $dateOrDates = $current_time;
                        echo esc_html("Date to check: " . substr($current_time, 0, 10) . "\n");
                    }

                    $args = array(
                        'status'       => $status,
                        'date_created' => $dateOrDates, // Correct range format for WooCommerce
                        'return'       => 'ids', // Just return IDs to save memory
                        'limit'        => -1, // Retrieve all orders
                    );



                    if (isset($_POST['order_numbers']) && !empty($_POST['order_numbers'])) {
                        $order_numbers = explode(',', sanitize_text_field(wp_unslash($_POST['order_numbers'])));
                        $orders = array_reverse($order_numbers);
                        $howManyOrders = count($orders);
                        echo esc_html("\nTotal orders found: $howManyOrders\n");
                        echo esc_html("Orders found: \n" . wp_json_encode($orders) . "\n");
                    } else {
                        if (isset($start_date) && isset($end_date)) {
                            echo esc_html("Start date: $start_date\n");
                            echo esc_html("End date: $end_date\n");
                        }
                        $statuses = wp_json_encode($status);
                        echo esc_html("Selected statuses: $statuses\n");
                        $orders = array_reverse(wc_get_orders($args));
                        $howManyOrders = count($orders);
                        echo esc_html("\nTotal orders found: $howManyOrders\n");
                        echo esc_html("Orders found: \n" . wp_json_encode($orders) . "\n");

                        $take = isset($_POST['take']) ? intval($_POST['take']) : 0;
                        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

                        if ($take > 0 && $offset >= 0) {
                            $args['limit'] = $take;
                            $args['offset'] = $offset;
                            $orders = wc_get_orders($args);
                        } else {
                            $orders;
                        }
                        $selectedOrders = count($orders);
                        echo esc_html("\nTotal orders selected: $selectedOrders\n\n");
                        echo esc_html("Selected orders " . wp_json_encode(array_reverse($orders)) . "\n");
                    }

                    $sanitized_post = array_map('sanitize_text_field', wp_unslash($_POST));
                    echo "\n" . wp_json_encode($sanitized_post);

                    // Display a confirmation form before running the function
                    if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
                        echo '<script type="text/javascript">
                            document.getElementById("reportsForm").style.display = "none";
                            document.getElementById("pp_all_orders").style.display = "none";
                            document.getElementById("selctedYearForm").style.display = "none";
                        </script>';
                        echo '<style>
                        table#pp_all_orders, form#selctedYearForm {
                            display: none;
                        }
                        </style>';
                        $payPlusGateway = new WC_PayPlus_Gateway();
                        $payPlusGateway->payPlusOrdersCheck($nonce, $forceInvoice, $forceAll, $allStatuses, $getInvoice, $reportOnly, $orders, $status, $howManyOrders);
                        echo '<br></br><button onclick="window.history.go(-2)">Go Back</button>';
                    } else {
                        echo '<script type="text/javascript">
                            document.getElementById("reportsForm").style.display = "none";
                        </script>';
                        echo '<style>
                        table#pp_all_orders, form#selctedYearForm {
                            display: none;
                        }
                        </style>';
                        echo '<form method="post">';
                        echo '<input type="hidden" name="verifyPayPlusOrders" value="' . esc_attr($nonce) . '">';
                        echo '<input type="hidden" name="month" value="' . esc_attr($month) . '">';
                        echo '<input type="hidden" name="year" value="' . esc_attr($year) . '">';
                        echo '<input type="hidden" name="take" value="' . esc_attr($take) . '">';
                        echo '<input type="hidden" name="offset" value="' . esc_attr($offset) . '">';
                        echo '<input type="hidden" name="orderStatus" value="' . esc_attr($orderStatus) . '">';
                        echo '<input type="hidden" name="getInvoice" value="' . esc_attr($getInvoice) . '">';
                        echo '<input type="hidden" name="forceInvoice" value="' . esc_attr($forceInvoice) . '">';
                        echo '<input type="hidden" name="forceAll" value="' . esc_attr($forceAll) . '">';
                        echo '<input type="hidden" name="reportOnly" value="' . esc_attr($reportOnly) . '">';
                        echo '<input type="hidden" name="allStatuses" value="' . esc_attr($allStatuses) . '">';
                        echo '<input type="hidden" name="order_numbers" value="' . esc_attr(implode(',', array_reverse($orders))) . '">';
                        echo '<p>Are you sure you want to run the PayPlus Orders Validator?</p>';
                        echo '<button type="submit" name="confirm" value="yes">Yes</button>';
                        echo '<button type="submit" name="confirm" value="no">No</button>';
                        echo '</form><br>';
                        echo '<button onclick="history.back()">Go Back</button>';
                    }
                }
            }
        } else {
            wp_die('You do not have permission to perform this action.');
        }
    }


    public static function getFormFields()
    {
        $listOrderStatus = ['default-woo' => __('Default Woo', 'payplus-payment-gateway')];
        $listOrderStatus = array_merge($listOrderStatus, wc_get_order_statuses());
        $formFields = [
            'plugin_title' => [
                'title' => __('PayPlus Plugin Settings', 'payplus-payment-gateway'),
                'type' => 'title',
                'description' => __('Basic plugin settings - set these and you`re good to go!', 'payplus-payment-gateway'),
            ],
            'enabled' => [
                'title' => __('Enable PayPlus+ Payment', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable/Disable', 'payplus-payment-gateway'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout', 'payplus-payment-gateway'),
                'default' => __('Pay with Debit or Credit Card', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'payplus-payment-gateway'),
                'type' => 'textarea',
                'default' => __('Pay securely by Debit or Credit Card through PayPlus', 'payplus-payment-gateway'),
            ],
            'api_test_mode' => [
                'title' => __('Plugin Environment', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => ['no' => __('Production Mode', 'payplus-payment-gateway'), 'yes' => __('Sandbox/Test Mode', 'payplus-payment-gateway')],
                'description' => __('Activate test mode', 'payplus-payment-gateway'),
                'label' => __('Enable Sandbox Mode', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'api_key' => [
                'title' => __('API Key', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus API Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'secret_key' => [
                'title' => __('Secret Key', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus Secret Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'payment_page_id' => [
                'title' => __('Payment Page UID', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('Your payment page UID can be found under Payment Pages in your side menu in PayPlus account', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'device_uid' => [
                'title' => __('POS Device UID (If applicable)', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('Your POS Device UID can be found in your PayPlus account', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'dev_api_key' => [
                'title' => __('Development API Key', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus Dev API Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'dev_secret_key' => [
                'title' => __('Devlopment Secret Key', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('PayPlus Dev Secret Key you can find in your account under Settings', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'dev_payment_page_id' => [
                'title' => __('Development Payment Page UID', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('Your Dev payment page UID can be found under Payment Pages in your side menu in PayPlus account', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'dev_device_uid' => [
                'title' => __('Development POS Device UID (If applicable)', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('Your Dev POS Device UID can be found in your PayPlus account', 'payplus-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'transaction_type' => [
                'title' => __('Transactions Type', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Use global default', 'payplus-payment-gateway'),
                    '1' => __('Charge', 'payplus-payment-gateway'),
                    '2' => __('Authorization', 'payplus-payment-gateway'),
                ],
                'default' => '1',
            ],
            'check_amount_authorization' => [
                'title' => __('Allow amount change', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'yes',
                'desc_tip' => true,
                'label' => __('Transaction amount change', 'payplus-payment-gateway'),
                'description' => __('Choose this to be able to charge a different amount higher/lower than the order total (A number field will appear beside the "Make Paymet" button)', 'payplus-payment-gateway'),
            ],
            'checkout_page_title' => [
                'title' => __('Checkout Page Options', 'payplus-payment-gateway'),
                'type' => 'title',
                'description' => __('Setup for the woocommerce checkout page.', 'payplus-payment-gateway'),
            ],
            'hide_icon' => [
                'title' => __('Hide PayPlus Icon', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' => __('Hide PayPlus Icon In The Checkout Page', 'payplus-payment-gateway'),
                'label' => __('Hide PayPlus Icon In The Checkout Page', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'default' => 'no',
            ],
            'enable_design_checkout' => [
                'title' => __('Design checkout', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' => __('Place the payment icons on the left of the text - relevant for classic checkout page only.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'label' => __('Change icon layout on checkout page.', 'payplus-payment-gateway'),
            ],
            'create_pp_token' => [
                'title' => __('Saved Credit Cards', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Payment via Saved Cards', 'payplus-payment-gateway'),
                'default' => 'no',
                'desc_tip' => true,
                'description' => __('Allow customers to securely save credit card information as tokens for convenient future or recurring purchases.
                <br><br>Saving cards can be done either during purchase or through the "My Account" section in the website.', 'payplus-payment-gateway'),
            ],
            'send_add_data' => [
                'title' => __('Add Data Parameter', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' => __('Relevant only if the clearing company demands "add_data" or "x" parameters', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'label' => __('Send add data parameter on transaction', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'import_applepay_script' => [
                'title' => __('Apple Pay', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Add Apple Pay Script', 'payplus-payment-gateway'),
                'description' => __('Include Apple Pay Script', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'default' => 'yes'
            ],
            'payment_page_title' => [
                'title' => __('Payment Page Options', 'payplus-payment-gateway'),
                'type' => 'title',
                'description' => __('Setup for the PayPlus Payment Page.', 'payplus-payment-gateway'),
            ],
            'display_mode' => [
                'title' => __('Display Mode', 'payplus-payment-gateway'),
                'type' => 'select',
                'description' => __('Set the way the PayPlus Payment Page will be loaded in/from the wordpress checkout page.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'options' => [
                    'redirect' => __('Redirect', 'payplus-payment-gateway'),
                    'iframe' => __('iFrame on the next page', 'payplus-payment-gateway'),
                    'samePageIframe' => __('iFrame on the same page', 'payplus-payment-gateway'),
                    'popupIframe' => __('iFrame in a Popup', 'payplus-payment-gateway'),
                ],
                'default' => 'redirect',
            ],
            'iframe_height' => [
                'title' => __('iFrame Height', 'payplus-payment-gateway'),
                'type' => 'number',
                'default' => 600,
            ],
            'hide_identification_id' => [
                'title' => __('Hide ID Field In Payment Page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Use global default', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('No', 'payplus-payment-gateway'),
                ],
                'default' => '0',
                'description' => __('Hide the identification field in the payment page - ID or Social Security...', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'hide_payments_field' => [
                'title' => __('Hide Number Of Payments In Payment Page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('Use global default', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('No', 'payplus-payment-gateway'),
                ],
                'default' => '0',
                'description' => __('Hide the option to choose more than one payment.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'hide_other_charge_methods' => [
                'title' => __('Hide Other Payment Methods On Payment Page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                ],
                'default' => '1',
                'description' => __('Hide the other payment methods on the payment page.<br>Example: If you have Google Pay and Credit Cards - 
                when the customer selects payment with Google Pay he will only see the Google Pay in the payment page and will not see the CC fields.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'enable_double_check_if_pruid_exists' => [
                'title' => __('Double check ipn', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'label' => __('Double check ipn (Default: Unchecked)', 'payplus-payment-gateway'),
                'description' => __('Before opening a payment page and if a PayPlus payment request uid already exists for this order, perform an ipn check.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'update_statuses_in_ipn' => [
                'title' => __('Update statuses in ipn response', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'label' => __('Update statuses in ipn response (Default: Unchecked)', 'payplus-payment-gateway'),
                'description' => __('In ipn response check status (This will run with or without the callback status update)', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'order_status_title' => [
                'title' => __('Order Settings', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'successful_order_status' => [
                'title' => __('Successful Order Status', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => $listOrderStatus,
                'default' => 'default-woo',
            ],
            'fire_completed' => [
                'title' => __('Payment Completed', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Fire Payment Completed On Successful Charge', 'payplus-payment-gateway'),
                'description' => __('Only relevant if you are using the "Default Woo" in Successful Order Status option above this one.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'default' => 'yes',
            ],
            'failure_order_status' => [
                'title' => __('Failure Order Status', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => $listOrderStatus,
                'default' => 'default-woo',
            ],
            'sendEmailApproval' => [
                'title' => __('Successful Transaction E-mail Through PayPlus Servers', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                ],
                'default' => '0',
            ],
            'sendEmailFailure' => [
                'title' => __('Failure Transaction E-mail Through PayPlus Servers', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                ],
                'default' => '0',
            ],
            'callback_addr' => [
                'title' => __('Callback url', 'payplus-payment-gateway'),
                'type' => 'url',
                'description' => __('To receive transaction information you need a web address<br>(Only http:// or https:// links are applicable)', 'payplus-payment-gateway'),
                'default' => '',
            ],
            'send_products' => [
                'title' => __('Hide products from transaction data', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Send all items as: "General Product" in PayPlus transaction data.', 'payplus-payment-gateway'),
                'description' => __('Send all items as: "General Product" in PayPlus transaction data.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'class' => 'payplus-documents'
            ],
            'recurring_order_set_to_paid' => [
                'title' => __('Mark as "paid" successfully created subscription orders', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'no',
            ],
            'add_product_field_transaction_type' => [
                'title' => __('Add Product Field Transaction Type', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'desc_tip' => true,
                'description' => __('Add a field to the product page to choose the transaction type for the product.<br>
                If this is enabled:<br><br>
                The order is set to J5 - Approval transaction if at least one product is set to Approval.<br><br>
                If all products are set to Charge and the main charge method is already J5, the order is set to J4 - Charge transaction.<br><br>
                However, any Approval product always sets the transaction to Approval.', 'payplus-payment-gateway'),
                'default' => 'no',
            ],
            'exist_company' => [
                'title' => __('Display company name on the invoice', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'no',
                'desc_tip' => true,
                'description' => __('If this option is selected,
                       the name that will appear on the invoice will be taken from the company name field and not from the personal name field.
                         If no company name is entered, the name that will be written on the invoice will be the first name', 'payplus-payment-gateway'),
            ],
            'balance_name' => [
                'title' => __('Display Balance Name', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'no',
            ],
            'block_ip_transactions' => [
                'title' => __('Block ip transactions', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'yes',
                'desc_tip' => true,
                'description' => __('If the client fails transactions more than the number
                         of times you entered, his IP will be blocked for one hour.', 'payplus-payment-gateway'),
            ],
            'block_ip_transactions_hour' => [
                'title' => __('Number of times per hour to block ip', 'payplus-payment-gateway'),
                'type' => 'text',
                'default' => '10',
            ],
            'advanced_title' => [
                'title' => __('PayPlus Advanced Features', 'payplus-payment-gateway'),
                'type' => 'title',
            ],
            'show_get_payplus_data_buttons' => [
                'title' => __('Always display Get PayPlus Data Buttons', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Always display Get PayPlus Data Buttons - Regardless of orders status.', 'payplus-payment-gateway'),
                'label' => __('Get PayPlus Data Buttons Always', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'show_payplus_integrity_check' => [
                'title' => __('Show PayPlus Hash Check button', 'payplus-payment-gateway'),
                'label' => __('Show PayPlus Hash Check button', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Shows the PayPlus Hash check button.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'token_order_payment' => [
                'title' => __('Enable/Disable token payment (Through Admin)', 'payplus-payment-gateway'),
                'label' => __('Applicable for users that can edit orders.', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('If the user can edit orders, and there are saved tokens in the customer account. A token select and "Pay With Token" button will be shown.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'payplus_cron_service' => [
                'title' => __('Activate PayPlus cron', 'payplus-payment-gateway'),
                'label' => __('Enable PayPlus orders cron service.', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('PayPlus cron processes "cancelled" or "pending" orders that are over 30 minutes old, created today, have a payment_page_uid, and do not have the cron test flag (to avoid retesting already tested orders).
Orders that were successful and cancelled manually will not be tested or updated via cron.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'payplus_orders_check_button' => [
                'title' => __('Display PayPlus "Orders Validator Button"', 'payplus-payment-gateway'),
                'label' => __('Show PayPlus "Orders Validator Button" on the side menu.', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('The "PayPlus Orders Validator" button checks all orders created within the last day are in "pending" status or "cancelled" and contain "payplus_page_request_uid". It verifies the PayPlus IPN Process and sets the correct status if needded.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'enable_orders_table' => [
                'title'   => __('Enable display of orders table select in PayPlus Orders Validator', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'description' => __('Display orders table on top of the PayPlus Orders Validator to select orders by month, year and status via checkboxes.', 'payplus-payment-gateway'),
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            'payplus_show_sub_gateways_side_menu' => [
                'title' => __('Display PayPlus Subgateways on the side menu', 'payplus-payment-gateway'),
                'label' => __('Show all subgatways on the side menu.', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('If the side menu is displayed and this is enabled the subgateways will also be displayed.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'payplus_data_save_order_note' => [
                'title' => __('Transaction data in order notes', 'payplus-payment-gateway'),
                'label' => __('Save PayPlus transaction data to the order notes', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Whenever a transaction is done add the payplus data to the order note.<br>This data also appears in the PayPlus Data metabox.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'show_payplus_data_metabox' => [
                'title' => __('Show PayPlus Metabox', 'payplus-payment-gateway'),
                'label' => __('Show the transaction data in the PayPlus dedicated metabox', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => __('Adds the PayPlus transaction data in a dedicated metabox on the side in the order page.', 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'use_old_fields' => [
                'title' => __('Legacy post meta support', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Slower! (For stores that support HPOS with old fields used)', 'payplus-payment-gateway'),
                'description' =>  __('Check this to view orders meta data created before HPOS was enabled on your store.<br>This doesn`t affect stores with no HPOS.<br>If you want to reduce DB queries and are viewing new orders, uncheck this.', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'default' => 'no',
            ],
            'disable_menu_header' => [
                'title' => __('Hide the top menu', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' =>  __('Hide the PayPlus top menu', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'label' => __('Hide the PayPlus top menu', 'payplus-payment-gateway'),
            ],
            'disable_menu_side' => [
                'title' => __('Hide the side menu', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'description' =>  __('Hide the PayPlus side menu', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'label' => __('Hide the PayPlus side menu', 'payplus-payment-gateway'),
            ],
            'hide_custom_fields_buttons' => [
                'title'   => __('Disable custom fields editing in orders', 'payplus-payment-gateway'),
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            'enable_dev_mode' => [
                'title'   => __('Enable partners dev mode', 'payplus-payment-gateway'),
                'desc_tip' => true,
                'description' => __('Enable dev mode for PayPlus partners.', 'payplus-payment-gateway'),
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            'disable_woocommerce_scheduler' => [
                'title' => __('Disable woocommerce scheduler', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'logging' => [
                'title' => __('Logging', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Log debug messages', 'payplus-payment-gateway'),
                'default' => 'yes',
                'custom_attributes' => array('disabled' => 'disabled'),
            ],
        ];
        return $formFields;
    }
}
