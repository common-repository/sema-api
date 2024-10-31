<?php
/**
 * Render the Insert Pages Settings page.
 *
 * @package sema-search
 */
error_reporting(E_ERROR); 
$membership=0;
$subscription=['aces'=>0,'uiux'=>0,'prod'=>0];


/**
 * Add 'SEMA Search' to the Settings menu.
 *
 * @return void
 */

function sema_add_admin_menu() {
	
	add_options_page( 'SEMA', 'SEMA Setting', 'manage_options', 'sema_setting', 'sema_options_page' );
	add_options_page( 'SEMA', 'SEMA Import', 'manage_options', 'sema_import', 'sema_product_import_page' );
	//add_options_page( 'SEMA', 'SEMA Fitment', 'manage_options', 'sema_fitment', 'sema_product_import_page' );
}
add_action( 'admin_menu', 'sema_add_admin_menu' );

/**
 * check site info
 */
function check_site(){
	global $wpdb,$membership,$subscription;

	$row2 = $wpdb->get_row(_sql("SHOW COLUMNS FROM wp_sema_brands LIKE 'options'") );
	if(empty($row2)){
		$wpdb->query(_sql("ALTER TABLE wp_sema_brands ADD COLUMN options varchar(5000) NULL DEFAULT NULL,ADD COLUMN check_date datetime NULL DEFAULT NULL,ADD COLUMN updates mediumint(9) NULL DEFAULT 0,ADD COLUMN updates_info varchar(500) NULL DEFAULT NULL,ADD COLUMN finishedproducts mediumint(9) NULL DEFAULT NULL ;") );
	}	

	$options = get_option( 'sema_settings' );
	$token=$options['sema_token'];
	$siteid=$options['siteid'];
	$brandids=$options['sema_aaia'];
	$lastcheck=$options['lastcheck'];
	$membership=array_key_exists('membership',$options)?$options['membership']:"0";
	$subscription=array_key_exists('subscription',$options)?$options['subscription']:['aces'=>0,'uiux'=>0,'prod'=>0];

	// check site info once every 2 minutes
	if($lastcheck && microtime(true)-$lastcheck<120) return;
	$site = rtrim(str_replace('http://','',str_replace('https://','',get_site_url())),',');
	if($site){
		/*
		openssl_public_encrypt($token, $token_encrypted, $pubKey);
		if($token_encrypted){
			$url="https://demo.semadata.org/shopify/ajax.php?site=$site&type=wp_get_store&sema_token=".urlencode($token_encrypted)."&brandids=$input[sema_aaia]";
			$response = wp_remote_get($url, array('sslverify' => FALSE));
		}*/
		$url="https://demo.semadata.org/shopify/ajax.php?siteid=$siteid&site=$site&type=wp_get_store&sema_token=".urlencode($token)."&brandids=$brandids";
		$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
		if(!is_wp_error( $request )){
			$response=json_decode($response['body'],true);
			if($response['success']){
				$options['siteid']=$response['siteinfo']['id'];
				//$response['siteinfo']['membership']=1;// for test purpose
				$options['membership']=$response['siteinfo']['membership'];
				$options['uuid']=$response['siteinfo']['uuid'];
				$options['subscription']=$response['siteinfo']['subscription'];
				if($options['membership']==1){
					$url="https://apps.semadata.org/sdapi/plugin/export/branddatasets?token=$token&purpose=WP";
					$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
					$response=json_decode($response['body'],true);
					if($response['Success']){
						$brandnames=$response['BrandDatasets'];
						//remove duplicated brands
						$pre=0;
						foreach($brandnames as $k=>$v){
							if($k>0 && $v['AAIABrandId']==$brandnames[$pre]['AAIABrandId']) unset($brandnames[$k]);
							else $pre=$k;
						}
						$wpdb->query(_sql("UPDATE wp_sema_brands SET active=0;"));
						$brandids='';
						$k=0;
						foreach($brandnames as $brand){
							$aaia=$brand['AAIABrandId'];
							$key=array_search($aaia,array_column($brandnames,''));
							$brandids.="$aaia,";
							$brandname=str_replace("'",'',$brand['BrandName']);
							$wpdb->query($wpdb->prepare(_sql("INSERT INTO wp_sema_brands(brandid,brandname,selectednodes,undeterminednodes,termids,active,importedproducts,currentpid,totalproducts,unimportedproducts,unimported_reason,priceadjustment,pricetoimport,prefix,importdate)
							VALUES (%s,%s,'','','',1,0,0,0,0,'',0,null,null,null) ON DUPLICATE KEY UPDATE brandname=%s,active=1;"),$aaia,$brandname,$brandname));
							$k++;if($k>=15) break;
						}
						$brandids && $brandids=substr($brandids,0,strlen($brandids)-1);		
						$options['sema_aaia']=$brandids;
					} 
				}
				$options['lastcheck']=floor(microtime(true));
				update_option( 'sema_settings', $options );
			}		}

		//$response=json_decode($response,true);
	}
 
}
/**
 * import page
 */
function sema_product_import_page() {
	check_site();
	//require_once( plugin_dir_path(__DIR__).'sema-api/import.php');
	// change import.php to imports.php because it's conflicted with ABSPATH . 'wp-admin/includes/import.php'	
	include("imports.php"); 
}
/**
 * option page
 */
function sema_options_page() {
	global $membership,$subscription;
	check_site();
	$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
	?>
<script>var ajax_url='<?php echo(esc_url($ajax_url)); ?>';</script>
<div class="woocommerce">
	<?php $_GET['tab']='option';include("includes/menutab.php"); ?>
	<form id="sema-form" action='options.php' method='post'>
	<div class="pipe-main-box">
        <div class="pipe-view p-20p">
            <div class="tool-box">
				<?php
				settings_fields( 'semaSettings' );
				do_settings_sections( 'semaSettings' );
				submit_button();
				?>
            </div>
        </div>
    </div>	
	</form>			
</div>

	<?php
}

/**
 * Register settings fields.
 *
 * @return void
 */
function sema_settings_init() {
	register_setting( 'semaSettings', 'sema_settings','sema_validate_options');
	add_settings_section(
		'sema_section',
		__( 'SEMA Settings', 'sema-search' ),
		'sema_settings_section_callback',
		'semaSettings'
	);
	add_settings_field(
		'membership',
		__( 'Membership', 'sema-search' ),
		'membership_render',
		'semaSettings',
		'sema_section'
	);
	add_settings_field(
		'sema_token',
		__( 'SDC Login', 'sema-search' ),
		'sema_token_render',
		'semaSettings',
		'sema_section'
	);
	add_settings_field(
		'sema_aaia',
		__( 'AAIA Brand ID', 'sema-search' ),
		'sema_aaia_render',
		'semaSettings',
		'sema_section'
	);

	// Backend option section
	add_settings_section(
		'sema_import_options',
		__( 'Backend Options', 'sema-search' ),
		null,
		'semaSettings'
	);
	add_settings_field(
		'sema_product_update',
		__( 'Mandatory update', 'sema-search' ),
		'sema_product_update_render',
		'semaSettings',
		'sema_import_options'
	);
	add_settings_field(
		'sema_product_currency',
		__( 'Currency', 'sema-search' ),
		'sema_product_currency_render',
		'semaSettings',
		'sema_import_options'
	);
	/*
	add_settings_field(
		'sema_batch_size',
		__( 'Batch size', 'sema-search' ),
		'sema_product_batch_render',
		'semaSettings',
		'sema_import_options'
	);*/

	// Frontend option section
	add_settings_section(
		'sema_search_options',
		__( 'Frontend Options', 'sema-search' ),
		null,
		'semaSettings'
	);
	add_settings_field(
		'sema_hide_empty',
		__( 'Category/Product', 'sema-search' ),
		'sema_hide_empty_render',
		'semaSettings',
		'sema_search_options'
	);
	/*add_settings_field(
		'sema_hide_submodel',
		__( 'Search bar', 'sema-search' ),
		'sema_hide_submodel_render',
		'semaSettings',
		'sema_search_options'
	);*/
	add_settings_section(
		'sema_membership_options',
		__( 'Membership Options', 'sema-membership' ),
		null,
		'semaSettings'
	);	
	add_settings_field(
		'sema_show_engine',
		__( 'Hide Engine Info', 'sema-search' ),
		'sema_show_engine_render',
		'semaSettings',
		'sema_membership_options'
	);
	add_settings_field(
		'sema_hide_brandid',
		__( 'Hide Brand ID', 'sema-search' ),
		'sema_hide_brandid_render',
		'semaSettings',
		'sema_membership_options'
	);
	wp_enqueue_script('sema-js-backend', plugins_url( '/js/semasearch-backend.js', __FILE__ ),array('jquery','jquery-ui-dialog','jquery-ui-autocomplete','jquery-ui-widget', 'jquery-ui-position', 'jquery-ui-tooltip'));
	wp_enqueue_style('sema-css-main', plugins_url('/css/main.css', __FILE__));
	wp_enqueue_style('sema-css-jstree', plugins_url('/css/jtree.css', __FILE__));
	wp_enqueue_style('sema-css-woocommerce', plugins_url('/woocommerce/assets/css/admin.css'));

}

add_action( 'admin_init', 'sema_settings_init' );

/**
 * Sanitize each setting field as needed
 *
 * @param array $input Contains all settings fields as array keys
 */
function sema_validate_options($input) {
	global $wpdb;

	$options = get_option( 'sema_settings' );
	$input['sema_aaia'] = implode(',',preg_split('/[\ \n\,]+/', strtoupper($input['sema_aaia'])));
	$token=$options['sema_token'];
	if($input['membership']) $membership=$input['membership'];
	else $membership=$options['membership'];
	if($token){
		//if(array_key_exists('rows_imported',$input)) return $input;


		$url="https://apps.semadata.org/sdapi/plugin/export/branddatasets?token=$token&purpose=WP";
		$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
		$response=json_decode($response['body'],true);
	
		$brandnames=$response['BrandDatasets'];
		$brandids=array_filter(array_map('trim',explode(",",$input['sema_aaia'])));
		$input['sema_aaia']=implode(',',$brandids);
		//require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		if($membership==10 && $brandids){
			$wpdb->query(_sql("UPDATE wp_sema_brands SET active=0;"));
			foreach($brandids as $aaia){
				$key=array_search($aaia,array_column($brandnames,'AAIABrandId'));
				if($key===false) $brandname='';
				else $brandname=str_replace("'",'',$brandnames[$key]['BrandName']);
				$wpdb->query($wpdb->prepare(_sql("INSERT INTO wp_sema_brands(brandid,brandname,selectednodes,undeterminednodes,termids,active,importedproducts,currentpid,totalproducts,unimportedproducts,unimported_reason,priceadjustment,pricetoimport,prefix,importdate)
				VALUES (%s,%s,'','','',1,0,0,0,0,'',0,null,null,null) ON DUPLICATE KEY UPDATE brandname=%s,active=1;"),$aaia,$brandname,$brandname));
			}
		}

		if($options['sema_hide_productwoimage'] && !$input['sema_hide_productwoimage']){// show products without images
			$wpdb->query(_sql("UPDATE wp_posts p inner join wp_postmeta pm on p.ID=pm.post_id and pm.meta_key='_termid' set p.post_status='publish'
			where post_type='product' and post_status='draft' and ID NOT IN (SELECT post_id FROM wp_postmeta WHERE meta_key = '_thumbnail_id');"));
		}else if(!$options['sema_hide_productwoimage'] && $input['sema_hide_productwoimage']){// hide products without images
			$wpdb->query(_sql("UPDATE wp_posts p inner join wp_postmeta pm on p.ID=pm.post_id and pm.meta_key='_termid' set p.post_status='draft'
			where post_type='product' and post_status='publish' and ID NOT IN (SELECT post_id FROM wp_postmeta WHERE meta_key = '_thumbnail_id');"));
		}
		//update token and brandid
		//$site = str_replace('/','',str_replace('http://','',str_replace('https://','',get_site_url())));
		$site = rtrim(str_replace('http://','',str_replace('https://','',get_site_url())),',');


	}

	$input=array_merge($options,$input);
	return $input;
	
}

/**
 * Set meaningful defaults for settings.
 *
 * @return array Insert Pages settings.
 */
function sema_set_defaults() {
	$options = get_option( 'sema_settings' );
	if ( false === $options || empty($options) ) {
		$options = array();
	}

	if ( ! array_key_exists( 'sema_format', $options ) ) {
		$options['sema_format'] = 'slug';
	}

	if ( ! array_key_exists( 'sema_wrapper', $options ) ) {
		$options['sema_wrapper'] = 'block';
	}
	if ( ! array_key_exists( 'sema_hide_empty', $options ) ) {
		$options['sema_hide_empty'] = '1';
	}

	if ( ! array_key_exists( 'sema_insert_method', $options ) ) {
		$options['sema_insert_method'] = 'legacy';

		// Set default to 'normal' if gutenberg plugin is enabled (legacy insert
		// method will cause the gutenberg editor to load only the inserted page if
		// an insert page shortcode exists in a Shortcode block anywhere on the page.
		if ( function_exists( 'gutenberg_init' ) ) {
			$options['sema_insert_method'] = 'normal';
		}
	}

	if ( ! array_key_exists( 'sema_tinymce_filter', $options ) ) {
		$options['sema_tinymce_filter'] = 'normal';
	}
	//$user=$options['sema_username'];
	//$pass=$options['sema_password'];


	//update_option( 'sema_settings', $options );

	return $options;
}

/**
 * Print heading for Insert Pages settings page.
 *
 * @return void
 */
function sema_settings_section_callback( $section_passed) {
	esc_html_e( 'Do not share your API token with others for security purpose.', 'sema-setting' );
}



/**
 * Print 'SEMA token' setting.
 *
 * @return void
 */
function membership_render() {
	global $membership,$subscription;
	?>
	<span id="membership_text"><?=($membership==10)?"Premium":"Free"?></span>
	<input type="hidden"  name="sema_settings[membership]" value="<?php echo(esc_attr($membership)); ?>" id="membership"><br />
	<table><tr>
	<td width="20%"><input type="checkbox" <?php echo(($subscription['aces'])?'checked':''); ?> disabled > ACES</td>
	<td width="20%"><input type="checkbox" <?php echo(($subscription['uiux'])?'checked':''); ?> disabled> UI/UX</td>
	<td width="*"><input type="checkbox" <?php echo(($subscription['prod'])?'checked':''); ?> disabled> Productivity</td>
	</tr></table>
	<?php
}

/**
 * Print 'SEMA token' setting.
 *
 * @return void
 */
function sema_token_render() {
	$options = get_option( 'sema_settings' );
	if ( false === $options || empty($options) ) {
		$options = array();
	}
	if ( false === $options || ! is_array( $options ) || ! array_key_exists( 'sema_token', $options ) ) {
		$options = sema_set_defaults();
	}
	//$token=(strlen($options['sema_token'])<50)?"":$options['sema_token'];
	$token=$options['sema_token'];
	if(!empty($token) && strlen($token)<50) $token='';
	if($token==''){
	?>
	<input type="text" id="sema_username" value="<?php echo(esc_attr($options['sema_username'])); ?>">
		<input type="password"  id="sema_password" value="<?php echo(esc_attr($options['sema_password'])); ?>"> <button class="dba-search-btn" id="token">Generate Token</button><br />

	<small><em>Please enter your SEMA SDC username and password to generate a token, and then save the token.</em></small><br />
	<?php
	}
	?>
	<input type="hidden"  name="sema_settings[sema_user]" value="<?php echo(esc_attr($options['sema_user'])); ?>" id="sema_user"><br />
	<input type="text"  name="sema_settings[sema_token]" value="<?php echo(esc_attr($token)); ?>" id="sema_token" style="width: 500px;" <?=($token)?"readonly":""?>><br />
	<small><em><div id="passwordwrong-msg-div" style="display:none;color:red" >Username/password is wrong</div></em></small>
<script>
var showUpdate=<?php echo((!empty($token) && strlen($token)<50)?'true':'false'); ?>;
jQuery(function($){
	$('#dialog_upgrade').dialog({
		autoOpen: showUpdate,
		modal: true,
		height: 250,
		width: 800,
		title: "API upgrade from v1 to v2"
	});
	$("#token2").on("click",function(e) { 
		e.preventDefault();
		var user = $("#sema_username_pop" ).val().trim();  
		var pass = $("#sema_password_pop" ).val().trim();  
		if(user=='' || pass==''){
			$("#unavailable-msg-div").show();
			return;
		}
		jQuery.ajax({
			url : ajax_url,
			type : 'post',
			data : {
			   action : 'get_semadata',
			   type : 'token',
			   user : user,
			   pass: pass
				
			},
			success : function( data ) {
				var token = data;
				if(token != undefined && token.length != 0){
					location.reload(true);
				}else{
					$("#unavailable-msg-div").show();  
				}
			}
        });
    });	
});
</script>
	<?php
}


/**
 * Print 'SEMA aaia' setting.
 *
 * @return void
 */
function sema_aaia_render() {
	global $membership,$subscription;
	$options = get_option( 'sema_settings' );
	?>
	<input type="hidden"  name="sema_settings[rebuild_fitment_point]" value="<?php echo(esc_attr($options['rebuild_fitment_point'])); ?>" id="rebuild_fitment_point">
	<input type="hidden"  name="sema_settings[sema_pageid]" value="<?php echo(esc_attr($options['sema_pageid'])); ?>" id="sema_pageid">
	<input type="hidden"  name="sema_settings[category_exist]" value="<?php echo(esc_attr($options['category_exist'])); ?>" id="category_exist">
	<input type="text"  name="sema_settings[sema_aaia]" value="<?php echo(esc_attr($options['sema_aaia'])); ?>" id="sema_aaia" style="width: 500px;" <?=($membership!=10)?"readonly":'placeholder="BBWQ,BKJF"'?>><br />
	<small><!--<em>Please use comma delimted format for mulitple brand IDs. </em>--><em>You need to get approval from a brand owner first in <a href="https://apps.semadata.org/#ReceiverRequests" target="_blank">SEMA Data</a>.</em><br /></small>
	<?php
}

/**
 * Print 'Hide empty category' setting.
 *
 * @return void
 */
function sema_hide_empty_render() {
	$options = get_option( 'sema_settings' );
	if ( ! array_key_exists( 'sema_hide_empty', $options ) ) {// set default value here.
		$options['sema_hide_empty'] = '0';
	}
	if ( ! array_key_exists( 'sema_hide_productwoimage', $options ) ) {// set default value here.
		$options['sema_hide_productwoimage'] = '1';
	}

	?>
	<fieldset>
		<input type='hidden' value='0' name='sema_settings[sema_hide_empty]'>
		<input type="checkbox"  name="sema_settings[sema_hide_empty]" <?php echo(($options['sema_hide_empty'])?'checked':''); ?> >
		Hide empty categories.
	</fieldset>
	<fieldset>
		<input type='hidden' value='0' name='sema_settings[sema_hide_productwoimage]'>
		<input type="checkbox"  name="sema_settings[sema_hide_productwoimage]" <?php echo(($options['sema_hide_productwoimage'])?'checked':''); ?> >
		Hide products without images.
	</fieldset>
	<?php
}

/**
 * Print 'Hide empty category' setting.
 *
 * @return void
 */
function sema_hide_submodel_render() {
	$options = get_option( 'sema_settings' );
	if ( ! array_key_exists( 'sema_hide_submodel', $options ) ) {// set default value here.
		$options['sema_hide_submodel'] = '0';
	}
	?>
	<input type='hidden' value='0' name='sema_settings[sema_hide_submodel]'>
	<input type="checkbox"  name="sema_settings[sema_hide_submodel]" <?php echo(($options['sema_hide_submodel'])?'checked':''); ?> >
	Hide sub-model from search bar.
	<?php
}
/**
 * Print 'Show Engine Info' setting.
 *
 * @return void
 */
function sema_show_engine_render() {
	$options = get_option( 'sema_settings' );
	if ( ! array_key_exists( 'sema_show_engine', $options ) ) {// set default value here.
		$options['sema_show_engine'] = '0';
	}
	?>
	<input type='hidden' value='0' name='sema_settings[sema_show_engine]'>
	<input type="checkbox"  name="sema_settings[sema_show_engine]" <?php echo(($options['sema_show_engine'])?'checked':''); ?> >
	Show Engine Info
	<?php
}
/**
 * Print 'Hide Brand ID' setting.
 *
 * @return void
 */
function sema_hide_brandid_render() {
	$options = get_option( 'sema_settings' );
	if ( ! array_key_exists( 'sema_hide_brandid', $options ) ) {// set default value here.
		$options['sema_hide_brandid'] = '0';
	}
	?>
	<input type='hidden' value='0' name='sema_settings[sema_hide_brandid]'>
	<input type="checkbox"  name="sema_settings[sema_hide_brandid]" <?php echo(($options['sema_hide_brandid'])?'checked':''); ?> > Hide Brand ID
	<?php
}


/**
 * Print 'Mandatory update' setting.
 *
 * @return void
 */
function sema_product_update_render() {

	$options = get_option( 'sema_settings' );
	if ($options && ! array_key_exists( 'sema_hide_submodel', $options ) ) {// set default value here.
		$options['sema_hide_submodel'] = '0';
	}	
	if (!array_key_exists( 'sema_mandatory_update', $options ) || !is_array($options['sema_mandatory_update'])) {// set default value here.
		$options['sema_mandatory_update']=array();
	}
	$options['sema_mandatory_update']=array_merge(array('title'=>'','description'=>'','price'=>'','image'=>'','fitment'=>'','attribute'=>''),$options['sema_mandatory_update']);
	
	?>
	<input type='hidden' value='0' name='sema_settings[sema_mandatory_update]'>
	<table><tr>
		<td width="15%"><input type="checkbox"  name="sema_settings[sema_mandatory_update][title]"  <?php echo(($options['sema_mandatory_update']['title'])?'checked':''); ?>> Title</td>
		<td width="15%"><input type="checkbox"  name="sema_settings[sema_mandatory_update][description]"  <?php echo(($options['sema_mandatory_update']['description'])?'checked':''); ?>> Description</td>
		<td width="15%"><input type="checkbox"  name="sema_settings[sema_mandatory_update][price]"  <?php echo(($options['sema_mandatory_update']['price'])?'checked':''); ?>> Price</td>
		<td width="15%"><input type="checkbox"  name="sema_settings[sema_mandatory_update][image]"  <?php echo(($options['sema_mandatory_update']['image'])?'checked':''); ?>> Images</td>
		<td width="15%"><input type="checkbox"  name="sema_settings[sema_mandatory_update][fitment]"  <?php echo(($options['sema_mandatory_update']['fitment'])?'checked':''); ?>> Fitments</td>
		<td width="15%"><input type="checkbox"  name="sema_settings[sema_mandatory_update][attribute]"  <?php echo(($options['sema_mandatory_update']['attribute'])?'checked':''); ?>> Attributes</td>
	</tr></table>
	<small><em>Mandatory update will override product with the most updated product information that manufacturers provide.</em></small>
	<?php
}

/**
 * Print 'Currency' setting.
 *
 * @return void
 */
function sema_product_currency_render() {

	$options = get_option( 'sema_settings' );
	$sema_currency = (!array_key_exists( 'sema_product_currency', $options ))? 'USD':$options['sema_product_currency'];
	?>
	<select name="sema_settings[sema_product_currency]">
		<option value="USD"<?=$sema_currency == 'USD' ? ' selected="selected"' : '';?>>USD</option>
		<option value="CAD"<?=$sema_currency == 'CAD' ? ' selected="selected"' : '';?>>CAD</option>
		<option value="EUR"<?=$sema_currency == 'EUR' ? ' selected="selected"' : '';?>>EUR</option>
	</select>
	<?php
}
/**
 * Print 'Batch size' setting.
 *
 * @return void
 */
function sema_product_batch_render() {

	$options = get_option( 'sema_settings' );
	if ($options && ! array_key_exists( 'sema_batchsize', $options ) ) {// set default value here.
		$options['sema_batchsize'] = '10';
	}	

	?>
	<div><input type='number' min="1" max="10" step="1" size="2" value='<?php echo(esc_attr($options['sema_batchsize']));?>' name='sema_settings[sema_batchsize]'></div>
	<small><em>Batch size is the number of products that will be imported in each batch.</em></small>
	<?php
}