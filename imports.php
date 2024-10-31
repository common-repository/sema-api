<?php
global $wpdb,$api_url;
//wp_register_script( );
//$locale = localeconv();
$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
$options = get_option( 'sema_settings' );
$siteid = $options['siteid'];
$token = $options['sema_token'];
$brandids = $options['sema_aaia'];
$membership=array_key_exists('membership',$options)?$options['membership']:"0";
$subscription=array_key_exists('subscription',$options)?$options['subscription']:['aces'=>0,'uiux'=>0,'prod'=>0];
$fitment_updated=$options['fitment_updated'];
if (!defined('ABSPATH')) {
    exit;
}

// update wp_sema_brands 
/*
$row2 = $wpdb->get_row(_sql("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'wp_sema_brands' AND column_name = 'options'") );
if(empty($row2)){
    $wpdb->query(_sql("ALTER TABLE wp_sema_brands ADD COLUMN options varchar(5000) NULL DEFAULT NULL,ADD COLUMN check_date datetime NULL DEFAULT NULL,ADD COLUMN updates mediumint(9) NULL DEFAULT 0,ADD COLUMN updates_info varchar(500) NULL DEFAULT NULL,ADD COLUMN finishedproducts mediumint(9) NULL DEFAULT NULL ;") );
}*/	


$_GET=array_merge(array('section'=>'','orderby'=>'','order'=>''),$_GET);


if($_GET['section']=='fitment'){
    $_GET['tab']='fitment';
    include("includes/menutab.php");
    include('includes/views/html-sema-fitment.php');
    return;
}elseif($_GET['section']=='fitment_edit'){
        $_GET['tab']='fitment';
        include("includes/menutab.php");
        include('includes/views/html-sema-fitment-edit.php');
        return;
}elseif($_GET['section']=='attribute'){
    $_GET['tab']='attribute';
    include("includes/menutab.php");
    include('html-sema-attribute.php');
    include('includes/views/html-sema-attribute.php');
    return;
}else{
    $_GET['tab']='import';
    include("includes/menutab.php");
}

$baselink=admin_url('options-general.php?page=sema_import');
$import_url=admin_url('options-general.php?import=product_import');


if($_GET['type']=='loadBrandUpdates'){
    $return='';
    if($membership==10 && $subscription['prod']){
        require_once("includes/parallet.php");	
        $urls = array();$return=array();
        $sql="SELECT brandid,termids,DATE(importdate) as importdate FROM wp_sema_brands WHERE active=1 AND length(brandname)>0 AND length(termids)>0 AND (check_date is null OR DATEDIFF(NOW(),check_date)>0) ORDER BY brandid LIMIT 2";
        $arrBrands = $wpdb->get_results(_sql($sql),ARRAY_A);
        if($arrBrands){
            //update brand updates
            foreach($arrBrands as $b){
                $targetdate=($b['importdate'])?$b['importdate']:'2022-01-01';
                $urls[]="https://apps.semadata.org/sdapi/plugin/brand/newdata?AAIA_BrandID=$b[brandid]&TargetDate=$targetdate&CategoryIds=$b[termids]&Token=$token&Purpose=Shopify";
            }
            $getter = new ParallelGet($urls);
            $sql='';
            $check_date=date("Y-m-d H:i:s");
            foreach($getter->response as $b){
                $b=json_decode($b,true);
                if($b['errors']) continue;
                $b=array_filter($b,function($e,$k){
                    if(!in_array($k,array('BrandID','Success','Message','BrandName','NewCategoryCount','PriceChangeCount'))) return $e;
                },ARRAY_FILTER_USE_BOTH);
                $nb=json_encode($b);
                $newskucount=$b['NewSKUCount'];
                $removedskucount=$b['RemovedSKUCount'];
                if($sql) $sql.=" UNION SELECT '$b[BrandAAIAID]','$check_date','$nb'";
                else $sql="SELECT '$b[BrandAAIAID]' as id,'$check_date' as c,'$nb' as i";
                // prepare return
                $tooltip='';
                foreach($b as $k=>$in){
                    $tooltip.="<div>$k: $in</div>";
                }
                $brandid=$b['BrandAAIAID'];
                $updates_html="";
                $newskucount && $updates_html.="<span class=\"update-plugins\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"$tooltip\" brandid=\"$brandid\"><span class=\"plugin-count\">$newskucount</span></span>";
                $removedskucount && $updates_html.="<span class=\"update-plugins-red\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"Discontinued: $removedskucount\" brandid=\"$brandid\"><span class=\"plugin-count\">$removedskucount</span></span>";

                $return[$brandid]=$updates_html;
            }
            if($sql){
                $sql="UPDATE wp_sema_brands b INNER JOIN ($sql) v ON b.brandid = v.id SET b.check_date='$check_date',updates_info=v.i ;";
                $wpdb->query(_sql($sql));
            }
        }

    }
    echo "<!--WC_START-->";
    echo json_encode($return);
    echo "<!--WC_END-->";
    exit;
}

