<?php
//error_reporting(E_ERROR); 

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WordPress Importer class for managing the import process of a CSV file
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( ! class_exists( 'WP_Importer' ) )	return;

class SEMA_Product_Import extends WP_Importer {

	var $id;
	var $file_url;
	var $delimiter;
        var $merge;
	var $merge_empty_cells;
	var $categoryInsert='';

	// Results
	var $import_results  = array();
	/**
	 * Constructor
	 */
	public function __construct() {
		if(WC()->version < '2.7.0'){
			$this->log                     = new WC_Logger();

		}else
		{
			$this->log                     = wc_get_logger();

		}
		
		$this->import_page             = 'product_import';
		$this->file_url_import_enabled = apply_filters( 'woocommerce_csv_product_file_url_import_enabled', true );
	}
	function sema_insertCategory($catId,$catName,$parentId){
		//Define the category
		global $wpdb;

		if($catId>9000000 && $my_cat_id){
			$realcatId=$catId % 1000;
			$my_real_cat_id=$wpdb->get_var($wpdb->prepare(_sql("SELECT term_id FROM wp_terms WHERE term_group=%d"),$realcatId));
			$wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_sema_attr_taxonomy WHERE abs(term_id)=%d;"),$my_real_cat_id));
			$wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_terms WHERE term_id=%d;"),$my_real_cat_id));
			$wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_termmeta WHERE term_id=%d;"),$my_real_cat_id));
			$wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_term_taxonomy WHERE term_id=%d;"),$my_real_cat_id));
		}

		$my_cat_id=$wpdb->get_var($wpdb->prepare(_sql("SELECT term_id FROM wp_terms WHERE term_group=%d"),$catid));
		if(!$my_cat_id){
			$my_cat = array('cat_name' => $catName,'category_parent' => $parentId, 'taxonomy'=>'product_cat');
			// Create the category
			$my_cat_id = wp_insert_category($my_cat);    
			// Create category meta info
			//add_term_meta( $my_cat_id, "category_termid" , $catId);
			wp_update_term($my_cat_id, 'product_cat', array(
				'term_group' => $catId,
			) );
		}else{
			$wpdb->query($wpdb->prepare(_sql("UPDATE wp_term_taxonomy SET parent=%d WHERE term_id=%d AND taxonomy='product_cat' "),$parentId,$my_cat_id));
		}
		return $my_cat_id;

	}
	function _sql( $sql ) {
		global $wpdb;
		$sql = str_replace(' wp_',' '.$wpdb->prefix,$sql);
		return $sql;
	}	
	/**
	 * Registered callback function for the WordPress Importer
	 *
	 * Manages the three separate stages of the CSV import process
	 */
	public function dispatch() {
		global $woocommerce, $wpdb,$api_url;
		$batchsize=10;

		if ( ! empty( $_POST['delimiter'] ) ) {
			$this->delimiter = stripslashes( trim( $_POST['delimiter'] ) );
		}else if ( ! empty( $_GET['delimiter'] ) ) {
			$this->delimiter = stripslashes( trim( $_GET['delimiter'] ) );
		}

		if ( ! $this->delimiter )
			$this->delimiter = ',';
                
                if ( ! empty( $_POST['merge'] ) || ! empty( $_GET['merge'] ) ) {
			$this->merge = 1;
		} else{
			$this->merge = 0;
		}
                
		if ( ! empty( $_POST['merge_empty_cells'] ) || ! empty( $_GET['merge_empty_cells'] ) ) {
			$this->merge_empty_cells = 1;
		} else{
			$this->merge_empty_cells = 0;
		}

		$step = sanitize_text_field(empty( $_GET['step'] ) ? 0 : (int) $_GET['step']);

		switch ( $step ) {
			case 0 :
				$this->header();
				$this->greet();
			break;
			case 4 :
				$aaia=sanitize_text_field($_GET['aaia']);
				$options = get_option( 'sema_settings' );
				$siteid=$options['siteid'];

				// Check access - cannot use nonce here as it will expire after multiple requests
				if ( ! current_user_can( 'manage_woocommerce' ) )
					die();

				add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

				if ( function_exists( 'gc_enable' ) )
					gc_enable();

				@set_time_limit(0);
				@ob_flush();
				@flush();
				$wpdb->hide_errors();
				
				//$options = get_option( 'sema_settings' );
				//$options['products_exist']='1';
				//update_option( 'sema_settings', $options );


				wp_defer_term_counting( true );
				wp_defer_comment_counting( true );


				// reset transients for products
				if ( function_exists( 'wc_delete_product_transients' ) ) {
					wc_delete_product_transients();
				} else {
					$woocommerce->clear_product_transients();
				}

				delete_transient( 'wc_attribute_taxonomies' );

				//$wpdb->query(_sql("DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_wc_product_type_%')"));


				$this->backfill_parents();

				// update umimportedproducts
				// todo here.....
				//$importedproducts=$wpdb->get_var(_sql("select count(*) from wp_posts p INNER JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' WHERE p.post_type='product' AND brand.meta_value='$aaia';"));
				//$unimportedproducts=$wpdb->get_var(_sql("select count(*) from wp_sema_products where brandid='$aaia' and wpid is null;"));
				//$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands SET unimportedproducts=%d,unimported_reason=%s,currentpid=0 WHERE brandid=%s "),$unimportedproducts,$unimportedreason,$aaia));
				$importedproducts=$wpdb->get_var(_sql("select sum(case when status>0 then 1 else 0 end) from wp_sema_products WHERE brandid='$aaia';"));
				$unimportedproducts=$wpdb->get_var(_sql("select sum(case when status<=0 then 1 else 0 end) from wp_sema_products WHERE brandid='$aaia';"));
				$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands set importedproducts=%d,unimportedproducts=%d WHERE brandid='$aaia';"),$importedproducts,$unimportedproducts));
				
				$url="$api_url/ajax.php?siteid=$siteid&uuid=$uuid&type=wp_rebuild_fitment";
				$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '180'));

				
				// SUCCESS
				echo "<!--WC_START-->";
				_e( '<a href="options-general.php?page=sema_import">Import completed.</a>', 'product-import-for-woo' );
				echo "<!--WC_END-->";
				$this->import_end();
				if (WC()->version >= '3.6' && !wc_update_product_lookup_tables_is_running()) {
                    wc_update_product_lookup_tables();
                }

				//exit;
			break;
			case 200 :
				$_GET=array_merge(['partno'=>''],$_GET);
				$aaia=sanitize_text_field($_GET['aaia']);
				$update=sanitize_text_field($_GET['update']);
				if(!$aaia){
					exit;
				}

				$row=$wpdb->get_row($wpdb->prepare(_sql("SELECT * FROM wp_sema_brands WHERE brandid=%s"),$aaia),ARRAY_A);
				$termids=$row['termids'];
				$currentpid = $row['currentpid'];

				$this->header();
				$arrReturn = $wpdb->get_results(_sql("SELECT term_id,term_group AS meta_value FROM wp_terms WHERE term_group>0;"),ARRAY_A );
				$arrCatId=array();
				foreach($arrReturn as $value){
					$arrCatId[$value['meta_value']]	= $value['term_id'];
				}
				if($update=='new') $tbody='Preparing new products for selected categories.';
				elseif($currentpid) $tbody='Preparing products for selected categories. Import starts from where it stopped.';
				else $tbody='Preparing products for selected categories';
				?>
				<table id="import-progress" class="widefat_importer widefat">
					<thead>
						<tr>
							<th class="status">&nbsp;</th>
							<th class="row"><?php _e( 'Row', 'product-import-for-woo' ); ?></th>
							<th><?php _e( 'SKU', 'product-import-for-woo' ); ?></th>
							<th><?php _e( 'Product', 'product-import-for-woo' ); ?></th>
							<th class="reason"><?php _e( 'Status Msg', 'product-import-for-woo' ); ?></th>
						</tr>
					</thead>
					<tfoot>
						<tr class="importer-loading">
							<td colspan="5"></td>
						</tr>
					</tfoot>
					<tbody><tr id="row-0" class="imported"><td><mark class="result" title="importd">Preparing..</mark></td><td class="row" colspan="4"><?php echo(esc_attr($tbody));?></td></tr></tbody>
				</table>
				<script type="text/javascript">
					jQuery(document).ready(function($) {

						if ( ! window.console ) { window.console = function(){}; }

						var i               = <?php echo(esc_attr($currentpid+1)); ?>;
						var done_count      = 0;
						var rows = [];
						var total = 0;
						var engs= [];

						function import_rows( sParts ) {
							return $.ajax({
								url:        '<?php echo add_query_arg( array( 'import' => $this->import_page, 'step' => '205', 'aaia' =>$aaia, 'merge' => ! empty( $_GET['merge'] ) ? '1' : '0' ), admin_url( 'admin.php' ) ); ?>&currentpid='+i,
								data:       {parts:sParts},
								type:       'POST',
								success:    function( response ) {
									if ( response ) {

										try {
											// Get the valid JSON only from the returned string
											if ( response.indexOf("<!--WC_START-->") >= 0 )
												response = response.split("<!--WC_START-->")[1]; // Strip off before after WC_START

											if ( response.indexOf("<!--WC_END-->") >= 0 )
												response = response.split("<!--WC_END-->")[0]; // Strip off anything after WC_END

											var results;
											if(response.indexOf('class="wp-die-message"')>=0){
												var arr = /<div class="wp-die-message">(.*)<\/div>/.exec(response);
												results={'error':response};
												
											}
											// Parse
											results = $.parseJSON( response );

											if ( results.error ) {

												$('#import-progress tbody').append( '<tr id="row-' + i + '" class="error"><td class="status" colspan="5">' + results.error + '</td></tr>' );

												i++;

											} else if ( results.import_results && $( results.import_results ).size() > 0 ) {

												$( results.import_results ).each(function( index, row ) {
													if(row['status']=='comment'){
														$('#import-progress tbody').append( '<tr id="row-" class=""><td> </td><td class="row"> </td><td>' + row['sku'] + '</td><td>' + row['post_id'] + ' - ' + row['post_title'] + '</td><td class="reason">' + row['reason'] + '</td></tr>' );
													}else{
														$('#import-progress tbody').append( '<tr id="row-' + i + '" class="' + row['status'] + '"><td><mark class="result" title="' + row['status'] + '">' + row['status'] + '</mark></td><td class="row">' + i + '</td><td>' + row['sku'] + '</td><td>' + row['post_id'] + ' - ' + row['post_title'] + '</td><td class="reason">' + row['reason'] + '</td></tr>' );
														i++;
													}
												});
											}

										} catch(err) {}

									} else {
										$('#import-progress tbody').append( '<tr class="error"><td class="status" colspan="5">' + '<?php _e( 'AJAX Error', 'product-import-for-woo' ); ?>' + '</td></tr>' );
									}

									var w = $(window);
									var row = $( "#row-" + ( i - 1 ) );

									if ( row.length ) {
										w.scrollTop( row.offset().top - (w.height()/2) );
									}

									done_count++;
									if(rows.length>0){
										var data = rows.shift();
										import_rows( data);
									}else{
										$('body').trigger( 'woocommerce_csv_import_request_complete' );
									}
								},
								error: function(response){
									$('#import-progress tbody').append( '<tr class="error"><td class="status" colspan="5">' + '<?php _e( 'AJAX timeout', 'product-import-for-woo' ); ?>' + '</td></tr>' );
									i++;
									var w = $(window);
									var row = $( "#row-" + ( i - 1 ) );
									if ( row.length ) {
										w.scrollTop( row.offset().top - (w.height()/2) );
									}
									if(rows.length>0){
										var data = rows.shift();
										import_rows( data);
									}else{
										$('body').trigger( 'woocommerce_csv_import_request_complete' );
									}

								},
							});
						}
						function prepare_products(  ) {
							return $.ajax({
								url:        '<?php echo add_query_arg( array( 'import' => $this->import_page, 'step' => '201', 'aaia' =>$aaia,'update'=>$update,'partno'=>$_GET['partno'], 'category_insert' => ! empty( $_POST['category_insert'] ) ? '1' : '0' ), admin_url( 'admin.php' ) ); ?>',
								type:       'POST',
								success:    function( response ) {
									if ( response ) {
										try {
											// Get the valid JSON only from the returned string
											if ( response.indexOf("<!--WC_START-->") >= 0 )
												response = response.split("<!--WC_START-->")[1]; // Strip off before after WC_START
											if ( response.indexOf("<!--WC_END-->") >= 0 )
												response = response.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
											// Parse
											var results = $.parseJSON( response );
											if ( results.error ) {
												$('#import-progress tbody').append( '<tr id="row-' + i + '" class="error"><td class="status" colspan="5">' + results.error + '</td></tr>' );
												i++;
											} else if ( results.import_results && $( results.import_results ).size() > 0 ) {
												$( results.import_results ).each(function( index, row ) {
													rows.push(row);
												});
											}
										} catch(err) {}

									} else {
										$('#import-progress tbody').append( '<tr class="error"><td class="status" colspan="5">' + '<?php _e( 'AJAX Error', 'product-import-for-woo' ); ?>' + '</td></tr>' );
									}

									var w = $(window);
									var row = $( "#row-" + ( i - 1 ) );

									if ( row.length ) {
										w.scrollTop( row.offset().top - (w.height()/2) );
									}
									if(results.total>0){
										$('#import-progress tbody').html('<tr id="row-0" class="imported"><td><mark class="result" title="importd">Ready</mark></td><td class="row" colspan="4">'+results.total+' products ready to be imported.</td></tr>');
										var data = rows.shift();
										import_rows( data);
									}else{
										$('body').trigger( 'woocommerce_csv_import_request_complete' );
									}

									//done_count++;
									//$('body').trigger( 'woocommerce_csv_import_request_complete' );
								},
							});
						}

						prepare_products();

						$('body').on( 'woocommerce_csv_import_request_complete', function() {
							var data = {
								action: 'woocommerce_csv_import_request',
							};

							$.ajax({
								url: '<?php echo add_query_arg( array( 'import' => $this->import_page, 'step' => '4', 'aaia' => $aaia ), admin_url( 'admin.php' ) ); ?>',
								data:       data,
								type:       'POST',
								success:    function( response ) {
									if ( response.indexOf("<!--WC_START-->") >= 0 )
										response = response.split("<!--WC_START-->")[1]; // Strip off before after WC_START
									if ( response.indexOf("<!--WC_END-->") >= 0 )
										response = response.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
									$('#import-progress tbody').append( '<tr class="complete"><td colspan="5">' + response + '</td></tr>' );
									$('.importer-loading').hide();
								}
							});
						} );

						// Regenerate a specified image via AJAX
					
					});
				</script>
				<?php


			break;
			case 201 : // prepare products
				global $woocommerce, $wpdb;
				$options = get_option( 'sema_settings' );
				$token=$options['sema_token'];
				$aaia=sanitize_text_field($_GET['aaia']);
				$partno=sanitize_text_field($_GET['partno']);
				$update=sanitize_text_field($_GET['update']);

				$row=$wpdb->get_row($wpdb->prepare(_sql("SELECT * FROM wp_sema_brands WHERE brandid=%s"),$aaia),ARRAY_A);
				$termids=$row['termids'];
				$currentpid = $row['currentpid'];
				$priceadjustment = $row['priceadjustment'];
				$pricetoimport = $row['pricetoimport'];
				$importdate = substr($row['importdate'],0,10);
				$updates_info=json_decode($row['updates_info'],true);
				if($partno){
					$arrPartno=array_filter(explode("|,",$partno));
					$count=count($arrPartno);
					$partnos=implode("|,",$arrPartno);
					$results = array();
					$results['import_results']  = array("|,".$partnos);
					$results['total']=$count;
					echo "<!--WC_START-->";
					echo json_encode( $results );
					echo "<!--WC_END-->";
					exit;
				}
				

