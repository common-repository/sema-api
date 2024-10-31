<?php
//error_reporting(E_ERROR); 
set_time_limit(300);

global $wpdb,$api_url;
$_GET=array_merge(array('make'=>'','model'=>'','rebuilt'=>''),$_GET);
	
$make=sanitize_text_field($_GET['make']);
$model=sanitize_text_field($_GET['model']);
$submodel=sanitize_text_field($_GET['submodel']);
$baselink=admin_url('options-general.php?page=sema_import&section=fitment');
$make && $breadscrumb="<a href=\"$baselink\">All</a> > <a href=\"$baselink&make=$make\">$make</a>";
$model && $breadscrumb.=" > <a href=\"$baselink&make=$make&model=$model\">$model</a>";
$submodel && $breadscrumb.=" > <a href=\"$baselink&make=$make&model=$model&submodel=$submodel\">$submodel</a>";
$breadscrumb && $breadscrumb="<span class=\"sema-breadcrumb\">$breadscrumb</span>";

$options = get_option( 'sema_settings' );

$arrYMMS=[];
$rebuild=(!array_key_exists('updatefitment',$options) || $options['updatefitment'] || $_GET['rebuild']=='true')?true:false;


/* **** $show_engine ****
todo: show show in fitment management
*/
$show_engine=false;
if($_REQUEST['type']=='buildfitment'){
	$make=$_REQUEST['make'];
	$url="$api_url/ajax.php?siteid=$siteid&type=wp_rebuild_fitment&show_engine=$show_engine&make=$make";
	$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
	$response=json_decode($response['body'],true);
	if ($response['success']){
		$message="$make Update Succeeded";
	}else $message="$make Update Failed";
	echo "<!--WC_START-->";
	echo $message;
	echo "<!--WC_END-->";
	return; 
}
if($rebuild){
	$url="$api_url/ajax.php?siteid=$siteid&type=wp_rebuild_fitment&show_engine=$show_engine&cleanup=true";
	$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
	$response=json_decode($response['body'],true);
	if ($response['success']){
		$makes=$response['makes'];
		$datastr="'".implode("','",explode(',',$makes))."'";
		$options['updatefitment']='0';
		update_option( 'sema_settings', $options );
	}
}
/*
$rebuild=($options['updatefitment'])?$options['updatefitment']:false;
$url="$api_url/ajax.php?siteid=$siteid&type=wp_rebuild_fitment&make=$make&model=$model&submodel=$submodel&rebuild=$rebuild&show_engine=$show_engine";
$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout'=>60,));
$response=json_decode($response['body'],true);
if ($response['success']){
	$arrYMMS=$response['ymms'];
	if($rebuild){
		$options['updatefitment']='0';
		update_option( 'sema_settings', $options );
	}
}*/
if($rebuild==false){
	$url="$api_url/ajax.php?siteid=$siteid&type=wp_get_fitment&make=$make&model=$model&submodel=$submodel&show_engine=$show_engine";
	$response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
	$response=json_decode($response['body'],true);
	if ($response['success']){
		$arrYMMS=$response['ymms'];
	}
}

//$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );
wp_enqueue_style('sema-css-main', plugins_url('/css/main.css', __FILE__));

