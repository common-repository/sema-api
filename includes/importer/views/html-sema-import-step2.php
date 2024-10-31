<?php
//error_reporting(E_ERROR); 

if (!defined('ABSPATH')) {
    exit;
}

//$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );

$pricetoimport=($pricetoimport)?$pricetoimport:"AUTO";

@set_time_limit(0);
$arrSelected = explode(',',$selectednodes);
$options = get_option( 'sema_settings' );
$token=$options['sema_token'];
//$aaia=$options['sema_aaia'];

$url="https://apps.semadata.org/sdapi/plugin/lookup/categories";
$response = wp_remote_post($url, array(
    'body' => array(
        'token'=>$token,'aaia_brandids'=>$aaia,"purpose"=>"WP",
        //'token'=>$token,"purpose"=>"WP",
    ),
    'timeout' => '60','redirection' => 5,'redirection' => 5,'httpversion' => '1.0','sslverify' => false,'blocking' => true,
));
$response=json_decode($response['body'],true);

$arrCatId=array();
$parent=array();
if(array_key_exists('Categories',$response)){
    // 3-tier categories: CategoryID->SubCategoryID->PartTerminologyID
    // Due to Autocare Assc list the same subcategory under multiple categories
    // SubCategoryID(in category tree) = 9,000,000+CategoryID*1,000+SubCategoryID
    foreach($response['Categories'] as $k=>$v){
        if(in_array($v['CategoryId'],$arrCatId)) continue;
        $arrCatId[]=$v['CategoryId'];
        $p=array('id'=>'C'.$v['CategoryId'],'text'=>$v['Name']);
        if(in_array($p['id'],$arrSelected)) $p['state']=array('selected'=>'true');
        //if(in_array($p['id'],$arrUndetermined)) $p['state']=array('opened'=>'true');
        if(array_key_exists('Categories',$v) && count($v['Categories'])>0){
            $kids=array();
            foreach($v['Categories'] as $kk=>$vv){
                $realSubcategoryId = 9000000+$v['CategoryId']*1000+$vv['CategoryId'];
                //$realSubcategoryId = $vv['CategoryId'];
                if(in_array($realSubcategoryId,$arrCatId)) continue;
                $arrCatId[]=$realSubcategoryId;
                $k=array('id'=>'S'.$realSubcategoryId,'text'=>$vv['Name']);
                if(in_array($k['id'],$arrSelected)) $k['state']=array('selected'=>'true');
                //if(in_array($k['id'],$arrUndetermined)) $k['state']=array('opened'=>'true');
                if(array_key_exists('Categories',$vv) && count($vv['Categories'])>0){
                    $kkids=array();
                    foreach($vv['Categories'] as $kkk=>$vvv){
                        if(in_array($vvv['CategoryId'],$arrCatId)) continue;
                        $arrCatId[]=$vvv['CategoryId'];
                        $kk=array('id'=>$vvv['CategoryId'],'text'=>$vvv['Name']);
                        if(in_array($kk['id'],$arrSelected)) $kk['state']=array('selected'=>'true');
                        $kkids[]=$kk;
                    }
                    $k['children']=$kkids;
                }
                $kids[]=$k;               
            }
            $p['children']=$kids;

        }
        $parent[]=$p;
    }
}
$node = json_encode($parent,true);

?>