				$this->hf_log_data_change( 'API-import', ' start to import' );

				$PageNumber=1;$PageSize=5000;$batchs=[];
				$url="https://apps.semadata.org/sdapi/plugin/lookup/productsbycategory";
				do{
					$parameters=['body' => array(
							'token'=>$token,'aaia_brandids'=>$aaia,'CategoryIds'=>$termids,"PageSize"=>$PageSize,"PageNumber"=>$PageNumber,"purpose"=>"WP",
						),
						'timeout' => '60','redirection' => 5,'redirection' => 5,'httpversion' => '1.0','sslverify' => false,'blocking' => true,];
					if($update=='new'){
						$parameters['body']['action']="created";
						$parameters['body']['targetdate']=$importdate;
					} 
					$response = wp_remote_post($url, $parameters);
					$response=json_decode($response['body'],true);
										
					$arrParts=array();$partstring="";
					$total=$response['AvailableCount'];
					$batchcount=count($response['Products']);
					//if($currentpid>=$total) $currentpid=0;
					foreach($response['Products'] as $p){
						$count++;
						//if($count<=$currentpid) continue;
						$sParts.="|,".$p['PartNumber'];
						$partstring.="|,".$p['PartNumber'];
						$arrParts[]=$p['PartNumber'];
						if($count==$total || $count % $batchsize == 0){ //$batchsize=10;
							$batchs[]=$sParts;
							$sParts='';
						}
					}
					$PageNumber++;
				}while($batchcount>=$PageSize);
				if($currentpid>=$total) $currentpid=0;
				if($currentpid) $batchs=array_slice($batchs,$currentpid/$batchsize);
				if(empty($update)){// regular update
					$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands SET totalproducts=%d,importdate=NOW() WHERE brandid=%s "),$total,$aaia));
					if($total){
						$options['updatefitment']='1';
						update_option( 'sema_settings', $options );
					}
				}else{
					$updates_info['NewSKUCount']=0;
					$updates_info=json_encode($updates_info);
					$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands SET updates=0,updates_info=%s,importdate=NOW() WHERE brandid=%s "),$updates_info,$aaia));
				}
				$results = array();
				$results['import_results']  = $batchs;
				$results['total']=$total;


				echo "<!--WC_START-->";
				echo json_encode( $results );
				echo "<!--WC_END-->";
				//exit;
				break;
			case 205 : // batch import product, default batch size is 10.
				// Check access - cannot use nonce here as it will expire after multiple requests
				$options = get_option( 'sema_settings' );
				$token=$options['sema_token'];

				//$parts=sanitize_text_field($_POST['parts']);
				$parts=sanitize_text_field($_REQUEST['parts']);

				$aaia=sanitize_text_field($_GET['aaia']);
				if ( ! current_user_can( 'manage_woocommerce' ) )
					die();

				add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

				if ( function_exists( 'gc_enable' ) )
					gc_enable();
				@set_time_limit(0);
				@ob_flush();
				@flush();
				$wpdb->hide_errors();

				$this->importProduct($parts,$aaia);
				$this->import_end();

				$results                    = array();
				$results['import_results']  = $this->import_results;


				echo "<!--WC_START-->";
				echo json_encode( $results );
				echo "<!--WC_END-->";
				//exit;
				break;
			case 302 : // select category
				$aaia=sanitize_text_field($_GET['aaia']);
				$row=$wpdb->get_row($wpdb->prepare(_sql("SELECT * FROM wp_sema_brands WHERE brandid=%s;"),$aaia),ARRAY_A);
				$selectednodes=$row['selectednodes'];
				$priceadjustment=$row['priceadjustment'];
				$pricetoimport=$row['pricetoimport'];
				$customprefix=$row['prefix'];
				
				$undeterminednodes="";
				//$this->header();
				$this->chooseCategory($aaia,$selectednodes,$priceadjustment,$customprefix,$pricetoimport);
				exit;
				break;
			case 303 : // log
				$aaia=sanitize_text_field($_GET['aaia']);
				$selectednodes=$wpdb->get_var($wpdb->prepare(_sql("SELECT selectednodes FROM wp_sema_brands WHERE brandid=%s;"),$aaia));
				$undeterminednodes="";
				//$this->header();
				include( 'views/html-sema-import-log.php' );
				exit;
				break;
			case 305 : // select category
				$aaia=sanitize_text_field($_GET['aaia']);
				$selectednodes=$wpdb->get_var(_sql("SELECT GROUP_CONCAT(selectednodes SEPARATOR ',') FROM wp_sema_brands WHERE active=1"));