?>
<script>var ajax_url='<?php echo(esc_url($ajax_url)); ?>';</script>
<div class="woocommerce">
    <div class="pipe-main-box">
		<input type="hidden" name="fitmentids" id="fitmentids" value="">
		<h3 style="display:inline-block">Fitments</h3><?=$breadscrumb?>
		<table class="wp-list-table widefat fixed striped tags" id="fitment-table">
			
			<?php
			$arr= array( 'tr' => array('class'=>array(),'make'=>array(),'model'=>array(),'submodel'=>array()), 
			'th' => array('class'=>array(),'colspan' => array()), 
			'td' => array('class' => array()), 
			'span' => array('class' => array(),'data-tip' => array()), 
			'a' => array('href' => array()), 
			'div' => array('class' => array()), 
			'input' => array('id' => array(),'type' => array(),'class' => array(),'onclick' => array(),'value' => array(),'restore' => array()) );

			if(count($arrYMMS)>0){
				$makemodel=$modelvalue=$submodelvalue='';
				if($model){//model & submodel
					echo('<tr><th class="manage-column" width="100px"><label>Make</label></th><th class="manage-column" width="150px"><label>Model</label></th><th class="manage-column" width="200px"><label>Sub-model</label></th><th class="manage-column" width="100px"><label>Engine</label></th><th class="manage-column" width="*"><label>Years</label></th><th class="manage-column" width="100px"><label>Products</label></th><th class="manage-column" width="80px"><label>Action</label></th></tr>');
					foreach($arrYMMS as $b){
						$background="";
						$fitmentid=$b['id'];
						$editlink = admin_url("options-general.php?page=sema_import&section=fitment_edit&fitmentid=$b[id]&make=$b[make]&model=$b[model]");

						if($b['custom']=='1'){
							$background=" style='background:aliceblue'";
						}
						$modelhide=($modelvalue==$b['model'])?"hidden":"";
						$submodelhide=($submodelvalue==$b['submodel'])?"hidden":"";
						$strike=($b['custom']==-1)?"strike":"";
						$row="<tr class=\"$strike\" $background make='$b[make]' model='$b[model]' submodel='$b[submodel]'><td><span class='$modelhide'><a href=\"$baselink&make=$b[make]\">$b[make]</a></span></td><td><span class='$modelhide'><a href=\"$baselink&make=$b[make]&model=$b[model]\">$b[model]</a></span></td><td><span class='$submodelhide'><a href=\"$baselink&make=$b[make]&model=$b[model]&submodel=$b[submodel]\">$b[submodel]</a> ";
						if(empty($strike)) $row.="<input type='button' class='button button-secondary submodel-delete' value='Delete' restore=0>";
						else $row.="<input type='button' class='button button-secondary submodel-delete' value='Restore' restore=1>";
						$row.="</span></td><td>$b[liter]</td><td>$b[year]</td><td>$b[products]</td>
						<td class='td_action'>";
						if(empty($strike)) $row.="<input type='button' class='button button-primary' value='Assign' onclick='location.href=\"$editlink\"'></td></tr>";
						else $row.="</td></tr>";
												
						$modelvalue=$b['model'];
						$submodelvalue=$b['submodel'];
						echo(wp_kses( $row, $arr ));
					}
				}elseif($make){//make
					echo('<tr><th class="manage-column" width="200px"><label>Make</label></th><th class="manage-column" width="300px"><label>Model</label></th><th class="manage-column" width="100px"><label>Products</label></th><th class="manage-column" width="100px"><label>Action</label></th></tr>');
					foreach($arrYMMS as $b){
						$background="";
						$fitmentid=$b['id'];
						if($b['custom']=='1'){
							$background=" style='background:aliceblue'";
						}
						$link = admin_url("options-general.php?page=sema_import&section=fitment&make=$b[make]&model=$b[model]");
						$makehide=($makevalue==$b['make'])?"hidden":"";
						$strike=($b['custom']==-1)?"strike":"";
						if(empty($strike)) $row="<tr $background  make='$b[make]' model='$b[model]'><td><span class='$makehide'><a href=\"$baselink&make=$b[make]\">$b[make]</a></span></td><td><a href='$link'>$b[model]</a></td><td>$b[products]</td><td class='td_action'><input type='button' class='button button-secondary model-delete' value='Delete' restore=0></td></tr>";
						else $row="<tr class=\"strike\" $background  make='$b[make]' model='$b[model]'><td><span class='$makehide'><a href=\"$baselink&make=$b[make]\">$b[make]</a></span></td><td><a href='$link'>$b[model]</a></td><td>$b[products]</td><td class='td_action'><input type='button' class='button button-secondary model-delete' value='Restore' restore=1></td></tr>";
						$makevalue=$b['make'];
					
						echo(wp_kses( $row, $arr ));
					}					
				}else{//all
					echo('<tr><th class="manage-column" width="200px"><label>Make</label></th><th class="manage-column" width="300px"><label>Model</label></th><th class="manage-column" width="100px"><label>Products</label></th><th class="manage-column" width="100px"><label>Action</label></th></tr>');
					foreach($arrYMMS as $b){
						$background="";
						$fitmentid=$b['id'];
						if($b['custom']=='1'){
							$background=" style='background:aliceblue'";
						}
						$strike=($b['custom']==-1)?"strike":"";
						if(empty($strike)) $row="<tr $background make='$b[make]'><td><div><a href=\"$baselink&make=$b[make]\">$b[make]</a></div></td><td><a href='$baselink&make=$b[make]'>$b[model]</a></td><td>$b[products]</td><td class='td_action'><input type='button' class='button button-secondary make-delete' value='Delete' restore=0></td></tr>";
						else $row="<tr class=\"strike\" $background make='$b[make]'><td><div><a href=\"$baselink&make=$b[make]\">$b[make]</a></div></td><td><a href='$baselink&make=$b[make]'>$b[model]</a></td><td>$b[products]</td><td class='td_action'><input type='button' class='button button-secondary make-delete' value='Restore' restore=1></td></tr>";
						$makevalue=$b['make'];
						
						echo(wp_kses( $row, $arr ));
					}					
				}

			}else echo("<tr><th colspan=5>Fitments do not exist. Please update fitment table <a href=\"$baselink&rebuild=true\">HERE</a>. </td><td>");
			?>                            
		</table>
		<br>
        <div class="clearfix"></div>
    </div>
