<?php
//error_reporting(E_ERROR); 

global $wpdb;
if (!defined('ABSPATH')) {
    exit;
}
$reason=array('-1'=>'Missing image','-2'=>'Image type not supported','-5'=>'Missing title','-6'=>'Missing category','-7'=>'Missing price','-20'=>'API error','-40'=>'Other error');

$arrBrand = $wpdb->get_results(_sql("SELECT brandid,brandname,selectednodes,importedproducts,currentpid,totalproducts,unimportedproducts,unimported_reason FROM wp_sema_brands WHERE active>0 and unimportedproducts>0 ;"),ARRAY_A );

// status 1=publish, -1=missing image,-2=image type not supported, -5=missing title, -6=missing category, -7=missing price,-20=api error, -40=other error, 99=discontinued
if($_REQUEST['type']=='download'){
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="importlog_'.date('Y-m-d').'.csv"');
    $arrBrand = $wpdb->get_results(_sql("select b.brandid,b.unimportedproducts,p.sku,p.status,p.error from _shopify_products p inner join _shopify_brands b on p.storeid=b.storeid and p.brandid=b.brandid and p.storeid=$storeid and b.storeid=$storeid where p.status<0 order by p.sku;"),ARRAY_A );
    $fp = fopen('php://output', 'wb');
    fputcsv($fp, ['Brand','SKU','Error']);
    foreach ($arrBrand as $r) {
        $line = [$r['brandid'],$r['sku'],$reason[$r['status']]];
        fputcsv($fp, $line);
    }
    fclose($fp);
    return;
}
$aaia=$_GET['brandid'];
$arrBrand = $wpdb->get_results(_sql("select b.brandid,b.brandname,p.partno as sku,p.status from wp_sema_products p inner join wp_sema_brands b on p.brandid=b.brandid where b.brandid='$aaia' and p.status<0 ;"),ARRAY_A );

?>

<div class="woocommerce">

    <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
    <a href="<?php echo admin_url('options-general.php?page=sema_setting'); ?>" class="nav-tab nav-tab"><?php _e('Settings', 'product-import'); ?></a>
        <a href="<?php echo admin_url('options-general.php?page=sema_import'); ?>" class="nav-tab nav-tab-active"><?php _e('Data Import', 'product-import'); ?></a>
        <a href="https://www.semadata.org/resellers" target="_blank" class="nav-tab nav-tab"><?php _e('Join SEMA Data Co-op', 'product-import'); ?></a>
    </h2>
    <div class="pipe-main-box">
        <div class="pipe-view p-20p">
            <div class="tool-box">
            <table class="wp-list-table widefat fixed striped tags">
            <tr>
                <th class="manage-column" width="10%"><label>Brand ID</label></th>
                <th class="manage-column" width="20%"><label>Brand Name</label></th>
                <th class="manage-column"><label>Errors</label></th>
                <th class="manage-column"><label>SKU</label></th>
                <th class="manage-column"><label>Reason</label></th>
            </tr>                        
            <?php
            if(count($arrBrand)>0){
                $prebrandid='';
                for($i=0;$i<count($arrBrand);$i++){
                    $b=$arrBrand[$i];
                    $count=$b['unimportedproducts'];
                    $brandid=$b['brandid'];$brandname=$b['brandname'];$sku=$b['sku'];
                    $error = $reason[$b['status']];
                    if($brandid<>$prebrandid){
                        echo("<tr id=\"$brandid\" class=\"brandheader\"><td>$brandid</td><td>$brandname</td><td>$count</td><td>&nbsp;</td><td><i class=\"fas fa-chevron-down\"> </i></td></tr>");
                        $prebrandid = $brandid;
                    }
                    echo("<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>$sku</td><td>$error</td></tr>");
                }
            }else echo("<tr><th colspan=4>There's no failure of product import.</th><td>");
            ?>                            
        </table>
            </div>
            <br><br>
            <button type="button" class="cancel button" onclick="location.href='options-general.php?page=sema_import'">Back</button>


        </div>
        <div class="clearfix"></div>
    </div>
</div>
