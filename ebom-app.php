<?php
/*
* Plugin Name: EBOM APP
* Version: 1.0
* Author: 610 Web Lab
*/

class Ebom_App
{
    private $my_plugin_screen_name;
    private static $instance;
    static function GetInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function PluginMenu()
    {
        
        $this->my_plugin_screen_name = add_menu_page(
            'Ebom App',
            'Ebom App',
            'manage_options',
            'ebom-app',
            array($this, 'EbomApp'),
            plugins_url('/ebom-app/images/icon.png', __DIR__),
            36
        );
        $this->my_plugin_screen_name = add_submenu_page(
            $parent_slug='ebom-app', 
            $page_title='Ebom App Setting',
            $menu_title='Setting', 
            'manage_options', 
            'ebom-app-setting', 
            array($this, 'EbomAppSetting')
        );
        
    }
    public function InitPlugin()
    {
        register_deactivation_hook(__FILE__, array($this, 'Deactivation'));

        if (!in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return false;

        add_action('admin_menu', array($this, 'PluginMenu'));
        add_action( 'woocommerce_checkout_order_processed', array($this, 'CheckoutOrderProcessed') );
        //add_action( 'woocommerce_order_status_completed', array($this, 'CheckoutOrderProcessed') );
        register_activation_hook(__FILE__, array($this, 'Activate')); 

    }

    public function Activate() 
    {

        global $wpdb;
        global $jal_db_version;
        $table = $wpdb->prefix . 'settings';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                cust_id varchar(150) NOT NULL,
                username varchar(150) NOT NULL,
                password varchar(150) NOT NULL,
                token_id varchar(150) NOT NULL,
                environment varchar(15) NOT NULL,
                updated_time datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $table_order = $wpdb->prefix . 'orders';
        $sql = "CREATE TABLE $table_order (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            order_id varchar(11) NOT NULL,
            customer_name varchar(150) NOT NULL,
            customer_phone varchar(20) NULL,
            customer_email varchar(150) NOT NULL,
            customer_address varchar(250) NULL,
            items varchar(250) NOT NULL,
            status varchar(20) NULL,
            error varchar(20) NULL,
            created_time datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
            updated_time datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            CONSTRAINT UC_Order UNIQUE (order_id)
        ) $charset_collate;";

        dbDelta($sql);
    }
    public function Deactivation() { 
        global $wpdb;
        $table = $wpdb->prefix . 'settings';
        $sql = "DROP TABLE IF EXISTS $table"; 
        $wpdb->query($sql);

        $table_order = $wpdb->prefix . 'orders';
        $sql = "DROP TABLE IF EXISTS $table_order"; 
        $wpdb->query($sql);
    
    }
    public function GetSettings()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'settings';
        $sql = "select * from $table";
        $setting = $wpdb->get_row( $sql);
        return $setting;
    }
    public function EbomApp()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'orders';
        if(isset($_GET['id']))
        {
           if(isset($_GET['action']) && $_GET['action']=='delete')
           {
                $sql = "Delete from $table where id=".$_GET['id'];
                $wpdb->query($sql);
                wp_safe_redirect(admin_url('admin.php?page=ebom-app'));
                exit();
           }   
        }

        $sql = "select * from $table";
        $orders = $wpdb->get_results( $sql);
        $num_rows = $wpdb->num_rows;

        $currentPage = 1;
        if(isset($_GET['pageno']) && is_numeric($_GET['pageno']) && $_GET['pageno']>0){
            $currentPage = $_GET['pageno'];
        }
        $limit = 20;
        $offset = ($currentPage-1)*$limit;
        $total_pages = ceil($num_rows/$limit);

        $sql = "select * from $table order by order_id desc limit $offset,$limit";
        $orders = $wpdb->get_results( $sql);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Orders</h1>
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">Order No.</th>
                        <th class="manage-column">Customer</th>
                        <th class="manage-column">Phone</th>
                        <th class="manage-column">Email</th>
                        <th class="manage-column">Address</th>
                        <th class="manage-column">Total</th>
                        <th class="manage-column">Items</th>
                        <th class="manage-column">Status</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(sizeof($orders)>0)
                    {
                        foreach($orders as $order)
                        {
                            $wcOrder = new WC_Order( $order->order_id);
                        ?>
                            <tr>
                                <td><a href="<?php echo admin_url('/post.php?post='.$order->order_id.'&action=edit')?>">#<?php echo $order->order_id; ?></a></th>
                                <td><?php echo $order->customer_name; ?></th>
                                <td><?php echo $order->customer_phone; ?></th>
                                <td><?php echo $order->customer_email; ?></td>
                                <td><?php echo $order->customer_address; ?></td>
                                <td><?php echo $wcOrder->get_total().' '.$wcOrder->get_currency(); ?></td>
                                <td><?php echo sizeof(explode(',',$order->items)); ?></td>
                                <td><?php echo $order->status; ?></td>
                                <td><a href="<?php echo admin_url('admin.php?page=ebom-app&id='.$order->id.'&action=delete'); ?>">Delete</a></td>
                            </tr>
                        <?php 
                        } 
                    }
                    else
                    {
                    ?>
                        <tr><td colspan="9">No Order Found</td></tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php
            
            if($total_pages>1){
                echo '<ul style="display: flex;">';
                    if($currentPage>1){
                        $previous = $currentPage-1;
                        echo '<li style="padding:5px;"><a href="'.admin_url('admin.php?page=ebom-app&pageno='.$previous).'" style="text-decoration: none;border: 1px solid #ccc;padding: 5px;">Previous</a></li>';
                    }
                    if($currentPage<$total_pages){
                        $next =$currentPage+1;
                        echo '<li style="padding:5px;"><a href="'.admin_url('admin.php?page=ebom-app&pageno='.$next).'" style="text-decoration: none;border: 1px solid #ccc;padding: 5px;">Next</a></li>';
                    }
                echo '</ul>';
            }
            ?>
        </div>
        <?php
    }
    public function EbomAppSetting()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'settings';

        
        if(isset($_POST['submit'])){
            if(empty($_POST['customerid']) || empty($_POST['username']) || empty($_POST['password']) || empty($_POST['token_id'])){
                $error = "Please Enter All Required Fields";
            }
            else{
                $customerid = $_POST['customerid'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                $token_id = $_POST['token_id'];
                $environment = $_POST['environment'];
                $now = date('Y-m-d H:i:s');
                if(isset($_POST['id'])){
                    $sql = "update $table set `cust_id`='$customerid',username='$username',password='$password',token_id='$token_id',environment='$environment',updated_time='$now' where id=".$_POST['id'];
                }
                else{
                    $sql = "insert into $table (`cust_id`,`username`,`password`,`token_id`,environment,updated_time) values ('$customerid','$username','$password','$token_id','$environment','$now')";
                }
                if($wpdb->query($sql))
                {
                    $success = "Setting Saved Successfully";
                }
                else{
                    $error = "Failed to Save Setting";
                }
            }
        }

        $setting = $this->GetSettings();
    ?>
        <div class="edit-form-section edit-comment-section">
            <div class="inside">
                <div id="comment-link-box">
                    <h2>Setting</h2>
                </div>
            </div>
            <div id="namediv" class="stuffbox">
                <div class="inside">
                    <fieldset>
                        <form method="post">
                            <?php if($error) echo '<b>'.$error.'</b>'; ?>
                            <?php if($success) echo '<b>'.$success.'</b>'; ?>
                            <table class="form-table editcomment" role="presentation">
                                <tbody>
                                    <tr>
                                        <td class="first"><label>Customer Id</label></td>
                                        <td><input type="text" name="customerid" value="<?php if($setting->cust_id) echo $setting->cust_id; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td class="first"><label>User name</label></td>
                                        <td>
                                            <input type="text" name="username" value="<?php if($setting->username) echo $setting->username; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="first"><label>Password</label></td>
                                        <td>
                                            <input type="password" name="password" value="<?php if($setting->password) echo $setting->password; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="first"><label>Token Id</label></td>
                                        <td>
                                            <input type="text" name="token_id" value="<?php if($setting->token_id) echo $setting->token_id; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Environment</td>
                                        <td>
                                            <select name="environment">
                                                <option value="dev" <?php if($setting->environment && $setting->environment=='dev') echo 'selected'; ?>>Dev</option>
                                                <option value="live" <?php if($setting->environment && $setting->environment=='live') echo 'selected'; ?>>Live</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php 
                                    if($setting->id)
                                    {
                                    ?>
                                        <input type="hidden" name="id" value="<?php echo $setting->id;?>">
                                    <?php
                                    } 
                                    ?>
                                    <tr>
                                        <td class="first"><input type="submit" name="submit" value="Save Settings"></td><td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                    </fieldset>
                </div>
            </div>
        </div>
    <?php
    }
    public function CheckoutOrderProcessed($order_id)
    {

        global $wpdb;
        $table_order = $wpdb->prefix . 'orders';
        $setting = $this->GetSettings();
        $order = new WC_Order( $order_id );
        
        $created_date = (array)$order->get_date_created();
        $name = $order->get_billing_first_name().' '.$order->get_billing_last_name();

        $addressline3 = $order->get_billing_city();
        if(!empty($order->get_shipping_state())) $addressline3 .= ', '.$order->get_shipping_state();
        if(!empty($order->get_shipping_country())) $addressline3 .= ', '.$order->get_shipping_country();

        $xml ='<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
            <soap12:Body>    
                <JobPublish xmlns="http://embroideryworks.co.nz/">
                <job>
                    <authorization>
                        <customerid>'.$setting->cust_id.'</customerid>
                        <username>'.$setting->username.'</username>
                        <password>'.$setting->password.'</password>
                        <guid>'.$setting->token_id.'</guid>
                    </authorization>
                    <header>
                        <purchaseorderid>'.$order_id.'</purchaseorderid>
                        <duedate>
                            <year>'.date('Y',strtotime($created_date['date'])).'</year>
                            <month>'.date('m',strtotime($created_date['date'])).'</month>
                            <day>'.date('d',strtotime($created_date['date'])).'</day>
                        </duedate>
                        <comments></comments>
                        <description>BOM Order #'.$order_id.'</description>
                        <deliverydetails>
                            <name>'.$name.'</name>
                            <company>'.$order->get_billing_company().'</company>
                            <phone>'.$order->get_billing_phone().'</phone>
                            <email>'.$order->get_billing_email().'</email>
                            <addressline1>'.$order->get_billing_address_1().'</addressline1>
                            <addressline2>'.$order->get_billing_address_2().'</addressline2>
                            <addressline3>'.$addressline3.'</addressline3>
                            <addressline4>'.$order->get_billing_postcode().'</addressline4>
                            <deliverymethod></deliverymethod>
                        </deliverydetails>
                        <auxilliary>
                            <string1></string1>
                            <string2></string2>
                            <string3></string3>
                            <string4></string4>
                        </auxilliary>
                    </header>
                    <boms>';
                
                
                $items = [];
                foreach($order->get_items() as $item)
                {
                    $items[] = $item->get_name();
                    $product = $item->get_product();
                    
                    $xml.='<bom>
                        <code>'.$product->get_sku().'</code>
                        <size></size>
                        <quantity>'.$item->get_quantity().'</quantity>
                    </bom>';
                }

                $customer_id = $order->get_customer_id();
                $phone = $order->get_billing_phone();
                $email = $order->get_billing_email();
                //insert into custom order table
                $sql = "INSERT INTO $table_order (user_id, order_id, customer_name,customer_phone,customer_email,customer_address,items,created_time,updated_time) 
                VALUES($customer_id, $order_id, '$name','$phone','$email','$addressline3','".implode(', ', $items)."','".$created_date['date']."','".$created_date['date']."') ON DUPLICATE KEY UPDATE order_id=$order_id";
                $wpdb->query($sql);

        $xml .='</boms></job></JobPublish></soap12:Body>
        </soap12:Envelope>';
        
        $status =  'error';
        $error  =  'Unknown Error';
        if($setting->environment=='dev')
        {
            $url = "https://integrateembworks.bluerocket.co.nz/jobconsumer.asmx"; // dev url
        }
        else if($setting->environment=='live')
        {
            $url = "https://integrate.embworks.co.nz/jobconsumer.asmx"; //live url 
        }
        else
        {
            $url = "https://integrateembworks.bluerocket.co.nz/jobconsumer.asmx"; // dev url
        }
        $directory_path = plugin_dir_path( __DIR__).'ebom-app/xml/';
        if($result = $this->PostWP($xml,$url)){
            
            if($result['ResultCode'] == '00'){
                $status =  'success';
                $error =  '';
            }
            else{
                if(isset($result['Errors'])){
                    $status =  'error';
                    
                    if(isset($result['Errors']['Error']['ErrorMessage'])){
                        $error  =  $result['Errors']['Error']['ErrorMessage'];
                    }
                    else{
                        $error  =  $result['Errors']['Error'][0]['ErrorMessage'];
                    }

                }
            }
             
            file_put_contents($directory_path.'/'.$order_id."-order.xml",$xml);
        }
        
        $sql ="update $table_order set status='$status',error='$error' where order_id=".$order_id;
        $wpdb->query($sql);
        if($status ==  'error'){
            $this->SendMail($order_id,$result);
        }
        unlink($directory_path.'/'.$order_id."-order.xml");
    }
    public function SendMail($order_id,$result)
    {
        $ResultCode = $result['ResultCode'];
        $JobNumber = $result['JobNumber'];
        if($result['Errors']['Error']['ErrorMessage']){
            $ErrorMessage = $result['Errors']['Error']['ErrorMessage'];
            $ErrorCode = $result['Errors']['Error']['ErrorCode'];
        }
        else{
            $ErrorMessage = $result['Errors']['Error'][0]['ErrorMessage'];
            $ErrorCode = $result['Errors']['Error'][0]['ErrorCode'];
        }
        $directory_path = plugin_dir_path( __DIR__).'ebom-app/xml/'; 

        $attachments = array( $directory_path . '/'.$order_id.'-order.xml' );
        
        $admin_email = get_option( 'admin_email' );
        
        $subject = "EW Job Integration Failure Notification";
        ob_start();
        include("email_header.php");
        ?>
        <table role="presentation" border="0" cellpadding="0" cellspacing="10px" style="padding: 30px 30px 0px 60px;">
            <tr>
                <td>
                    <h2>EWJobLoader has failed at some point trying to integrate a job.</h2>
                    <p>View the following steps and details below. </p>
                    <p>JOB LOADED: False </p>
                    <p>NOTIFICATIONS SENT: False</p>
                </td>
            </tr>
        </table>    
        <table role="presentation" border="0" cellpadding="0" cellspacing="10px" style="padding: 0px 30px 0px 60px;">
            <tr>
                <td>
                    <h2> Job details follow. </h2>
                    <p>PO Number:<?php echo $order_id; ?></p>      
                </td>
            </tr>
        </table> 
        <table role="presentation" cellpadding="0" cellspacing="10px" style="padding: 0px 30px 0px 60px;" >
            <tr>
                <td>
                    <h2> The job publish response generated these details. </h2>
                    <p>Job Number: <?php echo $JobNumber; ?></p> 
                    <p>Result Code: <?php echo $ResultCode; ?></p>  
                    <p>Error Code: <?php echo $ErrorCode; ?></p>  
                    <p>Error Message: <?php echo $ErrorMessage; ?></p>       
                    
                </td>
            </tr>
        </table> 
        <table role="presentation" bgcolor="#EAF0F6" width="100%" style="margin-top: 50px;" >
            <tr>
                <td align="center" style="padding: 30px 30px;">
                    <h2> Enumerable Errors</h2>
                    <p>embroideryworks.JobPublishError</p> 
                    <p>A file has been attached to this email, please use this to determine the cause.</p>          
                </td>
            </tr>
        </table>
        <?php
        include("email_footer.php");
        $body  = ob_get_contents();
        ob_end_clean();

        $headers = array('Content-Type: text/html; charset=UTF-8','From: Me Myself <'.$admin_email .'>');
        
        if(wp_mail( $admin_email , $subject, $body, $headers,$attachments)){
            return true;
        }
        else{
            return false;
        }
        
        
    }
    public function PostWP($xml,$url)
    {
        $resp = wp_remote_post(
            $url,
            array(
                'method'      => 'POST',
                'headers'     => array(
                    'Content-Type' => 'text/xml',
                    "Accept: text/xml",
                ),
                'body'        => $xml,
                'sslverify'   => 'false'
            )
        );
        if($resp['body'])
        {
            $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $resp['body']);
            $xml = new \SimpleXMLElement($response);
            
            $array = json_decode(json_encode((array)$xml), TRUE); 
            
            if(!empty($array)){

                if(isset($array['soapBody']) && isset($array['soapBody']['JobPublishResponse'])) {
                    return $array['soapBody']['JobPublishResponse']['JobPublishResult'];
                }

            }
        }
        return false;

    }
}

$Ebom_App = Ebom_App::GetInstance();
$Ebom_App->InitPlugin();


?>