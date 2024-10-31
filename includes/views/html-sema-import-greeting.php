<?php
global $wpdb;
//wp_register_script( );
//$locale = localeconv();
$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
$options = get_option( 'sema_settings' );
$siteid = $options['siteid'];
$token = $options['sema_token'];
$brandids = $options['sema_aaia'];
$membership=array_key_exists('membership',$options)?$options['membership']:"0";
$subscription=array_key_exists('subscription',$options)?$options['subscription']:['aces'=>0,'uiux'=>0,'prod'=>0];
if (!defined('ABSPATH')) {
    exit;
}

$_GET=array_merge(array('section'=>'','orderby'=>'','order'=>''),$_GET);

/*
if($_GET['section']=='fitment'){
    include('html-sema-fitment.php');
    return;
}elseif($_GET['section']=='fitment_edit'){
    include('html-sema-fitment-edit.php');
    return;
}elseif($_GET['section']=='attribute'){
    include('html-sema-attribute.php');
    return;
}elseif($_GET['section']=='attribute_edit'){
    include('html-sema-attribute-edit.php');
    return;
}*/



$orderby=sanitize_text_field($_GET['orderby']);
$order=sanitize_text_field($_GET['order']);

$orderbysql='';
if($orderby){
    $orderbysql=" ORDER BY $orderby $order";
}
$arrBrand = $wpdb->get_results(_sql("SELECT * FROM wp_sema_brands WHERE active>0 $orderbysql"),ARRAY_A );
$arrInactiveBrand = $wpdb->get_results(_sql("SELECT * FROM wp_sema_brands WHERE active=0 AND (termids<>'' OR importedproducts>0) $orderbysql"),ARRAY_A );
$admin_url=admin_url('options-general.php?import=product_import');
$o_brand=$o_name=$o_prefix=$o_products=$o_imported=$o_unimported=$o_date='desc';
$s_brand=$s_name=$s_prefix=$s_products=$s_imported=$s_unimported=$s_date='sortable';
$l_brand=$l_name=$l_prefix=$l_products=$l_imported=$l_unimported=$l_date='asc';
switch ($orderby) {
    case 'brandid':
        $s_brand='sorted';
        $o_brand=$order;
        $l_brand=($order=='asc')?'desc':'asc';
    break;
    case 'brandname':
        $s_name='sorted';
        $o_name=$order;
        $l_name=($order=='asc')?'desc':'asc';
    break;
    case 'prefix':
        $s_prefix='sorted';
        $o_prefix=$order;
        $l_prefix=($order=='asc')?'desc':'asc';
    break;
    case 'totalproducts':
        $s_products='sorted';
        $o_products=$order;
        $l_products=($order=='asc')?'desc':'asc';
    break;
    case 'importedproducts':
        $s_imported='sorted';
        $o_imported=$order;
        $l_imported=($order=='asc')?'desc':'asc';
    case 'unimportedproducts':
        $s_unimported='sorted';
        $o_unimported=$order;
        $l_unimported=($order=='asc')?'desc':'asc';
    break;
    case 'importdate':
        $s_date='sorted';
        $o_date=$order;
        $l_date=($order=='asc')?'desc':'asc';
    break;
}
wp_enqueue_style('sema-css-jquery-ui',"https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css");
$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
?>
<div class="woocommerce">
<?php $_GET['tab']='option';include("includes/menutab.php"); ?>
    <div class="pipe-main-box">
       
        <div class="pipe-import-text">
            <!--<button class='button button-primary' id="import-synccategory" >Sync Categories <div id="loader-newfitment-save" class="loader-div-out" style="display:none"></div></button>-->
            <!--<input type='button' class='button button-primary' onclick='location.href="<?php echo(esc_url($admin_url."&step=500")); ?>"' value='Sync Categories'>-->
            <input type='button' class='button button-primary' onclick='location.href="<?php echo(esc_url($admin_url."&step=400")); ?>"' value='Rebuild Fitments'>
        </div>		
        <div class="pipe-view p-20p">

            <div class="tool-box">
                <table class="wp-list-table widefat fixed striped pages">
                    <tr>
                        <th class="manage-column <?php echo(esc_attr("$s_brand $o_brand"));?>" width="7%"><a href="options-general.php?page=sema_import&amp;orderby=brandid&amp;order=<?php echo(esc_attr($l_brand));?>"><label>Brand</label><span class="sorting-indicator"></span></a></th>
                        <th class="manage-column <?php echo(esc_attr("$s_prefix $o_prefix"));?>" width="7%"><a href="options-general.php?page=sema_import&amp;orderby=prefix&amp;order=<?php echo(esc_attr($l_prefix));?>"><label>Prefix</label><span class="sorting-indicator"></span></a></th>
                        <th class="manage-column <?php echo(esc_attr("$s_name $o_name"));?>" width="15%"><a href="options-general.php?page=sema_import&amp;orderby=brandname&amp;order=<?php echo(esc_attr($l_name));?>"><label>Brand Name</label><span class="sorting-indicator"></span></a></th>
                        <th class="manage-column" width="7%"><label>Price Adj</label></th>
                        <th class="manage-column"><label>Categories</label></th>
                        <th class="manage-column <?php echo(esc_attr("$s_products $o_products"));?>"><a href="options-general.php?page=sema_import&amp;orderby=totalproducts&amp;order=<?php echo(esc_attr($l_products));?>"><label>Products</label><span class="sorting-indicator"></span></a></th>
                        <th class="manage-column <?php echo(esc_attr("$s_imported $o_imported"));?>"><a href="options-general.php?page=sema_import&amp;orderby=importedproducts&amp;order=<?php echo(esc_attr($l_imported));?>"><label>Imported</label><span class="sorting-indicator"></span></a></th>
                        <th class="manage-column "><label>Failed</label></th>
                        <th class="manage-column <?php echo(esc_attr("$s_date $o_date"));?>"><a href="options-general.php?page=sema_import&amp;orderby=importdate&amp;order=<?php echo(esc_attr($l_date));?>"><label>Import Date</label><span class="sorting-indicator"></span></a></th>
                        <th class="manage-column" width="150px"><label>Action</label></th>
                    </tr>                        
