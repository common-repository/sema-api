<?php
global $wpdb;
//wp_register_script( );
//$locale = localeconv();


if (!defined('ABSPATH')) {
    exit;
}
//$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );

//$options = get_option( 'sema_settings' );
//$category_exist=$options['category_exist'];
//$products_imported=$options['rows_imported'];
//$options['category_exist']='1';
//update_option( 'sema_settings', $options );
$wpdb->query(_sql("update wp_sema_brands b left JOIN (SELECT meta_value,COUNT(meta_value) as count FROM wp_postmeta WHERE meta_key='_brandid' GROUP BY meta_value) x ON b.brandid=x.meta_value SET b.importedproducts=ifnull(x.count,0)"));
$arrBrand = $wpdb->get_results(_sql("SELECT * FROM wp_sema_brands WHERE active>0;"),ARRAY_A );
?>


<div class="woocommerce">

    <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
    <a href="<?php echo admin_url('options-general.php?page=sema_setting'); ?>" class="nav-tab nav-tab"><?php _e('Settings', 'product-import'); ?></a>
        <a href="<?php echo admin_url('options-general.php?page=sema_import'); ?>" class="nav-tab nav-tab-active"><?php _e('Data Import', 'product-import'); ?></a>
        <a href="https://www.semadata.org/resellers" target="_blank" class="nav-tab nav-tab"><?php _e('Join SEMA Data Co-op', 'product-import'); ?></a>
    </h2>
    <div class="pipe-main-box">
        <div class="pipe-view p-20p" style="width:90%">
            <div class="tool-box">
                <table class="wp-list-table widefat fixed striped tags">
                    <tr>
                        <th class="manage-column" width="7%"><label>Brand ID</label></th>
                        <th class="manage-column" width="15%"><label>Brand Name</label></th>
                        <th class="manage-column" width="7%"><label>Price Adj</label></th>
                        <th class="manage-column"><label>Categories</label></th>
                        <th class="manage-column"><label>Products</label></th>
                        <th class="manage-column"><label>Imported</label></th>
                        <th class="manage-column"><label>Unimported</label></th>
                        <th class="manage-column" width="150px"><label>Action</label></th>
                    </tr>                        
<?php
$arr= array( 'tr' => array('class'=>array()), 
'th' => array('colspan' => array()), 
'td' => array('class' => array()), 
'span' => array('class' => array(),'data-tip' => array()), 
'a' => array('href' => array()), 
'input' => array('id' => array(),'type' => array(),'class' => array(),'onclick' => array(),'value' => array()) );

if(count($arrBrand)>0){
    foreach($arrBrand as $b){
        if($b['selectednodes']) $count=count(explode(',',$b['selectednodes']));
        else $count=0;
        if(1==1 || $b['unimportedproducts']){
            $r=json_decode($b['unimported_reason'],true);
            $reason="Products without name: $r[withoutname]<br>Product without image: $r[withoutimage]<br>Image other than jpg, gif, png: $r[invalidmediatype]";
            $reason = "<span class='woocommerce-help-tip' data-tip='$reason'></span>";
        } 
        //else $reason='';
        if($b['importedproducts']) $deletebutton="<input id='deletebutton' type='button' class='deletebutton button-primary' brandid='$b[brandid]' value='Delete'>";
        else $deletebutton="";
        if($b['priceadjustment']){
            if($b['priceadjustment']>0) $priceadjustment="+$b[priceadjustment]%";
            else $priceadjustment="$b[priceadjustment]%";
        }else $priceadjustment="";

        $row="<tr><td class='column-posts'>$b[brandid]</td><td>$b[brandname]</td><td class='column-posts'>$priceadjustment</td><td class='column-posts'>$count </td><td class='column-posts'>$b[totalproducts]</td><td class='column-posts'>$b[importedproducts] $deletebutton</td><td class='column-posts'>$b[unimportedproducts] $reason</td><td><input type='button' class='button button-primary' onclick='location.href=\"/wp-admin/options-general.php?import=product_import&step=302&aaia=$b[brandid]\"' value='Setting'> ";
        if($count) $row.="<input type='button' class='button button-primary' onclick='location.href=\"/wp-admin/options-general.php?import=product_import&step=200&aaia=$b[brandid]\"' value='Import'></td></tr>";
        else $row.="</td></tr>";
        echo(wp_kses( $row, $arr ));
    }
}else echo("<tr><th colspan=7>Brand Ids do not exist yet. Please set up your Brand Ids first at <a href='options-general.php?page=sema_setting'>SEMA Settings</a> page. &nbsp; <input type='button' class='button button-primary' onclick='location.href=\"options-general.php?page=sema_setting\"' value='Go Settings'></td><td>");
?>                            
                </table>
              
            </div>
            <br><br>

        </div>
        <div class="clearfix"></div>
    </div>
</div>
<script>

jQuery(document).ready(function ($) {
    $('.woocommerce-help-tip' ).tipTip( {
                        'attribute': 'data-tip',
                        'fadeIn': 50,
                        'fadeOut': 50,
                        'delay': 200
                    } );
    $('.woocommerce-help-tip' ).click(function (){
        location.href="/wp-admin/options-general.php?import=product_import&step=303";
    });

    $( ".deletebutton" ).each(function(index) {
        $(this).on("click", function(){
            var brandid = $(this).attr('brandid'); 
            var r = confirm("Do you want to delete all products under "+brandid);
            if (r == true) {
                window.location="options-general.php?import=product_import&step=900&aaia="+brandid;
            } 
        });
    });
});
</script>