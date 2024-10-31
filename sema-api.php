<?php
/*
  Plugin Name: SEMA API
  Plugin URI: http://demo.semadata.org/how-to-install-and-set-up-the-plugin/
  Description: Via SEMA API, add auto parts search to your wordpress and import products to your WooCommerce Store.
  Author: SEMA Data
  Author URI: https://www.semadata.org/
  Version: 5.25
  Tested Up To: 6.5.2
  License:           GPLv3
  License URI:       https://www.gnu.org/licenses/gpl-3.0.html
  Text Domain: sema-api

 *
 * ███████╗███████╗███╗   ███╗ █████╗      ███████╗██████╗  
 * ██╔════╝██╔════╝████╗ ████║██╔══██╗     ██╔════╝██╔══██╗
 * ███████╗█████╗  ██╔████╔██║███████║     ███████╗██████╔╝
 * ╚════██║██╔══╝  ██║╚██╔╝██║██╔══██║     ╚════██║██╔═══██╗
 * ███████║███████╗██║ ╚═╝ ██║██║  ██║ ██╗ ███████║███████╔╝
 * ╚══════╝╚══════╝╚═╝     ╚═╝╚═╝  ╚═╝ ╚═╝ ╚══════╝╚══════╝
 * 
 */

//error_reporting(E_ERROR); 
//session_start(['read_and_close' => true]);
$api_url=($_SERVER['SERVER_NAME']=="sdcdemo.com")?"https://localhost":"https://demo.semadata.org/shopify";


