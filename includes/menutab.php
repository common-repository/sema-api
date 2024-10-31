<?php 
global $membership,$subscription,$api_url;
$tab = $_GET['tab'];
$time=time();

$options = get_option( 'sema_settings' );
$siteid=$options['siteid'];
if($siteid){
    $fields=array();
    if(is_array($options['sema_mandatory_update']) && array_key_exists('fitment',$options['sema_mandatory_update'])) $fields[]='fitment';
    if(is_array($options['sema_mandatory_update']) && array_key_exists('attribute',$options['sema_mandatory_update'])) $fields[]='attribute';
    $fields=implode(',',$fields);
    $url="$api_url/ajax.php?siteid=$siteid&type=wp_get_notices&membership=$membership";
    $response = wp_remote_get($url, array('sslverify' => FALSE,'timeout' => '60'));
	$response=json_decode($response['body'],true);
	if ($response['success']){
		$notices=$response['notices'];
	} 
}	

if($membership==10){
?>
    <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
        <a href="<?php echo admin_url('options-general.php?page=sema_setting'); ?>" class="nav-tab <?php if($tab=='option') echo("nav-tab-active");?>"><?php _e('Settings', 'product-import'); ?></a>
        <a href="<?php echo admin_url('options-general.php?page=sema_import'); ?>" class="nav-tab <?php if($tab=='import') echo("nav-tab-active");?>"><?php _e('Data Import', 'product-import'); ?></a>
<?php 
if($subscription['aces']) echo("<a href=\"".admin_url('options-general.php?page=sema_import&section=fitment')."\" class=\"nav-tab ".(($tab=='fitment')?'nav-tab-active':'')."\">Fitment</a>");
if($subscription['uiux']) echo("<a href=\"".admin_url('options-general.php?page=sema_import&section=attribute')."\" class=\"nav-tab ".(($tab=='attribute')?'nav-tab-active':'')."\">Attribute</a>"); 
?>
        <a href="https://www.semadata.org/resellers" target="_blank" class="nav-tab nav-tab-premium"><?php _e('Join SEMA Data Co-op', 'product-import'); ?></a>
        <span id="loader-account" class="loader-div-out" style="display:none;"></span>        
    </h2>
    
<?php 
    foreach($notices as $n){
        $noticeicon=[1=>'success',2=>'warning',3=>'error'];
        echo("<div id=\"\" class=\"notice notice-".$noticeicon[$n['status']]."\" style=\"position: relative; margin-left: 0;\"><a class=\"notice-dismiss\"><span class=\"screen-reader-text\">Dismiss this notice.</span></a><p>$n[message]</p></div>");
    }

}else{ ?>
    <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
		<a href="<?php echo admin_url('options-general.php?page=sema_setting'); ?>" class="nav-tab <?php if($tab=='option') echo("nav-tab-active");?>"><?php _e('Settings', 'product-import'); ?></a>
        <a href="<?php echo admin_url('options-general.php?page=sema_import'); ?>" class="nav-tab <?php if($tab=='import') echo("nav-tab-active");?>"><?php _e('Data Import', 'product-import'); ?></a>
        <a href="https://www.semadata.org/resellers" target="_blank" class="nav-tab nav-tab-premium"><?php _e('Join SEMA Data Co-op', 'product-import'); ?></a>
        <span id="loader-account" class="loader-div-out" style="display:none;"></span>        
    </h2>
<?php
foreach($notices as $n){
    $noticeicon=[1=>'success',2=>'warning',3=>'error'];
    echo("<div id=\"\" class=\"notice notice-".$noticeicon[$n['status']]."\" style=\"position: relative; margin-left: 0;\"><a class=\"notice-dismiss\"><span class=\"screen-reader-text\">Dismiss this notice.</span></a><p>$n[message]</p></div>");
}
}?>