<?php
//error_reporting(E_ERROR); 

global $wpdb;
$arrYMMS=array();


//$plugin_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', WP_PLUGIN_URL );
$ajax_url=preg_replace( '|https?://[^/]+(/.*)|i', '$1', admin_url("admin-ajax.php") );

wp_enqueue_script('sema-js-backend', plugins_url( '/js/semasearch-backend.js', __FILE__ ),array('jquery','jquery-ui-dialog','jquery-ui-autocomplete','jquery-ui-widget', 'jquery-ui-position'));
wp_enqueue_style('sema-css-main', plugins_url('/css/main.css', __FILE__));

?>

<div class="woocommerce">

<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
    	<a href="<?php echo admin_url('options-general.php?page=sema_setting'); ?>" class="nav-tab nav-tab"><?php _e('Settings', 'product-import'); ?></a>
        <a href="<?php echo admin_url('options-general.php?page=sema_import'); ?>" class="nav-tab nav-tab"><?php _e('Data Import', 'product-import'); ?></a>
        <a href="<?php echo admin_url('options-general.php?page=sema_import&section=fitment'); ?>" class="nav-tab nav-tab-active"><?php _e('Fitment', 'product-import'); ?></a>
        <a href="https://www.semadata.org/resellers" target="_blank" class="nav-tab nav-tab"><?php _e('Join SEMA Data Co-op', 'product-import'); ?></a>
    </h2>
    <div class="pipe-main-box">
		<div class="ymms-bar">
			<input type="text" value="" id="suggest-mms" placeholder="Search Make Model Submodel here. e.g. Ford F-150"/>
			<input type="hidden" id="suggest-mms-value" />
		</div>
		<div class="ymms-bar">
			<select id="year-select" data-placeholder="Years" multiple class="chosen-select" tabindex="8" style="display:none">
	        </select>

		</div>
		<div class="ymms-bar">
			<button class="dba-search-btn" id="newfitment-add">Add</button>
		</div>
		<div class="ymms-content">
			<table id="fitment-table" class="wp-list-table widefat fixed striped tags">
				<tr>
					<td class="manage-column" width="5%"><input type="checkbox" id='fitmentids' id='fitmentid'/></td>
					<th class="manage-column" width="10%"><label>Make</label></th>
					<th class="manage-column" width="15%"><label>Model</label></th>
					<th class="manage-column" width="15%"><label>Sub-model</label></th>
					<th class="manage-column" width="55%"><label>Years</label>
					<span class="button-ymms-save">
						<div id="loader-newfitment-save" class="loader-div-out" style="display:none"></div>
						<i id="newfitment-save" class="fas fa-save" data-toggle="tooltip" data-placement="bottom" title="Save fitment"></i> </span>
					</th>
				</tr>                        
				<tr id="introduction"><td colspan="5">
				  <ul class="fitment-bulletin">
					<li>
						<span class="fa-stack fa-2x">
							<i class="fas fa-search fa-stack-2x" style="color:green"></i>
							<i class="fas fa-car fa-stack-1x"></i>
						</span>
						<div class="fitment-bulletin-text">1. Start by searching Make, Model and Sub-model.  Select Years and create a new fitment.</div></li>
					<li>
						<span class="fa-stack fa-2x">
							<i class="fas fa-check fa-stack-2x" style="color:green"></i>
							<i class="fas fa-car fa-stack-1x"></i>
						</span>						
						<div class="fitment-bulletin-text">2. Check fitments you want and save them.</div></li>
				  </ul>
				</td></tr>
			</table>
			<br>
			<div class="clearfix"></div>
		</div>

        <div class="clearfix"></div>
    </div>
</div>

</div>

	

