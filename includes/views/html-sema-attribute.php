<?php
//error_reporting(E_ERROR); 
set_time_limit(300);

global $wpdb;
$options = get_option( 'sema_settings' );

$arrYMMS=[];

$catid=$_REQUEST['catid'];

if($catid){
	$_SESSION['catid']=$catid;
}else{
	$catid=$_SESSION['catid'];
}
if($_REQUEST['type']=='retrieve'){
	$row = $wpdb->get_row(_sql("SELECT t.name,t.term_id as id,tt.name AS kname,tt.term_id AS kid,ttt.name AS kkname,ttt.term_id AS kkid FROM wp_terms t INNER JOIN wp_term_taxonomy x ON t.term_id = x.term_id 
	INNER JOIN wp_term_taxonomy tx ON tx.parent=t.term_id INNER JOIN wp_terms tt ON tt.term_id=tx.term_id
	LEFT JOIN wp_term_taxonomy ttx ON ttx.parent=tt.term_id LEFT JOIN wp_terms ttt ON ttt.term_id=ttx.term_id
	WHERE t.term_group>0 AND x.parent=0 and ttt.term_id=$catid",''),ARRAY_A );
	$catid=$row['kkid'];
	$catname=$row['kkname'];
	if($catid){
		$arrAttrs = $wpdb->get_results($wpdb->prepare(_sql("SELECT attr_name,status,count(DISTINCT product_id) as prodcount,count(DISTINCT attr_value) as attrcount,GROUP_CONCAT(DISTINCT attr_value SEPARATOR ', ') as attrvalues,GROUP_CONCAT(DISTINCT id SEPARATOR ',') as attrids FROM wp_sema_attr_taxonomy WHERE term_id=%d AND status>=1 GROUP BY attr_name having count(DISTINCT attr_value)>1 ORDER BY attr_name"),$catid),ARRAY_A);
		$arrAttrs_deleted = $wpdb->get_results($wpdb->prepare(_sql("SELECT attr_name,count(DISTINCT product_id) as prodcount,count(DISTINCT attr_value) as attrcount,GROUP_CONCAT(DISTINCT attr_value SEPARATOR ', ') as attrvalues,GROUP_CONCAT(DISTINCT id SEPARATOR ',') as attrids FROM wp_sema_attr_taxonomy WHERE term_id=%d AND status=0 GROUP BY attr_name having count(DISTINCT attr_value)>1 ORDER BY attr_name"),$catid),ARRAY_A);
		$arrAttrs_sv = $wpdb->get_results($wpdb->prepare(_sql("SELECT attr_name,count(DISTINCT product_id) as prodcount,count(DISTINCT attr_value) as attrcount,GROUP_CONCAT(DISTINCT attr_value SEPARATOR ', ') as attrvalues,GROUP_CONCAT(DISTINCT id SEPARATOR ',') as attrids FROM wp_sema_attr_taxonomy WHERE term_id=%d AND status>=1 GROUP BY attr_name having count(DISTINCT attr_value)=1 ORDER BY attr_name"),$catid),ARRAY_A);
		
	}	
	?>
	<!--WC_START-->
	<input type="hidden" id="attrids_marked" value=",">
	<input type="hidden" id="catid" value="<?=$catid?>">
	<h3 style="display:inline-block">Attributes - <?=$catname?></h3><span id="loader-div-attributeload" class="loader-div-out" style="display:none"></span>
	<span class="vehicle_buttons"><input type='button' class='button button-primary attribute-hide' value='Hide'></span>
	<table class="wp-list-table widefat fixed striped tags">
	<tr><th class="manage-column" width="30px"><input type='checkbox' id='attribute_checkall' value='' /></th>
		<th class="manage-column" width="200px"><label>Attribute Name</label></th>
		<th class="manage-column" width="100px"><label>Number of Products</label></th>
		<th class="manage-column" width="100px"><label>Number of Values</label></th>
		<th class="manage-column" width="*"><label>Values</label></th><th class="manage-column" width="90px"><label>Action</label> </th></tr>
		<?php
		if($arrAttrs){
			foreach($arrAttrs as $b){
				$background="";
				$link="attribute_edit.php?shop=$shop&catid=$catid&attrname=".urlencode($b['attr_name']);
				$feature=($b['status']>1)?"attr-highlight":"";
				/*
				$arrvalues=explode('|;',$b['attrvalues']);
				$selectoptions="";
				foreach($arrvalues as $v){
					$selectoptions.="	<option selected>$v</option>";
				}*/
				$row="<tr $background><td><input type='checkbox' class='attribute_check' value='$b[attrids]'  width='30px'/><td>$b[attr_name]</td><td>$b[prodcount]</td><td>$b[attrcount]</td>";
				$row.="<td>$b[attrvalues]</td>";
	
				//<p style=\"white-space: pre-line\">$b[attrvalues]</p></td>";
				//$row.="<td class='td_action'><input type='hidden' class='attrids' value='$b[attrids]' text='$b[attr_name]'><input type='button' class='button button-primary' value='Edit' onclick='location.href=\"$link\"'></td>";
				$row.="<td class='td_action'><i class=\"fa fa-star $feature\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Add to feature attributes\"></i></td>";
				
				?>
			
				<?php
				echo($row);
			}

		}else echo("<tr><th colspan=5>No attribute shows up.</td><td>");
		?>                            
	</table>
	<br>
	<div class="clearfix"></div>
	<?php
		if($arrAttrs_deleted || $arrAttrs_sv){
	?>
	<table class="wp-list-table widefat fixed striped tags ">
	<tr class="row-inactive-brand"><th class="manage-column" width="200px"><label>Hidden Attribute</label></th>
		<th class="manage-column" width="100px"><label>Number of Products</label></th>
		<th class="manage-column" width="100px"><label>Number of Values</label></th>
		<th class="manage-column" width="*"><label>Values</label></th><th class="manage-column" width="90px"><label>Action</label></th></tr>
		<?php
		foreach($arrAttrs_deleted as $b){
			$background="";
			$link="attribute_edit.php?shop=$shop&catid=$catid&attrname=".urlencode($b['attr_name']);
			/*
			$arrvalues=explode('|;',$b['attrvalues']);
			$selectoptions="";
			foreach($arrvalues as $v){
				$selectoptions.="	<option selected>$v</option>";
			}*/
			$row="<tr class=\"row-inactive-brand\"><td>$b[attr_name]</td><td>$b[prodcount]</td><td>$b[attrcount]</td>";
			$row.="<td>$b[attrvalues]</td>";

			//<p style=\"white-space: pre-line\">$b[attrvalues]</p></td>";
			$row.="<td class='td_action'><input type='hidden' class='attrids' value='$b[attrids]' text='$b[attr_name]'><input type='button' class='button button-secondary attribute-restore' value='Restore'></td>";
			?>
		
			<?php
			echo($row);
		}
		foreach($arrAttrs_sv as $b){
			$background="";
			$link="attribute_edit.php?shop=$shop&catid=$catid&attrname=".urlencode($b['attr_name']);
			$row="<tr class=\"row-inactive-brand\"><td>$b[attr_name]</td><td>$b[prodcount]</td><td>$b[attrcount]</td>";
			$row.="<td>$b[attrvalues]</td><td class='td_action'>&nbsp;</td>";
			?>
		
			<?php
			echo($row);
		}			
		?>
	</table>	
	<!--WC_END-->
	<?php 
	}	
	return;
}elseif($_REQUEST['type']=='featureattribute'){
	$attrids = sanitize_text_field(trim($_REQUEST['attrids']));
	$catid = sanitize_text_field(trim($_REQUEST['catid']));
	$status = sanitize_text_field(trim($_REQUEST['status']));
	if($attrids){
		$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_attr_taxonomy SET status=%d WHERE term_id=%d AND id in ($attrids) "),$status,$catid,$attrname));
		echo json_encode('<!--WC_START-->true<!--WC_END-->');	
	}else echo json_encode('<!--WC_START-->false<!--WC_END-->');
	return;
}elseif($_REQUEST['type']=='hideattribute'){
	$attrids = sanitize_text_field(trim($_REQUEST['attrids']));
	$attrids = implode(',',wp_parse_id_list($attrids));
	$catid = sanitize_text_field(trim($_REQUEST['catid']));
	if($catid && $attrids){
		$attrlinks=$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_attr_taxonomy SET status=0 WHERE term_id=%d AND id in ($attrids) "),$catid));
	}
	echo json_encode('<!--WC_START-->true<!--WC_END-->');	return;
}elseif($_REQUEST['type']=='restoreattribute'){
	$attrids = sanitize_text_field(trim($_REQUEST['attrids']));
	$attrids = implode(',',wp_parse_id_list($attrids));
	$catid = sanitize_text_field(trim($_REQUEST['catid']));
	if($catid && $attrids){
		$attrlinks=$wpdb->query($wpdb->prepare(_sql("UPDATE wp_sema_attr_taxonomy SET status=1 WHERE term_id=%d AND id in ($attrids) "),$catid));
	}
	echo json_encode('<!--WC_START-->true<!--WC_END-->');	return;
}



$arrReturn = $wpdb->get_results(_sql("SELECT t.name,t.term_id as id,tt.name AS kname,tt.term_id AS kid,ttt.name AS kkname,ttt.term_id AS kkid FROM wp_terms t INNER JOIN wp_term_taxonomy x ON t.term_id = x.term_id 
INNER JOIN wp_term_taxonomy tx ON tx.parent=t.term_id INNER JOIN wp_terms tt ON tt.term_id=tx.term_id
LEFT JOIN wp_term_taxonomy ttx ON ttx.parent=tt.term_id LEFT JOIN wp_terms ttt ON ttt.term_id=ttx.term_id
WHERE t.term_group>0 AND x.parent=0 ORDER BY t.name,kname,kkname",''),ARRAY_A );
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
$node=json_encode($node,true);


//$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("options-general.php") );
wp_enqueue_style('sema-css-main', plugins_url('/css/main.css', __FILE__));

?>
<div class="woocommerce">
<?php $_GET['tab']='attribute';include("menutab.php"); ?>
    <div class="pipe-main-box">
		
		<div id="container1">
			<div id="col1">
				<div class="sema-search-container">
					<input type="text" class="sema-search-box" id="sema-category-search-input" placeholder="Search a Category..">
				</div>			
				<div id="tree">&nbsp;</div>
			</div>
			<div id="col2"></div>
		</div>
		<br>
        <div class="clearfix"></div>
    </div>
</div>

<script>
var shop='<?php echo($shop); ?>';
var catid='<?php echo($catid); ?>';
var ajax_url='<?php echo(esc_url($ajax_url)); ?>?page=sema_import&section=attribute';

jQuery(document).ready(function($){
	if(catid==''){
		catId=getLocalStorageItem('catId');
		if(catId[0]!='C' || catId[0]!='S') catid=catId;	
	}else{
		catId=getSetLocalStoreItem('catId',catid);
	}	
	$('#tree').bind("ready.jstree", function () {
      $('#tree').jstree(true).select_node(catId,true);
      $('#tree').jstree(true).open_node(catId);
	  if(catid) $.get(ajax_url+'&type=retrieve&catid='+catid, function (d) {
		// Get the valid JSON only from the returned string
		if ( d.indexOf("<!--WC_START-->") >= 0 ) d = d.split("<!--WC_START-->")[1]; // Strip off before after WC_START
		if ( d.indexOf("<!--WC_END-->") >= 0 ) d = d.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
		if(d){
			$('#col2').html(d);
			activateFunctions();
		}
	  });		  

	}).bind("select_node.jstree", function (e,data) {
		e.preventDefault();
    	if(data.node.id!=catid) {
			getSetLocalStoreItem('catId',data.node.id);
			if(data.node.id[0]!='C' && data.node.id[0]!='S'){
				//document.location.href=data.node.a_attr.href;
				catid=getSetLocalStoreItem('catId',data.node.id);
				$("#loader-div-attributeload").show();
				$.get(ajax_url+'&type=retrieve&catid='+catid, function (d) {
					if ( d.indexOf("<!--WC_START-->") >= 0 ) d = d.split("<!--WC_START-->")[1]; // Strip off before after WC_START
					if ( d.indexOf("<!--WC_END-->") >= 0 )	d = d.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
					if(d){
						$('#col2').html(d);
						activateFunctions();
					}
				});				
			}else{
				if($('#tree').jstree(true).is_open(data.node.id)) $('#tree').jstree(true).close_node(data.node.id);
				else $('#tree').jstree(true).open_node(data.node.id);
			}
	    }
	}).jstree({
      'core' : {
         'data' : <?php echo($node);?>,
         'force_text' : true,
         'check_callback' : true,
         'themes' : {
            'icons' : false,
            'responsive' : false,
         }
      },
      'plugins' : ['wholerow','activate_node','search'],
   });
   var to = false;
    $('#sema-category-search-input').keyup(function () {
        if(to) { clearTimeout(to); }
        to = setTimeout(function () {
            var v = $('#sema-category-search-input').val();
            $('#tree').jstree(true).search(v);
        }, 250);
    });    
   function activateFunctions(){
		var catid=$("#catid").val();
		$('.attribute-hide').click(function(){
			var attrids=$("#attrids_marked").val();
			$(this).attr('disabled',true);
			$.post(ajax_url+'&type=hideattribute&catid='+catid,{'attrids':attrids}, function (d) {
				if ( d.indexOf("<!--WC_START-->") >= 0 ) d = d.split("<!--WC_START-->")[1]; // Strip off before after WC_START
				if ( d.indexOf("<!--WC_END-->") >= 0 )	d = d.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
				if(d=='true'){
					$.get(ajax_url+'&type=retrieve&catid='+catid, function (d) {
						if ( d.indexOf("<!--WC_START-->") >= 0 ) d = d.split("<!--WC_START-->")[1]; // Strip off before after WC_START
						if ( d.indexOf("<!--WC_END-->") >= 0 )	d = d.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
						if(d){
							$('#col2').html(d);
							activateFunctions();
						}
					});
				}else{
					$(".attribute-hide").attr('disabled',false);
				}
			});

		});
		$('.attribute-restore').click(function(){
			var tr=$(this).closest('tr')
			var attrids=tr.find('.attrids').val();
			var attrname=tr.find('.attrids').attr('text');
			//var del=confirm("Are you sure you want to restore this attribute?\n"+attrname);
			var restorebutton=$(this);
			//if (del==true){
				restorebutton.attr('disabled',true);
				$.get(ajax_url+'&type=restoreattribute&catid='+catid+'&attrids='+attrids, function (d) {
					if ( d.indexOf("<!--WC_START-->") >= 0 ) d = d.split("<!--WC_START-->")[1]; // Strip off before after WC_START
					if ( d.indexOf("<!--WC_END-->") >= 0 )	d = d.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
					if(d=='true'){
						//tr.remove();
						$.get(ajax_url+'&type=retrieve&catid='+catid, function (d) {
							if ( d.indexOf("<!--WC_START-->") >= 0 ) d = d.split("<!--WC_START-->")[1]; // Strip off before after WC_START
							if ( d.indexOf("<!--WC_END-->") >= 0 )	d = d.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
							if(d){
								$('#col2').html(d);
								activateFunctions();
							}
						});
					}else{
						restorebutton.attr('disabled',false);
					}
				});
			//}
		});	
		$("input:checkbox:not(:disabled).attribute_check").change(function(){
			var attrids_marked=$("#attrids_marked").val();
			if($(this).is(":checked")) {
				$("#attrids_marked").val(attrids_marked+$(this).val()+',');
			}else{
				$("#attrids_marked").val(attrids_marked.replace($(this).val()+',',''));
			}
		});
		$("#attribute_checkall").change(function () {
			$("#attrids_marked").val(',');
			$("input:checkbox:not(:disabled).attribute_check").prop('checked', $(this).prop("checked")).change();
		});	
		$('.fa-star').on('click',function(){
			$(this).toggleClass('attr-highlight');
			var status=1;
			var attrids=$(this).closest('tr').find('[type=checkbox]').val();
			if($(this).hasClass('attr-highlight')) status=100;
			$.get(ajax_url+'&type=featureattribute&catid='+catid+'&attrids='+attrids+'&status='+status, function(){});	
		});
	}
			
	function getLocalStorageItem(itemName){
		item=window.localStorage.getItem(itemName);
		if(item===null) return '';
		else return item;
	}
	function getSetLocalStoreItem(itemName,itemValue){
		if(itemValue===null){
			var value=window.localStorage.getItem(itemName);
			if(value==null) return '';
			else return window.localStorage.getItem(itemName);
		}else{
			window.localStorage.setItem(itemName,itemValue);
			return itemValue;
		}
	}	
});

</script>