</div>

	
<script>
jQuery(document).ready( function($) {
<?php if($rebuild){ ?>	
	var rows=[<?php echo($datastr); ?>];
	var i=1;
	if(rows.length>0){
		$("#fitment-table tbody").html("");
		var make = rows.shift();
		update_rows( make);
	}
	function update_rows(make){
		$("#loader-account").css('display', 'inline-block');
		$.get('<?=$baselink?>&type=buildfitment&make='+make, function (d) {
			if ( d.indexOf("<!--WC_START-->") >= 0 )	d = d.split("<!--WC_START-->")[1]; // Strip off before after WC_START
			if ( d.indexOf("<!--WC_END-->") >= 0 ) d = d.split("<!--WC_END-->")[0]; // Strip off anything after WC_END
			if(d){
				$("#loader-account").hide();
				$('#fitment-table tbody').append( '<tr id="row-' + i + '" class="error"><td class="status" colspan="5"> -' + d + '</td></tr>' );

				if(rows.length>0){
					var data = rows.shift();
					update_rows( data);
				}else{
					document.location.href='<?=$baselink?>';
				}
				var w = $(window);
				var row = $( "#row-" + i);

				if ( row.length ) {
					w.scrollTop( row.offset().top - (w.height()/2) );
				}

			}else $("#loader-account").hide();
			
		});		
	}

<?php } ?>		
    $("#fitment_batchassign").click(function(){
        var ids=$(".fitmentid:checked").map(function () {return this.value;}).get().join(",");
        $("#fitmentids").val(ids);
        $("#fitment_form").submit()

    });
    $(".fitment_assign").click(function(e){
        var ids=$(this).closest("tr").find(".fitmentid").val();
        $("#fitmentids").val(ids);
        $("#fitment_form").submit()
    });
	$('.submodel-delete').click(function(){
		var tr=$(this).closest('tr')
		var table=tr.closest('table');
		var make=tr.attr("make");
		var model=tr.attr("model");
		var submodel=tr.attr("submodel");
		var trs=$(this).closest('tr').closest('table').find("tr[submodel='"+submodel+"']");
		var restore=$(this).attr('restore');
		var del=confirm("Are you sure you want to delete this model?\n"+make+" "+model+" "+submodel);
		if (del==true){
			$(this).attr('disabled',true);
			$.get(ajax_url+'?action=get_semadata&type=deletefitment&make='+make+'&model='+model+'&submodel='+submodel+'&restore='+restore, function (d) {
				if(d=='true'){
					location.reload();
				}
			});
		}
	});
	$('.model-delete').click(function(){
		var tr=$(this).closest('tr')
		var table=tr.closest('table');
		var make=tr.attr("make");
		var model=tr.attr("model");
		var trs=$(this).closest('tr').closest('table').find("tr[model='"+model+"']");
		var restore=$(this).attr('restore');
		var del=confirm("Are you sure you want to delete this model?\n"+make+" "+model);
		if (del==true){
			$(this).attr('disabled',true);
			$.get(ajax_url+'?action=get_semadata&type=deletefitment&make='+make+'&model='+model+'&restore='+restore, function (d) {
				if(d=='true'){
					location.reload();
				}
			});
		}
	});

	$('.make-delete').click(function(){
		var tr=$(this).closest('tr');
		var make=tr.attr("make");
		var trs=$(this).closest('tr').closest('table').find("tr[make='"+make+"']");
		var restore=$(this).attr('restore');
		var del=confirm("Are you sure you want to delete all fitments for this make?\n"+make);
		if (del==true){
			$(this).attr('disabled',true);
			$.get(ajax_url+'?action=get_semadata&type=deletefitment&make='+make+'&restore='+restore, function (d) {
				if(d=='true'){
					location.reload();
				}
			});
		}
	});

});
</script>