if ( ! class_exists( 'SEMASearchPlugin' ) ) {
	function _sql( $sql ) {
		global $wpdb;
		$sql = str_replace(' wp_',' '.$wpdb->prefix,$sql);
		return $sql;
	}	
	/**
	 * Class SEMASearchPlugin
	 */
	class SEMASearchPlugin {
		/**
		 * Page ID being inserted.
		 *
		 * @var int
		 */
		protected $page_id;


		/**
		 * Constructor.
		 */
		public function __construct() {
			global $wpdb;
			// Include the code that generates the options page.
			require_once dirname( __FILE__ ) . '/options.php';
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			if(!is_admin()){
				//add_filter('posts_search', array( $this, 'product_search_sku'),20);
				// added by S+Even 3/24/2020
				add_filter( 'woocommerce_product_tabs', 'sema_new_product_tab' );
				function sema_new_product_tab( $tabs ) {
					// Adds the new tab
					$tabs['desc_tab'] = array(
					'title' => __( 'Applications', 'woocommerce' ),
					'priority' => 20,
					'callback' => 'sema_new_product_tab_application'
					);

					return $tabs;
				}
				
			}else{
				// todo, add fitment sheet to admin products tab
				//add_action( 'woocommerce_product_data_tabs', 'sema_product_tab' );
				//add_filter( 'woocommerce_product_data_panels', 'sema_product_content_fitment' ); 
				//add_action( 'woocommerce_process_product_meta', 'sema_save_fitments' );

				function sema_save_fitments( $post_id ){
					global $wpdb;
         			if (isset($_POST['sema_product_fitment']) && isset($_POST['sema_ymms_changed']) && $_POST['sema_ymms_changed'] == 1){
					  $fitments = sanitize_textarea_field(stripslashes($_POST['sema_product_fitment']));
					  $sku=$_POST['_sku'];
					  $brandid=explode('-',$sku,2)[0];
					  $arrFitments=explode(PHP_EOL,$fitments);
					  $values = array();
					  foreach($arrFitments as $fitment){
						$arrFitment=explode(',',$fitment);
						if(count($arrFitment)==4){
							$make=trim($arrFitment[0]);
							$model=trim($arrFitment[1]);
							$submodel=trim($arrFitment[2]);
							$years=trim($arrFitment[3]);
							$arrYears=explode(' ',$years);
							foreach($arrYears as $year){
								//$values[] = "('$brandid','$sku',$year,'$make','$model','$submodel',$post_id)";
								//remove duplicated ones
								$values["$year,$make,$model,$submodel"]="('$brandid','$sku',$year,'$make','$model','$submodel',$post_id)";
							}
						}
					  }
					  $wpdb->query(_sql("DELETE FROM wp_sema_parts WHERE productid='$post_id' "));
					  $chunks=array_chunk($values, 50);
					  foreach($chunks as $chunk){
						$vals=implode(',', $chunk);
						$sql="INSERT INTO wp_sema_parts(brandid,partno,year,make,model,submodel,productid) VALUES $vals; " ;
						$wpdb->query(_sql($sql));
					}
				  	
                
					}    
				}
				
				function sema_product_tab( $array ) { 
					// make filter magic happen here... 
					$array['semafitments']=array('label'=>"SEMA Fitments",
						'target'=>"sema_product_data_fitments",
						'class'=>array(),
						'priority'=>200);
					return $array; 
				}; 
				/**
				 * Contents of the gift card options product tab.
				 */
				function sema_product_content_fitment() {

					global $post;
					
					global $wpdb;
					$product=wc_get_product($post->ID);
					$sku=$product->get_sku();
					$sku=explode('-',$sku,2);
					$brandid=$wpdb->get_var($wpdb->prepare(_sql("select meta_value from wp_postmeta where post_id=%d and meta_key='_brandid'"),$product->id));
					$sku=$brandid.'-'.$sku[1];
					$YMMS = $wpdb->get_var($wpdb->prepare(_sql("select GROUP_CONCAT(line SEPARATOR '\r\n') from (
						select productid,concat(make,',',model,',',submodel,',',GROUP_CONCAT(year order by year SEPARATOR ' ')) as line from wp_sema_parts
											where partno=%s AND make<>'' group by make,model,submodel order by make,model,submodel ) x group by productid"),$sku));
					$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
?>
	<div id="sema_product_data_fitments" class="panel woocommerce_options_panel hidden" >
      <p class="form-field sema_product_fitment_field ">
		<label for="sema_product_fitment">Format instructions</label><span class="woocommerce-help-tip"></span><textarea class="short" style="height: 17.5em;" name="sema_product_fitment" id="sema_product_fitment" placeholder="" rows="2" cols="20"><?=$YMMS?></textarea> </p>       
        <?php if(empty($YMMS)){?><a href="#" id="ymm_sample">Click here to fill the field with sample fitments</a><?php }?>
            <input type="hidden" id="sema_ymms_changed" name="sema_ymms_changed" value="0">     
      <div class="ymm-selector-container">
        <p class="form-field">
          <input type="text" name="partfinder_search_field" id="ymm_search_field" class="short ymm-search-field" value="" placeholder=" search format: make,model,submodel">
          <button type="button" class="button ymm-search-button">Search</button>
        </p>
        <div id="ymm_not_found" style="display:none">No matches found.</div>
        <p class="form-field">            
          <select class="ymm-result-select" size="10" multiple="multiple" disabled="disabled"></select>
          <br>
          <button type="button" class="button ymm-add-button" disabled="disabled">Add to fitments</button>			     
        </p>
      </div>       
    </div>      
      <script type="text/javascript">
var ajax_url='<?=esc_url($ajax_url)?>';		
(function ($) {
	"use strict";
  
	$.widget("sema.productFitments", { 
  
	   
	  _create : function () {
  
		$.extend(this, this.options);
		
		this.fitmentArea = $('#sema_product_fitment');
		this.searchField = $('#ymm_search_field');    		
		this.notFoundMessage = $('#ymm_not_found')
		this.resultSelect = $('.ymm-result-select');    
		this.addButton = $('.ymm-add-button');
		
		this.replaceToolTip(); //display wider toolTip message
  
		this._on({ 
		  "keypress #ymm_search_field": $.proxy(this.preventSubmit, this), //prevent submitting product when user clicks Enter in the search field                     
		  "click button.ymm-search-button": $.proxy(this.searchValues, this),   
		  "click button.ymm-add-button": $.proxy(this.addValue, this)                                                        
		});         
				
	  },
	
	
	  replaceToolTip : function(){
		this.element.find('.woocommerce-help-tip').replaceWith('<span class="woocommerce-help-tip"></span>');
		this.element.find('.woocommerce-help-tip').tipTip({
		  fadeIn:50,
		  fadeOut:50,
		  delay:200,
		  enter:function(){
			$(this.tiptip_holder).css("maxWidth","400px")
			$(this.tiptip_content).css("maxWidth","400px");        
		  },
		  exit:function(){
			$(this.tiptip_holder).fadeOut(0).css("maxWidth", this.maxWidth);      
			$(this.tiptip_content).css("maxWidth", '');          
		  },
		  content:this.toolTipMessage      
		})
	  },
  
	
	  preventSubmit : function(event){
		if (event.keyCode == 13) {//user clicks Enter after typing a search word
		  event.preventDefault();
		  this.searchValues();
		  return false;          
		}
	  },
	  
		
	  searchValues : function(){
  
		this.clearResult();
		  
		var keyword = this.searchField.val();
		if (keyword != '')
		  this._load(keyword);
	  },
  
  
	  clearResult : function(){  
		this.resultSelect[0].options.length = 0;  
		this.notFoundMessage.hide();
		this.disableAddButton();              
	  },
  
  
	  _load : function(keyword){
  
		var widget = this;
		$.ajax({
			type: 'GET',
			url: ajax_url,
			async: true,
			data: {action:'get_semadata',type:'searchmms', keyword : keyword},
			dataType: 'json'
		}).done(
			function (data) {
			  if (!data.error){                         
				widget.showResult(data);             
			  }  
			}
		  );      
	  },
  
	
	  showResult : function(options){
		var option, noSpaceRs;
	  
		if (options.length){
	  
		  var l = options.length;
  
		  var addedValues = [];
		  
		  var value = this.fitmentArea.val().split(' ').join('');        
		  if (value){
			addedValues = value.split("\n");          
		  }
		   
		  this.resultTitles = {};
		  
		  var ind = 0;    
		  for (var i=0;i<l;i++){
			option = options[i];
			
			noSpaceRs = option.split(' ').join('');      
			if (addedValues.indexOf(noSpaceRs) != -1){//skip values that already exist in the fitment text area
			  continue;
			}       
			   
			this.resultSelect[0].options[ind] = new Option(option, option);
			this.resultSelect[0].options[ind].selected = true;
			this.resultTitles[option] = option;
			
			ind++;        
		  }
		  
		  this.enableAddButton();      
		} else {   
		  this.disableAddButton(); 
		  this.notFoundMessage.show();      
		}       
	  },
  
	
	  disableAddButton : function(){
		this.resultSelect[0].disabled = true;
		this.addButton[0].disabled = true;      
		this.addButton.addClass('disabled');    	  	
	  },
	
	  enableAddButton : function(){
		this.resultSelect[0].disabled = false;    
		this.addButton[0].disabled = false;      
		this.addButton.removeClass('disabled');          	  	
	  },
  
	  
	  addValue : function(){
		
		var values = [];
		
		var fitments = this.fitmentArea.val();        
		if (fitments){
		  values = fitments.split("\n").filter(String);          
		}
					
		var options = this.resultSelect.val();
		if (options){    
		  var l = options.length;	  
		  for (var i=0;i<l;i++){
			values.push(options[i]);
		  }
		  this.resultSelect.find("option:selected").remove();        
		  values.sort();
		}
  
		this.fitmentArea.val(values.join("\n")).change();
	  }
	  
		  
	}); 

	$('#sema_product_fitment').change(function(){jQuery('#sema_ymms_changed').val(1)});
	$('#ymm_sample').click(function(){
		var sample =
		"Dodge,Ram 1500,BASE,2009 2010\r\n"+
		"Dodge,Ram 1500,Laramie,2009 2010\r\n"+
		"Dodge,Ram 1500,SLT,2009 2010\r\n"+
		"Ford,F-150,FX4,2009 2010 2011 2012 2013 2014 \r\n";       
		$('#sema_product_fitment').val(sample).change();
		return false;
	});
	$('#sema_product_data_fitments').productFitments({
		ajaxUrl : "http://sdcdemo.com/wp-admin/admin-ajax.php",
		toolTipMessage : "Please make sure each line contains the fields: make, model, submodel, and years, separated by commas. In case a submodel is not specified, please use BASE as the submodel. Example:<br> Dodge,Ram 1500,BASE,2009 2010<br> Dodge,Ram 1500,Laramie,2009 2010<br>In search field, you may use make, model or submodel. Example:<br>Ford<br>Ford,F-150"
	});   	


  })(jQuery);
  



      </script>
				
            
					<?php


				}
			}
			// default group_concat max lengh is 1024
			$wpdb->query(_sql("SET @@group_concat_max_len = 10240;"));
			//session_start();
			//if (!isset($_SESSION)) {
			//	session_start(['read_and_close' => true]);
			//}
		}

		/*						   
		function product_search_sku($where) {
			global $pagenow, $wpdb, $wp,$wp_query;
			error_reporting(E_ERROR | E_PARSE); 
			//var_dump(in_array('product', $wp->query_vars['post_type']));
			
			if ((is_admin() && 'edit.php' != $pagenow) 
            || !is_search()  
            || !isset($wp->query_vars['s']) 
            //post_types can also be arrays..
            || (isset($wp->query_vars['post_type']) && 'product' != $wp->query_vars['post_type'])
            || (isset($wp->query_vars['post_type']) && is_array($wp->query_vars['post_type']) && !in_array('product', $wp->query_vars['post_type']) ) 
            ) {
				return $where;
			}

			$search_ids = array();
			$terms = explode(',', $wp->query_vars['s']);
		
			foreach ($terms as $term) {
				//Include the search by id if admin area.
				if (is_admin() && is_numeric($term)) {
					$search_ids[] = $term;
				}
				//Search for a regular product that matches the sku.
				$sku_to_id = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value LIKE '%%%s%%';", wc_clean($term)));
		
				$search_ids = array_merge($search_ids, $sku_to_id);
			}
		
			$search_ids = array_filter(array_map('absint', $search_ids));
		
			if (sizeof($search_ids) > 0) {
				$where = str_replace(')))', ") OR ({$wpdb->posts}.ID IN (" . implode(',', $search_ids) . "))))", $where);
			}
			
			//remove_filters_for_anonymous_class('posts_search', 'WC_Admin_Post_Types', 'product_search', 10);
			return $where;
		}*/
		
		
		/**
		 * Getter for page_id.
		 *
		 * @return int Page ID being inserted.
		 */
		public function get_page_id() {
			return $this->page_id;
		}


		/**
		 * Setter for page_id.
		 *
		 * @param int $id Page ID being inserted.
		 */
		public function set_page_id( $id ) {
			$this->page_id = $id;

			return $this->page_id;
		}


		/**
		 * Action hook: WordPress 'init'.
		 *
		 * @return void
		 */
		public function insert_pages_init() {
			// Register the [insert] shortcode.
			wp_enqueue_script('sema-catalog', plugins_url( '/js/semacatalog_eager.js?v=4', __FILE__ ),array('jquery'));
			wp_enqueue_script('sema-pagination', plugins_url( '/js/pagination.js', __FILE__ ));
			wp_enqueue_script('sema-jstree', plugins_url( '/js/jstree.js', __FILE__ ));
			wp_enqueue_style('sema-semasearch', plugins_url('/css/semasearch.css', __FILE__));
			wp_enqueue_style('sema-jstree', plugins_url('/css/jtree.css', __FILE__));
			wp_enqueue_style('sema-pagination', plugins_url('/css/pagination.css', __FILE__));

			add_shortcode( 'semasearch', array( $this, 'shortcode_handle_semasearch' ) );
			add_shortcode( 'semasearchbar', array( $this, 'shortcode_handle_semasearchbar' ) );
			
			add_filter("query_vars", function( $vars ){
				$vars = array_merge($vars,array('yearselect','makeselect','modelselect','submodelselect'));
				/*
				$vars[] = 'yearselect';
				$vars[] = 'makeselect';
				$vars[] = 'modelselect';
				$vars[] = 'submodelselect';*/
				return $vars;
			});
		}


		/**
		 * Action hook: WordPress 'admin_init'.
		 *
		 * @return void
		 */
		public function insert_pages_admin_init() {
			global $jal_db_version;
			// if( ! function_exists('get_plugin_data') ){
			// 	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			// }
			// $plugin_data = get_plugin_data( __FILE__ );			
			// $plugin_version = $plugin_data['Version'];
			//$plugin_dir_base= str_replace("sema-api/",'', plugin_dir_url(__FILE__ ));

			// Get options set in WordPress dashboard (Settings > Insert Pages).
			wp_enqueue_script('sema-js-backend', plugins_url( '/js/semasearch-backend.js', __FILE__ ),array('jquery'));
			//wp_enqueue_script('sema-js-jstree', plugins_url( '/js/jstree.js', __FILE__ ));
			//wp_enqueue_script('sema-js-pagination', plugins_url( '/js/pagination.js', __FILE__ ));
			//wp_enqueue_script('sema-js-chosen', plugins_url( '/js/chosen.jquery.js', __FILE__ ));
			wp_enqueue_style('sema-css-jstree', plugins_url('/css/jtree.css', __FILE__));
			wp_enqueue_style('sema-css-chosen', plugins_url('/css/chosen.css', __FILE__));
			wp_enqueue_style('sema-css-fontawesome', plugins_url('/css/fontawesome.css', __FILE__));
			wp_enqueue_style('sema-css-pagination', plugins_url('/css/pagination.css', __FILE__));
			wp_enqueue_style('sema-css-main', plugins_url('/css/main.css', __FILE__));
			wp_enqueue_style('sema-css-woocommerce', plugins_url('/woocommerce/assets/css/admin.css'));
			

			//if ( (isset($_GET['import']) && $_GET['import'] == 'product_import') || (isset($_GET['page']) && $_GET['page'] == 'product_import')) {
				//wp_enqueue_style('sema-css-productimport', plugins_url('/css/wf-style.css', __FILE__));
			//}

		}

		public function sema_delete_post($post_id){
			global $wpdb;
			$sku = $wpdb->get_var($wpdb->prepare(_sql("SELECT psku.meta_value FROM wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku' 
			WHERE p.post_type='product' and p.id=%d"),$post_id));
			if($sku){
				//$wpdb->query(_sql("DELETE FROM wp_sema_parts WHERE partno='$sku';"));
				$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_parts SET productid=null WHERE productid=%d;"),$post_id));
			}
		
		}

		/**
		 * Shortcode hook: Replace the [insert ...] shortcode with the inserted page's content.
		 *
		 * @param  array  $atts    Shortcode attributes.
		 * @param  string $content Content to replace shortcode.
		 * @return string          Content to replace shortcode.
		 */
		public function shortcode_handle_semasearchbar( $atts, $content = null ) {
			global $wpdb;
			$_wp=$wpdb->prefix;
			$url='catalog-search';$hide_submodel=0;$where='';
			//if(array_key_exists('url',$atts)) $url=$atts['url'];
			$options = get_option( 'sema_settings' );
			if ( array_key_exists( 'sema_hide_submodel', $options ) && $options['sema_hide_submodel']) $hide_submodel=1;
			$siteurl = preg_replace( '|https?://[^/]+(/.*)|i', '$1',get_option( 'siteurl' ));
			//$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
			$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
			?>
<script>var ajax_url='<?php echo(esc_url($ajax_url)); ?>';</script>
<div class="sema-search-bar">
	<form action="/<?php echo($url); ?>/" method="POST" id="form-searchbar"><div>
		<!--<input type="text" id="keyword" name="keyword" class="catalog-input">&nbsp; -->
		<select id="year-select" name="yearselect" class="catalog-select"><option value="">- Year -</option></select>
		<select id="make-select" name="makeselect" class="catalog-select"><option value="">- Make -</option></select>
		<select id="model-select" name="modelselect" class="catalog-select"><option value="">- Model -</option></select>
		<select id="sub-model-select" name="submodelselect" class="catalog-select"><option value="">- Sub-Model -</option></select>
		<button class="dba-search-btn" id="search">search</button>
		<button class="dba-search-btn" id="reset">reset</button>
	</div></form>
</div>
<script type='text/javascript'>
/* <![CDATA[ */
var year='<?php echo(esc_attr($year));?>';
var make='<?php echo(esc_attr($make));?>';
var model='<?php echo(esc_attr($model));?>';
var submodel='<?php echo(esc_attr($submodel));?>';

<?php if($hide_submodel) echo("$('#sub-model-select').hide();");?>
//search products from given year/brand/model/submodel
jQuery(document).ready(function ($) {
	jQuery(document).on('click', '#search', function (e) {
		e.preventDefault();
		var yearselected = $( "#year-select option:selected" ).val();
		if(yearselected){
			$("#form-searchbar").submit();
		}

	});
});
/* ]]> */
</script>
			<?php

		}


		/**
		 * Shortcode hook: Replace the [insert ...] shortcode with the inserted page's content.
		 *
		 * @param  array  $atts    Shortcode attributes.
		 * @param  string $content Content to replace shortcode.
		 * @return string          Content to replace shortcode.
		 */
		public function shortcode_handle_semasearch( $atts, $content = null ) {
			global $wpdb;
			$_wp=$wpdb->prefix;
			$hide_empty=0;$hide_submodel=0;$where='';
			session_start();
			
			$preset_year = sanitize_text_field( get_query_var( 'yearselect' ) );
			$preset_make = sanitize_text_field( get_query_var( 'makeselect' ) );
			$preset_model = sanitize_text_field( get_query_var( 'modelselect' ) );
			$preset_submodel = sanitize_text_field( get_query_var( 'submodelselect' ) );
			if($preset_year){
				unset($_SESSION['productbycat']);
				$_SESSION['productbycat']['year']=$preset_year;
				$year=$preset_year;
				$_SESSION['productbycat']['make']='';
				$_SESSION['productbycat']['model']='';
				$_SESSION['productbycat']['submodel']='';
			}else $year=sanitize_text_field($_SESSION['productbycat']['year']);
			if($preset_make){
				$_SESSION['productbycat']['make']=$preset_make;
				$make=$preset_make;
			}else $make=sanitize_text_field($_SESSION['productbycat']['make']);
			if($preset_model){
				$_SESSION['productbycat']['model']=$preset_model;
				$model=$preset_model;
			}else $model=sanitize_text_field($_SESSION['productbycat']['model']);
			if($preset_submodel){
				$_SESSION['productbycat']['submodel']=$preset_submodel;
				$submodel=$preset_submodel;
			}else $submodel=sanitize_text_field($_SESSION['productbycat']['submodel']);
			$textsearchkeyword=sanitize_text_field($_SESSION['productbycat']['keyword']);

			$options = get_option( 'sema_settings' );
			if ( array_key_exists( 'sema_hide_empty', $options ) && $options['sema_hide_empty']) $hide_empty=1;
			if ( array_key_exists( 'sema_hide_submodel', $options ) && $options['sema_hide_submodel']) $hide_submodel=1;
			//if($hide_empty) $where.=" AND ttm.meta_value>0";
			if($hide_empty) $where.=" AND ttx.count>0";
			$siteid=$options['siteid'];
			

			$arrReturn = $wpdb->get_results(_sql("SELECT t.name,t.term_id as id,tt.name AS kname,tt.term_id AS kid,ttt.name AS kkname,ttt.term_id AS kkid FROM wp_terms t INNER JOIN wp_term_taxonomy x ON t.term_id = x.term_id 
			INNER JOIN wp_term_taxonomy tx ON tx.parent=t.term_id INNER JOIN wp_terms tt ON tt.term_id=tx.term_id
			LEFT JOIN wp_term_taxonomy ttx ON ttx.parent=tt.term_id LEFT JOIN wp_terms ttt ON ttt.term_id=ttx.term_id
			WHERE t.term_group>0 AND x.parent=0 $where ORDER BY t.name,kname,kkname"),ARRAY_A );

			$node=array();
			$uniqueids=array();
			$count=count($arrReturn);
			$node=$p=$k=$kk=array();
			for($i=0;$i<$count;$i++){
				$r=$arrReturn[$i];
				if($r['kkid']){
					if($i==0 || $r['id']!=$arrReturn[$i-1]['id']) $p=array("id"=>'C'.$r['id'],"text"=>$r['name']);
					if($i==0 || $r['kid']!=$arrReturn[$i-1]['kid']) $k=array("id"=>'S'.$r['kid'],"text"=>$r['kname']);
					$kk=array("id"=>$r['kkid'],"text"=>$r['kkname']);
					$k['children'][]=$kk;
				}else{
					if($i==0 || $r['id']!=$arrReturn[$i-1]['id']) $p=array("id"=>'S'.$r['id'],"text"=>$r['name']);
					if($i==0 || $r['kid']!=$arrReturn[$i-1]['kid']) $k=array("id"=>$r['kid'],"text"=>$r['kname']);
				}
				if($i==$count-1 || $r['kid']!=$arrReturn[$i+1]['kid']){
					$p['children'][]=$k;
					$k=array();
				}
				if($i==$count-1 || $r['id']!=$arrReturn[$i+1]['id']){
					$node[]=$p;
					$p=array();
				}
			}
			$node = json_encode($node,true);

			//$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
			$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
			//$ajax_url="https://demo.semadata.org/shopify/ajax.php?siteid=$siteid&";
			//$ajax_url="https://localhost/ajax.php?siteid=$siteid&";
			?>
<script>var ajax_url='<?php echo(esc_url($ajax_url)); ?>';</script>
<div class="sema-content">
	<div>
		<select id="year-select" name="yearselect" class="catalog-select"><option value="">- Year -</option></select>
		<select id="make-select" name="makeselect" class="catalog-select"><option value="">- Make -</option></select>
		<select id="model-select" name="modelselect" class="catalog-select"><option value="">- Model -</option></select>
		<select id="sub-model-select" name="submodelselect" class="catalog-select"><option value="">- Sub-Model -</option></select>
		<button class="dba-search-btn" id="search">search</button>
		<button class="dba-search-btn" id="reset">reset</button>
	</div>
	<div id="container1">
	<div id="col1">
		<div class="sema-search-container">
		<input type="text" class="sema-search-box" id="sema-text-search-input" placeholder="Search For a Product..">
		<button class="sema-search-button" id="sema-text-search-button" >Go</button>
		</div>
		<!--<div class="col-header">Category</div>-->
		<div id="tree">&nbsp;</div>

	<div id="product-filter-list" class="product-filter-list">
	</div>



	</div>
	<div id="col2">
		<div id="search-main">
			<div class="col-header">Products <div class="search-title-text"></div></div>
			<div id="search-title-div">
				<div id="" class="ags-breadcrumb"><span class="ags_sticky_link_label">YOUR SELECTION: </span></div>
            	<div id="filter_category" class="ags-breadcrumb" style="display:none;">
					<span class="ags_sticky_link_label">Category: </span>
					<span id="filter_category_value" class="ags_sticky_link_value"></span>
					<a class="ags-sticky-remove">[x]</a>
				</div>
            	<div id="filter_keyword" class="ags-breadcrumb" style="display:none;">
					<span class="ags_sticky_link_label">Keyword: </span>
					<span id="filter_keyword_value" class="ags_sticky_link_value"></span>
					<a class="ags-sticky-remove">[x]</a>
				</div>
            	<div id="filter_YMM" class="ags-breadcrumb" style="display:none;">
					<span class="ags_sticky_link_label">Year/Make/Model: </span>
					<span id="filter_YMM_value" class="ags_sticky_link_value"></span>
					<a class="ags-sticky-remove" id="gs_sticky_ymm">[x]</a>
				</div>
			</div>
			<div id="loader-div-out"></div>
		</div>
		<div id="pagination-product"></div>
	</div>
</div>
<script type='text/javascript'>
/* <![CDATA[ */
var treedata=<?php echo($node);?>;
var year='<?php echo(esc_attr($year));?>';
var make='<?php echo(esc_attr($make));?>';
var model='<?php echo(esc_attr($model));?>';
var submodel='<?php echo(esc_attr($submodel));?>';
var textsearchkeyword='<?php echo(esc_attr($textsearchkeyword));?>';

<?php if($hide_submodel) echo("$('#sub-model-select').hide();");?>

/* ]]> */
</script>
			<?php

		}
	}
}

// Initialize SEMASearchPlugin object.
if ( class_exists( 'SEMASearchPlugin' ) ) {
	$insert_pages_plugin = new SEMASearchPlugin();
}


function sema_new_product_tab_application( $slug, $tab ) {
	global $wpdb,$product;
	$options = get_option( 'sema_settings' );
	$siteid=$options['siteid'];
	if($siteid && $product->id){
		$url="https://demo.semadata.org/shopify/ajax.php?type=wp_get_product_fitment&siteid=$siteid&productid=".$product->id."&sema_token=".urlencode($token);
		$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
		if(!is_wp_error( $response )){
			$response=json_decode($response['body'],true);
			$fitment_html=$response['Fitment'];
			echo($fitment_html);
		}
	}
}


// Actions and Filters handled by SEMASearchPlugin class.
if ( isset( $insert_pages_plugin ) ) {
	// Get options set in WordPress dashboard (Settings > Insert Pages).
	$options = get_option( 'sema_settings' );
	if ( false === $options || empty($options) ) {
		$options = array();
	}
	add_action( 'admin_head', array( $insert_pages_plugin, 'insert_pages_admin_init' ), 0 );
	

	// add REST API trigger
	/*
	add_action( 'rest_api_init', function () {
		register_rest_route( 'semarestservice', '/rest_endpoint', array(
				'methods' => 'GET', 
				'callback' => 'sema_rest_call' 
		) );
	});
	
	function sema_rest_call($data) {
		global $wpdb;
		$type = $data->get_param( 'type' );
		$keyword = $data->get_param( 'keyword' );
		if($type=='suggestmms'){
		}elseif($type=='suggestyears'){

		}
		return $response;
	
	}*/

	// Register shortcode [insert ...] only for front end.
	if ( !is_admin() ) add_action( 'init', array( $insert_pages_plugin, 'insert_pages_init' ), 1 );
	// register shortcode to product page

	// added by Steven 1/10/2020
	//add_action( 'woocommerce_before_single_product', array( $insert_pages_plugin, 'before_product_page'), 15 );

	// Register shortcode [insert ...] when TinyMCE is included in a frontend ACF form.
	//add_action( 'acf_head-input', array( $insert_pages_plugin, 'insert_pages_init' ), 1 ); // ACF 3.
	//add_action( 'acf/input/admin_head', array( $insert_pages_plugin, 'insert_pages_init' ), 1 ); // ACF 4.

	// Add TinyMCE button for shortcode.
	// disabled by Steven 4/24/2020
	add_action('before_delete_post', array( $insert_pages_plugin, 'sema_delete_post' ));

	// Ajax: get year list from SEMA.
	// added by Steven Bao 8/27/2019
	add_action( 'wp_ajax_get_semadata','sema_getdata_callback',10,1);
	add_action( 'wp_ajax_nopriv_get_semadata','sema_getdata_callback',10,1);

	//add_action( 'plugins_loaded', 'sema_plugin_updated' );
	function sema_plugin_updated() {
		global $wpdb;


		/*
		$brandtable =$wpdb->get_row(_sql("SHOW TABLES LIKE 'wp_sema_brands';") );
		if($brandtable==null){
			sema_create_tables();
		}*/
		/*
		$row2 = $wpdb->get_row(_sql("SHOW COLUMNS FROM wp_sema_brands LIKE 'pricetoimport';") );
		if(!$row2){
			$wpdb->query(_sql("ALTER TABLE wp_sema_brands ADD COLUMN pricetoimport varchar(10) AFTER priceadjustment") );
		}*/
		/*
		if(in_array($_REQUEST['page'],["sema_import","sema_setting"])){
			$row2 = $wpdb->get_row(_sql("SHOW COLUMNS FROM wp_sema_brands LIKE 'options'") );
			if(empty($row2)){
				$wpdb->query(_sql("ALTER TABLE wp_sema_brands ADD COLUMN options varchar(5000) NULL DEFAULT NULL,ADD COLUMN check_date datetime NULL DEFAULT NULL,ADD COLUMN updates mediumint(9) NULL DEFAULT 0,ADD COLUMN updates_info varchar(500) NULL DEFAULT NULL,ADD COLUMN finishedproducts mediumint(9) NULL DEFAULT NULL ;") );
			}	
		}else{
			
		}*/
	

		//update_option( 'sema_plugin_version', '3.52' );
	}

	/**
	 * Register TinyMCE plugin for the toolbar button if in compatibility mode.
	 * (to work around a SiteOrigin PageBuilder bug).
	 *
	 * @see  https://wordpress.org/support/topic/button-in-the-toolbar-of-tinymce-disappear-conflict-page-builder/
	 */
	/*
	if ( array_key_exists('sema_tinymce_filter',$options) &&  'compatibility' === $options['sema_tinymce_filter'] ) {
		add_filter( 'mce_external_plugins', array( $insert_pages_plugin, 'insert_pages_handle_filter_mce_external_plugins' ) );
		add_filter( 'mce_buttons', array( $insert_pages_plugin, 'insert_pages_handle_filter_mce_buttons' ) );
	}*/

	register_activation_hook( __FILE__, 'sema_activation' );
	register_uninstall_hook( __FILE__, 'sema_uninstall' );
	function sema_uninstall() {
		global $woocommerce, $wpdb;
		//require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name='catalog-search' ");
		wp_delete_post($id);
		$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix."sema_products;");
		$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix."sema_fitment;");
		$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix."sema_brands;");
		$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix."sema_attr_taxonomy;");

		delete_option( 'sema_settings' );
	}
	function sema_activation($networkwide){
		global $woocommerce, $wpdb, $jal_db_version;
		if (is_multisite() && $networkwide) 
		wp_die('This plugin can\'t be activated networkwide. Please activate it in each site');
	
		$sema_plugin_data = get_plugin_data( __FILE__ );
		$sema_plugin_version = $sema_plugin_data['Version'];
		update_option( 'sema_plugin_version', $sema_plugin_version );

		// check if multisite
		$site_id=get_current_blog_id();

		// Create Catalog Search Page
		$my_post = array(
		  'post_title'    => 'Catalog Search',
		  'post_content'  => '[semasearch]',
		  'post_status'   => 'publish',
		  'post_author'   => 1,
		  'post_type' => 'page',
		  'post_excerpt' => ''
		  //'post_category' => array( 8,39 )
		);
		 
		// Insert the page into the database
		$post_id=wp_insert_post( $my_post);
		update_post_meta($post_id,'_wp_page_template','template-fullwidth.php');
		$options = get_option('sema_settings');
		if(!is_array($options)) $options=array();
		$options['sema_pageid']=$post_id;
		$options['site_id']=$site_id;
		update_option('sema_settings',$options );

		$options = get_option( 'sema_settings' );
		$token=$options['sema_token'];
		$aaia=$options['sema_aaia'];
	
		sema_create_tables();

	}

	function sema_create_tables(){
		global $wpdb;
		//$charset_collate = $wpdb->get_charset_collate();
		$charset_collate = " DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
		// create SEMA tables
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
		$table_name = $wpdb->prefix . 'sema_brands';
		$sql = "CREATE TABLE $table_name (
  			brandid varchar(5) NOT NULL,
			brandname varchar(50) NOT NULL,
			selectednodes TEXT NOT NULL,
			undeterminednodes varchar(1000) NOT NULL,
			termids TEXT NOT NULL,
			active mediumint(9) NOT NULL,
			importedproducts mediumint(9) NOT NULL,
			currentpid mediumint(9) NOT NULL,
			totalproducts mediumint(9) NOT NULL,
			unimportedproducts mediumint(9) NOT NULL,
			unimported_reason TEXT NOT NULL,
			priceadjustment mediumint(9),
			pricetoimport varchar(10) NULL DEFAULT NULL,
			prefix varchar(10) DEFAULT NULL,
			importdate datetime DEFAULT NULL,
			options varchar(5000) NULL DEFAULT NULL,
			check_date datetime NULL,
			updates mediumint(9) NULL,
			updates_info varchar(500) NULL,
			finishedproducts mediumint(9) NULL,
			PRIMARY KEY  (brandid)
		) $charset_collate;";
		dbDelta("DROP TABLE IF EXISTS $table_name;");
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'sema_attr_taxonomy';
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			product_id mediumint(9) NOT NULL,
			term_id mediumint(9) NOT NULL,
			attr_name varchar(50) NOT NULL,
			attr_value varchar(50) NOT NULL,
			`status` tinyint(4) NULL DEFAULT 1,
			PRIMARY KEY  (id),
			index attr_name_value(attr_name,attr_value)
		) $charset_collate;";
		dbDelta("DROP TABLE IF EXISTS $table_name;");
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'sema_fitment';
		$sql = "CREATE TABLE $table_name (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`make` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`submodel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`year` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
			`products` int(11) DEFAULT 0,
			`custom` int(10) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			UNIQUE KEY `unique` (`make`,`model`,`submodel`,`year`(100)) USING BTREE
		  ) $charset_collate;";
		dbDelta("DROP TABLE IF EXISTS $table_name;");
		dbDelta( $sql );
		$sema_plugin_data = get_plugin_data( __FILE__ );
		$sema_plugin_version = $sema_plugin_data['Version'];
		update_option( 'sema_plugin_version', $sema_plugin_version );

		$table_name = $wpdb->prefix . 'sema_products';
		$sql = "CREATE TABLE $table_name (
			`productid` int(11) NOT NULL,
			`brandid` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`partno` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`status` int(11) DEFAULT NULL,
			`wpid` int(11) DEFAULT NULL,
			PRIMARY KEY (`productid`),
			KEY `idx_wpid` (`wpid`) USING BTREE,
			KEY `idx_brandid` (`brandid`) USING BTREE,
  			KEY `idx_brandidpartno` (`brandid`,`partno`) USING BTREE
		  ) $charset_collate;";
		dbDelta("DROP TABLE IF EXISTS $table_name;");
		dbDelta( $sql );
		$sema_plugin_data = get_plugin_data( __FILE__ );
		$sema_plugin_version = $sema_plugin_data['Version'];
		update_option( 'sema_plugin_version', $sema_plugin_version );
		
	}
	function sema_getdata_callback($type){
		global $wpdb,$api_url;
		
		$options = get_option( 'sema_settings' );
		$token=$options['sema_token'];
		$aaia=$options['sema_aaia'];
		$siteid=$options['siteid'];
		//if(isset($options['category_exist']) && $options['category_exist']=='1') $category_exist=1;
		$category_exist=1;
		$aaia_str='';
		$aaias = array_unique(array_filter(array_map('trim', explode(',', $aaia))));
		for($i=0;$i<count($aaias);$i++){
			if($i<5){// limit number of brand id to max 5
				$aaia_str.="&aaia_brandids=".$aaias[$i];
			}
		}



		if($_GET && $_GET['type']=='category'){
			$arrReturn = $wpdb->get_results(_sql("SELECT t.name,t.term_id as id,tt.name AS kname,tt.term_id AS kid,ttt.name AS kkname,ttt.term_id AS kkid FROM wp_terms t INNER JOIN wp_term_taxonomy x ON t.term_id = x.term_id 
			INNER JOIN wp_term_taxonomy tx ON tx.parent=t.term_id INNER JOIN wp_terms tt ON tt.term_id=tx.term_id
			LEFT JOIN wp_term_taxonomy ttx ON ttx.parent=tt.term_id LEFT JOIN wp_terms ttt ON ttt.term_id=ttx.term_id
			WHERE t.term_group>0 AND x.parent=0",''),ARRAY_A );
			$node=array();
			$uniqueids=array();
			$count=count($arrReturn);
			$node=$p=$k=$kk=array();
			for($i=0;$i<$count;$i++){
				$r=$arrReturn[$i];
				if($r['kkid']){
					if($i==0 || $r['id']!=$arrReturn[$i-1]['id']) $p=array("id"=>'C'.$r['id'],"text"=>$r['name']);
					if($i==0 || $r['kid']!=$arrReturn[$i-1]['kid']) $k=array("id"=>'S'.$r['kid'],"text"=>$r['kname']);
					$kk=array("id"=>$r['kkid'],"text"=>$r['kkname']);
					$k['children'][]=$kk;
				}else{
					if($i==0 || $r['id']!=$arrReturn[$i-1]['id']) $p=array("id"=>'S'.$r['id'],"text"=>$r['name']);
					if($i==0 || $r['kid']!=$arrReturn[$i-1]['kid']) $k=array("id"=>$r['kid'],"text"=>$r['kname']);
				}
				if($i==$count-1 || $r['kid']!=$arrReturn[$i+1]['kid']){
					$p['children'][]=$k;
					$k=array();
				}
				if($i==$count-1 || $r['id']!=$arrReturn[$i+1]['id']){
					$node[]=$p;
					$p=array();
				}


			}	
			echo(json_encode($node,true));
		}elseif($_REQUEST['type']=='searchmms'){
			$where='1=1';
			$k=sanitize_text_field(trim($_REQUEST['keyword']));
			list($make,$model,$submodel)=array_map('trim', explode(',',$k));
			$make && $where.=" AND make='$make'";
			$model && $where.=" AND model='$model'";
			$submodel && $where.=" AND submodel='$submodel'";
			$results = $wpdb->get_col(_sql("SELECT CONCAT(make,',',model,',',submodel,',',GROUP_CONCAT(distinct year SEPARATOR ' ')) as ymms FROM wp_sema_parts WHERE $where group by make,model,submodel ORDER BY make,model,submodel "));
			
			echo json_encode($results); exit;

			$arrMMS=array();
			foreach($results as $val){
				$arrMMS[]=array('mms'=>sema_highlightWords($val['mms'],$k),'mms2'=>$val['mms2']);
			}
			$response=array("success"=>true,"message"=>"","mms"=>$arrMMS);
			//$response=array("success"=>true,"message"=>"","mms"=>$results);
			echo json_encode($response);
		}elseif($_REQUEST['type']=='suggestmms'){
			$k=sanitize_text_field(trim($_REQUEST['keyword']));
			$results = $wpdb->get_results(_sql("SELECT distinct CONCAT(make,' ',model,' ',submodel) AS mms, CONCAT(make,'|/',model,'|/',submodel) as mms2 FROM _shopify_ymms_full 
			where CONCAT(make,' ',model,' ',submodel) like '%$k%' ORDER BY make ASC, model asc, submodel asc limit 50"),ARRAY_A);
			
			$arrMMS=array();
			foreach($results as $val){
				$arrMMS[]=array('mms'=>sema_highlightWords($val['mms'],$k),'mms2'=>$val['mms2']);
			}
			$response=array("success"=>true,"message"=>"","mms"=>$arrMMS);
			//$response=array("success"=>true,"message"=>"","mms"=>$results);
			echo json_encode($response);
		}elseif($_REQUEST['type']=='suggestyears'){
			$k=sanitize_text_field(trim($_REQUEST['keyword']));
			$mms=explode("|/",$k);
			if(count($mms)==3){
				$results = $wpdb->get_results(_sql("SELECT year FROM _shopify_ymms_full 
				where make='$mms[0]' AND model='$mms[1]' AND submodel='$mms[2]' ORDER BY year "),ARRAY_A);
			}
			$response=array("success"=>true,"message"=>"","years"=>$results);
			echo json_encode($response);
		}elseif($_REQUEST['type']=='addfitment'){
			$fitments = sanitize_text_field(trim($_GET['fitments']));
			$fitmentAry = array_unique(array_filter(explode('|,', $fitments)));
			foreach($fitmentAry as $fitment){
				$fitment=explode('|/',$fitment);
				$make=$fitment[0];
				$model=$fitment[1];
				$submodel=$fitment[2];
				$year=$fitment[3];
				$wpdb->query(_sql("INSERT wp_sema_fitment(make,model,submodel,year,products,custom) VALUES ('$make','$model','$submodel','$year',0,1)"));
		
			}
			
		
			echo json_encode("true");
				
		}elseif($_GET && $_GET['type']=='productbycat'){
			session_start();
			$woocommercePermalinks = get_option( 'woocommerce_permalinks');
			$woocommercePermalinks = $woocommercePermalinks['product_base'];
			$sema_hide_productwoimage = get_option( 'sema_hide_productwoimage' );
			
			$join="";

			$catId=sanitize_text_field($_GET['catId']);
			$termId=sanitize_text_field($_GET['catId']);
			$year=sanitize_text_field($_GET['year']);
			$make=sanitize_text_field($_GET['make']);
			$model=sanitize_text_field($_GET['model']);
			$submodel=sanitize_text_field($_GET['submodel']);
			$keyword=sanitize_text_field($_GET['keyword']);
			$set=sanitize_text_field($_GET['set']);
			//$universal='Y';
			$pageNumber=sanitize_text_field((array_key_exists('pageNumber',$_GET))?$_GET['pageNumber']:'');
			if(array_key_exists('pageSize',$_GET)){
				$_SESSION['productbycat']['pageSize']=$_GET['pageSize'];
				$pageSize=$_GET['pageSize'];
			}else{
				$_SESSION['productbycat']['pageSize']=($_SESSION['productbycat']['pageSize'])?$_SESSION['productbycat']['pageSize']:5;
				$pageSize=$_SESSION['productbycat']['pageSize'];
			}
			//$pageSize=5;
			$filters=sanitize_text_field((array_key_exists('filters',$_GET))?$_GET['filters']:'');
			// remove \, which comes from url escape of single/double quotes.
			$filters=str_replace("\\","",$filters);
			
			if(empty($set)  ){ // loading home page
				$catId=sanitize_text_field($_SESSION['productbycat']['catId']);
				$termId=sanitize_text_field($_SESSION['productbycat']['termId']);
				$year=sanitize_text_field($_SESSION['productbycat']['year']);
				$make=sanitize_text_field($_SESSION['productbycat']['make']);
				$model=sanitize_text_field($_SESSION['productbycat']['model']);
				$submodel=sanitize_text_field($_SESSION['productbycat']['submodel']);
				$pageSize=sanitize_text_field($_SESSION['productbycat']['pageSize']);
				$pageSize=(empty($pageSize))?5:$pageSize;
				$filters=sanitize_text_field($_SESSION['productbycat']['filters']);
				$keyword=sanitize_text_field($_SESSION['productbycat']['keyword']);
				//$pageNumber=sanitize_text_field($_SESSION['productbycat']['pageNumber']);
			}else{ // refreshing page
				if($pageNumber){
					$_SESSION['productbycat']['pageNumber']=$pageNumber;
				}else{
					$_SESSION['productbycat']['catId']=$catId;
					$_SESSION['productbycat']['termId']=$termId;
					$_SESSION['productbycat']['catName']=sanitize_text_field($_GET['catId']);
					$_SESSION['productbycat']['year']=$year;
					$_SESSION['productbycat']['make']=$make;
					$_SESSION['productbycat']['model']=$model;
					$_SESSION['productbycat']['submodel']=$submodel;
					$_SESSION['productbycat']['pageSize']=$pageSize;
					$_SESSION['productbycat']['filters']=$filters;
					$_SESSION['productbycat']['keyword']=$keyword;
					$_SESSION['productbycat']['pageNumber']='1';
				}
			}
			$inselect="";$where="";$universal="";$where_universal="";$filter_names=array();$arrFilterObj=array();$arrFilter_checked=array();$arrFilters=array();
			$categoryid='';$terminologyid='';
			if($filters){
				$arrFilterObj=array();
				$arrFilters=explode('|;',$filters);
				$wherefilter=array();
				foreach($arrFilters as $filter){
					//$arr=explode('=',$filter);
					$arrfilter=explode('=',$filter);
					$attr_name=$arrfilter[0];
					unset($arrfilter[0]);
					$attr_value=trim(implode('=',$arrfilter ));
								  
					if($attr_name=='Brand'){
						$brandid = $wpdb->get_var($wpdb->prepare(_sql("SELECT brandid FROM wp_sema_brands where brandname=%s " ),$attr_value));
						$where .= " AND brand.meta_value='$brandid' ";
					}else if ($attr_name=='Universal Parts'){
						$universal='Y';
					}else{
						$filter_names[]=$attr_name;
						$arrFilterObj[$attr_name][]=$attr_value;
						$wherefilter[]="(attr_name='".$attr_name."' AND attr_value='".$attr_value."')";
					}
				}
				if(count($wherefilter)){
					$wherefilter=implode(" OR ",$wherefilter);
					$filter_count=count($arrFilterObj);
					$pids_fitler = $wpdb->get_var(_sql("SELECT GROUP_CONCAT(id SEPARATOR ',') as ids FROM (SELECT GROUP_CONCAT(product_id SEPARATOR ',') as id FROM wp_sema_attr_taxonomy WHERE $wherefilter GROUP BY product_id HAVING COUNT(product_id)>=$filter_count ) x"));
					$where .=" AND p.ID in ($pids_fitler)";
				}
			}
			$pids="";		
			if($siteid){
				$fields=array();
				array_key_exists('fitment',$options['sema_mandatory_update']) && $fields[]='fitment';
				array_key_exists('attribute',$options['sema_mandatory_update']) && $fields[]='attribute';
				$fields=implode(',',$fields);
				$url="$api_url/ajax.php?siteid=$siteid&type=wp_get_productsbyymms&catid=$termId&year=$year&make=$make&model=$model&submodel=$submodel&filters=".urlencode($filters)."&keyword=".urlencode($keyword)."&count=$count&pageNumber=$pageNumber";
				$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
				$response=json_decode($response['body'],true);
				if ($response['success']) $pids=$response['pids'];
				$inselect=" AND FIND_IN_SET(p.id,'$pids') ";
				//if($submodel) $inselect=" AND p.id in (SELECT  distinct productid FROM wp_sema_parts WHERE (year='$year' and make='$make' and model='$model' and submodel='$submodel') $where_universal)";
				//else if($model) $inselect=" AND p.id in (SELECT  distinct productid FROM wp_sema_parts WHERE (year='$year' and make='$make' and model='$model') $where_universal)";
				//else if($make) $inselect=" AND p.id in (SELECT  distinct productid FROM wp_sema_parts WHERE (year='$year' and make='$make') $where_universal)  ";
				//else $inselect=" AND p.id in (SELECT  distinct productid FROM wp_sema_parts WHERE year='$year' $where_universal)  ";
			}		
								
			if(empty($termId)){
				//$where="";
			}elseif(substr($termId,0,1)=="C"){
				$termId=substr($termId,1,strlen($termId)-1);
				$categoryid=$termId;
				$taxId = $wpdb->get_var($wpdb->prepare(_sql("SELECT GROUP_CONCAT(tt.term_taxonomy_id SEPARATOR ',') AS terms  FROM wp_term_taxonomy t INNER join wp_term_taxonomy tt ON tt.parent=t.term_id and t.taxonomy='product_cat' and tt.taxonomy='product_cat' where t.parent=%d ;  "),$termId) );
				$join .= " INNER JOIN wp_term_relationships AS tr ON tr.object_id = p.ID ";
				$where .= " AND tr.term_taxonomy_id in ($taxId)";
			}elseif(substr($catId,0,1)=="S"){
				$termId=substr($termId,1,strlen($termId)-1);
				$categoryid=$termId;
				$taxId = $wpdb->get_var($wpdb->prepare(_sql("SELECT GROUP_CONCAT(t.term_taxonomy_id SEPARATOR ',') AS terms  FROM wp_term_taxonomy t where t.parent=%d and t.taxonomy='product_cat'; "),$termId) );
				$join .= " INNER JOIN wp_term_relationships AS tr ON tr.object_id = p.ID ";
				$where .= " AND tr.term_taxonomy_id in ($taxId)";
			}else{
				//$terminologyid=$termid;
				$taxId = $wpdb->get_var($wpdb->prepare(_sql("SELECT GROUP_CONCAT(t.term_taxonomy_id SEPARATOR ',') AS terms  FROM wp_term_taxonomy t where t.term_id=%d and t.taxonomy='product_cat'; "),$termId) );
				$join .= " INNER JOIN wp_term_relationships AS tr ON tr.object_id = p.ID ";
				$where .= " AND tr.term_taxonomy_id in ($taxId)";
			}
			// hide products if wooCom sets true "hide out-of-stock products"
			if(($year || $fitler || $termId) && $wooHideOutofStock=='yes'){
				$join.= " LEFT JOIN wp_postmeta stock ON p.ID=stock.post_id AND stock.meta_key='_stock' ";
				$where.= " AND (stock.meta_value is null or (stock.meta_value<>'0' AND LEFT(stock.meta_value,1)<>'-'))";
			} 
			$arrFilters=array();
			if($keyword) $where .= " AND p.post_title like '%$keyword%' ";
			if(empty($pageNumber)){
				if(!$set){
					$pageNumber=sanitize_text_field($_SESSION['productbycat']['pageNumber']);
					$ids=sanitize_text_field($_SESSION['productbycat']['ids']);
					$count=sanitize_text_field($_SESSION['productbycat']['count']);
					$brandids=sanitize_text_field($_SESSION['productbycat']['brandids']);
				}
				if(empty($ids) && empty($count)){
					// show products only with images
					// inner JOIN wp_postmeta thumb ON p.ID=thumb.post_id AND thumb.meta_key='_thumbnail_id' 
					/*$sql="SELECT COUNT(DISTINCT p.id) as count,GROUP_CONCAT(DISTINCT p.ID SEPARATOR ',') as ids,GROUP_CONCAT(DISTINCT brand.meta_value SEPARATOR ''',''') as brandids 
					FROM wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku' $join 
					LEFT JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' 
					WHERE p.post_type='product' AND p.post_status = 'publish' $where ";*/
					$sql="SELECT COUNT(DISTINCT p.id) as count,GROUP_CONCAT(DISTINCT p.ID SEPARATOR ',') as ids,GROUP_CONCAT(DISTINCT brand.meta_value SEPARATOR ''',''') as brandids 
					FROM wp_posts p INNER JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' $join
					WHERE p.post_type='product' AND p.post_status = 'publish' $where ";
					//-- INNER JOIN wp_term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id and tt.taxonomy='product_cat'

					if($year) $sql.=$inselect;
					$row = $wpdb->get_row(_sql($sql),ARRAY_A);
					$count = $row['count'];
					$ids = $row['ids'];
					$brandids = $row['brandids'];
					$_SESSION['productbycat']['ids']=$ids;
					$_SESSION['productbycat']['count']=$count;
					$_SESSION['productbycat']['brandids']=$brandids;
				}
				$brandids = "'$brandids'";
				$brandnames = $wpdb->get_var(_sql("SELECT GROUP_CONCAT(DISTINCT brandname ORDER BY brandname ASC SEPARATOR '|;') as brandnames FROM wp_sema_brands WHERE brandid in ($brandids) and brandname<>'' "));

				//$pageCount=ceil($count/$pageSize);
				if($termId || $brandid){
					$filter_names = "'".implode("','",$filter_names)."'";
					//todo if $ids=''
					$in_ids=($ids)?$ids:'0';
					$sql2="SELECT x.attr_name,group_concat(DISTINCT x.attr_value SEPARATOR '|;') as attr_values  FROM (
						SELECT attr_name,attr_value,status FROM wp_sema_attr_taxonomy
						WHERE status>0 AND product_id IN ($in_ids) AND attr_name NOT IN ($filter_names)
						GROUP BY attr_name,attr_value
						) x GROUP BY x.attr_name HAVING COUNT(x.attr_name)>1 ORDER BY status DESC,attr_name ";
					if(empty($pageNumber)) $pageNumber=1;
					$arrFilters = $wpdb->get_results(_sql($sql2),ARRAY_A );
					foreach($arrFilters as &$f){
						$value=explode('|;',$f['attr_values']);
						natsort($value);
						$f['attr_values']=implode('|;',$value);
					}
				}
				if(count(explode('|;',$brandnames))>1) array_unshift($arrFilters,array('attr_name'=>'Brand','attr_values'=>$brandnames));
				//if($year && $universal=="") array_unshift($arrFilters,array('attr_name'=>'Universal Parts','attr_values'=>'Included'));
				$response['Products']=array();
				$response['Pagination']['count']=$count;
				$response['Pagination']['size']=$pageSize;
				$response['Pagination']['catId']=$catId;
				$response['Pagination']['pageNumber']=($_SESSION['productbycat']['pageNumber'])?$_SESSION['productbycat']['pageNumber']:$pageNumber;
				$response['Brandids']=$brandids;
				$response['Filters']=$arrFilters;
				$response['Filters_checked']=sanitize_text_field($_SESSION['productbycat']['filters']);
				if($set!=1) $response['Preset']=array(
					'catId'=>sanitize_text_field($_SESSION['productbycat']['catId']),
					'catName'=>sanitize_text_field($_SESSION['productbycat']['catName']),
					'year'=>sanitize_text_field($_SESSION['productbycat']['year']),
					'make'=>sanitize_text_field($_SESSION['productbycat']['make']),
					'model'=>sanitize_text_field($_SESSION['productbycat']['model']),
				);
				$pageNumber=1;
				//echo json_encode($response);
			} else $count = sanitize_text_field($_SESSION['products']['count']);
			$_SESSION['productbycat']['pageNumber']=$pageNumber;
			$uploadpath=wp_upload_dir();
			$uploadpath = $uploadpath['baseurl']."/";
			$start=($pageNumber-1)*$pageSize;
			//$end=$pageNumber*$pageSize-1;
			$ids = explode(',',sanitize_text_field($_SESSION['productbycat']['ids']));
			$ids = array_slice($ids, $start,$pageSize);
			$ids = implode(',',$ids);
			//INNER JOIN wp_term_relationships AS tr ON tr.object_id = p.ID
			// todo if ids=''
			$sema_hide_brandid=false;
			if(array_key_exists('sema_hide_brandid',$options) && $options['sema_hide_brandid']) $sema_hide_brandid=true;

			empty($ids) && $ids='0';
			$sql="SELECT p.ID,concat('/product/',p.post_name) as guid, p.post_title, p.post_content, p.post_excerpt,psku.meta_value AS sku, pprice.meta_value AS price,CONCAT('$uploadpath',img.meta_value) AS image
			FROM wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku'
			LEFT JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' 
			INNER JOIN wp_postmeta pprice ON p.ID=pprice.post_id AND pprice.meta_key='_price'
			left JOIN wp_postmeta thumb ON p.ID=thumb.post_id AND thumb.meta_key='_thumbnail_id' 
			left JOIN wp_postmeta img ON  thumb.meta_value=img.post_id AND img.meta_key='_wp_attached_file'
			WHERE p.post_type='product' AND p.post_status = 'publish' AND p.id in ($ids) ";
			$arrReturn = $wpdb->get_results(_sql($sql),ARRAY_A );
			foreach($arrReturn as $i=>$p){
				$arrReturn[$i]['guid']=get_permalink($p['ID']);
				if($sema_hide_brandid){
					$sku=$p['sku'];
					$sku=explode('-',$sku,2)[1];
					$arrReturn[$i]['sku']=$sku;
				}
			}
			$response['Products']=$arrReturn;

			$count_end = $start + count($arrReturn);
			if($count) $response['Pagination']['text']=($pageSize*($pageNumber-1)+1)." - $count_end of $count results";
			else $response['Pagination']['text']=" 0 results";

			echo json_encode($response);

			exit;

		}elseif($_GET && $_GET['type']=='products'){
			$inselect="";$where="";$categoryid='';$terminologyid='';
			session_start();
			$catId=sanitize_text_field($_GET['catId']);
			$termId=sanitize_text_field($_GET['catId']);
			$year=sanitize_text_field($_GET['year']);
			$make=sanitize_text_field($_GET['make']);
			$model=sanitize_text_field($_GET['model']);
			$submodel=sanitize_text_field($_GET['submodel']);
			$keyword=sanitize_text_field(trim($_GET['keyword']));
			if($keyword) $where= " AND (p.post_title like '%$keyword%' OR psku.meta_value like '$keyword%' ) ";
			//$universal='Y';
			$pageNumber=sanitize_text_field((array_key_exists('pageNumber',$_GET))?$_GET['pageNumber']:'');
			//$pageSize=(array_key_exists('pageSize',$_GET))?$_GET['pageSize']:10;
			$pageSize=20;



			if(empty($pageNumber) || $pageNumber==1){
				if(empty($count)){
					// show products only with images
					// inner JOIN wp_postmeta thumb ON p.ID=thumb.post_id AND thumb.meta_key='_thumbnail_id' 
					$sql="SELECT COUNT(p.id) as count
					FROM wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku' 
					WHERE p.post_type='product' AND p.post_status in ('publish','draft') AND psku.meta_value<>'' $where ";
					$count = $wpdb->get_var(_sql($sql));
				}
				$pageNumber=1;
				$_SESSION['products']['count']=$count;

				//echo json_encode($response);
			}
			$response['Products']=array();
			$response['Pagination']['count']=($_SESSION['products']['count'])? $_SESSION['products']['count']: $count;
			$response['Pagination']['size']=$pageSize;
			$response['Pagination']['pageNumber']=$pageNumber;

			$uploadpath=wp_upload_dir();
			$uploadpath = $uploadpath['baseurl']."/";
			$start=($pageNumber-1)*$pageSize;
			//$end=$pageNumber*$pageSize-1;
			// todo if ids=''
			$sql="SELECT p.id,concat('/product/',p.post_name) as guid, p.post_title as name,psku.meta_value AS sku,CONCAT('$uploadpath',img.meta_value) AS image
			FROM wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku' 
			left JOIN wp_postmeta thumb ON p.ID=thumb.post_id AND thumb.meta_key='_thumbnail_id' 
			left JOIN wp_postmeta img ON  thumb.meta_value=img.post_id AND img.meta_key='_wp_attached_file'
			WHERE p.post_type='product' AND p.post_status in ('publish','draft') AND psku.meta_value<>'' $where ORDER BY psku.meta_value LIMIT $start,20 ";
			$arrReturn = $wpdb->get_results(_sql($sql),ARRAY_A );
			$response['Products']=$arrReturn;


			echo json_encode($response);
			exit;
		}elseif($_GET && $_GET['type']=='loadDiscontinuedProducts'){
			$productids='';$inselect="";$where="";$universal="";$arrFilterObj=array();$arrFilter_checked=array();$brandlist=array();

			$next=$previous=$cursor="";
			$pageSize=20;
		
			$where_ymms="";
			$keyword = str_replace('"','',str_replace("'",'',str_replace('%','',str_replace('*','',trim($_GET['keyword'])))));
		
			if($keyword) $where=" AND (sku like '$keyword%' OR name like '%$keyword%')";
			$pagination = $_GET['pagination'];
			$brandid=$_REQUEST['brandid'];
			$targetdate=$_REQUEST['targetdate'];
		
			// minute and second equal 0; 2024-02-01 12:00:00
			$row = $wpdb->get_row(_sql("SELECT brandid,termids,updates_info FROM wp_sema_brands WHERE brandid='$brandid' AND (DATEDIFF(NOW(),ifnull(check_date,'2020-01-01'))>0 OR (ifnull(minute(check_date),1)<>0 OR ifnull(second(check_date),1)<>0))"),ARRAY_A );
			if($row){//update discontinued products
				$termids=$row['termids'];
				$url="https://apps.semadata.org/sdapi/plugin/lookup/discontinuedproducts?token=$token&purpose=WP&AAIA_BrandID=$brandid&TargetDate=$targetdate&CategoryIds=$termids";
				$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
				$response=json_decode($response['body'],true);
				$parts='';
				if($response['Success']){
					$parts=implode("','$brandid-",$response['PartNumbers']);
					$parts="'$brandid-$parts'";
				}
				if($parts){
					$wpdb->query(_sql("UPDATE wp_sema_products SET status=99 WHERE brandid='$brandid' and partno in ($parts);"));
				}
				$updates_info=json_decode($row['updates_info'],true);
				$updates_info['RemovedSKUCount']=$wpdb->get_var(_sql("SELECT COUNT(p.id) as count FROM wp_posts p INNER JOIN wp_sema_products psku ON p.ID=psku.wpid WHERE p.post_type='product' AND psku.brandid='$brandid' AND psku.status=99 "));
				$updates_info=json_encode($updates_info);
		
				$wpdb->query(_sql("UPDATE wp_sema_brands SET check_date=DATE_FORMAT(now(),'%Y-%m-%d %k:00:00'),updates_info='$updates_info' WHERE brandid='$brandid';"));
			}
			$pageNumber=(array_key_exists('pageNumber',$_GET))?$_GET['pageNumber']:'';
			if(empty($pageNumber)) $pageNumber=1;
			$count=0;

			// show products only with images
			// inner JOIN wp_postmeta thumb ON p.ID=thumb.post_id AND thumb.meta_key='_thumbnail_id' 
			$sql="SELECT COUNT(p.id) as count FROM wp_posts p INNER JOIN wp_sema_products psku ON p.ID=psku.wpid
			WHERE p.post_type='product' AND psku.brandid='$brandid' AND psku.status=99 ";
			$count = $wpdb->get_var(_sql($sql));
			$count_end = ($count < $pageSize*$pageNumber)?$count:$pageSize*$pageNumber;
			$count_start=$pageSize*($pageNumber-1);
			//


			$uploadpath=wp_upload_dir();
			$uploadpath = $uploadpath['baseurl']."/";
			$sql="SELECT p.id,p.post_title as name,psku.partno AS sku,CONCAT('$uploadpath',img.meta_value) AS image
			FROM wp_posts p INNER JOIN wp_sema_products psku ON p.id=psku.wpid
			left JOIN wp_postmeta thumb ON p.id=thumb.post_id AND thumb.meta_key='_thumbnail_id' 
			left JOIN wp_postmeta img ON  thumb.meta_value=img.post_id AND img.meta_key='_wp_attached_file'
			WHERE p.post_type='product' AND psku.brandid='$brandid' AND psku.status=99 ORDER BY psku.partno LIMIT $count_start,$pageSize ";
			$products = $wpdb->get_results(_sql($sql),ARRAY_A );
			
			$response=array();
			$response['Products']=$products;
			$response['Pagination']['count']=$count;
			$response['Pagination']['size']=$pageSize;
			$response['Pagination']['pageNumber']=$pageNumber;
			if($count) $response['Pagination']['text']=($count_start+1)." - $count_end of $count results";
			else $response['Pagination']['text']="No products found";
			echo json_encode($response);
			exit;
		}elseif($_GET && $_GET['type']=='assigntoproducts'){
			$fitmentid = sanitize_text_field(trim($_GET['fitmentid']));
			$pids = sanitize_text_field(trim($_GET['productids']));
			$pids = implode(',',wp_parse_id_list($pids));
			if($fitmentid && $pids){
				$url="$api_url/ajax.php?siteid=$siteid&type=wp_update_fitment&fitmentid=$fitmentid&pids=$pids";
				$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
				$response=json_decode($response['body'],true);
				if ($response['success']){
					echo json_encode("true");						
				}	
			}		
			echo json_encode("false");						
		}elseif($_GET && $_GET['type']=='savefitment'){
			$fitmentid = sanitize_text_field(trim($_GET['fitmentid']));
			$dids = sanitize_text_field(trim($_GET['deletedpids']));
			$pids = sanitize_text_field(trim($_GET['pids']));
			if($fitmentid && ($pids || $dids)){
				$url="$api_url/ajax.php?siteid=$siteid&type=wp_update_fitment&fitmentid=$fitmentid&pids=$pids&dids=$dids";
				$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
				$response=json_decode($response['body'],true);
				if ($response['success']){
					echo json_encode("true");						
				}	
			}		
			echo json_encode("true");
		}elseif($_REQUEST['type']=='deletefitment'){
			$make=sanitize_text_field(trim($_GET['make']));
			$model=sanitize_text_field(trim($_GET['model']));
			$submodel=sanitize_text_field(trim($_GET['submodel']));
			$restore=sanitize_text_field(trim($_GET['restore']));
			$url="$api_url/ajax.php?siteid=$siteid&type=wp_delete_fitment&make=$make&model=$model&submodel=$submodel&restore=$restore";
			$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
			$response=json_decode($response['body'],true);
			if($response['success']) echo json_encode(true);
			else echo json_encode(false);
		}elseif($_GET && $_GET['type']=='savecategories'){
			global $woocommerce, $wpdb, $jal_db_version;
			$categoryMatch=false;
			wp_suspend_cache_invalidation( true );

			if(WC()->version < '2.7.0'){
				$memory    = size_format( woocommerce_let_to_num( ini_get( 'memory_limit' ) ) );
				$wp_memory = size_format( woocommerce_let_to_num( WP_MEMORY_LIMIT ) );
			}else{
				$memory    = size_format( wc_let_to_num( ini_get( 'memory_limit' ) ) );
				$wp_memory = size_format( wc_let_to_num( WP_MEMORY_LIMIT ) );
			}

			$return = 'success';
			$aaia=sanitize_text_field(trim($_GET['aaia']));
			$options = get_option( 'sema_settings' );
			$token=$options['sema_token'];
			//$aaia=$options['sema_aaia'];
		
			$selectednodes = sanitize_text_field($_POST['selectednodes']);
			$undeterminednodes = sanitize_text_field($_POST['undeterminednodes']);
			$priceadjustment = sanitize_text_field($_POST['priceadjustment']);
			$pricetoimport = sanitize_text_field($_POST['pricetoimport']);
			$customprefix = sanitize_text_field(trim($_POST['customprefix']));
			$termids = array();
			foreach(explode(',',$selectednodes) as $t){
				if(is_numeric($t)) $termids[]=$t;
			}
			$termids=implode(',',$termids);
			$selectednodes=str_replace('S','',str_replace('C','',$selectednodes));
			$undeterminednodes=str_replace('S','',str_replace('C','',$undeterminednodes));
			//$termids_old=$wpdb->get_var(_sql("SELECT termids FROM  wp_sema_brands  WHERE brandid='$aaia'"));
			$row=$wpdb->get_row($wpdb->prepare(_sql("SELECT termids,undeterminednodes,prefix FROM  wp_sema_brands  WHERE brandid=%s"),$aaia),ARRAY_A);
			$termids_old=$row['termids'];
			$undeterminednodes_old=$row['undeterminednodes'];
			$customprefix_old=$row['prefix'];
			if($termids_old==$termids && $undeterminednodes==$undeterminednodes_old) $categoryMatch=true;
			$row=$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands SET selectednodes=%s,undeterminednodes=%s,priceadjustment=%s,termids=%s,pricetoimport=%s WHERE brandid=%s"),$selectednodes,$undeterminednodes,$priceadjustment,$termids,$pricetoimport,$aaia));
			if($undeterminednodes) $selectednodes .=','.$undeterminednodes;
			$selectednodes = explode(',',$selectednodes);
			

			$options['updatefitment']='1';
			update_option( 'sema_settings', $options );

			// update categories
			$url="https://apps.semadata.org/sdapi/plugin/lookup/categories";
			$parameters=array(
				'token'=>$token,"purpose"=>"WP",
			);
			if(strtoupper($aaia)!='ALL') $parameters['aaia_brandids']=$aaia;
			$response = wp_remote_post($url, array(
				'body' => $parameters,
				'timeout' => '60','redirection' => 5,'redirection' => 5,'httpversion' => '1.0','sslverify' => false,'blocking' => true,
			));
			$response=json_decode($response['body'],true);

			$arrCatId=array();
			if(array_key_exists('Categories',$response)){
				// 3-tier categories: CategoryID->SubCategoryID->PartTerminologyID
				// Due to Autocare Assc list the same subcategory under multiple categories
				// SubCategoryID(in category tree) = 9,000,000+CategoryID*1,000+SubCategoryID

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

			if(strtoupper($customprefix)==$aaia) $customprefix='';
			if($customprefix){
				if(empty($customprefix_old)) $customprefix_old=$aaia;
				if($customprefix!=$customprefix_old){
					$sql="UPDATE wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku' 
					INNER JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' 
					SET psku.meta_value=replace(psku.meta_value,'$customprefix_old-','$customprefix-')
					WHERE p.post_type='product' AND brand.meta_value='$aaia'";
					$wpdb->query(_sql($sql));
					$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands SET prefix=%s WHERE brandid=%s"),$customprefix,$aaia));
				}
			}else{
				if($customprefix_old){
					$sql="UPDATE wp_posts p INNER JOIN wp_postmeta psku ON p.ID=psku.post_id AND psku.meta_key='_sku' 
					INNER JOIN wp_postmeta brand ON p.ID=brand.post_id AND brand.meta_key='_brandid' 
					SET psku.meta_value=replace(psku.meta_value,'$customprefix_old-','$aaia-')
					WHERE p.post_type='product' AND brand.meta_value='$aaia'";
					$wpdb->query(_sql($sql));
					$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_brands SET prefix=null WHERE brandid=%s"),$aaia));
				}
			}
			if (WC()->version >= '3.6' && !wc_update_product_lookup_tables_is_running()) {
				wc_update_product_lookup_tables();
			}
			echo("true");
		}elseif($_GET && $_GET['type']=='synccategories'){
			global $woocommerce, $wpdb, $jal_db_version;
			$categoryMatch=false;
			wp_suspend_cache_invalidation( true );

			if(WC()->version < '2.7.0'){
				$memory    = size_format( woocommerce_let_to_num( ini_get( 'memory_limit' ) ) );
				$wp_memory = size_format( woocommerce_let_to_num( WP_MEMORY_LIMIT ) );
			}else{
				$memory    = size_format( wc_let_to_num( ini_get( 'memory_limit' ) ) );
				$wp_memory = size_format( wc_let_to_num( WP_MEMORY_LIMIT ) );
			}

			$return = 'success';
			$options = get_option( 'sema_settings' );
			$token=$options['sema_token'];
			//$aaia=$options['sema_aaia'];
		
			$selectednodes=$wpdb->get_var(_sql("SELECT GROUP_CONCAT(selectednodes SEPARATOR ',') FROM wp_sema_brands WHERE active=1"));
			$selectednodes = explode(',',$selectednodes);
			$selectednodes = array_unique($selectednodes);
			


			// update categories
			$url="https://apps.semadata.org/sdapi/plugin/lookup/categories";
			$response = wp_remote_post($url, array(
				'body' => array('token'=>$token,"purpose"=>"WP",),
				'timeout' => '60','redirection' => 5,'redirection' => 5,'httpversion' => '1.0','sslverify' => false,'blocking' => true,
			));
			$response=json_decode($response['body'],true);

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

			// delete all categories (including non-SEMA categories) but the default categories
			// ** USE THIS PART CAREFULLY
			/*
			$catids_deleted=$wpdb->get_var(_sql("SELECT GROUP_CONCAT(DISTINCT t.term_id SEPARATOR ',') from wp_terms t where t.term_id<>0 "));
			if($catids_deleted){
				$wpdb->query(_sql("DELETE FROM wp_terms WHERE term_id in ($catids_deleted) "));
				$wpdb->query(_sql("DELETE FROM wp_termmeta WHERE term_id in ($catids_deleted)"));
				$wpdb->query(_sql("DELETE FROM wp_term_taxonomy WHERE term_id in ($catids_deleted)"));
			}*/
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

			echo(true);
		}elseif($_REQUEST['type']=='token'){
			$user=sanitize_text_field($_POST['user']);
			$pass=sanitize_text_field($_POST['pass']);
			$url="https://apps.semadata.org/sdapi/plugin/token/get?userName=$user&password=$pass&purpose=WP";
			$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
			$response=json_decode($response['body'],true);
			if ($response['Success']){
				$token=$response['Token'];
				$options['sema_token']=$token;
				$options['sema_user']=$user;
				update_option( 'sema_settings', $options );
			}else{
				$token='';
			}
			echo($token);
		}elseif($_REQUEST['type']=='year'){
			/*
			$sku=sanitize_text_field($_POST['sku']);
			if($sku) $results = $wpdb->get_results($wpdb->prepare(_sql("SELECT DISTINCT `year` FROM wp_sema_parts WHERE partno=%s AND `year`<>''  ORDER BY `year` DESC;"),$sku),ARRAY_A);
			else $results = $wpdb->get_results(_sql("SELECT DISTINCT `year` FROM wp_sema_parts WHERE `year`<>''  ORDER BY `year` DESC;"),ARRAY_A);*/
			$url="$api_url/ajax.php?siteid=$siteid&type=year";
			$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
			$response=json_decode($response['body'],true);
			if ($response['success']) $years=$response['Years'];
			$response=array("success"=>true,"message"=>"","Years"=>$years);
			echo json_encode($response);
		}elseif($_REQUEST['type']=='make'){
			$year=sanitize_text_field($_REQUEST['year']);
			$url="$api_url/ajax.php?siteid=$siteid&type=make&year=$year";
			$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
			$response=json_decode($response['body'],true);
			if ($response['success']) $makes=$response['Makes'];
			$response=array("success"=>true,"message"=>"","Makes"=>$makes);
			echo json_encode($response);
		}elseif($_REQUEST['type']=='model'){
			$year=sanitize_text_field($_REQUEST['year']);
			$make=sanitize_text_field($_REQUEST['make']);
			$url="$api_url/ajax.php?siteid=$siteid&type=model&year=$year&make=$make";
			$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
			$response=json_decode($response['body'],true);
			if ($response['success']) $models=$response['Models'];
			$response=array("success"=>true,"message"=>"","Models"=>$models);
			echo json_encode($response);
		}elseif($_REQUEST['type']=='submodel'){
			$year=sanitize_text_field($_REQUEST['year']);
			$make=sanitize_text_field($_REQUEST['make']);
			$model=sanitize_text_field($_REQUEST['model']);
			$url="$api_url/ajax.php?siteid=$siteid&type=submodel&year=$year&make=$make&model=$model";
			$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
			$response=json_decode($response['body'],true);
			if ($response['success']) $submodels=$response['Submodels'];
			$response=array("success"=>true,"message"=>"","Submodels"=>$submodels);
			echo json_encode($response);
		}
		wp_die();
	
	}

	function sema_highlightWords($text, $word){
		$text = preg_replace('#'. preg_quote($word) .'#i', '<b>\\0</b>', $text);
		return $text;
	}
		
	// insert category function
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

		$my_cat_id=$wpdb->get_var($wpdb->prepare(_sql("SELECT term_id FROM wp_terms WHERE term_group=%d"),$catId));
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
	
	
}


if (!defined('ABSPATH') || !is_admin()) {
    return;
}



/**
 * Check if WooCommerce is active
 */
//if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

if (!class_exists('SEMA_Data_Import')){

	/**
	 * Main CSV Import class
	 */
	class SEMA_Data_Import {

		/**
		 * Constructor
		 */
		public function __construct() {
			if (!defined('SEMA_API_FILE')) {
				define('SEMA_API_FILE', __FILE__);
			}

			if (!defined('SEMA_plugin_path')) {
				define('SEMA_plugin_path', plugin_dir_path(__FILE__));
			}

			add_filter('woocommerce_screen_ids', array($this, 'woocommerce_screen_ids'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'sema_plugin_action_links'));
			add_action('init', array($this, 'load_plugin_textdomain'));
			//add_action('init', array($this, 'catch_export_request'), 20);
			add_action('admin_init', array($this, 'register_importers'));

			//add_action('admin_footer', array($this, 'deactivate_scripts'));
			// added by S+Even 12/2/2019
			add_filter( 'intermediate_image_sizes', array($this,'sa_remove_stock_image_sizes') );


			//add_filter('admin_footer_text', array($this, 'WT_admin_footer_text'), 100);

			include_once( 'includes/importer/class-sema-importer.php' );

			//if (defined('DOING_AJAX')) {
			//	include_once( 'includes/class-sema-ajax-handler.php' );
			//}
		}


		public function sema_plugin_action_links($links) {
			$plugin_links = array(
				'<a href="' . admin_url('options-general.php?page=sema_setting') . '">' . __('Settings', 'product-import-export-for-woo') . '</a>',
				'<a href="' . admin_url('options-general.php?page=sema_import') . '">' . __('Import', 'product-import-export-for-woo') . '</a>',
			);
			if (array_key_exists('deactivate', $links)) {
				//$links['deactivate'] = str_replace('<a', '<a class="pipe-deactivate-link"', $links['deactivate']);
			}
			return array_merge($plugin_links, $links);
		}

		/**
		 * Add screen ID
		 */
		public function woocommerce_screen_ids($ids) {
			$ids[] = 'admin'; // For import screen
			return $ids;
		}

		/**
		 * Handle localization
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain('product-import-export-for-woo', false, dirname(plugin_basename(__FILE__)) . '/lang/');
		}


		/**
		 * Register importers for use
		 */
		public function register_importers() {
			register_importer('product_import', 'WebToffee WooCommerce Product Import (CSV)', __('Import <strong>products</strong> to your store via a csv file.', 'product-import-export-for-woo'), 'SEMA_Importer::product_importer');
		}

		function sa_remove_stock_image_sizes( $sizes ) {
			return array( 'woocommerce_thumbnail', 'woocommerce_single', 'woocommerce_gallery_thumbnail' );
		}
		private function hf_user_permission() {
			// Check if user has rights to export
			$current_user = wp_get_current_user();
			$user_ok = false;
			$admin_roles = apply_filters('hf_user_permission_roles', array('administrator', 'shop_manager'));
			if ($current_user instanceof WP_User) {
				$can_users = array_intersect($admin_roles, $current_user->roles);
				if (!empty($can_users)) {
					$user_ok = true;
				}
			}
			return $user_ok;
		}

	}

}

new SEMA_Data_Import();
session_write_close();
//}

// Welcome screen tutorial video --> Move this function to inside the class
//add_action('admin_init', 'impexp_welcome');
/*
if (!function_exists('impexp_welcome')) {

    function impexp_welcome() {
        if (!get_transient('_welcome_screen_activation_redirect')) {
            return;
        }
        delete_transient('_welcome_screen_activation_redirect');
        wp_safe_redirect(add_query_arg(array('page' => 'sema_setting'), admin_url('options-general.php')));
    }

}*/


