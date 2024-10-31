<?php
//error_reporting(E_ERROR); 

global $wpdb,$api_url;
$arrYMMS=array();

$uploadpath=wp_upload_dir();
$uploadpath = $uploadpath['baseurl']."/";
$fitmentid=urldecode($_GET['fitmentid']);

function compare_status($a, $b)
{
  return strnatcmp($b['status'],$a['status']);
}

$url="$api_url/ajax.php?siteid=$siteid&type=wp_get_productidsbyfitmentid&fitmentid=$fitmentid";
$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
$response=json_decode($response['body'],true);
if ($response['success']){
	$prods=$response['products'];
	$make=$response['make'];
	$model=$response['model'];
	$submodel=$response['submodel'];
	$years=$response['years'];
	$baselink=admin_url('options-general.php?page=sema_import&section=fitment');
	$make && $breadscrumb="<a href=\"$baselink\">All</a> > <a href=\"$baselink&make=$make\">$make</a>";
	$model && $breadscrumb.=" > <a href=\"$baselink&make=$make&model=$model\">$model</a>";
	$breadscrumb && $breadscrumb="<span class=\"sema-breadcrumb\">$breadscrumb</span>";
	$pids=implode(',',array_keys($prods));
}
if($pids){
	$products = $wpdb->get_results(_sql("SELECT p.id,p.post_title, psku.meta_value AS sku,CONCAT('$uploadpath',img.meta_value) AS image
	FROM wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku' 
	left JOIN wp_postmeta thumb ON p.ID=thumb.post_id AND thumb.meta_key='_thumbnail_id' 
	left JOIN wp_postmeta img ON  thumb.meta_value=img.post_id AND img.meta_key='_wp_attached_file'
	WHERE p.post_type='product' AND p.post_status in ('publish','draft')
	AND p.id in ($pids)"),ARRAY_A);
	foreach($products as $k=>$p){
		$products[$k] = array_merge($p, $prods[$p['id']]);
	}
	usort($products, 'compare_status');	
	$count_selected=count($products);
}



$img_placeholder=wc_placeholder_img_src();
//$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
wp_enqueue_style('sema-css-woocommerce', plugins_url('/woocommerce/assets/css/admin.css', __FILE__));
wp_enqueue_style('sema-css-jquery-ui',"https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css");

?>

<script>var ajax_url='<?php echo(esc_url($ajax_url)); ?>';</script>
<div class="woocommerce">
    <div class="pipe-main-box">
		<h3 style="display:inline-block">Fitments</h3><?=$breadscrumb?>

		<div class="ymms-content">
			<table id="fitment-table" class="wp-list-table widefat fixed striped tags">
				<tr>
					<td class="manage-column" width="30px"></td>
					<th class="manage-column" width="100px"><label>Make</label></th>
					<th class="manage-column" width="200px"><label>Model</label></th>
					<th class="manage-column" width="200px"><label>Sub-model</label></th>
					<th class="manage-column" width="*"><label></label></th>
				</tr>                        
				<?php
				echo("<tr><td><input type='checkbox' checked disabled /></td><td>$make</td><td>$model</td><td>$submodel</td><td>$years</td></tr>");
				?>                            
			</table>
			<br>
			<div class="clearfix"></div>
		</div>

        <div class="clearfix"></div>
    </div>
    <div class="pipe-main-box">
		<div class="ymms-content">
			<table id="fitment-table" class="wp-list-table widefat fixed striped tags">
				<tr>
					<td class="manage-column" width="30px"></td>
					<th class="manage-column" width="100px">Image</th>
					<th class="manage-column" width="20%"><label>SKU</label></th>
					<th class="manage-column" width="*"><label>Name</label>
					<span id="edit_save" class="button-ymms-save"><i class="fas fa-save" data-toggle="tooltip" data-placement="bottom" title="Save fitment"></i> </span>
					<span id="edit_assignto" class="button-ymms-add"><div id="loader-div-save" class="loader-div-out" style="display:none"></div> <i class="fas fa-plus-square" data-toggle="tooltip" data-placement="bottom" title="Add products"></i></span>
					</th>
				</tr>                        
				<?php
				$productIds=',';
				if(count($products)>0){
					$makemodel='';
					foreach($products as $b){
						if($b['image']) $img=str_replace('.png','-100x100.png',str_replace('.jpeg','-100x100.jpeg',str_replace('.jpg','-100x100.jpg',$b['image'])));
						else $img=$img_placeholder;
						if($b['status']) echo("<tr><td><input type='checkbox' id='check_productcid' name='check_productcid' class='fitment_products' value='$b[id]' checked /></td><td class='wildfat_image'><img src='$img' width='40px'></td><td>$b[sku]</td><td>$b[post_title]</td></tr>");
						else echo("<tr class=\"strike\"><td><input type='checkbox' id='check_productcid' name='check_productcid' class='fitment_products' value='$b[id]' /></td><td class='wildfat_image'><img src='$img' width='40px'></td><td>$b[sku]</td><td>$b[post_title]</td></tr>");
						$productIds.=$b['id'].',';
					}
				}else{?>
				<tr id="introduction"><td colspan="4">
				  <ul class="fitment-bulletin">
					<li>
						<span class="fa-stack fa-2x">
							<i class="fas fa-car fa-stack-2x" style="color:green"></i>
							<i class="fas fa-car-battery fa-stack-sx"></i>
						</span>						
						<div class="fitment-bulletin-text">Assign Fitments to your Products</div></li>
				  </ul>
				</td></tr>

				<?php }
				?>                             
			</table>
			<br>
			<div class="clearfix"></div>
		</div>

        <div class="clearfix"></div>
    </div>	
</div>

<div id="dialog-form" title="Assign to products">
	<div><input type="search" id="fitment-keyword"> <button id="fitment-search" class="ui-button ui-corner-all ui-widget">Search</button><div id="loader-div-out" class="loader-div-out"></div>
		<div id="fitment-info-total"><?php echo(($count_selected>2)?$count_selected.' products selected':$count_selected.' product selected'); ?></div>
	</div>
	<div class="clearfix"></div>
	<input type="hidden" id="productIds_checked" value=",">
	<input type="hidden" id="productIds_marked" value=",">
	<input type="hidden" id="fitmentid" value="<?php echo(esc_attr($fitmentid));?>">
	<table id="product-table" class="wp-list-table widefat fixed striped tags">
		<thead>
		<tr>
			<td class="manage-column" width="30px"><input type='checkbox' id='products_checkall' value='' /></td>
			<th class="manage-column" width="100px">Image</th>
			<th class="manage-column" width="300"><label>SKU</label></th>
			<th class="manage-column" width="80%"><label>Name</label></th>
		</tr>
		</thead>
		<tbody></tbody>
	</table>
	<div class="wp-clearfix"></div>
	<div id="pagination-product"></div>
</div>
 

<script>
var pagination='';
var productIds='<?php echo(esc_attr($productIds));?>';
jQuery(document).ready( function($) {
    function assigntoProducts() {
      var valid = true;
	  $("#loader-div-out").show();
	  var fitmentid = $("#fitmentid").val();
	  var productIds_marked = $("#productIds_marked").val();
	  $.get(ajax_url+'?action=get_semadata&type=assigntoproducts&fitmentid='+fitmentid+'&productids='+productIds_marked, function (d) {
			$("#loader-div-out").hide();
			document.location.href="options-general.php?page=sema_import&section=fitment_edit&fitmentid="+fitmentid+"&t="+Date.now();
		});
      return valid;
    }
 
    dialog = $( "#dialog-form" ).dialog({
      autoOpen: false,
      height: 700,
      width: 900,
      modal: true,
      buttons: {
        "Confirm": assigntoProducts,
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
      event.preventDefault();
      assigntoProducts();
    });
 
	$("#products_checkall").change(function () {
		$("input:checkbox:not(:disabled).product_check").prop('checked', $(this).prop("checked")).change();
	});
	/*
	$(".product_check").change(function () {
		$("input:checkbox:not(:disabled).product_check").prop('checked', $(this).prop("checked"));
	});	*/
    $(".fitment_products").on('change', function () {
        $(this).closest('tr').toggleClass('strike', $(this).not(':checked'));
    });	
    $("#edit_assignto" ).click(function() {
		showProducts();

      	dialog.dialog( "open" );
	});	
    $("#edit_save" ).click(function() {
		$("#loader-div-save").show();
		var fitmentid = $("#fitmentid").val();
		var deletedpids=$("input:checkbox:not(:disabled):not(:checked).fitment_products").map(function () {
			return this.value;
		}).get().join(",");
		var pids=$("input:checkbox:not(:disabled):checked.fitment_products").map(function () {
			return this.value;
		}).get().join(",");
		if(deletedpids){
			$.get(ajax_url+'?action=get_semadata&type=savefitment&fitmentid='+fitmentid+'&pids='+pids+'&deletedpids='+deletedpids, function (d) {
								//$('#data .default').text(d.content).show();
				document.location.href="options-general.php?page=sema_import&section=fitment_edit&fitmentid="+fitmentid+"&t="+Date.now();
				$("#loader-div-save").hide();

			});
		}else setTimeout(function() { $("#loader-div-save").hide(); }, 500);
		
	});		
	$("#fitment-search").click(function(){
		showProducts();
	});
	$('#fitment-keyword').keyup(function(e){
		if(e.keyCode == 13){
			showProducts();
		}
	});
	function markProducts(){
		var productIds_marked=$("#productIds_marked").val();
		marks=$("input:checkbox:not(:disabled):checked.product_check").map(function () {
			if(productIds_marked.indexOf(','+this.value+',') !=-1) return null;
			else return this.value;
		}).get().join(",");
		if(marks) $("#productIds_marked").val(productIds_marked+marks+',');
	}

	function showProducts() {
		$("#loader-div-out").show();
		var keyword = encodeURIComponent($("#fitment-keyword").val());
		var container = $('#pagination-product');
		container.pagination({
			dataSource: ajax_url+'?action=get_semadata&type=products&pagination='+pagination+'&keyword='+keyword,
			locator: 'Products',
			totalNumberLocator: function(response) {
				// you can return totalNumber by analyzing response content
				return response.Pagination.count;
			},
			pageSize: 20,
			ajax: {
				beforeSend: function() {
				//container.prev().html('Loading data from flickr.com ...');
				}
			},
			callback: function(response, pagination) {
				$("#loader-div-out").hide();
				$("#products_checkall").prop("checked", false);
				var container = $('#pagination-product');
				var productsAry = response;

				var allProductsDisplay = "<div class = 'productDiv'><div>--- No parts found matching your vehicle description ---</div></div>";
				var productsDisplay = '';
				var productIds_marked=$("#productIds_marked").val();
				for(var i = 0; i < productsAry.length; i++){
					var image=productsAry[i].image;
					if(image) image=image.replace('.jpg','-100x100.jpg').replace('.png','-100x100.png').replace('.jpeg','-100x100.jpeg');
					else image='<?php echo(esc_url($img_placeholder));?>';

					if(productIds.indexOf(','+productsAry[i].id+',') !=-1) productsDisplay += "<tr><td><input type='checkbox' class='product_check' value='"+productsAry[i].id+"' checked disabled width='30px'/></td><td class='wildfat_image' width='100px'><img width='40px' src='" + image + "'></td><td width='300px'>"+ productsAry[i].sku + "</td><td width='70%'>" +  productsAry[i].name + "</td></tr>";
					else if(productIds_marked.indexOf(','+productsAry[i].id+',') !=-1) productsDisplay += "<tr><td><input type='checkbox' class='product_check' value='"+productsAry[i].id+"' checked width='30px'/></td><td class='wildfat_image' width='100px'><img width='40px' src='" + image + "'></td><td width='300px'>"+ productsAry[i].sku + "</td><td width='70%'>" +  productsAry[i].name + "</td></tr>";
					else productsDisplay += "<tr><td width='30px'><input type='checkbox' class='product_check' value='"+productsAry[i].id+"' /></td><td class='wildfat_image' width='100px'><img width='40px' src='" + image + "'></td><td width='300px'>"+ productsAry[i].sku + "</td><td width='70%x'>" +  productsAry[i].name + "</td></tr>";
				}
				/*
				for(var i = 0; i < 2; i++){
					productsDisplay = productsDisplay + productsDisplay;
				}*/
				
				$("#product-table>tbody").empty().append(productsDisplay);
				$("input:checkbox:not(:disabled).product_check").change(function(){
					var productIds_marked=$("#productIds_marked").val();
					if($(this).is(":checked")) {
						temp = productIds_marked+$(this).val();
						temp = temp.split(',');
						temp = temp.filter(String);
						temp = unique(temp);
						temp = ','+temp.join(',')+',';
						$("#productIds_marked").val(temp);
					}else{
						$("#productIds_marked").val(productIds_marked.replace($(this).val()+',',''));
					}
					productIds_total = productIds+$("#productIds_marked").val();
					arrProducts = productIds_total.split(',');
					arrProducts = arrProducts.filter(String);
					//arrProducts = unique(arrProducts);
					if(arrProducts.length<2) $("#fitment-info-total").html(arrProducts.length+' product selected');
					else $("#fitment-info-total").html(arrProducts.length+' products selected');
					$("#fitment-info-total").show();
				});
			}
		})
	};	
	function unique(list) {
		var result = [];
		$.each(list, function(i, e) {
			if ($.inArray(e, result) == -1) result.push(e);
		});
		return result;
	}
	/*
	.data("ui-autocomplete")._renderItem = function (ul, item) {
         return $("<li></li>")
             .data("item.autocomplete", item)
             .append("<a>" + item.label + "</a>")
             .appendTo(ul);
	 };*/
	var proto = $.ui.autocomplete.prototype,initSource = proto._initSource;

	function filter( array, term ) {
		var matcher = new RegExp( $.ui.autocomplete.escapeRegex(term), "i" );
		return $.grep( array, function(value) {
			return matcher.test( $( "<div>" ).html( value.label || value.value || value ).text() );
		});
	}

	$.extend( proto, {
		_initSource: function() {
			if ( this.options.html && $.isArray(this.options.source) ) {
			this.source = function( request, response ) {
				response( filter( this.options.source, request.term ) );
			};
			} else {
			initSource.call( this );
			}
		},

		_renderItem: function( ul, item) {
			return $( "<li></li>" )
			.data( "item.autocomplete", item )
			.append( $( "<a></a>" )[ this.options.html ? "html" : "text" ]( item.label ) )
			.appendTo( ul );
		}
	});	 
});
</script>
</body>
</html>