<script>
jQuery(document).ready( function($) {	
	$(".chosen-select").chosen({width: "100%"});
	$("#suggest-mms").
	autocomplete({
		minLength: 3,html: true,
		source : function( request, response ) {
			host=$(location).attr('host');

			$.get({
				url: "https://demo.semadata.org/wp-json/semarestservice/rest_endpoint/?type=suggestmms&keyword="+request.term+'&host='+host,
				dataType: "json",
				type: 'GET',
				success: function (data) {
					data=$.parseJSON(data);
                    var success = data['success'];
                    if (success == true && data['mms']!=null) {
						var result = new Array(data['mms'].length);
						$(data['mms']).each(function (idx, n) {
							result[idx] = {
								label: n['mms'],
								value: n['mms2'],
								data: n
							};
						});						
                    }					
					response( result );
				}
			});
		},
		focus: function (event, ui) {
			/*$("#suggest-mms").val(ui.item.label);*/
			return false;
		},
		select: function (event, ui) {
			$("#suggest-mms").val(ui.item.label.replace( /(<([^>]+)>)/ig, ''));
			$("#suggest-mms-value").val(ui.item.value);                    
			//$('.chosen-choices li[class=search-choice]').remove();
			$(".chosen-select").empty().trigger('chosen:updated');
			$(".chosen-choices").addClass("ui-autocomplete-loading");
			host=$(location).attr('host');
			$.get({
				url: "https://demo.semadata.org/wp-json/semarestservice/rest_endpoint/?type=suggestyears&keyword="+ui.item.value+'&host='+host,
				dataType: "json",
				type: 'GET',
				success: function (data) {
					data=$.parseJSON(data);
					var success = data['success'];
					var selectOptions = "";
                    if (success == true && data['years']!=null) {
						$(data['years']).each(function (idx, n) {
               				selectOptions += '<option value = "' + n['year'] + '" selected>' + n['year'] + '</option>';
						});
						$(".chosen-select").empty().append(selectOptions).trigger('chosen:updated');
				
                    }					
					$(".chosen-choices").removeClass("ui-autocomplete-loading");
				}
			});

			return false;
		}
	});
	$("#newfitment-add").click(function(e){
		var mms=$("#suggest-mms-value").val();
		var years=$("#year-select").val().join(" ");
		if(mms && years){
			$("#fitment-table tr#introduction").remove();
			arrmms=mms.split("|/");
			row='<tr><td><input type="checkbox" value="'+mms+'|/'+years+'" class="checkbox-fitment" checked></td><td>'+arrmms[0]+'</td><td>'+arrmms[1]+'</td><td>'+arrmms[2]+'</td><td>'+years+'</td></tr>';
			$("#fitment-table").append(row);
			$("#suggest-mms-value").val("");
			$("#suggest-mms").val("");
			$(".chosen-select").empty().trigger('chosen:updated');
		}
	});


	$("#newfitment-save").click(function(e){
		$("#loader-newfitment-save").show();
		var fitments=$("input:checkbox:checked.checkbox-fitment").map(function () {
			return this.value;
		}).get().join("|,");
		if(fitments){
			$.get("<?php echo html_entity_decode(wp_nonce_url("$ajax_url?action=get_semadata&type=addfitment"));?>&fitments="+fitments, function (d) {
								//$('#data .default').text(d.content).show();
				document.location.href="options-general.php?page=sema_import&section=fitment&t="+Date.now();
				$("#loader-newfitment-save").hide();

			});
		}else setTimeout(function() { $("#loader-newfitment-save").hide(); }, 500);
	});

	/*

    function assigntoProducts() {
      var valid = true;
      //allFields.removeClass( "ui-state-error" );
 
      valid = valid && checkLength( name, "username", 3, 16 );
      valid = valid && checkLength( email, "email", 6, 80 );
      valid = valid && checkLength( password, "password", 5, 16 );
 
      valid = valid && checkRegexp( name, /^[a-z]([0-9a-z_\s])+$/i, "Username may consist of a-z, 0-9, underscores, spaces and must begin with a letter." );
      valid = valid && checkRegexp( email, emailRegex, "eg. ui@jquery.com" );
      valid = valid && checkRegexp( password, /^([0-9a-zA-Z])+$/, "Password field only allow : a-z 0-9" );
 
      if ( valid ) {
        $( "#users tbody" ).append( "<tr>" +
          "<td>" + name.val() + "</td>" +
          "<td>" + email.val() + "</td>" +
          "<td>" + password.val() + "</td>" +
        "</tr>" );
        dialog.dialog( "close" );
      }
      return valid;
    }
 
    dialog = $( "#dialog-form" ).dialog({
      autoOpen: false,
      height: 400,
      width: 350,
      modal: true,
      buttons: {
        "Confirm": assigntoProducts,
        Cancel: function() {
          dialog.dialog( "close" );
        }
      },
      close: function() {
        form[ 0 ].reset();
        //allFields.removeClass( "ui-state-error" );
      }
    });
 
    form = dialog.find( "form" ).on( "submit", function( event ) {
      event.preventDefault();
      assigntoProducts();
    });
 
    $( "#assignto" ).button().on( "click", function() {
      dialog.dialog( "open" );
    });	
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
