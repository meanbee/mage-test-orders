<?php

/**
 *
 * The purpose of this dude is to save us time when creating test orders
 * The idea is to have random customer data and an order with a number of products in the cart (between 1 and 3).
 *
 * Things I made use of:
 *  - http://inchoo.net/ecommerce/magento/programming-magento/programatically-create-customer-and-order-in-magento-with-full-blown-one-page-checkout-process-under-the-hood/
 *  - http://pravams.com/2011/11/11/magento-create-order-programmatically/
 *  - http://fakester.biz/
 *
 */


// And we're away
require_once("../app/Mage.php");

Mage::app();


// How many orders are we creating
$num_orders = (int) $_GET["orders"];

// Get some random customer information
$customers = getCustomers($num_orders);



// Create a few orders
for ($i = 1; $i <= $num_orders; $i++) {

    // Get random products
    $products = getProducts();

    // Get a customer
    $customer = getCustomer(array_pop($customers));

    // Order some mofo products for some mofo customer
    $order = createOrder($products, $customer);
    printf("Created order %s\n", $order->getIncrementId());

}



/*
 * Get random customer information from fakester.biz
 *
 * @return array
 */
function getCustomers($num_customers) {

    $url = "http://fakester.biz/json?n=$num_customers";
    $json = file_get_contents($url);
    $data = json_decode($json);

    /*
     * Fakester return these fields for customers:
     *
     *   [name] => Johnson, Kreiger and Jenkins
     *   [first_name] => Citlalli
     *   [last_name] => Gorczany
     *   [prefix] => Dr.
     *   [suffix] => Inc
     *   [city] => Loisshire
     *   [city_prefix] => Lake
     *   [city_suffix] => bury
     *   [country] => United Arab Emirates
     *   [secondary_address] => Suite 720
     *   [state] => Wyoming
     *   [state_abbr] => OK
     *   [street_address] => 61204 Lang Garden
     *   [street_name] => Lakin Unions
     *   [street_suffix] => Dam
     *   [zip_code] => 38126-1906
     *   [bs] => unleash world-class technologies
     *   [catch_phrase] => Vision-oriented grid-enabled throughput
     *   [domain_name] => mayer.org
     *   [domain_suffix] => info
     *   [domain_word] => hoppe
     *   [email] => jefferey@baileysimonis.name
     *   [free_email] => emmitt@hotmail.com
     *   [ip_v4_address] => 163.49.36.30
     *   [ip_v6_address] => 61b4:5b6:7d1d:db11:ab29:e003:eb4:161f
     *   [user_name] => meghan
     *
     */

    return $data;
}


/*
 *  We're using guest customers so all we need to do is translate the array
 */
function getCustomer($data) {

    return array(
        'firstname' => $data->{'first_name'},
        'lastname' => $data->{'last_name'},
        'email' => $data->{'email'},
        'street' => $data->{'street_address'},
        'city' => $data->{'city'},
        'region' => $data->{'state'},
        'postcode' => $data->{'zip_code'},
        'telephone' => '123456',
        'country_id' => 'GB'
    );
}

/*
 * Given some customer and product information, create an order
 *
 * @return Mage_Customer_Model_Customer
 */
function createOrder($products, $customer) {

    // Create quote
    $quote = Mage::getModel('sales/quote')
                ->setStoreId(Mage::app()->getStore()->getId());

    // Set email
    $quote->setCustomerEmail($customer['email']);

    // Add products to quote
    foreach ($products as $product) {

        // Not sure why, but we need to load the product again to avoid the erro:
        // "Stock item for Product is not valid"
        $product = Mage::getModel('catalog/product')->load($product->getId());
        $quote->addProduct($product, new Varien_Object(array('qty' => rand(1, 3))));
    }

    // Add address
    $billingAddress = $quote->getBillingAddress()->addData($customer);
    $shippingAddress = $quote->getShippingAddress()->addData($customer);

    // Set shipping and payment moethods
    $shippingAddress->setCollectShippingRates(true)->collectShippingRates()
        ->setShippingMethod('flatrate_flatrate')
        ->setPaymentMethod('checkmo');

    $quote->getPayment()->importData(array('method' => 'checkmo'));
    $quote->collectTotals()->save();

    // Order that shizzle.
    $service = Mage::getModel('sales/service_quote', $quote);
    $service->submitAll();

    $order = $service->getOrder();

    return $order;
}

/*
 * Get random collection of products
 *
 * @return Mage_Catalog_Model_Product_Collection
 */
function getProducts() {

    // How many products in the order
    $num_products = rand(1, 3);

    // Make sure they are simple products to save bother of configuring products
    $collection = Mage::getResourceModel('catalog/product_collection')
                    ->addAttributeToFilter('type_id', array('eq' => 'simple'));

    // Randomise and limit
    $collection->getSelect()->order('rand()');
    $collection->getSelect()->limit($num_products);

    // Let's make sure they're in stock too.
    Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);


    return $collection;
}