$orderby=sanitize_text_field($_GET['orderby']);
$order=sanitize_text_field($_GET['order']);

$orderbysql='';
if($orderby){
    $orderbysql=" ORDER BY $orderby $order";
}
$arrBrand = $wpdb->get_results(_sql("SELECT * FROM wp_sema_brands WHERE active>0 $orderbysql"),ARRAY_A );
$arrInactiveBrand = $wpdb->get_results(_sql("SELECT * FROM wp_sema_brands WHERE active=0 AND (termids<>'' OR importedproducts>0) $orderbysql"),ARRAY_A );
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
  //wp_enqueue_script('sema-js-jquery-ui',"https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js");
  wp_enqueue_style('sema-css-jquery-ui',"https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css");
  $plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
?>

<div class="woocommerce">
    <div class="pipe-main-box">
       
        <div class="pipe-import-text">
            <!--<button class='button button-primary' id="import-synccategory" >Sync Categories <div id="loader-newfitment-save" class="loader-div-out" style="display:none"></div></button>-->
            <!--<input type='button' class='button button-primary' onclick='location.href="<?php echo(esc_url($import_url."&step=500")); ?>"' value='Sync Categories'>-->
            <?php if(empty($fitment_updated) || $fitment_updated=="0000-00-00 00:00:00"){?>		
            <input type='button' class='button button-primary' onclick='location.href="<?php echo(esc_url($import_url."&step=400")); ?>"' value='Rebuild Fitments'>
            <?php }?>		
    
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
        $brandid=$b['brandid'];
        if($b['selectednodes']) $count=count(explode(',',$b['selectednodes']));
        else $count=0;
        if(1==1 || $b['unimportedproducts']){
            //$r=json_decode($b['unimported_reason'],true);
            //$reason="Products without name: $r[withoutname]<br>Product without image: $r[withoutimage]<br>Image other than jpg, gif, png: $r[invalidmediatype]";
            //$reason = "<span class='woocommerce-help-tip' data-tip='$reason'></span>";
            $unimportedproducts="<a href=\"$import_url&step=303&brandid=$brandid\">$b[unimportedproducts]</a>";
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

        // updates
        $updates_info=json_decode($b['updates_info'],true);
        $newskucount=$updates_info['NewSKUCount'];
        $removedskucount=$updates_info['RemovedSKUCount'];
        $updates_html="";
        if($b['brandname']){
            if($membership==10 && $subscription['prod']){
                $tooltip='';
                foreach($updates_info as $k=>$in){
                    $tooltip.="<div>$k: $in</div>";
                }
                $targetdate=($b['importdate'])?$b['importdate']:'2022-01-01';
                $newskucount && $updates_html.="<span class=\"update-plugins\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"$tooltip\" brandid=\"$brandid\"><span class=\"plugin-count\">$newskucount</span></span>";
                $removedskucount && $updates_html.="<span class=\"update-plugins-red\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"Discontinued: $removedskucount\" brandid=\"$brandid\" targetdate=\"$targetdate\"><span class=\"plugin-count\">$removedskucount</span></span>";
            }else{
                $updates_html="<span class=\"update-plugins-dot\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"$tooltip\" brandid=\"$brandid\"><span class=\"plugin-count\"></span></span>";
            }
        }

        //progress bar
        if($membership==10 && $b['brandname']){
            if(empty($b['totalproducts'])) $percent=0;
            else $percent=($b['finishedproducts']>=$b['totalproducts'])?100:round(100*$b['finishedproducts']/$b['totalproducts']);
            $progressbar="<div class=\"progress\" data-toggle=\"tooltip\" role=\"progressbar\" aria-label=\"Success example\" aria-valuenow=\"25\" aria-valuemin=\"0\" aria-valuemax=\"100\" title=\"Completion: $percent%\"><div class=\"progress-bar bg-success\" style=\"width: $percent%\"></div></div>";
        }
                
        $row="<tr><td class='column-posts'>$b[brandid]</td><td class='column-posts'>$b[prefix]</td><td>$b[brandname] <span id=\"div-notification-$brandid\">$updates_html</span></td><td class='column-posts'>$priceadjustment</td><td class='column-posts'>$count </td><td align=\"center\">$progressbar $b[totalproducts]</td><td class='column-posts'>$b[importedproducts] $deletebutton</td><td class='column-posts'>$unimportedproducts</td><td class='column-posts'>$b[importdate]</td><td> ";
        if($membership==10 && $b['brandname']){
            $row.="<input type='button' class='button button-primary' onclick='location.href=\"$import_url&step=302&aaia=$b[brandid]\"' value='Setting'> ";
            if($count) $row.="<input type='button' class='button button-primary' onclick='location.href=\"$import_url&step=200&aaia=$b[brandid]\"' value='Import'>";
        }
        $row.="</td></tr>";
        //echo(wp_kses( $row, $arr ));
        echo($row);
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

$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
?>                            
                </table>
              
            </div>
            <br><br>

        </div>
        <div class="clearfix"></div>
    </div>
</div>

<div id="dialog-form" title="Delete discontinued products">
	<div>
		<div>
            <input type="search" id="keyword"> <button id="keyword-search" class="ui-button ui-corner-all ui-widget">Search</button>
		    <div id="loader-discontinued-products" class="loader-div-out"></div>
		    <div class="search-title-text"></div><div id="pagination-product"></div>
		</div>
		<div class="clearfix"></div>
		<input type="hidden" id="productIds_marked" value=",">
		<table id="product-table" class="wp-list-table widefat fixed striped tags">
			<thead>
			<tr>
				<th class="manage-column" width="30px"><input type='checkbox' id='products_checkall' value='' /></th>
				<th class="manage-column manage-column-name" width="100px">Image</th>
				<th class="manage-column manage-column-name" width="300">SKU</th>
				<th class="manage-column manage-column-name" width="80%">Name</th>
				<th class="manage-column manage-column-message" colspan="3" width="100%" style="display:none"></th>
			</tr>
			</thead>
			<tbody></tbody>
		</table>
        
        <div class="wp-clearfix"></div>
    </div>	

</div>
 
<script>
var ajax_url='<?php echo(esc_url($ajax_url)); ?>';
var productcount=0;

jQuery(document).ready(function ($) {
   
    $('.woocommerce-help-tip' ).click(function (){
        location.href="<?php echo(esc_url($import_url));?>&step=303";
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
	$("#products_checkall").change(function () {
		if(productcount>20){
			if($(this).prop("checked")){
				$(".manage-column-name").hide();
				$(".manage-column-message").html($("input:checkbox:not(:disabled).product_check").length+" selected <a href=\"#\" class=\"quicklinks\" id=\"select-all-discontinued\">Select all discontinued products under this brand</a>");
				$("#select-all-discontinued").on("click",function(){
					$(".manage-column-message").html("All 20+ products are selected");
					$("#productIds_marked").val("all");
				});				
				$(".manage-column-message").show();
			}else{
				$(".manage-column-name").show();
				$(".manage-column-message").hide();
			}
		}
		$("input:checkbox:not(:disabled).product_check").prop('checked', $(this).prop("checked")).change();
	});	

	$('[data-toggle="tooltip"]').tooltip({  position: { my: "left+15 center", at: "right center" },content: function () {
              return $(this).prop('title');
    }});

<?php if($membership==10 & $subscription['prod']){ ?>	
	$('.update-plugins-red').on('click',function(){
		var brandid = $(this).attr('brandid'); 
		var targetdate = $(this).attr('targetdate'); 
		$("#products_checkall").prop('checked',false);
		$("#productIds_marked").val(',');
		$("#keyword").val("");
		loadDiscontinuedProducts(brandid,targetdate);
		pbrandid=brandid;
		ptargetdate=targetdate;
		dialog.data('brandid', brandid).data('targetdate', targetdate).dialog( "open" );		//var r = confirm("Do you want to delete all products under "+brandid);
		//if (r == true) {
			//window.location="productimport.php?shop=<?php echo($shop); ?>&step=900&aaia="+brandid;
		//} 
		//var r = confirm("Do you want to delete all discontinued products under "+brandid);
	});
	$('.update-plugins').on('click',function(){
		var brandid = $(this).attr('brandid'); 
		var r = confirm("Do you want to import new products under "+brandid);
		if (r == true) {
            var s="<?php echo(esc_url($import_url));?>&update=new&step=200&aaia="+brandid;
			window.location.href=s;
		} 
	});	

    dialog = $( "#dialog-form" ).dialog({
      autoOpen: false,
      height: 800,
      width: 1000,
      modal: true,
      buttons: {
        "Confirm": deleteDiscontinued,
        Cancel: function() {
          dialog.dialog( "close" );
        }
      },
      close: function() {
        //form[ 0 ].reset();
        //allFields.removeClass( "ui-state-error" );
      }
    });
 
    form = dialog.find( "form" ).on( "submit", function( event ) {
      deleteDiscontinued();
    });

	function deleteDiscontinued() {
	  event.preventDefault();
	  $("#loader-div-out").show();
	  var productIds_marked = $("#productIds_marked").val();
	  var brandid = $(this).data('brandid');
	  var targetdate = $(this).data('targetdate');
	  document.location.href="<?php echo(esc_url($import_url));?>&step=900&aaia="+brandid+"&productids="+productIds_marked+"&t="+Date.now();
    }
	function loadDiscontinuedProducts(brandid,targetdate){
    	//$("#loader-div-out").show();
		var keyword = encodeURIComponent($("#keyword").val());
        var container = $('#pagination-product');
        container.pagination({
            dataSource: 'admin-ajax.php?action=get_semadata&type=loadDiscontinuedProducts&brandid='+brandid+'&targetdate='+targetdate+'&keyword='+keyword,
            locator: 'Products',
            pageSize: 20,
            totalNumberLocator: function(response) {
                productcount=response.Pagination.count;
                return response.Pagination.count;
            },			   
            triggerPagingOnInit:true,
            ajax: {
                beforeSend: function() {
                $("#product-table>tbody").empty();
                $(".search-title-text").html('');					
                //$("#loader-discontinued-products").show();// show set display to block
                    $("#loader-discontinued-products").css('display', 'inline-block');
                    //var aTag = $("div[class='entry-content']");
                    //$('html,body').animate({scrollTop: aTag.offset().top},'fast');
                }
            },
            
            callback: function(productsAry, pagination) {
                $("#loader-discontinued-products").hide();
                if(pagination.totalNumber>0){
                pageend = (pagination.pageSize*pagination.pageNumber>pagination.totalNumber)?pagination.totalNumber:pagination.pageSize*pagination.pageNumber;
                searchtext = (pagination.pageSize*(pagination.pageNumber-1)+1)+" - "+pageend+" of "+ pagination.totalNumber+" results";//+searchtextArr[1];
                }else searchtext="0 result";
                
                $(".search-title-text").html(searchtext);
                var allProductsDisplay = "<div class = 'productDiv'><div>--- No parts found matching your vehicle description ---</div></div>";
                var productsDisplay = '';
                var productIds_marked=$("#productIds_marked").val();
                for(var i = 0; i < productsAry.length; i++){
                    if(productIds_marked.indexOf(','+productsAry[i].id+',') !=-1) productsDisplay += "<tr><td><input type='checkbox' class='product_check' value='"+productsAry[i].id+"' checked width='30px'/></td><td class='wildfat_image' width='100px'><img width='50px' src='" + productsAry[i].image + "'></td><td width='300px'>"+ productsAry[i].sku + "</td><td width='70%'>" +  productsAry[i].name + "</td></tr>";
                    else productsDisplay += "<tr><td width='30px'><input type='checkbox' class='product_check' value='"+productsAry[i].id+"' /></td><td class='wildfat_image' width='100px'><img width='50px' src='" + productsAry[i].image + "'></td><td width='300px'>"+ productsAry[i].sku + "</td><td width='70%x'>" +  productsAry[i].name + "</td></tr>";
                }

                $("#product-table>tbody").empty().append(productsDisplay);
                $("input:checkbox:not(:disabled).product_check").change(function(){
                    /*
                    var productIds_marked=$("input:checkbox:not(:disabled).product_check:checked").map(function () {
                            return this.value;
                        }).get().join(",");
                    $("#productIds_marked").val(productIds_marked);*/
                    var productIds_marked=$("#productIds_marked").val();
                    if($(this).is(":checked")) {
                        $("#productIds_marked").val(productIds_marked+$(this).val()+',');
                    }else{
                        $("#productIds_marked").val(productIds_marked.replace($(this).val()+',',''));
                    }                    

                });				  

            }
        });
        container.show();
	}
	function loadBrandUpdates(){
		$("#loader-account").css('display', 'inline-block');
		$.get('<?php echo(esc_url($baselink));?>&type=loadBrandUpdates', function (d) {
            // Get the valid JSON only from the returned string
            if ( d.indexOf("<!--WC_START-->") >= 0 ) d = d.split("<!--WC_START-->")[1]; // Strip off before after WC_START
            if ( d.indexOf("<!--WC_END-->") >= 0 ) d = d.split("<!--WC_END-->")[0]; // Strip off anything after WC_END

			var jsonData = JSON.parse(d);
			if(Object.keys(jsonData).length){
				$.each(jsonData, function(key, value){
					$('#div-notification-'+key).html(value);
				});
				$('[data-toggle="tooltip"]').tooltip({  position: { my: "left+15 center", at: "right center" },content: function () {
						return $(this).prop('title');
				}});				
				$("#loader-account").hide();
				setTimeout(function(){
					loadBrandUpdates();
				},100);
			}else $("#loader-account").hide();
			
		});		
	}
	loadBrandUpdates();
<?php } ?>		

});

</script>