				//$this->header();
				$this->syncCategory($aaia,$selectednodes,$priceadjustment,$customprefix);
				exit;
				break;
			case 400 : // prepare products for fitment update
				$_GET=array_merge(['partno'=>''],$_GET);
				$returns=$wpdb->get_results(_sql("SELECT GROUP_CONCAT(id) AS ids FROM (  
					SELECT  @row := @row +1 AS rownum, id
					FROM  (  SELECT @row :=0 ) r, (SELECT p.ID FROM wp_posts p inner JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' WHERE p.post_type='product' AND p.post_status = 'publish') p
				   ) x  GROUP BY FLOOR((rownum - 1) / 10);"),ARRAY_A);
				$count=count($returns)*10;
				$returns=array_column($returns, 'ids');
				$datastr="'".implode("','",$returns)."'";
				//
				$options = get_option( 'sema_settings' );
				$options['fitment_updated']=date("Y-m-d H:i:s");;
				update_option( 'sema_settings', $options );

				$this->header();
				?>
				<table id="import-progress" class="widefat_importer widefat">
					<thead>
						<tr>
							<th class="status">&nbsp;</th>
							<th class="row">Row</th>
							<th>Brand</th>
							<th>Product</th>
							<th class="reason">Status Msg</th>
						</tr>
					</thead>
					<tfoot>
						<tr class="importer-loading">
							<td colspan="5"></td>
						</tr>
					</tfoot>
					<tbody><tr id="row-0" class="imported"><td><mark class="result" title="importd">Preparing..</mark></td><td class="row" colspan="4"><?php echo(esc_attr("Fitments of $count products to be rebuilt."));?></td></tr></tbody>
				</table>
				<script type="text/javascript">
					jQuery(document).ready(function($) {
						var rows = [<?php echo($datastr); ?>];
						var i = 1;
						var data = rows.shift();
						import_rows( data);
						function import_rows( sParts ) {
							return $.ajax({
								url:        '<?php echo add_query_arg( array( 'import' => $this->import_page, 'step' => '405'), admin_url( 'admin.php' ) ); ?>',
								data:       {parts:sParts},
								type:       'POST',
								success:    function( response ) {
									if ( response ) {

										try {
											// Get the valid JSON only from the returned string
											if ( response.indexOf("<!--WC_START-->") >= 0 )
												response = response.split("<!--WC_START-->")[1]; // Strip off before after WC_START

											if ( response.indexOf("<!--WC_END-->") >= 0 )
												response = response.split("<!--WC_END-->")[0]; // Strip off anything after WC_END

											var results;
											if(response.indexOf('class="wp-die-message"')>=0){
												var arr = /<div class="wp-die-message">(.*)<\/div>/.exec(response);
												results={'error':response};
												
											}
											// Parse
											results = $.parseJSON( response );

											if ( results.error ) {

												$('#import-progress tbody').append( '<tr id="row-' + i + '" class="error"><td class="status" colspan="5">' + results.error + '</td></tr>' );

												i++;

											} else if ( results.import_results && $( results.import_results ).size() > 0 ) {

												$( results.import_results ).each(function( index, row ) {
													if(row['status']=='comment'){
														$('#import-progress tbody').append( '<tr id="row-" class=""><td> </td><td class="row"> </td><td>' + row['sku'] + '</td><td>' + row['post_id'] + ' - ' + row['post_title'] + '</td><td class="reason">' + row['reason'] + '</td></tr>' );
													}else{
														$('#import-progress tbody').append( '<tr id="row-' + i + '" class="' + row['status'] + '"><td><mark class="result" title="' + row['status'] + '">' + row['status'] + '</mark></td><td class="row">' + i + '</td><td>' + row['sku'] + '</td><td>' + row['post_id'] + ' - ' + row['post_title'] + '</td><td class="reason">' + row['reason'] + '</td></tr>' );
														i++;
													}
												});
											}

										} catch(err) {}

									} else {
										$('#import-progress tbody').append( '<tr class="error"><td class="status" colspan="5">' + '<?php _e( 'AJAX Error', 'product-import-for-woo' ); ?>' + '</td></tr>' );
									}

									var w = $(window);
									var row = $( "#row-" + ( i - 1 ) );

									if ( row.length ) {
										w.scrollTop( row.offset().top - (w.height()/2) );
									}

									if(rows.length>0){
										var data = rows.shift();
										import_rows( data);
									}else{
										$('#import-progress tbody').append( '<tr class="complete"><td colspan="5"><a href="options-general.php?page=sema_import">Update completed.</a></td></tr>' );
										$('.importer-loading').hide();
									}
								},
								error: function(response){
									$('#import-progress tbody').append( '<tr class="error"><td class="status" colspan="5">AJAX timeout</td></tr>' );
									i++;
									var w = $(window);
									var row = $( "#row-" + ( i - 1 ) );
									if ( row.length ) {
										w.scrollTop( row.offset().top - (w.height()/2) );
									}
									if(rows.length>0){
										var data = rows.shift();
										import_rows( data);
									}else{
										$('#import-progress tbody').append( '<tr class="complete"><td colspan="5"><a href="options-general.php?page=sema_import">Update completed.</a></td></tr>' );
										$('.importer-loading').hide();
									}

								},
							});
						}
					});
				</script>
				<?php


				break;
			case 405 : // batch update product fitments, default batch size is 10.
				$options = get_option( 'sema_settings' );
				$siteid=$options['siteid'];

				$ids=sanitize_text_field($_REQUEST['parts']);

				if ( ! current_user_can( 'manage_woocommerce' ) )
					die();

				add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

				if ( function_exists( 'gc_enable' ) )
					gc_enable();
				@set_time_limit(0);
				@ob_flush();
				@flush();
				$wpdb->hide_errors();
				$start=microtime(true);
				
				$uploadpath=wp_upload_dir();
				$uploadpath = $uploadpath['baseurl']."/";
				
				$sql="SELECT p.id,p.post_title as name,psku.meta_value AS sku,brand.meta_value AS brandid, CONCAT('$uploadpath',img.meta_value) AS image, 1 as status
				FROM wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku'
				LEFT JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' 
				left JOIN wp_postmeta thumb ON p.ID=thumb.post_id AND thumb.meta_key='_thumbnail_id' 
				left JOIN wp_postmeta img ON  thumb.meta_value=img.post_id AND img.meta_key='_wp_attached_file'
				WHERE p.post_type='product' AND p.post_status = 'publish' AND p.id in ($ids) ";
				$prods = $wpdb->get_results(_sql($sql),ARRAY_A );
				//'$productid','$siteid','$brandid','$sku','$name','$status','$image'
				if($siteid && $prods){

					$url="$api_url/ajax.php?siteid=$siteid&uuid=$uuid&type=wp_update_products&fields=fitment&products=".urlencode(json_encode($prods));
					$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
					if(count($prods)){
						$exetime=round((microtime(true)-$start)/count($prods),1);
						foreach($prods as $p){
							$this->add_import_result( 'imported', "Import successful.($exetime\")", $p['id'], $p['sku'],$p['brandid'] );
	
						}
					}
				}	

				$results                    = array();
				$results['import_results']  = $this->import_results;


				echo "<!--WC_START-->";
				echo json_encode( $results );
				echo "<!--WC_END-->";
				//exit;
				break;

			case 500 :
				// delete old categories (term_group between 100 to 1000)
				$wpdb->query(_sql("DELETE FROM wp_sema_attr_taxonomy WHERE term_id>=100 and term_id<1000;"));
				$catids_deleted=$wpdb->get_var(_sql("SELECT GROUP_CONCAT(DISTINCT term_id SEPARATOR ',') FROM wp_terms WHERE term_group>=100 and term_group<1000;"));
				if($catids_deleted){
					$wpdb->query(_sql("DELETE FROM wp_terms WHERE term_id in ($catids_deleted) "));
					$wpdb->query(_sql("DELETE FROM wp_termmeta WHERE term_id in ($catids_deleted)"));
					$wpdb->query(_sql("DELETE FROM wp_term_taxonomy WHERE term_id in ($catids_deleted)"));
				}
				// delete unlinkd subcategories and termanology
				$catids_deleted=$wpdb->get_var(_sql("SELECT GROUP_CONCAT(DISTINCT t.term_id SEPARATOR ',') from wp_terms t inner join wp_term_taxonomy tt on t.term_id=tt.term_id where t.term_group>100 and tt.parent=0 "));
				if($catids_deleted){
					$wpdb->query(_sql("DELETE FROM wp_terms WHERE term_id in ($catids_deleted) "));
					$wpdb->query(_sql("DELETE FROM wp_termmeta WHERE term_id in ($catids_deleted)"));
					$wpdb->query(_sql("DELETE FROM wp_term_taxonomy WHERE term_id in ($catids_deleted)"));
				}
				// delete categories whose parent categories are old categories (term_group between 100 to 1000)
				$catids_deleted=$wpdb->get_var(_sql("SELECT GROUP_CONCAT(DISTINCT t.term_id SEPARATOR ',') from wp_terms t inner join wp_term_taxonomy tt on t.term_id=tt.term_id left join  wp_terms p on tt.parent=p.term_id 
				where t.term_group>0 and tt.parent>0 and p.term_id is null "));
				if($catids_deleted){
					$wpdb->query(_sql("DELETE FROM wp_terms WHERE term_id in ($catids_deleted) "));
					$wpdb->query(_sql("DELETE FROM wp_termmeta WHERE term_id in ($catids_deleted)"));
					$wpdb->query(_sql("DELETE FROM wp_term_taxonomy WHERE term_id in ($catids_deleted)"));
				}
		
				// delete all imported categories
				$catids_deleted=$wpdb->get_var(_sql("SELECT GROUP_CONCAT(DISTINCT t.term_id SEPARATOR ',') from wp_terms t where t.term_group>0 "));
				if($catids_deleted){
					$wpdb->query(_sql("DELETE FROM wp_terms WHERE term_id in ($catids_deleted) "));
					$wpdb->query(_sql("DELETE FROM wp_termmeta WHERE term_id in ($catids_deleted)"));
					$wpdb->query(_sql("DELETE FROM wp_term_taxonomy WHERE term_id in ($catids_deleted)"));
				}
				// delete all categories but the default categories
				// ** USE THIS PART CAREFULLY
				/*
				$catids_deleted=$wpdb->get_var(_sql("SELECT GROUP_CONCAT(DISTINCT t.term_id SEPARATOR ',') from wp_terms t where t.term_id<>0 "));
				if($catids_deleted){
					$wpdb->query(_sql("DELETE FROM wp_terms WHERE term_id in ($catids_deleted) "));
					$wpdb->query(_sql("DELETE FROM wp_termmeta WHERE term_id in ($catids_deleted)"));
					$wpdb->query(_sql("DELETE FROM wp_term_taxonomy WHERE term_id in ($catids_deleted)"));
				}*/				
				$returns=$wpdb->get_results(_sql("SELECT brandid,selectednodes FROM wp_sema_brands "),ARRAY_A);
				$datastr='';
				foreach($returns as $r){
					//$nodes=array_filter(explode(',',$r['selectednodes']));
					//foreach($nodes as $n){
					//	$datastr.='["'.$r['brandid'].'","'.$n.'"],';
					//}
					$datastr.='["'.$r['brandid'].'","'.$r['selectednodes'].'"],';
				}
				$this->header();
				?>
				<table id="import-progress" class="widefat_importer widefat">
					<thead>
						<tr>
							<th class="status">&nbsp;</th>
							<th class="row">Row</th>
							<th>Brand</th>
							<th>Category</th>
							<th class="reason">Status Msg</th>
						</tr>
					</thead>
					<tfoot>
						<tr class="importer-loading">
							<td colspan="5"></td>
						</tr>
					</tfoot>
					<tbody><tr id="row-0" class="imported"><td><mark class="result" title="importd">Preparing..</mark></td><td class="row" colspan="4">Synchronizing  categories  for selected brands</td></tr></tbody>
				</table>
				<script type="text/javascript">
					jQuery(document).ready(function($) {

						if ( ! window.console ) { window.console = function(){}; }

						var i               = <?php echo(esc_attr($currentpid+1)); ?>;
						var done_count      = 0;
						var rows = [<?php echo($datastr); ?>];
						var total = 0;
						if(rows.length>0){
							var data = rows.shift();
							import_rows( data);
						}
						function import_rows( data ) {
							var brand=data[0],category=data[1];
							return $.ajax({
								url:        '<?php echo add_query_arg( array( 'import' => $this->import_page, 'step' => '505' ) ); ?>&currentpid='+i,
								data:       {'brand':brand,'category':category},
								type:       'POST',
								success:    function( response ) {
									if ( response ) {

										try {
											// Get the valid JSON only from the returned string
											if ( response.indexOf("<!--WC_START-->") >= 0 )
												response = response.split("<!--WC_START-->")[1]; // Strip off before after WC_START

											if ( response.indexOf("<!--WC_END-->") >= 0 )
												response = response.split("<!--WC_END-->")[0]; // Strip off anything after WC_END

											// Parse
											var results = $.parseJSON( response );

											if ( results.error ) {

												$('#import-progress tbody').append( '<tr id="row-' + i + '" class="error"><td class="status" colspan="5">' + results.error + '</td></tr>' );

												i++;

											} else if ( results.import_results && $( results.import_results ).size() > 0 ) {

												$( results.import_results ).each(function( index, row ) {
													if(row['status']=='comment'){
														$('#import-progress tbody').append( '<tr id="row-" class=""><td> </td><td class="row"> </td><td>' + row['sku'] + '</td><td>' + row['post_id'] + ' - ' + row['post_title'] + '</td><td class="reason">' + row['reason'] + '</td></tr>' );
													}else{
														$('#import-progress tbody').append( '<tr id="row-' + i + '" class="' + row['status'] + '"><td><mark class="result" title="' + row['status'] + '">' + row['status'] + '</mark></td><td class="row">' + i + '</td><td>' + row['sku'] + '</td><td>' + row['post_id'] + ' - ' + row['post_title'] + '</td><td class="reason">' + row['reason'] + '</td></tr>' );
														i++;
													}
												});
											}

										} catch(err) {}

									} else {
										$('#import-progress tbody').append( '<tr class="error"><td class="status" colspan="5">' + '<?php _e( 'AJAX Error', 'product-import-for-woo' ); ?>' + '</td></tr>' );
									}

									var w = $(window);
									var row = $( "#row-" + ( i - 1 ) );

									if ( row.length ) {
										w.scrollTop( row.offset().top - (w.height()/2) );
									}

									done_count++;
									if(rows.length>0){
										var data = rows.shift();
										import_rows( data);
									}else{
										$('body').trigger( 'woocommerce_csv_import_request_complete' );
									}
								},
								error: function(XMLHttpRequest, textStatus, errorThrown) { 
									$('#import-progress tbody').append( '<tr id="row-' + i + '" class="skipped"><td><mark class="result" title="">Skipped</mark></td><td class="row">' + i + '</td><td>' + brand + '</td><td>500 Internal Server Error</td><td class="reason">Skipped</td></tr>' );
									i++;
									var w = $(window);
									var row = $( "#row-" + ( i - 1 ) );

									if ( row.length ) {
										w.scrollTop( row.offset().top - (w.height()/2) );
									}

									done_count++;
									if(rows.length>0){
										var data = rows.shift();
										import_rows( data);
									}else{
										$('body').trigger( 'woocommerce_csv_import_request_complete' );
									}								
								},							
							});
						}
						$('body').on( 'woocommerce_csv_import_request_complete', function() {
							var data = {
								action: 'woocommerce_csv_import_request',
							};

							$.ajax({
								url: '<?php echo add_query_arg( array( 'import' => $this->import_page, 'step' => '4', 'aaia' => $aaia ), admin_url( 'admin.php' ) ); ?>',
								data:       data,
								type:       'POST',
								success:    function( response ) {
									if ( response.indexOf("<!--WC_START-->") >= 0 )
										response = response.split("<!--WC_START-->")[1]; // Strip off before after WC_START
									if ( response.indexOf("<!--WC_END-->") >= 0 )
										response = response.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
									$('#import-progress tbody').append( '<tr class="complete"><td colspan="5">' + response + '</td></tr>' );
									$('.importer-loading').hide();
								}
							});
						} );

						// Regenerate a specified image via AJAX
					
					});
				</script>
				<?php


			break;
			case 505 : // batch import product, default batch size is 10.
				// Check access - cannot use nonce here as it will expire after multiple requests
				$options = get_option( 'sema_settings' );
				$token=$options['sema_token'];

				//$brand=sanitize_text_field($_POST['brand']);

				$aaia=sanitize_text_field($_REQUEST['brand']);
				$selectednodes=sanitize_text_field($_REQUEST['category']);
	
				if ( ! current_user_can( 'manage_woocommerce' ) )
					die();

				add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

				if ( function_exists( 'gc_enable' ) )
					gc_enable();
				@set_time_limit(0);
				@ob_flush();
				@flush();
				$wpdb->hide_errors();

				$options = get_option( 'sema_settings' );
				$token=$options['sema_token'];
				//$aaia=$options['sema_aaia'];
			
				$selectednodes = explode(',',$selectednodes);
				$selectednodes = array_unique($selectednodes);
				
	
	
				// update categories
				$url="https://apps.semadata.org/sdapi/plugin/lookup/categories";
				$response = wp_remote_post($url, array(
					'body' => array('token'=>$token,'purpose'=>'WP','aaia_brandids'=>$aaia),
					'timeout' => '120','redirection' => 5,'redirection' => 5,'httpversion' => '1.0','sslverify' => false,'blocking' => true,
				));
	
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					$this->add_import_result( 'failed', "API timeout.", $error_message, '', $aaia );
				} else {
					$response=json_decode($response['body'],true);
					$arrCatId=array();
					if(array_key_exists('Categories',$response)){
						// 3-tier categories: CategoryID->SubCategoryID->PartTerminologyID
						// Due to Autocare Assc list the same subcategory under multiple categories
						// SubCategoryID(in category tree) = 9,000,000+CategoryID*1,000+SubCategoryID
						foreach($response['Categories'] as $k=>$v){
							if(array_key_exists('Categories',$v)){
								foreach($v['Categories'] as $kk=>$vv){
									$realSubcategoryId = 9000000+$v['CategoryId']*1000+$vv['CategoryId'];
									if(array_key_exists('Categories',$vv)){
										foreach($vv['Categories'] as $kkk=>$vvv){
											if(in_array($vvv['CategoryId'],$selectednodes)){
												if(!in_array($realSubcategoryId,$selectednodes)) $selectednodes[]=$realSubcategoryId;
												if(!in_array($v['CategoryId'],$selectednodes)) $selectednodes[]=$v['CategoryId'];
											}
										}
							
									}                
								}
					
							}
						}
						foreach($response['Categories'] as $k=>$v){
							if(in_array($v['CategoryId'],$arrCatId) || !in_array($v['CategoryId'],$selectednodes)) continue;
							else $arrCatId[]=$v['CategoryId'];
							$cid=sema_insertCategory($v['CategoryId'],$v['Name'],0);
							if(array_key_exists('Categories',$v)){
								foreach($v['Categories'] as $kk=>$vv){
									$realSubcategoryId = 9000000+$v['CategoryId']*1000+$vv['CategoryId'];
									if(in_array($realSubcategoryId,$arrCatId) || !in_array($realSubcategoryId,$selectednodes)) continue;
									else $arrCatId[]=$realSubcategoryId;
									$ccid=sema_insertCategory($realSubcategoryId,$vv['Name'],$cid);
									if(array_key_exists('Categories',$vv)){
										foreach($vv['Categories'] as $kkk=>$vvv){
											if(in_array($vvv['CategoryId'],$arrCatId) || !in_array($vvv['CategoryId'],$selectednodes)) continue;
											else $arrCatId[]=$vvv['CategoryId'];
											$cccid=sema_insertCategory($vvv['CategoryId'],$vvv['Name'],$ccid);
										}
							
									}                
								}
					
							}
						}
					}
					$this->add_import_result( 'imported', 'Synced successfully.', '', count($selectednodes).' categories updated', $aaia );
				}					
				


				$results                    = array();
				$results['import_results']  = $this->import_results;


				echo "<!--WC_START-->";
				echo json_encode( $results );
				echo "<!--WC_END-->";
				//exit;
			break;
			case 900 : // delete products
				global $wpdb;
				$aaia=sanitize_text_field($_GET['aaia']);
				$productids=$_GET['productids'];
				$productids=trim(str_replace('"','',str_replace("'",'',str_replace('*','',str_replace('%','',$productids)))),',');
				if(!$aaia){
					exit;
				}

				if(strtolower($productids)=='all'){
					$result=$wpdb->get_results(_sql("SELECT wpid as id FROM wp_sema_products WHERE brandid='$aaia' and status=99"), ARRAY_A);
				}elseif($productids){
					$result=$wpdb->get_results(_sql("SELECT wpid as id FROM wp_sema_products WHERE brandid='$aaia' and wpid in ($productids) "), ARRAY_A);
				}else{
					$wpdb->query(_sql("DELETE FROM wp_sema_products WHERE brandid='$aaia' and wpid is null "));
					$result=$wpdb->get_results(_sql("SELECT wpid as id FROM wp_sema_products WHERE brandid='$aaia' and wpid is not null "), ARRAY_A);
				} 

				//$result=$wpdb->get_results($wpdb->prepare(_sql("SELECT p.id FROM wp_posts p INNER JOIN wp_postmeta pm ON p.ID=pm.post_id AND pm.meta_key='_brandid' WHERE pm.meta_value=%s"),$aaia), ARRAY_A);
				$result=array_column($result,'id');

				$batchs = array();$count=0;$batchsize=20;$sParts="";
				$total=count($result);
				foreach($result as $p){
					$count++;
					$sParts.="|,".$p;
					if($count==$total || $count % $batchsize == 0){ //$batchsize=20;
						$batchs[]=$sParts;
						$sParts='';
					}
				}
				$batchs=json_encode($batchs);
				$this->header();
				$tbody='<tr id="row-0" class="imported"><td><mark class="result" title="importd">Ready</mark></td><td class="row" colspan="4">'.$count.' products to be deleted.</td></tr>';
				?>
				<table id="import-progress" class="widefat_importer widefat">
					<thead>
						<tr>
							<th class="status">&nbsp;</th>
							<th class="row"><?php _e( 'Row', 'product-import-for-woo' ); ?></th>
							<th><?php _e( 'SKU', 'product-import-for-woo' ); ?></th>
							<th><?php _e( 'Product', 'product-import-for-woo' ); ?></th>
							<th class="reason"><?php _e( 'Status Msg', 'product-import-for-woo' ); ?></th>
						</tr>
					</thead>
					<tfoot>
						<tr class="importer-loading">
							<td colspan="5"></td>
						</tr>
					</tfoot>
					<tbody><?php echo($tbody); ?></tbody>
				</table>
				<script type="text/javascript">
					jQuery(document).ready(function($) {

						if ( ! window.console ) { window.console = function(){}; }

						var i               = <?php echo(esc_attr($currentpid+1)); ?>;
						var done_count      = 0;
						var rows = <?php echo($batchs); ?>;
						var total = 0;

						function import_rows( sParts ) {
							return $.ajax({
								url:        '<?php echo add_query_arg( array( 'import' => $this->import_page, 'step' => '901', 'aaia' =>$aaia ), admin_url( 'admin.php' ) ); ?>',
								data:       {parts:sParts},
								type:       'POST',
								success:    function( response ) {
									if ( response ) {

										try {
											// Get the valid JSON only from the returned string
											if ( response.indexOf("<!--WC_START-->") >= 0 ) response = response.split("<!--WC_START-->")[1]; // Strip off before after WC_START
											if ( response.indexOf("<!--WC_END-->") >= 0 ) response = response.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
											var results = $.parseJSON( response );
											if ( results.error ) {

												$('#import-progress tbody').append( '<tr id="row-' + i + '" class="error"><td class="status" colspan="5">' + results.error + '</td></tr>' );

												i++;

											} else if ( results.import_results && $( results.import_results ).size() > 0 ) {

												$( results.import_results ).each(function( index, row ) {
													$('#import-progress tbody').append( '<tr id="row-' + i + '" class="' + row['status'] + '"><td><mark class="result" title="' + row['status'] + '">' + row['status'] + '</mark></td><td class="row">' + i + '</td><td>' + row['sku'] + '</td><td>' + row['post_id'] + ' - ' + row['post_title'] + '</td><td class="reason">' + row['reason'] + '</td></tr>' );
													i++;
												});
											}

										} catch(err) {}

									} else {
										$('#import-progress tbody').append( '<tr class="error"><td class="status" colspan="5">' + '<?php _e( 'AJAX Error', 'product-import-for-woo' ); ?>' + '</td></tr>' );
									}

									var w = $(window);
									var row = $( "#row-" + ( i - 1 ) );

									if ( row.length ) {
										w.scrollTop( row.offset().top - (w.height()/2) );
									}

									done_count++;
									if(rows.length>0){
										var data = rows.shift();
										import_rows( data);
									}else{
										$('body').trigger( 'woocommerce_csv_import_request_complete' );
									}
								},
							});
						}
						$('body').on( 'woocommerce_csv_import_request_complete', function() {
							$.ajax({
								url: '<?php echo add_query_arg( array( 'import' => $this->import_page, 'step' => '904', 'aaia' =>$aaia ), admin_url( 'admin.php' ) ); ?>',
								type:       'GET',
								success:    function( response ) {
									$('#import-progress tbody').append( '<tr class="complete"><td colspan="5"><a href="options-general.php?page=sema_import">Done</a></td></tr>' );
									$('.importer-loading').hide();
								}
							});
						} );

						var data = rows.shift();
						import_rows( data);


						// Regenerate a specified image via AJAX
					
					});
				</script>
				<?php


			break;				
			case 901 : // batch delete product, default batch size is 10.
				$options = get_option( 'sema_settings' );
				$token=$options['sema_token'];
				$siteid=$options['siteid'];
				$parts=sanitize_text_field($_POST['parts']);

				$aaia=sanitize_text_field($_GET['aaia']);
				if ( ! current_user_can( 'manage_woocommerce' ) )
					die();

				@set_time_limit(0);
				@ob_flush();
				@flush();
				$wpdb->hide_errors();

				$parts=array_filter(explode('|,',$parts));
				$pids=implode(',',$parts);
				foreach($parts as $id){
					//$p=wc_get_product($id);
					$this->wh_deleteProduct($id,true);

				}
				$pids && $wpdb->query(_sql("DELETE FROM wp_sema_products WHERE brandid='$aaia' and wpid in ($pids);"));
				if($siteid && $pids){
					$url="$api_url/ajax.php?siteid=$siteid&uuid=$uuid&type=wp_delete_products&ids=$pids";
					$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
				}
				//$count_deleted = count($parts);
				$importedproducts=$wpdb->get_var(_sql("select sum(case when status>0 then 1 else 0 end) from wp_sema_products WHERE brandid='$aaia' ;"));
				$unimportedproducts=$wpdb->get_var(_sql("select sum(case when status<=0 then 1 else 0 end) from wp_sema_products WHERE brandid='$aaia' ;"));
				$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands set importedproducts=%d,unimportedproducts=%d,check_date=null WHERE brandid='$aaia';"),$importedproducts,$unimportedproducts));
		

				$results                    = array();
				$results['import_results']  = $this->import_results;


				echo "<!--WC_START-->";
				echo json_encode( $results );
				echo "<!--WC_END-->";
				exit;
			break;	
			case 904 : // batch import product, default batch size is 10.
				// Check access - cannot use nonce here as it will expire after multiple requests
				$aaia=$_GET['aaia'];
				$wpdb->query(_sql("UPDATE (select brandid,sum(case when status>0 then 1 else 0 end) as importedproducts,sum(case when status<0 then 1 else 0 end) as unimportedproducts  from wp_sema_products where brandid='$aaia' group by brandid) x RIGHT JOIN wp_sema_brands b on x.brandid=b.brandid set b.unimportedproducts=x.unimportedproducts,b.importedproducts=x.importedproducts,currentpid=0,finishedproducts=0 where b.brandid='$aaia'"));	
		
				exit;
			break;						
		}

		$this->footer();
	}

	
		
	/**
	 * Method to delete Woo Product
	 * 
	 * @param int $id the product ID.
	 * @param bool $force true to permanently delete product, false to move to trash.
	 * @return \WP_Error|boolean
	 */
	function wh_deleteProduct($id, $force = FALSE)
	{
		global $wpdb;
		$product = wc_get_product($id);

		if(empty($product))
			return new WP_Error(999, sprintf(__('No %s is associated with #%d', 'woocommerce'), 'product', $id));

		$processing_product_sku=$product->get_sku();
		$processing_product_title=$product->get_name();
		$featured_image_id = $product->get_image_id();
		$image_galleries_id = $product->get_gallery_image_ids();
	


		// If we're forcing, then delete permanently.
		if ($force)
		{
			$wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_sema_attr_taxonomy WHERE product_id=%d; "),$id));
			//$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_parts SET productid=null WHERE productid=%s;"),$id));
			if( !empty( $featured_image_id ) ) {
				wp_delete_post( $featured_image_id );
			}
		
			if( !empty( $image_galleries_id ) ) {
				foreach( $image_galleries_id as $single_image_id ) {
					wp_delete_post( $single_image_id );
				}
			}			
			if ($product->is_type('variable'))
			{
				foreach ($product->get_children() as $child_id)
				{
					$child = wc_get_product($child_id);
					$child->delete(true);
				}
			}
			elseif ($product->is_type('grouped'))
			{
				foreach ($product->get_children() as $child_id)
				{
					$child = wc_get_product($child_id);
					$child->set_parent_id(0);
					$child->save();
				}
			}

			$product->delete(true);
			$result = $product->get_id() > 0 ? false : true;
		}
		else
		{
			$product->delete();
			$result = 'trash' === $product->get_status();
		}

		if ($result){
			$this->add_import_result( 'imported', 'Delete successful.', $id, $processing_product_title, $processing_product_sku );
			return true;
		}else{
			$this->add_import_result( 'failed', 'Fail to delete.', $id, $processing_product_title, $processing_product_sku );
			return false;
		}

		// Delete parent product transients.
		/*
		if ($parent_id = wp_get_post_parent_id($id))
		{
			wc_delete_product_transients($parent_id);
		}*/
	}	


	/**
	 * The main controller for the actual import stage.
	 */
	public function importProduct($parts,$aaia) {
		global $woocommerce, $wpdb,$api_url;
		if(empty($parts)) return;
		wp_suspend_cache_invalidation( true );

		if(WC()->version < '2.7.0'){
			$memory    = size_format( woocommerce_let_to_num( ini_get( 'memory_limit' ) ) );
			$wp_memory = size_format( woocommerce_let_to_num( WP_MEMORY_LIMIT ) );
		}else{
			$memory    = size_format( wc_let_to_num( ini_get( 'memory_limit' ) ) );
			$wp_memory = size_format( wc_let_to_num( WP_MEMORY_LIMIT ) );
				
		}

		$row=$wpdb->get_row($wpdb->prepare(_sql("SELECT * FROM wp_sema_brands WHERE brandid=%s"),$aaia),ARRAY_A);
		$termids=$row['termids'];
		$currentpid = $row['currentpid'];
		$priceadjustment = $row['priceadjustment']/100;
		$pricetoimport = $row['pricetoimport'];
		if(empty($priceadjustment)) $priceadjustment=0;

		$this->hf_log_data_change( 'API-import', '--get product from API--'.$sParts );
		$parts=trim($parts);
		$sParts=str_replace('|,','&partNumbers=',$parts);
		$merge=sanitize_text_field($_GET['merge']);
		
		wp_suspend_cache_invalidation( true );
		$this->hf_log_data_change( 'API-import', '---[ New Import ] PHP Memory: ' . $memory . ', WP Memory: ' . $wp_memory );
	
		$options = get_option( 'sema_settings' );
		$weight_unit = get_option('woocommerce_weight_unit');
		$sema_currency=$options['sema_product_currency'];

		$token=$options['sema_token'];
		$siteid=$options['siteid'];
		$uuid=$options['uuid'];
		$hide_productwoimage=$options['sema_hide_productwoimage'];
		if($hide_productwoimage==null) $hide_productwoimage=1;

		$url="https://apps.semadata.org/sdapi/plugin/lookup/products";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,"token=$token".$sParts."&aaia_brandid=$aaia&piesSegments=all&lookupAttributeLabels=true&lookupEXPILabels=true&purpose=WP");
		$response = curl_exec($ch);
		if (!curl_errno($ch)) {
			$info = curl_getinfo($ch);
			$this->hf_log_data_change( 'API-import',' '.$info['total_time'].' seconds to send a request to '.$info['url'].'. '.$info['size_download'].' bytes downloads' );
		}
		curl_close($ch);
		$response=json_decode($response,true);
		
		$prefix = $wpdb->get_var($wpdb->prepare(_sql("SELECT prefix FROM wp_sema_brands WHERE brandid=%s;"),$aaia));
		if(empty($prefix)) $prefix=$aaia;

		$arrReturn = $wpdb->get_results(_sql("SELECT term_id,term_group AS meta_value FROM wp_terms WHERE term_group>0;"),ARRAY_A );
		$arrCatId=array();
		foreach($arrReturn as $value){
			$arrCatId[$value['meta_value']]	= $value['term_id'];
		}		
		$products=array();
		foreach ( $response['Products'] as $key => &$item ) {
			$brand=$termid=$termlabel=$hazardous=$gtin=$shortdesc=$longdesc=$retailprice=$mapprice=$jobberprice=$listprice=$img1=$img1b=$img2=$img3=$img4=$img5=$img6=$img7=$img_logo=$video1=$video2=$video3=$video4=$video_youtube=$msdpdf=$inspdf=$universal="";
			$pfeatures=$height=$width=$length=$weight=$title=$title2=$title3=$marketing=$warranty=$year=$UOM_weight=$UOM_dimension='';
			$packageUOM=$qtyofeach='';
			$imgzz=$arrretailprice=$arrmapprice=$arrjobberprice=array();
			$part=$item['PartNumber'];
			$productid=$item['ProductId'];
			//$installation=$refurbished=$remanufactured='';
			$arrExtended=array();$arrAttributes=array();$_Attribute=array();
			$FABs=array();
			foreach($item['PiesAttributes'] as $seg){
				if($seg['PiesSegment'] && $seg['Value']){
					$segment=substr($seg['PiesSegment'],0,3);
					$value=trim($seg['Value']);
					
					if($segment=='B20') $brand=$value;
					elseif($segment=='B64') $termid=$value;
					elseif($segment=='B15') $termlabel=$value;
					elseif($segment=='B10') $gtin=$value;
					elseif($segment=='B32') $qtyofeach=$value;
					elseif($segment=='B33') $packageUOM=$value;
					elseif($segment=='B30') $universal=($value=='N')?"Y":"";
					elseif($segment=='F10') {
						//if(!preg_match('/(?i)^(F10_CATEGORY|F10_BRAND|F10_Long Description|F10_Short Description|F10_Extended Description|F10_Prop 65).*/', $seg['PiesSegment'])){ 
						if(!preg_match('/(?i)^(F10_CATEGORY|F10_BRAND|F10_Long Description|F10_Short Description|F10_Extended Description).*/', $seg['PiesSegment'])){ 
							if(substr($seg['PiesSegment'],0,9)=='F10_Title') $title=$value;
							else{
								if(!array_key_exists($seg['PiesSegment'],$arrAttributes)) $arrAttributes[$seg['PiesSegment']]=$value;
							}
						}
					//}elseif($segment=='E10') {
					}elseif(substr($seg['PiesSegment'],0,7)=='E10_EMS') { //EXPI
						if(!array_key_exists($seg['PiesSegment'],$arrExtended)) $arrExtended['Emissions ']=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='E10_LIS') { //EXPI
						if(!array_key_exists($seg['PiesSegment'],$arrExtended)) $arrExtended['Life Cycle Status']=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='C10_DES') {
						$title2=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='C10_DEF') {
						$title3=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='C10_FAB') {
						if(!array_key_exists($seg['PiesSegment'],$FABs)) $FABs[$seg['PiesSegment']]=$value;
					}elseif(substr($seg['PiesSegment'],0,4)=='F05_') {
						$attkey=str_replace('F05_','',$seg['PiesSegment']);
						$attkey=preg_replace("/_EN(_\d)*/","",$attkey);
						if(ctype_digit($attkey)){// check if it's integer string
							if(!array_key_exists($attkey,$_Attribute)) $_Attribute[$attkey]=$value;
						}
					}elseif(substr($seg['PiesSegment'],0,6)=='H25_EA') $height=$value;
					elseif(substr($seg['PiesSegment'],0,6)=='H30_EA') $width=$value;
					elseif(substr($seg['PiesSegment'],0,6)=='H35_EA') $length=$value;
					elseif(substr($seg['PiesSegment'],0,6)=='H40_EA') $UOM_dimension=$value;
					elseif(substr($seg['PiesSegment'],0,6)=='H45_EA') $weight=$value;
					elseif(substr($seg['PiesSegment'],0,6)=='H46_EA') $UOM_weight=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='C10_SHO') $shortdesc=htmlentities(trim($value));
					elseif(substr($seg['PiesSegment'],0,7)=='C10_EXT') $longdesc=htmlentities(trim($value));
					elseif(substr($seg['PiesSegment'],0,7)=='C10_MKT') $marketing=htmlentities(trim($value));
					elseif(substr($seg['PiesSegment'],0,7)=='D40_RET'){
						$arrretailprice[str_replace('D40_RET_','',$seg['PiesSegment'])]['number']=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='D40_RMP'){
						$arrmapprice[str_replace('D40_RMP_','',$seg['PiesSegment'])]['number']=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='D40_JBR'){
						$arrjobberprice[str_replace('D40_JBR_','',$seg['PiesSegment'])]['number']=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='D15_RET'){
						$arrretailprice[str_replace('D15_RET_','',$seg['PiesSegment'])]['currency']=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='D15_RMP'){
						$arrmapprice[str_replace('D15_RMP_','',$seg['PiesSegment'])]['currency']=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='D15_JBR'){
						$arrjobberprice[str_replace('D15_JBR_','',$seg['PiesSegment'])]['currency']=$value;
					}elseif(substr($seg['PiesSegment'],0,7)=='P05_P04') $img1=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_P01') $img2=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_P03') $img3=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_P02') $img4=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_P05') $img5=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_P06') $img6=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_P07') $img7=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_LGO') $img_logo=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P80_ZZ1') $video_youtube=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_ZZ2') $video1=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_ZZ3') $video2=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_ZZ4') $video3=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_ZZ5') $video4=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_ZZ6') $video5=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_ZZ7') $video6=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_ZZ8') $video7=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_ZZ9') $video8=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_MSD') $msdpdf=$value;
					elseif(substr($seg['PiesSegment'],0,7)=='P05_INS') $inspdf=$value;
					//elseif(substr($seg['PiesSegment'],0,4)=='B60_') $inspdf=$value;

				}

			}
			//check F10_Title, C10_DES, C10_DEF
			if(empty($title)) $title=$title2;
			if(empty($title)) $title=$title3;
			if(empty($img1) && empty($img2) && empty($img3) && empty($img4)) $img1=$img_logo;
			$this->typeCheck($img5,$imgzz);
			$this->typeCheck($img6,$imgzz);
			$this->typeCheck($img7,$imgzz);
			$this->typeCheck($video1,$imgzz);
			$this->typeCheck($video2,$imgzz);
			$this->typeCheck($video3,$imgzz);
			$this->typeCheck($video4,$imgzz);
			$this->typeCheck($video5,$imgzz);
			$this->typeCheck($video6,$imgzz);
			$this->typeCheck($video7,$imgzz);
			$this->typeCheck($video8,$imgzz);			

			//price section
			$retailprice=$mapprice=$jobberprice=0;
			foreach($arrretailprice as $retail){
				if($retail['currency']==$sema_currency){
					$retailprice=$retail['number'];
					break;
				}
			}
			foreach($arrmapprice as $map){
				if($map['currency']==$sema_currency){
					$mapprice=$map['number'];
					break;
				}
			}
			foreach($arrjobberprice as $jobber){
				if($jobber['currency']=$sema_currency){
					$jobberprice=$jobber['number'];
					break;
				}
			}
			// RMP -> JBR -> RET
			if($pricetoimport=='AUTO'){
				if($mapprice) $price=$mapprice;
				elseif($jobberprice) $price=$jobberprice;
				else $price=$retailprice;
			}elseif($pricetoimport=='RET'){
				if($retailprice) $price=$retailprice;
				elseif($mapprice) $price=$mapprice;
				else $price=$jobberprice;
			}elseif($pricetoimport=='JBR'){
				if($jobberprice) $price=$jobberprice;
				elseif($mapprice) $price=$mapprice;
				else $price=$retailprice;
			}elseif($pricetoimport=='RMP'){
				if($mapprice) $price=$mapprice;
				elseif($retailprice) $price=$retailprice;
				else $price=$jobberprice;
			}
			if($priceadjustment>=0){
				$price=$price*(1+$priceadjustment);
			}else $price=$price*(1+$priceadjustment);
			if($mapprice && $mapprice>$price) $price=$mapprice;
			if($retailprice<$price) $retailprice=$price;


			$sPackage="\r\n<h3>Packaging:</h3>\r\n";
			$sPackage.="\t<b>Quantity of Each: </b>".$qtyofeach."\r\n";
			$sPackage.="\t<b>Package UOM: </b>".$packageUOM."\r\n";
			if($UOM_dimension=='IN') $sPackage.="\t<b>Dimension: </b>$length x $width x $height inches\r\n";
			elseif($UOM_dimension=='CM') $sPackage.="\t<b>Dimension: </b>$length x $width x $height centimeters\r\n";
			$sPackage.="\t<b>Weight: </b>$weight $UOM_weight\r\n";




			$sAttributes="\r\n<h3>Additional Attributes:</h3>\r\n";
			$sAttributes.="\t<b>GTIN: </b>".$gtin."\r\n";
			$tax_array = array();
			foreach($arrAttributes as $attkey=>$attvalue){
				$attkey=str_replace('F10_','',$attkey);
				$attkey=preg_replace("/_EN(_\d)*/","",$attkey);
				if(ctype_digit($attkey)){// check if it's integer string
					$sAttributes.="\t<b>".$_Attribute[$attkey].": </b>".$attvalue."\r\n";
					$tax_array[$_Attribute[$attkey]]=$attvalue;
				}else{
					$sAttributes.="\t<b>".$attkey.": </b>".$attvalue."\r\n";
					if(substr($attkey,0,7)!='Package' && $attkey!='Warranty' && $attkey!='F10_Prop 65 (C, R or CR)' && $attkey!='F10_Prop 65 - Short Label' && $attkey!='F10_Prop 65 Warning Label'){
						$tax_array[$attkey]=$attvalue;
					}
				}
			}
			$sExtended="\r\n<h3>Extended Information:</h3>\r\n";
			foreach($arrExtended as $attkey=>$attvalue){
				$attkey=str_replace('E10_','',$attkey);
				$attkey=preg_replace("/_EN(_\d)*/","",$attkey);
				//if(ctype_digit($attkey)){// check if it's integer string
					//$sExtended.="\t<b>".$_Extended[$attkey].": </b>".$attvalue."\r\n";
					//$tax_array[$_Extended[$attkey]]=$attvalue;
				//}else{
					/*if(substr($attkey,0,7)!='Package' && $attkey!='Warranty' && $attkey!='F10_Prop 65 (C, R or CR)' && $attkey!='F10_Prop 65 - Short Label' && $attkey!='F10_Prop 65 Warning Label'){
					}*/
				//}
				$sExtended.="\t<b>".$attkey.": </b>".$attvalue."\r\n";
				$tax_array[$attkey]=$attvalue;
			}
			$sFABs="";
			foreach($FABs as $key=>$value){
				$sFABs.="\t<li>".$value."</li>\r\n";
			}
			$sFABs=($sFABs)?"\r\n<ul>".$sFABs."</ul>\r\n":"";
			$sVideo=$sPdf="";
			if($UOM_weight=='PG'){// lb
				switch($weight_unit) {
					case 'oz':
						$weight=$weight*16;
						break;
					case 'kg':
						$weight=round($weight*0.4536,2);
						break;
					case 'g':
						$weight=round($weight*453.6);
						break;
				}
			}else if($UOM_weight=='GT'){// kg
				switch($weight_unit) {
					case 'oz':
						$weight=round($weight*35.3);
						break;
					case 'lb':
						$weight=round($weight*2.2,2);
					break;
					case 'g':
						$weight=$weight*1000;
						break;
				}				
			}
			$mediaAry=array('video'=>array(),'pdf'=>array());
			$this->parseMedia($video1,$mediaAry);
			$this->parseMedia($video2,$mediaAry);
			$this->parseMedia($video3,$mediaAry);
			$this->parseMedia($video4,$mediaAry);
			$this->parseMedia($video_youtube,$mediaAry);
			$this->parseMedia($msdpdf,$mediaAry);
			$this->parseMedia($inspdf,$mediaAry);
			if($mediaAry['video']){
				$sVideo="\r\n<h3>Videos:</h3>\r\n";
				foreach($mediaAry['video'] as $v) $sVideo.=$v;
			}
			if($mediaAry['pdf']){
				$sPdf="\r\n<h3>PDF:</h3>\r\n";
				foreach($mediaAry['pdf'] as $v){
					if($v) $sPdf.=$v;
				}
			}
			$excerpt=$longdesc.$sFABs;
			//$description=$marketing.$sExtended.$sAttributes.$sVideo.$sPdf;
			$description=$marketing.$sAttributes.$sExtended.$sPackage.$sVideo.$sPdf;
			$product_cat=$arrCatId[$termid];
			$product = array('post_type' => 'product',
			'postmeta'=>array(
				//array('key'=>'_sku','value'=>$brand.'-'.$part),
				array('key'=>'_sku','value'=>$prefix.'-'.$part),
				array('key'=>'_price','value'=>$price),
				array('key'=>'_sale_price','value'=>$price),
				array('key'=>'_regular_price','value'=>$retailprice),
				array('key'=>'_weight','value'=>$weight),
				array('key'=>'_length','value'=>$length),
				array('key'=>'_width','value'=>$width),
				array('key'=>'_height','value'=>$height),
				array('key'=>'_tax_status','value'=>'taxable'),
				array('key'=>'_termid','value'=>$termid),
				array('key'=>'_brandid','value'=>$brand),
				array('key'=>'_productid','value'=>$productid),
			),
			'menu_order' => '','post_status' => 'publish',
			'post_title' => $title,
			'post_name' => '',
			'post_date' => '',
			'post_date_gmt' => '',
			'post_content' => $description,
			'post_excerpt' => $excerpt,
			'post_parent' => '',
			'post_password' => '',
			'post_author' => '',
			'comment_status' => 'open',
			'merging' => $merge,
			'post_id' => '0',
			'pid'=>$productid,
			'gpf_data' => '',
			'images' => array($img1),
			'attrs'=> array(),
			//	'pa_gtin'=>$gtin,
			//),
			'product_cat'=>$product_cat,
			//'sku' => $brand.'-'.$part,
			'sku' => $prefix.'-'.$part,
			'taxonomy'=>$tax_array,
			'post_status'=>'publish',
			);
			if($img2) $product['images'][]=$img2;
			if($img3) $product['images'][]=$img3;
			if($img4) $product['images'][]=$img4;
			foreach($imgzz as $img){
				if(count($product['images'])>3) break;
				$product['images'][]=$img;
			}			
			if($universal=='Y') $product['universal']="Y";
			$products[]=$product;
			unset($product );
			unset($item);
		}
		unset($response);
		$prods = array();
		foreach($products as $pkey=>$product){
			if(empty($product['post_title'])){
				$this->add_import_result( 'skipped', __( 'Failed to import', 'product-import-for-woo' ), '', 'No product name', $product['sku'] );
				$result = $wpdb->query($wpdb->prepare(_sql("INSERT wp_sema_products VALUES (%d,%s,%s,-5,null) ON DUPLICATE KEY UPDATE partno=value(partno),status=-5,wpid=null"),$product['postmeta'][11]['value'],$aaia,$product['sku']));			
				continue;
			}
			if(empty($product['images'][0])){
				$this->add_import_result( 'skipped', 'No product image', '', $product['post_title'], $product['sku'] );
				if($hide_productwoimage) $product['post_status']='draft';
				//continue;
			}else{
				$notvalidimage=false;
				$imageresponse = wp_remote_get( $product['images'][0], array(
					'timeout' => 50,
					"user-agent" => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:56.0) Gecko/20100101 Firefox/56.0",
					'sslverify' => FALSE
				) );
				if ( is_wp_error( $imageresponse ) || wp_remote_retrieve_response_code( $imageresponse ) !== 200 ){
					$secondprimaryimageexist=false;
					if($product['images'][1]){
						$imageresponse2 = wp_remote_get( $product['images'][1], array(
							'timeout' => 50,
							"user-agent" => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:56.0) Gecko/20100101 Firefox/56.0",
							'sslverify' => FALSE
						) );
						if ( !is_wp_error( $imageresponse2 ) && wp_remote_retrieve_response_code( $imageresponse2 ) === 200 ){
							$headers2 = wp_remote_retrieve_headers( $imageresponse2 );
							if ( isset( $headers2['content-type'] ) && strstr( $headers2['content-type'], 'image/' ) ) {
								if($headers2['content-type']=='image/jpeg' || $headers2['content-type']=='image/gif' || $headers2['content-type']=='image/png') {
									$products[$pkey]['images'][0]=$product['images'][1];
									unset($products[$pkey]['images'][1]);
									$secondprimaryimageexist=true;
									unset($headers2);unset($imageresponse2);
								}
							}

						}
					}
					if(!$secondprimaryimageexist){
						$this->add_import_result( 'skipped', 'Product image does not exist.', '',$product['post_title'], $product['sku'] );
						$product['images'][0]='';
						if($hide_productwoimage) $product['post_status']='draft';
					}
				}else{
					$headers = wp_remote_retrieve_headers( $imageresponse );
					if ( isset( $headers['content-type'] ) && strstr( $headers['content-type'], 'image/' ) ) {
						if($headers['content-type']=='image/jpeg' || $headers['content-type']=='image/gif' || $headers['content-type']=='image/png') $validtype=true;
					}
					if($validtype==false){
						$this->add_import_result( 'skipped', ucfirst($headers['content-type']).' not supported.', '', $product['post_title'], $product['sku'] );
						$product['images'][0]='';
						if($hide_productwoimage) $product['post_status']='draft';
					}
				}
				
				unset( $headers );unset($imageresponse);
			}
			/*elseif(function_exists( 'exif_imagetype' )){
				try{
					$ex1=exif_imagetype($product['images'][0]);
				} catch (Exception $e) {
					echo $e->getMessage();
				}
				if($ex1===false){
					$this->add_import_result( 'skipped', __( 'Warning', 'product-import-for-woo' ), '',$product['post_title'], 'Product image does not exist.' );
					$product['images'][0]='';
					$product['post_status']='draft';
					//continue;
				}else if($ex1>3){//1=IMAGETYPE_GIF,2=IMAGETYPE_JPEG,3=IMAGETYPE_PNG
					$this->add_import_result( 'skipped', __( 'Warning', 'product-import-for-woo' ), '', $product['post_title'], 'Image format not supported.' );
					$product['images'][0]='';
					$product['post_status']='draft';
					
					//continue;
				}
	
			}*/
			$time_start = microtime(true); 
			$this->parseTerms($product);
	
			$prods[]=$this->process_product( $product );
			$time_end = microtime(true);
			$time_exe = round($time_end - $time_start,3);
			
		}
		$batchsize=10;
		//$importedproducts=$wpdb->get_var(_sql("select count(*) from wp_posts p INNER JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' WHERE p.post_type='product' AND brand.meta_value='$aaia';"));
		//$unimportedproducts=$wpdb->get_var(_sql("select count(*) from wp_sema_products where brandid='$aaia' and wpid is null;"));
		//$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands SET importedproducts=%d,unimportedproducts=%d,unimported_reason=%s,currentpid=case when currentpid+%d>=totalproducts then 0 else currentpid+%d end 
		//WHERE brandid=%s "),$importedproducts,$unimportedproducts,$unimportedreason,$batchsize,$batchsize,$aaia));
		//$importedproducts=$wpdb->get_var(_sql("select sum(case when status>0 then 1 else 0 end) from wp_sema_products WHERE brandid='$aaia';"));
		//$unimportedproducts=$wpdb->get_var(_sql("select sum(case when status<=0 then 1 else 0 end) from wp_sema_products WHERE brandid='$aaia';"));
		//$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands set importedproducts=%d,unimportedproducts=%d WHERE brandid='$aaia';"),$importedproducts,$unimportedproducts));

		$wpdb->query(_sql("UPDATE (select brandid,sum(case when status>0 then 1 else 0 end) as importedproducts,sum(case when status<0 then 1 else 0 end) as unimportedproducts  from wp_sema_products group by brandid) x right join wp_sema_brands b on x.brandid=b.brandid set b.unimportedproducts=x.unimportedproducts,b.importedproducts=x.importedproducts"));
		$wpdb->query(_sql("UPDATE wp_sema_brands SET currentpid=case when currentpid+10>=totalproducts then 0 else currentpid+10 end ,finishedproducts=case when currentpid+10<=finishedproducts then finishedproducts else currentpid+10 end WHERE brandid='$aaia' "));


		if($siteid && $prods){
			$fields=array();
			array_key_exists('fitment',$options['sema_mandatory_update']) && $fields[]='fitment';
			array_key_exists('attribute',$options['sema_mandatory_update']) && $fields[]='attribute';
			$fields=implode(',',$fields);
			$url="$api_url/ajax.php?siteid=$siteid&uuid=$uuid&type=wp_update_products&fields=$fields&products=".urlencode(json_encode($prods));
			$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
		}		
		
		$this->hf_log_data_change( 'API-import', __( 'Finished processing products.', 'product-import-for-woo' ) );
		wp_suspend_cache_invalidation( false );
	}
	function typeCheck($media,&$arr){
		if($media){
			$media_ext=strtolower(trim(substr($media, -4)));
			if($media_ext=='.jpg' || $media_ext=='.gif' || $media_ext=='.png'){
				$arr[]=$media;
			}
		}
	}		
	/*
	** seperate parseTerms from parser class
	*/
	function parseMedia($url,&$mediaAry){
		if(empty($url)) return "";
		$parse = parse_url($url);
		$host=$parse['host'];
		$path=pathinfo($url);
		if(array_key_exists('extension', $path)) $ex=$path['extension'];
		else $ex="";
		$str="";
		if(strrpos($host,'youtube',0)!==false){
			parse_str( parse_url( $url, PHP_URL_QUERY ), $my_array_of_vars );
			$str="<iframe width='560' height='315' src='https://www.youtube.com/embed/".$my_array_of_vars['v']."' frameborder='0' allow='accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe>\r\n";
			$mediaAry['video'][]=$str;
		}else if(strrpos($host,'vimeo',0)!==false){
			parse_str( parse_url( $url, PHP_URL_QUERY ), $my_array_of_vars );
			$str="<div style='padding:56.25% 0 0 0;position:relative;'><iframe src='https://player.vimeo.com/video/".$path['filename']."?title=0&byline=0&portrait=0' style='position:absolute;top:0;left:0;width:100%;height:100%;' frameborder='0' allow='autoplay; fullscreen' allowfullscreen></iframe></div>\r\n<script src='https://player.vimeo.com/api/player.js'></script>\r\n";
			$mediaAry['video'][]=$str;
		}else{
			switch(strtolower($ex)){
				case "mp4":
					$str="<video width='100%' controls>\r\n<source src='$url' type='video/mp4'>\r\n</video>\r\n";
					$mediaAry['video'][]=$str;
				break;
				case "pdf":
					$str="<div><a href='$url' target='_blank'><img src='".plugins_url()."/sema-api/images/pdf_icon.png' style='display:inline'> ".urldecode($path['filename'])."</a></div>";
					$mediaAry['pdf'][]=$str;
				break;
			}
		}
		return $str;
	}
	/*
	** seperate parseTerms from parser class
	*/
	function parseTerms(&$product){
		global $wpdb;
		$attributes=null;
		$terms_array=array();
        $merging = $product['merging'];
		$attributes = array();

		foreach($product['attrs'] as $key=>$value){
			$attribute_key = $key;
			$attribute_name = $value;

			if (!$attribute_key)
				continue;

			// Taxonomy
			if (substr($attribute_key, 0, 3) == 'pa_') {

				//$taxonomy = $attribute_key;
				// adjusted by Steven
				$taxonomy = strtolower(sanitize_title($attribute_key));

				// Exists?
				if (!taxonomy_exists($taxonomy)) {


					$nicename = strtolower(sanitize_title(str_replace('pa_', '', $taxonomy)));

					$attribute_label = ucwords(str_replace('-', ' ', $nicename)); // for importing attribute name as human readable string
					$exists_in_db = $wpdb->get_var(_sql("SELECT attribute_id FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies WHERE attribute_name = '" . $nicename . "';"));

					if (!$exists_in_db) {
						// Create the taxonomy
						$wpdb->insert($wpdb->prefix . "woocommerce_attribute_taxonomies", array('attribute_name' => $nicename, 'attribute_label' => $attribute_label, 'attribute_type' => 'select', 'attribute_orderby' => 'menu_order', 'attribute_public' => '0'));
					} else {
					}

					// Register the taxonomy now so that the import works!
					register_taxonomy($taxonomy, array('product', 'product_variation'), array(
						'hierarchical' => true,
						'show_ui' => false,
						'query_var' => true,
						'rewrite' => false,
							)
					);
					// Clear cache and flush rewrite rules.
					wp_schedule_single_event( time(), 'woocommerce_flush_rewrite_rules' );
					delete_transient( 'wc_attribute_taxonomies' );
					WC_Cache_Helper::incr_cache_prefix( 'woocommerce-attributes' );
				}

				// Get terms
				$terms = array();
				$raw_terms = explode('|', $value);
				if (WC()->version < '2.7.0') {
					$raw_terms = array_map('wp_specialchars', $raw_terms);
					$raw_terms = array_map('trim', $raw_terms);
				} else {

					$raw_terms = array_map('esc_html', $raw_terms);
					$raw_terms = array_map('trim', $raw_terms);
				}

				if (sizeof($raw_terms) > 0) {

					foreach ($raw_terms as $raw_term) {

						if (empty($raw_term) && 0 != $raw_term) {
							continue;
						}

						// Check term existance
						$term_exists = term_exists($raw_term, $taxonomy, 0);
						$term_id = is_array($term_exists) ? $term_exists['term_id'] : 0;

						if (!$term_id) {
							$t = wp_insert_term(trim($raw_term), $taxonomy);

							if (!is_wp_error($t)) {
								$term_id = $t['term_id'];
							} else {
								break;
							}
						} else {
						}

						if ($term_id) {
							$terms[] = $term_id;
						}
					}
				}

				// Add to array
				$terms_array[] = array(
					'taxonomy' => $taxonomy,
					'terms' => $terms
				);
				
				// Ensure we have original attributes
				/*
				if (is_null($attributes) && $merging) {
					$attributes = array_filter((array) maybe_unserialize(get_post_meta($post_id, '_product_attributes', true)));
				} elseif (is_null($attributes)) {
					$attributes = array();
				}*/

				// Set attribute
				if (!isset($attributes[$taxonomy]))
					$attributes[$taxonomy] = array();

				$attributes[$taxonomy]['name'] = $taxonomy;
				$attributes[$taxonomy]['value'] = null;
				$attributes[$taxonomy]['is_taxonomy'] = 1;

				if (!isset($attributes[$taxonomy]['position']))
					$attributes[$taxonomy]['position'] = 0;
				if (!isset($attributes[$taxonomy]['is_visible']))
					$attributes[$taxonomy]['is_visible'] = 1;
				if (!isset($attributes[$taxonomy]['is_variation']))
					$attributes[$taxonomy]['is_variation'] = 0;
				// Remove empty attribues
				if (!empty($attributes))
				foreach ($attributes as $key => $value) {
					if (!isset($value['name']))
						unset($attributes[$key]);
				}					
				//$attr_array[]=$attributes;
			} else {

				if (!$value || !$attribute_key)
					continue;

				// Set attribute
				if (!isset($attributes[$attribute_key]))
					$attributes[$attribute_key] = array();

				$attributes[$attribute_key]['name'] = $attribute_name;
				$attributes[$attribute_key]['value'] = $value;
				$attributes[$attribute_key]['is_taxonomy'] = 0;

				if (!isset($attributes[$attribute_key]['position']))
					$attributes[$attribute_key]['position'] = 0;
				if (!isset($attributes[$attribute_key]['is_visible']))
					$attributes[$attribute_key]['is_visible'] = 1;
				if (!isset($attributes[$attribute_key]['is_variation']))
					$attributes[$attribute_key]['is_variation'] = 0;
			}
		}
		$product['attributes']=$attributes;
		$terms_array[] = array(
			'taxonomy' => 'product_cat',
			'terms' => $product['product_cat']
		);
		$product['terms']=$terms_array;

	
	}
	/**
	 * The main controller for the actual import stage.
	 */
	/*
	public function import() {
		global $woocommerce, $wpdb;
		if (!defined('SEMA_INVENTORY_STATUS')) {
			define('SEMA_INVENTORY_STATUS', get_option('woocommerce_manage_stock'));
		}
		if (!defined('SEMA_INVENTORY_THRESHOLD')) {
			define('SEMA_INVENTORY_THRESHOLD', get_option('woocommerce_notify_no_stock_amount'));
		}

		wp_suspend_cache_invalidation( true );

		$this->hf_log_data_change( 'API-import', '---' );
		$this->hf_log_data_change( 'API-import', __( 'Processing products.', 'product-import-for-woo' ) );
		foreach ( $this->parsed_data as $key => &$item ) {

			$product = $this->parser->parse_product( $item, $this->merge_empty_cells );
			if ( ! is_wp_error( $product ) ){
				$this->process_product( $product );
                        }else{
				$this->add_import_result( 'failed', $product->get_error_message(), 'Not parsed', json_encode( $item ), '-' );                                
                        }
			unset( $item, $product );
		}
		$this->hf_log_data_change( 'API-import', __( 'Finished processing products.', 'product-import-for-woo' ) );
		wp_suspend_cache_invalidation( false );
	}*/

	/**
	 * Parses the CSV file and prepares us for the task of processing parsed data
	 *
	 * @param string $file Path to the CSV file for importing
	 */

	public function hf_log_data_change ($content = 'API-import',$data='')
	{
		if (WC()->version < '2.7.0')
		{
			$this->log->add($content,$data);
		}else
		{
			$context = array( 'source' => $content );
			$this->log->log("debug", $data ,$context);
		}
	}
	public function import_start( $file, $mapping, $start_pos, $end_pos, $eval_field ) {

		if(WC()->version < '2.7.0'){
		$memory    = size_format( woocommerce_let_to_num( ini_get( 'memory_limit' ) ) );
		$wp_memory = size_format( woocommerce_let_to_num( WP_MEMORY_LIMIT ) );
		}else
		{
		$memory    = size_format( wc_let_to_num( ini_get( 'memory_limit' ) ) );
		$wp_memory = size_format( wc_let_to_num( WP_MEMORY_LIMIT ) );
			
		}
		$this->hf_log_data_change( 'API-import', '---[ New Import ] PHP Memory: ' . $memory . ', WP Memory: ' . $wp_memory );
		$this->hf_log_data_change( 'API-import', __( 'Parsing products CSV.', 'product-import-for-woo' ) );

		$this->parser = new SEMA_Parser( 'product' );

		list( $this->parsed_data, $this->raw_headers, $position ) = $this->parser->parse_data( $file, $this->delimiter, $mapping, $start_pos, $end_pos, $eval_field );

		$this->hf_log_data_change( 'API-import', __( 'Finished parsing products CSV.', 'product-import-for-woo' ) );

		unset( $import_data );

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		return $position;
	}

	/**
	 * Performs post-import cleanup of files and the cache
	 */
	public function import_end() {

		//wp_cache_flush(); Stops output in some hosting environments
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		do_action( 'import_end' );
	}


	public function product_exists( $title, $sku = '', $post_name = '' ) {
		global $wpdb;

		// Post Title Check
		$post_title = stripslashes( sanitize_post_field( 'post_title', $title, 0, 'db' ) );

	    $query = "SELECT ID FROM $wpdb->posts WHERE post_type = 'product' AND post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )";
	    $args = array();

	    /*
             * removed title check
            if ( ! empty ( $title ) ) {
	        $query .= ' AND post_title = %s';
	        $args[] = $post_title;
	    }
            

	    if ( ! empty ( $post_name ) ) {
	        $query .= ' AND post_name = %s';
	        $args[] = $post_name;
	    }

		*/

	    if ( ! empty ( $args ) ) {
	        $posts_that_exist = $wpdb->get_col( $wpdb->prepare( $query, $args ) );

	        if ( $posts_that_exist ) {

	        	foreach( $posts_that_exist as $post_exists ) {

		        	// Check unique SKU
		        	$post_exists_sku = get_post_meta( $post_exists, '_sku', true );

					if ( $sku == $post_exists_sku ) {
						return true;
					}

	        	}

		    }
		}

		// Sku Check
		if ( $sku ) {

			 $post_exists_sku = $wpdb->get_var( $wpdb->prepare( "
				SELECT $wpdb->posts.ID
			    FROM $wpdb->posts
			    LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )
			    WHERE $wpdb->posts.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )
			    AND $wpdb->postmeta.meta_key = '_sku' AND $wpdb->postmeta.meta_value = '%s'
			 ", $sku ) );

			 if ( $post_exists_sku ) {
				 return true;
			 }
		}

	    return false;
	}
        
        
	public function wf_get_product_id_by_sku( $sku ) {
		global $wpdb;

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
		//AND posts.post_status != 'trash'
		$id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT posts.ID
			FROM $wpdb->posts AS posts
			LEFT JOIN $wpdb->postmeta AS postmeta ON ( posts.ID = postmeta.post_id )
			WHERE posts.post_type IN ( 'product', 'product_variation' )
			AND postmeta.meta_key = '_sku'
			AND postmeta.meta_value = %s
			LIMIT 1",
			$sku
			)
		);

		return (int) apply_filters( 'wf_get_product_id_by_sku', $id, $sku );
	}

	/*
	** added Steven 9/18/2019
	*/
	public function regenerate_thumbnail($id) {
		global $_wp_additional_image_sizes;
		$image = get_post( $id );
		$fullsizepath = get_attached_file( $image->ID );

		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) )
			$this->die_json_error_msg( $image->ID, sprintf( __( 'The originally uploaded image file cannot be found at %s', 'product-import-for-woo' ), '<code>' . esc_html( $fullsizepath ) . '</code>' ) );

		@set_time_limit( 900 ); // 5 minutes per image should be PLENTY

		$metadata = wp_generate_attachment_metadata( $image->ID, $fullsizepath );

		if ( is_wp_error( $metadata ) ) return -1;
		if ( empty( $metadata ) ) return -1;

		// If this fails, then it just means that nothing was changed (old value == new value)
		wp_update_attachment_metadata( $image->ID, $metadata );
		return 1;
	}
	

	/**
	 * Create new posts based on import information
	 */
	public function process_product( $post ) {
		global $wpdb;
		$warning="";
		// wp_sema_products.status 1=publish, -1=missing image,-2=image type not supported,-5=missing title, -6=missing category, -7=missing price,-20=api error, -40=other error
		$status=1;
		$start=microtime(true);

		$options = get_option( 'sema_settings' );
		$token=$options['sema_token'];
		//$merging = !empty( $options['sema_mandatory_update'] );
		$merging = $options['sema_mandatory_update'];

		$pimid = $post['postmeta'][11]['value'];// product id in PIMs

		//$processing_product_id    = absint( $post['post_id'] );
		$processing_product_id=0;
		/*
		if($processing_product_id) $processing_product       = get_post( $processing_product_id );
		else{
			if ( ! empty( $post['sku'] ) ) {
				$processing_product_id = $this->wf_get_product_id_by_sku($post['sku']);                                            
			}			
		}
		$processing_product_title = $processing_product ? $processing_product->post_title : '';
		$processing_product_sku   = $processing_product ? $processing_product->sku : '';
		*/
		$aaia = $post['postmeta'][10]['value'];
		$sku=explode('-',$post['sku'],2);
		$partno=$sku[1];
		$sku=$aaia.'-'.$sku[1];

		if ( ! empty( $post['sku'] ) ) {
			//$processing_product_id = $this->wf_get_product_id_by_sku($post['sku']);
			//  wf_get_product_id_by_sku didn't do exact matchs                                
			$processing_product_id = $wpdb->get_var($wpdb->prepare(_sql("select post_id from wp_postmeta where meta_key='_sku' and meta_value = %s"),$post['sku']));                 
		}			
		if ( ! empty( $post['post_title'] ) ) {
			$processing_product_title = $post['post_title'];
			$status=-5;
		}

		if ( ! empty( $post['sku'] ) ) {
			$processing_product_sku = $post['sku'];
		}

		// Skip processed product
		if ( ! empty( $processing_product_id ) && isset( $this->processed_posts[ $processing_product_id ] ) ) {
			$this->add_import_result( 'skipped', __( 'Product already processed', 'product-import-for-woo' ), $processing_product_id, $processing_product_title, $processing_product_sku );
			$this->hf_log_data_change( 'API-import', __('> Post ID already processed. Skipping.', 'product-import-for-woo'), true );
			unset( $post );
			return;
		}

		if ( ! empty ( $post['post_status'] ) && $post['post_status'] == 'auto-draft' ) {
			$this->add_import_result( 'skipped', __( 'Skipping auto-draft', 'product-import-for-woo' ), $processing_product_id, $processing_product_title, $processing_product_sku );
			$this->hf_log_data_change( 'API-import', __('> Skipping auto-draft.', 'product-import-for-woo'), true );
			unset( $post );
			return;
		}
		// Check if post exists when importing

		if ( ! $merging ) {
			$is_post_type_product = get_post_type($processing_product_id);
			if (!empty($processing_product_id) && (in_array($is_post_type_product, array('product','product_variation')))) {
				$usr_msg = 'Skipped. Duplicated sku.';
				$this->add_import_result('skipped', __($usr_msg, 'sema_import_export'), $processing_product_id, $processing_product_title, $processing_product_sku);
				$this->hf_log_data_change('API-import', sprintf(__('> &#8220;%s&#8221;' . $usr_msg, 'sema_import_export'), esc_html($processing_product_title)), true);
				unset($post);
				return;
			}

			$existing_product = '';
			if (isset($processing_product_sku) && !empty($processing_product_sku)) {
				$existing_product = $this->wf_get_product_id_by_sku($processing_product_sku);                                            
			}
			if ($existing_product) {
				if ($this->delete_products == 1) {
					$product_to_be_deleted[] =$existing_product;
				}
				if (!$processing_product_id && empty($processing_product_sku)) {
					// if no sku , no id and no merge and has same title in DB -> just give message
					$usr_msg = 'Product with same title already exists.';
				} else {
					$usr_msg = 'Product with same SKU already exists.';
				}
				$this->add_import_result('skipped', __($usr_msg, 'product-import-for-woo'), $existing_product, $processing_product_title, $processing_product_sku);
				$this->hf_log_data_change('API-import', sprintf(__('> &#8220;%s&#8221;' . $usr_msg, 'product-import-for-woo'), esc_html($processing_product_title)).' with post ID:'.$existing_product, true);
				unset($post);
				return;
			}
                      
			if ( $processing_product_id && is_string( get_post_status( $processing_product_id ) ) ) {
				$this->add_import_result( 'skipped', __( 'Importing product(ID) conflicts with an existing post.', 'product-import-for-woo' ), $processing_product_id, get_the_title( $processing_product_id ), '' );
				$this->hf_log_data_change( 'API-import', sprintf( __('> &#8220;%s&#8221; ID already exists.', 'product-import-for-woo'), esc_html( $processing_product_id ) ), true );
				unset( $post );
				return;
			}
		}
		// Check post type to avoid conflicts with IDs
        $is_post_exist_in_db = get_post_type( $processing_product_id );
		if ( $merging && $processing_product_id && !empty($is_post_exist_in_db) && ($is_post_exist_in_db !== $post['post_type'] )) {
			$this->add_import_result( 'skipped', __( 'Importing product(ID) conflicts with an existing post which is not a product.', 'product-import-for-woo' ), $processing_product_id, $processing_product_title, $processing_product_sku );
			$this->hf_log_data_change( 'API-import', sprintf( __('> &#8220;%s&#8221; is not a product.', 'product-import-for-woo'), esc_html($processing_product_id) ), true );
			unset( $post );
			return;
		}

		if (!empty($is_post_exist_in_db) ) {
			$post_status = ($post['post_status']=='publish')?1:10;
			$result = $wpdb->query($wpdb->prepare(_sql("INSERT wp_sema_products(productid,brandid,partno,status,wpid) VALUES (%d,%s,%s,%d,%d) ON DUPLICATE KEY UPDATE status=VALUES(status),wpid=VALUES(wpid);"),$pimid,$aaia,$post['sku'],$post_status,$processing_product_id));			
			//$result = $wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_products SET pwid=%d WHERE productid=%d "),$processing_product_id,$pimid));			
			$post_id = $processing_product_id;
			if ( sizeof( $merging ) > 1 ) {
				// Only merge fields which are set

				$this->hf_log_data_change( 'API-import', sprintf( __('> Merging post ID %s.', 'product-import-for-woo'), $post_id ), true );

				$postdata = array('ID' => $post_id,'post_status'=>$post['post_status']);
				//$postdata = array();

				if(array_key_exists('title',$merging) && !empty( $post['post_title'] ) ) {
					$postdata['post_title'] = $post['post_title'];
				}
				if(array_key_exists('description',$merging) && !empty( $post['post_content'] ) ) {
					$postdata['post_content'] = $post['post_content'];
				}

				$time=date("Y-m-d H:i:s" );
				$postdata['post_date'] = $time;
				$postdata['post_date_gmt'] = get_gmt_from_date($time);
				$result = wp_update_post( $postdata);

				if ( ! $result ) {
					$this->add_import_result( 'failed', __( 'Failed to update product', 'product-import-for-woo' ), $post_id, $processing_product_title, $processing_product_sku );
					$this->hf_log_data_change( 'API-import', sprintf( __('> Failed to update product %s', 'product-import-for-woo'), $post_id ), true );
					unset( $post );
					return;
				} else {
					$this->hf_log_data_change( 'API-import', __( '> Merged post data: ', 'product-import-for-woo' ) . print_r( $postdata, true ) );
				}
			}

		} else {
            $merging = array();
			// Get parent
			$post_parent = (isset($post['post_parent'])?$post['post_parent']:'');
                        
			if ( $post_parent !== "" ) {
				$post_parent = absint( $post_parent );

				if ( $post_parent > 0 ) {
					// if we already know the parent, map it to the new local ID
					if ( isset( $this->processed_posts[ $post_parent ] ) ) {
						$post_parent = $this->processed_posts[ $post_parent ];

					// otherwise record the parent for later
					} else {
                                            
						$this->post_orphans[ intval( $processing_product_id ) ] = $post_parent;
						//$post_parent = 0;
                                                
					}
                                        
				}
			}

			// Insert product
			$this->hf_log_data_change( 'API-import', sprintf( __('> Inserting %s', 'product-import-for-woo'), esc_html( $processing_product_title ) ), true );
            $postdata = array(
				'import_id'      => $processing_product_id,
				'post_author'    => !empty($post['post_author']) ? absint($post['post_author']) : get_current_user_id(),
                                'post_date' => !empty( $post['post_date'] ) ? date("Y-m-d H:i:s", strtotime($post['post_date'])) : '',
                                'post_date_gmt' => ( !empty($post['post_date_gmt']) && $post['post_date_gmt'] ) ? date('Y-m-d H:i:s', strtotime($post['post_date_gmt'])) : '',
				'post_content'   => !empty($post['post_content'])?$post['post_content']:'',
				'post_excerpt'   => !empty($post['post_excerpt'])?$post['post_excerpt']:'',
				'post_title'     => $processing_product_title,
				'post_name'      => !empty( $post['post_name'] ) ? $post['post_name'] : sanitize_title( $processing_product_title ),
				'post_status'    => !empty( $post['post_status'] ) ? $post['post_status'] : 'publish',
				'post_parent'    => $post_parent,
				'menu_order'     => !empty($post['menu_order'])?$post['menu_order']:'',
				'post_type'      => !empty($post['post_type'])?$post['post_type']:"",
				'post_password'  => !empty($post['post_password'])?$post['post_password']:'',
				'comment_status' => !empty($post['comment_status'])?$post['comment_status']:'',
			);
			$post_status = ($post['post_status']=='publish')?1:10;
			$post_id = wp_insert_post( $postdata, true );

			if ( is_wp_error( $post_id ) ) {
				
				$result = $wpdb->query($wpdb->prepare(_sql("INSERT wp_sema_products VALUES (%d,%s,%s,-40,null) ON DUPLICATE KEY UPDATE status=-40,wpid=null"),$pmid,$aaia,$post['sk']));			
				$this->add_import_result( 'failed', __( 'Failed to import product', 'product-import-for-woo' ), $processing_product_id, $processing_product_title, $processing_product_sku );
				$this->hf_log_data_change( 'API-import', sprintf( __( 'Failed to import product &#8220;%s&#8221;', 'product-import-for-woo' ), esc_html($processing_product_title) ) );
				unset( $post );
				return;

			} else {
				$result = $wpdb->query($wpdb->prepare(_sql("INSERT wp_sema_products VALUES (%d,%s,%s,%d,%d) ON DUPLICATE KEY UPDATE status=%d,wpid=%d"),$pimid,$aaia,$post['sku'],$post_status,$post_id,$post_status,$post_id));			
				$this->hf_log_data_change( 'API-import', sprintf( __('> Inserted - post ID is %s.', 'product-import-for-woo'), $post_id ) );

			}
		}
		unset( $postdata );

		/*
		if(array_key_exists('universal',$post)){
			$return = $wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_sema_parts WHERE brandid=%s AND partno=%s"),$aaia,$sku));
			$return = $wpdb->query($wpdb->prepare(_sql("INSERT INTO wp_sema_parts(brandid,partno,year,make,model,submodel,productid) VALUES (%s,%s,'','','','',%s) " ),$aaia,$sku,$post_id));
		}else{
			if(!$is_post_exist_in_db || ($post_id && array_key_exists('fitment',$merging))){
				// begin to insert YMMS				
				$return = $wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_sema_parts WHERE brandid=%s AND partno=%s"),$aaia,$sku));
				$url="https://apps.semadata.org/sdapi/plugin/lookup/vehiclesbyproduct";
				$count_YMMS=0;
				// Get the response and close the channel.
				$response = wp_remote_post($url, array(
					'body' => array(
						'token'=>$token,'aaia_brandid'=>$aaia,'groupByPart'=>'true','partNumber'=>$partno,"purpose"=>"WP"
					),
					'timeout' => '60','redirection' => 5,'redirection' => 5,'httpversion' => '1.0','sslverify' => false,'blocking' => true,
				));
				if(array_key_exists('body',$response)){
					$response=json_decode($response['body'],true);
					$vehicles=$response['Parts'][0]['Vehicles'];
					$PartNumber=$response['Parts'][0]['PartNumber'];
					

					$vehicles = array_map('serialize', $vehicles);
					//$vehicles = array_map('strtolower', $vehicles);
					$vehicles = $this->array_iunique($vehicles);
					$vehicles = array_map('unserialize', $vehicles);

				
					$values = array();
					foreach($vehicles as $vehicle){
						$values[] = $wpdb->prepare( "(%s,%s,%s,%s,%s,%s)",$aaia,$aaia.'-'.$PartNumber, $vehicle['Year'], $vehicle['MakeName'], $vehicle['ModelName'], $vehicle['SubmodelName'] );
					}
					$count_YMMS+=count($values);
					$values=implode(',', $values);
					$wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_sema_parts WHERE brandid=%s AND partno=%s; "),$aaia,$PartNumber));
					$sql="INSERT INTO wp_sema_parts(brandid,partno,year,make,model,submodel) VALUES $values; " ;
					$wpdb->query(_sql($sql));
	
				}else{
					$this->add_import_result( 'failed', __( 'Time out. Failed to import fitments', 'product-import-for-woo' ), $processing_product_id, $processing_product_title, $processing_product_sku );
					unset( $post );
					return;
	
				}
			}
			//if(array_key_exists('fitment',$merging)){
			$return = $wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_parts SET productid=%d WHERE partno=%s; " ),$post_id,$sku));
			//}
		}
		*/
		// map pre-import ID to local ID
		if ( empty( $processing_product_id ) ) {
			$processing_product_id = (int) $post_id;
		}

		$this->processed_posts[ intval( $processing_product_id ) ] = (int) $post_id;

		// add categories, tags and other terms
		//if (empty($merging) && ! empty( $post['terms'] ) && is_array( $post['terms'] ) ) {
		if (! empty( $post['terms'] ) && is_array( $post['terms'] ) ) {

			$terms_to_set = array();

			foreach ( $post['terms'] as $term_group ) {

				$taxonomy 	= $term_group['taxonomy'];
				$terms		= $term_group['terms'];

				if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				if ( ! is_array( $terms ) ) {
					$terms = array( $terms );
				}

				$terms_to_set[ $taxonomy ] = array();

				foreach ( $terms as $term_id ) {

					if ( ! $term_id ) continue;

					$terms_to_set[ $taxonomy ][] = intval( $term_id );
				}

			}

			foreach ( $terms_to_set as $tax => $ids ) {
				if($tax=='product_cat') $tt_ids = wp_set_object_terms( $post_id, $ids, 'product_cat' );
				else $tt_ids = wp_set_post_terms( $post_id, $ids, $tax, false );
			}

			unset( $post['terms'], $terms_to_set );
		}

		// add/update post meta
		if ( ! empty( $post['postmeta'] ) && is_array( $post['postmeta'] ) ) {
			if($merging){
				if(array_key_exists('price',$merging)) {
					unset($post['postmeta'][0]);
					unset($post['postmeta'][4]);
					unset($post['postmeta'][5]);
					unset($post['postmeta'][6]);
					unset($post['postmeta'][7]);
					unset($post['postmeta'][8]);
					unset($post['postmeta'][9]);
					unset($post['postmeta'][10]);
					unset($post['postmeta'][11]);
				}else unset($post['postmeta']);
			}
			foreach ( $post['postmeta'] as $meta ) {
				//$key = apply_filters( 'import_post_meta_key', $meta['key'] );
				$key=$meta['key'];
				if ( $key ) {
					update_post_meta( $post_id, $key, maybe_unserialize( $meta['value'] ) );
				}
				/*
				if ( $key == '_file_paths' ) {
					do_action( 'woocommerce_process_product_file_download_paths', $post_id, 0, maybe_unserialize( $meta['value'] ) );
				}*/
				if ( $key == '_price' ) {
					if($meta['value']<=0) $warning.=" No retail price.";
				}


			}

			unset( $post['postmeta'] );
		}
		// Import images and add to post
		if ((empty($merging) || array_key_exists('image',$merging)) && !empty( $post['images'] ) && is_array($post['images']) ) {

			$featured    = true;
			$gallery_ids = array();

			if(array_key_exists('image',$merging)) {

				// Get basenames
				$image_basenames = array();

				foreach( $post['images'] as $image )
					$image_basenames[] = basename( $image );

				// Loop attachments already attached to the product
				//$attachments = get_posts( 'post_parent=' . $post_id . '&post_type=attachment&fields=ids&post_mime_type=image&numberposts=-1' );
                                
				$processing_product_object = wc_get_product($post_id);
				$attachments = $processing_product_object->get_gallery_attachment_ids();
				$post_thumbnail_id = get_post_thumbnail_id($post_id);
				if(isset($post_thumbnail_id)&& !empty($post_thumbnail_id)){
					$attachments[]=$post_thumbnail_id;
				}
                                
				foreach ( $attachments as $attachment_key => $attachment ) {

					$attachment_url 	= wp_get_attachment_url( $attachment );
					$attachment_basename 	= basename( $attachment_url );

					// Don't import existing images
					if ( in_array( $attachment_url, $post['images'] ) || in_array( $attachment_basename, $image_basenames ) ) {

						foreach( $post['images'] as $key => $image ) {

							if ( $image == $attachment_url || basename( $image ) == $attachment_basename ) {
								unset( $post['images'][ $key ] );

								$this->hf_log_data_change( 'API-import', sprintf( __( '> > Image exists - skipping %s', 'product-import-for-woo' ), basename( $image ) ) );

								if ( $key == 0 ) {
									update_post_meta( $post_id, '_thumbnail_id', $attachment );
									$featured = false;
								} else {
									$gallery_ids[ $key ] = $attachment;
								}
							}

						}

					} else {

						// Detach image which is not being merged
						$attachment_post = array();
						$attachment_post['ID'] = $attachment;
						$attachment_post['post_parent'] = '';
						wp_update_post( $attachment_post );
						unset( $attachment_post );

					}

				}

				unset( $attachments );
			}

			if ( $post['images'] ) foreach ( $post['images'] as $image_key => $image ) {
				if(strlen(trim($image))==0) continue;

				$this->hf_log_data_change( 'API-import', sprintf( __( '> > Importing image "%s"', 'product-import-for-woo' ), $image ) );

				$filename = basename( $image );

				$attachment = array(
						'post_title'   => preg_replace( '/\.[^.]+$/', '', $processing_product_title . ' ' . ( $image_key + 1 ) ),
						'post_content' => '',
						'post_status'  => 'inherit',
						'post_parent'  => $post_id
				);

				$attachment_id = $this->process_attachment( $attachment, $image, $post_id );

				if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {

					$this->hf_log_data_change( 'API-import', sprintf( __( '> > Imported image "%s"', 'product-import-for-woo' ), $image ) );

					// Set alt
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', $processing_product_title );

					if ( $featured ) {
						update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
					} else {
						$gallery_ids[ $image_key ] = $attachment_id;
					}

					update_post_meta( $attachment_id, '_woocommerce_exclude_image', 0 );
					$this->regenerate_thumbnail($attachment_id);
					$featured = false;
				} else {
					$this->hf_log_data_change( 'API-import', sprintf( __( '> > Error importing image "%s"', 'product-import-for-woo' ), $image ) );
					$this->hf_log_data_change( 'API-import', '> > ' . $attachment_id->get_error_message() );
				}

				unset( $attachment, $attachment_id );
			}

			$this->hf_log_data_change( 'API-import', __( '> > Images set', 'product-import-for-woo' ) );

			ksort( $gallery_ids );

			update_post_meta( $post_id, '_product_image_gallery', implode( ',', $gallery_ids ) );

			unset( $post['images'], $featured, $gallery_ids );
		}

		// Import attributes - import to wp_sema_attr_taxomony
		if (empty($merging) || array_key_exists('attribute',$merging)){
			//if(empty( $processing_product_id )){}
			$return = $wpdb->query($wpdb->prepare(_sql("DELETE FROM wp_sema_attr_taxonomy WHERE product_id=%d "),$post_id));
			$values = array();
			foreach($post['taxonomy'] as $key=>$tax){
				$values[] = $wpdb->prepare( "(%d,%d,%s,%s)", $post_id, $post['product_cat'], $key, $tax);
			}
			$values=implode(',', $values);
			$return = $wpdb->query(_sql("INSERT INTO wp_sema_attr_taxonomy (product_id,term_id,attr_name,attr_value) VALUES " . $values));

		}
		// Import attributes - import to woocommerce product meta

		if ((empty($merging) || array_key_exists('attribute',$merging)) && !empty( $post['attributes'] ) && is_array($post['attributes']) ) {

			if(array_key_exists('attribute',$merging)) {
				$attributes = array_filter( (array) maybe_unserialize( get_post_meta( $post_id, '_product_attributes', true ) ) );
				$attributes = array_merge( $attributes, $post['attributes'] );
			} else {
				$attributes = $post['attributes'];
			}

			// Sort attribute positions
			if ( ! function_exists( 'attributes_cmp' ) ) {
				function attributes_cmp( $a, $b ) {
				    if ( $a['position'] == $b['position'] ) return 0;
				    return ( $a['position'] < $b['position'] ) ? -1 : 1;
				}
			}
			uasort( $attributes, 'attributes_cmp' );

			update_post_meta( $post_id, '_product_attributes', $attributes );

			unset( $post['attributes'], $attributes );
		}
		$exetime=round(microtime(true)-$start,1);
		if ( $merging ) {
			$array_keys=implode('/',array_keys($merging));
			$this->add_import_result( 'merged', "Update $array_keys successfully.($exetime\")", $post_id, $processing_product_title, $processing_product_sku );
			$this->hf_log_data_change( 'API-import', sprintf( __('> Finished merging post ID %s.', 'product-import-for-woo'), $post_id ) );
		} else {
			$this->add_import_result( 'imported', 'Import successful.'.$warning, $post_id, $processing_product_title, $processing_product_sku );
			$this->hf_log_data_change( 'API-import', sprintf( __('> Finished importing post ID %s.', 'product-import-for-woo'), $post_id ) );
		}
        $return=array('pid'=>$post['pid'],'id'=>$post_id,'isnew'=>!$is_post_exist_in_db,'brandid'=>$aaia,'sku'=>$sku,'name'=>$post['post_title'],'status'=>$post_status,'image'=>get_the_post_thumbnail_url($post_id,'woocommerce_thumbnail'));
		return $return;
        //if($processing_product_object!=null) do_action('wf_refresh_after_product_import',$processing_product_object); // hook for forcefully refresh product
		//unset( $post );
	}


	function array_iunique($array) {
		return array_intersect_key(
			$array,
			array_unique(array_map("StrToLower",$array))
		);
	}	
	/**
	 * Log a row's import status
	 */
	protected function add_import_result( $status, $reason, $post_id = '', $post_title = '', $sku = '' ) {
		$this->import_results[] = array(
			'post_title' => $post_title,
			'post_id'    => $post_id,
			'sku'    	 => $sku,
			'status'     => $status,
			'reason'     => $reason
		);
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array $post Attachment post details from WXR
	 * @param string $url URL to fetch attachment from
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	public function process_attachment( $post, $url, $post_id ) {

		$attachment_id 		= '';
		$attachment_url 	= '';
		$attachment_file 	= '';
		$upload_dir 		= wp_upload_dir();

		// If same server, make it a path and move to upload directory
		/*if ( strstr( $url, $upload_dir['baseurl'] ) ) {

			$url = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		} else*/
		if ( strstr( $url, site_url() ) ) {
                    
                        $image_id = $this->wt_get_image_id_by_url($url);
                        if($image_id){
                            $attachment_id = $image_id;

                            $this->hf_log_data_change('API-import', sprintf(__('> > (Image already in the site)Inserted image attachment "%s"', 'product-import-for-woo'), $url));

                            $this->attachments[] = $attachment_id;

                            return $attachment_id;
                        }
			$abs_url 	= str_replace( trailingslashit( site_url() ), trailingslashit( ABSPATH ), urldecode($url) );
			$new_name 	= wp_unique_filename( $upload_dir['path'], basename( urldecode($url) ) );
			$new_url 	= trailingslashit( $upload_dir['path'] ) . $new_name;

			if ( copy( $abs_url, $new_url ) ) {
				$url = basename( $new_url );
			}
		}

		if ( ! strstr( $url, 'http' ) ) {  // if not a url

			// Local file
			$attachment_file 	= trailingslashit( $upload_dir['basedir'] ) . 'product_images/' . $url;

			// We have the path, check it exists
			if ( ! file_exists( $attachment_file ) )
				$attachment_file 	= trailingslashit( $upload_dir['path'] ) . $url;

			// We have the path, check it exists
			if ( file_exists( $attachment_file ) ) {

				$attachment_url 	= str_replace( trailingslashit( ABSPATH ), trailingslashit( site_url() ), $attachment_file );

				if ( $info = wp_check_filetype( $attachment_file ) )
					$post['post_mime_type'] = $info['type'];
				else
					return new WP_Error( 'attachment_processing_error', __('Invalid file type', 'product-import-for-woo') );
                                
                                
                                $image_id = $this->wt_get_image_id_by_url($attachment_url);
                                if($image_id){
                                    $attachment_id = $image_id;
                                    $this->hf_log_data_change('API-import', sprintf(__('> > (Image already in the site)Inserted image attachment "%s"', 'product-import-for-woo'), $url));
                                    $this->attachments[] = $attachment_id;
                                    return $attachment_id;
                                }

				$post['guid'] = $attachment_url;

				$attachment_id 		= wp_insert_attachment( $post, $attachment_file, $post_id );

			} else {
				return new WP_Error( 'attachment_processing_error', __('Local image did not exist!', 'product-import-for-woo') );
			}

		} else {

			// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
			if ( preg_match( '|^/[\w\W]+$|', $url ) )
				$url = rtrim( site_url(), '/' ) . $url;

			$upload = $this->fetch_remote_file( $url, $post );

			if ( is_wp_error( $upload ) )
				return $upload;

			if ( $info = wp_check_filetype( $upload['file'] ) )
				$post['post_mime_type'] = $info['type'];
			else
				return new WP_Error( 'attachment_processing_error', __('Invalid file type', 'product-import-for-woo') );

			$post['guid']       = $upload['url'];
			$attachment_file 	= $upload['file'];
			$attachment_url 	= $upload['url'];

			// as per wp-admin/includes/upload.php
			$attachment_id = wp_insert_attachment( $post, $upload['file'], $post_id );

			unset( $upload );
		}

		if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
			$this->hf_log_data_change( 'API-import', sprintf( __( '> > Inserted image attachment "%s"', 'product-import-for-woo' ), $url ) );

			$this->attachments[] = $attachment_id;
		}

		return $attachment_id;
	}
        
        function wt_get_image_id_by_url($image_url) {
            global $wpdb;
            $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url));
            return isset($attachment[0])&& $attachment[0]>0 ? $attachment[0]:'';
        }

	/**
	 * Attempt to download a remote file attachment
	 */
	public function fetch_remote_file( $url, $post ) {

		// extract the file name and extension from the url
		$file_name 		= basename( current( explode( '?', $url ) ) );
		$wp_filetype 	= wp_check_filetype( $file_name, null );
		$parsed_url 	= @parse_url( $url );

		// Check parsed URL
		if ( ! $parsed_url || ! is_array( $parsed_url ) )
			return new WP_Error( 'import_file_error', 'Invalid URL' );

		// Ensure url is valid
		$url = str_replace( " ", '%20', $url );

		// Get the file
		$response = wp_remote_get( $url, array(
                    'timeout' => 50,
                    "user-agent" => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:56.0) Gecko/20100101 Firefox/56.0",
                    'sslverify' => FALSE
		) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 )
			return new WP_Error( 'import_file_error', 'Error getting remote image');

		// Ensure we have a file name and type
		if ( ! $wp_filetype['type'] ) {

			$headers = wp_remote_retrieve_headers( $response );

			if ( isset( $headers['content-disposition'] ) && strstr( $headers['content-disposition'], 'filename=' ) ) {

				$disposition = end( explode( 'filename=', $headers['content-disposition'] ) );
				$disposition = sanitize_file_name( $disposition );
				$file_name   = $disposition;

			} elseif ( isset( $headers['content-type'] ) && strstr( $headers['content-type'], 'image/' ) ) {

				$file_name = 'image.' . str_replace( 'image/', '', $headers['content-type'] );

			}

			unset( $headers );
		}

		// Upload the file
		$upload = wp_upload_bits( $file_name, '', wp_remote_retrieve_body( $response ) );

		if ( $upload['error'] )
			return new WP_Error( 'upload_dir_error', $upload['error'] );

		if($upload['type']=='image/jpeg' || $upload['type']=='image/gif' || $upload['type']=='image/png'){
			if ( !function_exists( 'imagecreatefromgif' )) return new WP_Error( 'upload_function_error', 'Function imagecreatefromgif does not exist.' );
			switch ($upload['type']) {
				case 'image/gif':
					$src  = imagecreatefromgif($upload['file']);
					break;
				case 'image/png':
					$src  = imagecreatefrompng($upload['file']);
					break;
				default:
				$src  = imagecreatefromjpeg($upload['file']);
			}
			// check if actual image type is the same as the image extension
			if($src){
				$w = imagesx($src); // image width
				$h = imagesy($src); // image height
				
				//$width=($w>$h)?$w:$h;
				//$height=$width;
				// max picture size for wordpress is 2560x2560, resize picture, max size of plugins is 2048x2048 in order to reduce the number of thumbnail images.
				$max=2048;
				if($w>$max|| $h>$max){
					$width=$height=$max;
					if($w>=$h){
						$w_resized=$max;
						$h_resized=$w_resized*$h/$w;
					}else{
						$h_resized=$max;
						$w_resized=$h_resized*$w/$h;
					}
				}else{
					$width=($w>$h)?$w:$h;
					$height=$width;
					$w_resized=$w;
					$h_resized=$h;
				}
				// Destination image with white background
				$dst = imagecreatetruecolor($width, $height);
				imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
				
				// All Magic is here
				//imagecopyresampled($dst, $src , round(($width-$w)/2), round(($height-$h)/2), 0, 0, $w, $h, $w, $h);
				//imagejpeg($dst, $upload['file'], 100);
				imagecopyresampled($dst, $src , round(($width-$w_resized)/2), round(($height-$h_resized)/2), 0, 0, $w_resized, $h_resized, $w, $h);
				switch ($upload['type']) {
					case 'image/gif':
						imagegif($dst, $upload['file']);
						break;
					case 'image/png':
						imagepng($dst, $upload['file']);
						break;
					default:
						imagejpeg($dst, $upload['file'], 80);
				}
			}

		}
			// Get filesize
		$filesize = filesize( $upload['file'] );

		if ( 0 == $filesize ) {
			@unlink( $upload['file'] );
			unset( $upload );
			return new WP_Error( 'import_file_error', __('Zero size file downloaded', 'product-import-for-woo') );
		}

		unset( $response );

		return $upload;
	}

	/**
	 * Decide what the maximum file size for downloaded attachments is.
	 * Default is 0 (unlimited), can be filtered via import_attachment_size_limit
	 *
	 * @return int Maximum attachment file size to import
	 */
	public function max_attachment_size() {
		return apply_filters( 'import_attachment_size_limit', 0 );
	}

	/**
	 * Attempt to associate posts and menu items with previously missing parents
	 */
	public function backfill_parents() {
		global $wpdb;

		// find parents for post orphans
		if ( ! empty( $this->post_orphans ) && is_array( $this->post_orphans ) )
			foreach ( $this->post_orphans as $child_id => $parent_id ) {
				$local_child_id = $local_parent_id = false;
				if ( isset( $this->processed_posts[$child_id] ) )
					$local_child_id = $this->processed_posts[$child_id];
				if ( isset( $this->processed_posts[$parent_id] ) )
					$local_parent_id = $this->processed_posts[$parent_id];

				if ( $local_child_id && $local_parent_id )
					$wpdb->update( $wpdb->posts, array( 'post_parent' => $local_parent_id ), array( 'ID' => $local_child_id ), '%d', '%d' );
			}
	}

	/**
	 * Attempt to associate posts and menu items with previously missing parents
	 */
	public function link_product_skus( $type, $product_id, $skus ) {
		global $wpdb;

		$ids = array();

		foreach ( $skus as $sku ) {
			$ids[] = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sku' AND meta_value = %s;", $sku ) );
		}

		$ids = array_filter( $ids );

		update_post_meta( $product_id, "_{$type}_ids", $ids );
	}
	

	// Display import page title
	public function header() {
		if(WC()->version < '2.7.0'){
			$memory    = size_format( woocommerce_let_to_num( ini_get( 'memory_limit' ) ) );
			$wp_memory = size_format( woocommerce_let_to_num( WP_MEMORY_LIMIT ) );
		}else{
			$memory    = size_format( wc_let_to_num( ini_get( 'memory_limit' ) ) );
			$wp_memory = size_format( wc_let_to_num( WP_MEMORY_LIMIT ) );
				
		}

		$memoryinfo="PHP Memory: $memory, WP Memory: $wp_memory";		
		echo '<div class="pipe-import-wrap"><div class="pipe-import-text">'.$memoryinfo.'</div>';		
	}

	// Close div.pipe-import-wrap
	public function footer() {
		echo '</div>';
	}

	public function chooseCategory($aaia,$selectednodes,$priceadjustment,$customprefix,$pricetoimport) {
		include( 'views/html-sema-import-step2.php' );
	}

	public function syncCategory($aaia,$selectednodes,$priceadjustment,$customprefix) {
		include( 'views/html-sema-import-category.php' );
	}	
	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import
	 * @return int 60
	 */
	public function bump_request_timeout( $val ) {
		return 60;
	}
        /**
     * Get a list of all the product attributes for a post type.
     * These require a bit more digging into the values.
     */
    public static function get_all_product_attributes( $post_type = 'product' ) {
        global $wpdb;

        $results = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status IN ( 'publish', 'pending', 'private', 'draft' )
            AND pm.meta_key = '_product_attributes'",
            $post_type
        ) );

        // Go through each result, and look at the attribute keys within them.
        $result = array();

        if ( ! empty( $results ) ) {
            foreach( $results as $_product_attributes ) {
                $attributes = maybe_unserialize( maybe_unserialize( $_product_attributes ) );
                if ( ! empty( $attributes ) && is_array( $attributes ) ) {
                	foreach( $attributes as $key => $attribute ) {
                   		if ( ! $key ) {
                   	 		continue;
                   		}
                   	 	if ( ! strstr( $key, 'pa_' ) ) {
                   	 		if ( empty( $attribute['name'] ) ) {
                   	 			continue;
                   	 		}
                   	 		$key = $attribute['name'];
                   	 	}

                   	 	$result[ $key ] = $key;
                   	 }
                }
            }
        }

        sort( $result );

        return $result;
    }
}