<div class="woocommerce">

    <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
    <a href="<?php echo admin_url('options-general.php?page=sema_setting'); ?>" class="nav-tab nav-tab"><?php _e('Settings', 'product-import'); ?></a>
        <a href="<?php echo admin_url('options-general.php?page=sema_import'); ?>" class="nav-tab nav-tab-active"><?php _e('Data Import', 'product-import'); ?></a>
        <a href="https://www.semadata.org/resellers" target="_blank" class="nav-tab nav-tab"><?php _e('Join SEMA Data Co-op', 'product-import'); ?></a>
    </h2>

    <div class="pipe-main-box">
        <div class="pipe-view p-20p" style="width:90%">
            <h3 class="title">Brand ID: <?php echo(esc_attr($aaia));?></h3>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row">Custom Prefix</th><td>
                <div class="input-icon input-icon-right"  style="width:250px">
                    <input type="text" class="form-control" maxlength="5" placeholder="up to 5 alphanumeric characters" id="customprefix" name="customprefix" value="<?php echo(esc_attr($customprefix));?>"><i>-</i>
                </div>
                <small><em>Custom prefix is used to replace brand ID in product SKU and up to 5 alphanumeric characters.</em><br></small>
                </td></tr>
                <tr><th scope="row">Price to import</th><td>
                <div class="input-icon input-icon-right"  style="width:250px">
                    <select id="pricetoimport">
                        <option value="AUTO" <?php echo(esc_attr($pricetoimport == 'AUTO' ? ' selected="selected"' : ''));?>>Auto (Default)</option>
                        <option value="RMP" <?php echo(esc_attr($pricetoimport == 'RMP' ? ' selected="selected"' : ''));?>>Map Price (RMP)</option>
                        <option value="JBR" <?php echo(esc_attr($pricetoimport == 'JBR' ? ' selected="selected"' : ''));?>>Jobber Price (JBR)</option>
                        <option value="RET" <?php echo(esc_attr($pricetoimport == 'RET' ? ' selected="selected"' : ''));?>>Retail Price (RET)</option>
                    </select>
                </div>
                <small><em>If you choose to import the jobber price, you must set up mark up % below.</em><br></small>
                </td></tr>
                <tr><th scope="row">Price Adjustment</th><td>
                <div class="input-icon input-icon-right"  style="width:100px">
                    <input type="text" class="form-control" placeholder="-10 or 15"  id="priceadjustment" name="priceadjustment" value="<?php echo(esc_attr($priceadjustment));?>"><i>%</i>
                </div>
                <small><em>Sale prices will be certain percentage discount or markup on retail prices; Negative for discount and positive for markup.  Sale price won't be lower than MAP prices if set.</em><br></small>
                </td></tr>
            </tbody></table>  
            
            <h3 class="title">Please select categories to be imported</h3>
            <div class="tool-box">
                <div id="tree">&nbsp;</div>
            </div>
            <br><br>
            <!--<hr>
            <h3 class="title"><?php _e('Options', 'product-import'); ?></h3>-->
            <div class="tool-box">
                <table id="import-progress"><tr class="importer-loading" style="display:none"><td colspan="2">&nbsp;</td></tr></table>            
                <input type="hidden" id="aaia" name="aaia" value="<?php echo(esc_attr($aaia));?>">
                <input type="hidden" id="selectednodes" name="selectednodes">
                <input type="hidden" id="undeterminednodes" name="undeterminednodes">
                <p class="submit">
                    <input type="submit" id="savecategories" class="button button-primary" value="Save" />
                    <button type="button" class="cancel button" onclick="location.href='options-general.php?page=sema_import'">Cancel</button>
                </p>
            </div>
        </div>
        
        <div class="clearfix"></div>
    </div>
</div>
<script>
/* <![CDATA[ */
var treedata=<?php echo($node);?>;
/* ]]> */
jQuery(document).ready(function ($) {

    $('#tree')
    .jstree({
    'core' : {
        'data' : treedata,
        'force_text' : true,
        'check_callback' : true,
        'themes' : {
            'responsive' : false,
            'icons' : false,
        }
    },
    //'checkbox':{ 'tie_selection': false },
    'plugins' : ['wholerow','checkbox']
    });
    $('#savecategories').click(function() {
        var selectedCat = $('#tree').jstree(true).get_checked().join(',');
        var undeterminedCat = $('#tree').jstree(true).get_undetermined().join(',');
        var customprefix = $('#customprefix').val();
        var priceadjustment=($('#priceadjustment').val().trim()=='')?0:parseInt($('#priceadjustment').val());
        var pricetoimport=$('#pricetoimport').val();
        if(pricetoimport=='JBR'){
            if(isNaN(priceadjustment) || priceadjustment==0){
                alert('Please enter a non-zero number in the price adjustment field if you want to import jobber prices.');
                return;
            }
        }else if(pricetoimport=='RMP'){
            if(isNaN(priceadjustment) || priceadjustment<0){
                alert('Please use positive price adjustment because product price can not below MAP price');
                return;
            }
        }

        $('.importer-loading').show();
        $.ajax({
            type: "POST",
            url: "<?php echo html_entity_decode(wp_nonce_url("$ajax_url?action=get_semadata&type=savecategories&aaia=$aaia")); ?>",
            data: {'selectednodes':selectedCat,'undeterminednodes':undeterminedCat,'priceadjustment':priceadjustment,'customprefix':customprefix,'pricetoimport':pricetoimport},
            success: function(data) {
                $('.importer-loading').hide();
                location.href="options-general.php?page=sema_import";
                //alert('Categories are cached.');
            },
            error: function() {
                //alert('it broke');
            },
        });
    });  
    $('#customprefix').keydown(function (e) {
       var k = e.which;
        var ok = k >= 65 && k <= 90 || // A-Z
            k >= 96 && k <= 105 || // a-z
            k >= 35 && k <= 40 || // arrows
            k == 8 || // Backspaces
            k >= 48 && k <= 57; // 0-9

        if (!ok){
            e.preventDefault();
        }        
    });      
})

</script>