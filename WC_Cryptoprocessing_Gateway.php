<?php
class WC_Cryptoprocessing_Gateway extends WC_Payment_Gateway
{
    protected $testmode = false;
    protected $base_url = '';
    protected $api_key = '';
    protected $api_url = '';
    protected $store_id = '';

    public function __construct()
    {
        $this->id = 'wc_cp_payment';
        $this->icon = plugin_dir_url(__FILE__) .'logo.svg';
        $this->has_fields = true;
        $this->method_title = 'CryptoProcessing Gateway';
        $this->method_description = 'Crypto payments';

        $this->supports = array('products');

        $this->init_form_fields();

        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        $this->testmode = 'yes' === $this->get_option('testmode');

        $this->base_url = $this->testmode ? 'https://testnet.cryptoprocessing.io' : 'https://cryptoprocessing.io';
        $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('api_key');
        $this->store_id = $this->testmode ? $this->get_option('test_store_id') : $this->get_option('store_id');
        $this->api_url = $this->base_url .'/api/v1';

        add_action('woocommerce_update_options_payment_gateways_'. $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'wc_cp_payment'),
                'label'       => __('Enable CryptoProcessing Payment Gateway', 'wc_cp_payment'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => __('Title'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc_cp_payment'),
                'default'     => 'CryptoProcessing',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'wc_cp_payment'),
                'default'     => '',
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'wc_cp_payment'),
                'label'       => __('Enable test mode', 'wc_cp_payment'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API keys.', 'wc_cp_payment'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_api_key' => array(
                'title'       => __('Test API Key', 'wc_cp_payment'),
                'type'        => 'text'
            ),
            'test_store_id' => array(
                'title'       => __('Test Store ID', 'wc_cp_payment'),
                'type'        => 'text'
            ),
            'api_key' => array(
                'title'       => __('API Key', 'wc_cp_payment'),
                'type'        => 'text'
            ),
            'store_id' => array(
                'title'       => __('Store ID', 'wc_cp_payment'),
                'type'        => 'text'
            ),
        );
    }

    public function process_payment($order_id)
    {
        global $woocommerce;

        $order = wc_get_order($order_id);
        $order_data = $order->get_data();

        $request_data = array(
            'amount' => floatval($order_data['total']),
            'currency' => $order->get_currency(),
            'success_redirect_url' => '',
            'error_redirect_url' => '',
            'customer_email' => $order->get_billing_email()
        );

        $request_data = apply_filters('wc_cp_checkout_request_data', $request_data, $order);

        if(empty($request_data['amount']) || empty($request_data['currency']) || empty($request_data['customer_email']))
        {
            wc_add_notice(__('Something is wrong!', 'wc_cp_payment'), 'error');
            return false;
        }

        if(empty($this->api_key))
        {
            wc_add_notice(__('API key is not set! You can get is from your account dashboard.', 'wc_cp_payment'), 'error');
            return false;
        }

        if(empty($this->store_id))
        {
            wc_add_notice(__('Store ID is not set! You can get is from your account dashboard.', 'wc_cp_payment'), 'error');
            return false;
        }

        $response = wp_remote_post($this->getCheckoutUrl(), array(
            'headers' => array(
                'content-type' => 'application/json',
                'authorization' => 'Token '.$this->api_key
            ),
            'body' => json_encode($request_data)
        ));

        if(!is_wp_error($response))
        {
            $body = json_decode($response['body'], true);

            if(!empty($body['id']) && !empty($body['store_id']) && $body['store_id'] == $this->store_id)
            {
                $order->payment_complete();
                $order->reduce_order_stock();

                $order->add_order_note(__('Success, your order is paid. Thank you!', 'wc_cp_payment'), true);
                $woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->getCheckoutInvoiceUrl($body['id'])
                );
            }
            else
            {
                if(!empty($body['error']))
                {
                    wc_add_notice($body['error'], 'error');
                }
                else
                {
                    wc_add_notice(__('Please try again.', 'wc_cp_payment'), 'error');
                }

                return false;
            }
        }
        else
        {
            wc_add_notice(__('Connection error.', 'wc_cp_payment'), 'error');
            return false;
        }
    }

    public function getCheckoutUrl()
    {
        return $this->api_url .'/checkout/stores/' .$this->store_id . '/invoices';
    }

    public function getCheckoutInvoiceUrl($invoice_id)
    {
        return $this->base_url .'/checkout/invoices/'. $invoice_id;
    }
}