<?php
$arr= array( 'tr' => array('class'=>array()), 
            'th' => array('colspan' => array()), 
            'td' => array('class' => array()), 
            'span' => array('class' => array(),'data-tip' => array()), 
            'a' => array('href' => array()), 
            'input' => array('id' => array(),'type' => array(),'class' => array(),'onclick' => array(),'value' => array(),'brandid' => array()) );
if ( !class_exists( 'WooCommerce' )){
    $row="<tr><th colspan=7>Please install wooCommerce plugin before you import products</td><td>";
    echo(wp_kses( $row, $arr ));
}elseif(count($arrBrand)==0){
    $row="<tr><th colspan=7>Brand Ids do not exist yet. Please set up your Brand Ids first at SEMA Settings page.</td><td>";
    echo(wp_kses( $row, $arr ));
}else{
    foreach($arrBrand as $b){
        if($b['selectednodes']) $count=count(explode(',',$b['selectednodes']));
        else $count=0;
        if(1==1 || $b['unimportedproducts']){
            //$r=json_decode($b['unimported_reason'],true);
            //$reason="Products without name: $r[withoutname]<br>Product without image: $r[withoutimage]<br>Image other than jpg, gif, png: $r[invalidmediatype]";
            //$reason = "<span class='woocommerce-help-tip' data-tip='$reason'></span>";
            $unimportedproducts="<a href=\"$admin_url&step=303\">$b[unimportedproducts]</a>";
        }else{
            $unimportedproducts=0;
        }
        //else $reason='';
        if($b['importedproducts']) $deletebutton="<input id='deletebutton' type='button' class='deletebutton button-primary' brandid='$b[brandid]' value='Delete'>";
        else $deletebutton="";
        if($b['priceadjustment']){
            if($b['priceadjustment']>0) $priceadjustment="+$b[priceadjustment]%";
            else $priceadjustment="$b[priceadjustment]%";
        }else $priceadjustment="";
        if($b['importdate']) $b['importdate']=date( 'Y/m/d', strtotime($b['importdate']));
        $row="<tr><td class='column-posts'>$b[brandid]</td><td class='column-posts'>$b[prefix]</td><td>$b[brandname]</td><td class='column-posts'>$priceadjustment</td><td class='column-posts'>$count </td><td class='column-posts'>$b[totalproducts]</td><td class='column-posts'>$b[importedproducts] $deletebutton</td><td class='column-posts'>$unimportedproducts</td><td class='column-posts'>$b[importdate]</td><td><input type='button' class='button button-primary' onclick='location.href=\"$admin_url&step=302&aaia=$b[brandid]\"' value='Setting'> ";
        if($count) $row.="<input type='button' class='button button-primary' onclick='location.href=\"$admin_url&step=200&aaia=$b[brandid]\"' value='Import'></td></tr>";
        else $row.="</td></tr>";
        echo(wp_kses( $row, $arr ));
    }
}
if(count($arrInactiveBrand)>0){
    foreach($arrInactiveBrand as $b){
        if($b['selectednodes']) $count=count(explode(',',$b['selectednodes']));
        else $count=0;
        $deletebutton="<input id='deletebutton' type='button' class='deletebutton button-primary' brandid='$b[brandid]' value='Delete'>";

        if($b['priceadjustment']){
            if($b['priceadjustment']>0) $priceadjustment="+$b[priceadjustment]%";
            else $priceadjustment="$b[priceadjustment]%";
        }else $priceadjustment="";

        if($b['importdate']) $b['importdate']=date( 'Y/m/d', strtotime($b['importdate']));

        else $reason='';
        $row="<tr class='row-inactive-brand'><td class='column-posts'>$b[brandid]</td><td class='column-posts'>$b[prefix]</td><td>$b[brandname]</td><td>$priceadjustment</td><td class='column-posts'>$count </td><td class='column-posts'>$b[totalproducts]</td><td class='column-posts'>$b[importedproducts] $deletebutton</td><td class='column-posts'>$b[unimportedproducts]</td><td class='column-posts'>$b[importdate]</td><td></td></tr>";
        echo(wp_kses( $row, $arr ));
    }
}

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
   
    $('.woocommerce-help-tip' ).click(function (){
        location.href="<?php echo(esc_url($admin_url));?>&step=303";
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
    /*
	$("#import-synccategory").click(function(e){
		$("#loader-newfitment-save").show();
        $.get("<?php echo html_entity_decode(wp_nonce_url("$ajax_url?action=get_semadata&type=synccategories"));?>", function (d) {
            $("#loader-newfitment-save").hide();

        });
	});	 */

    dialog = $( "#dialog-semauserinfo" ).dialog({
      autoOpen: false,
      height: 480,
      width: 640,
      modal: true,
      close: function() {
        //form[ 0 ].reset();
        //allFields.removeClass( "ui-state-error" );
      }
    });


});

</script>