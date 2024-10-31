var make,model,submodel,year,treedata,ajax_url,textsearchkeyword;
jQuery(document).ready(function ($) {

   var yearselect = $("#year-select");
   var makeselect = $("#make-select");
   var modelselect = $("#model-select");
   var submodelselect = $("#sub-model-select");
   var productselect = $("product-select");
   var productcolumn = $("#col2");
   $('#make-select').find('option').remove().end().append("<option value=''>- Make -</option>");
   if(make) $('#make-select').append("<option value='"+make+"' selected>"+make+"</option>");
   $('#model-select').find('option').remove().end().append("<option value=''>- Model -</option>");
   if(model) $('#model-select').append("<option value='"+model+"' selected>"+model+"</option>");
   $('#sub-model-select').find('option').remove().end().append("<option value=''>- Submodel -</option>");
   if(submodel) $('#sub-model-select').append("<option value='"+submodel+"' selected>"+submodel+"</option>");
   if(textsearchkeyword) $('#sema-text-search-input').val(textsearchkeyword);

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
      'plugins' : ['wholerow']
   })
   .on('changed.jstree', function (e, data) {
      $("#product-filler-img").hide();         
      $( ".category-header" ).remove();
      $( ".productDiv" ).remove();
      $(".header-divider").show();  
      $("#product-type-img").show(); 

      year=$("#year-select option:selected").val();
      make=$("#make-select option:selected").val();
      model=$("#model-select option:selected").val();
      submodel=$("#sub-model-select option:selected").val();

      if(data && data.selected && data.selected.length) {
         if(window.catId!=data.selected[0]){
            window.catId=data.selected[0];
            filter='';
            $("#search-title-div").find('.ags-breadcrumb-filter').remove();
         }
         $('#tree').jstree(true).open_node(data.selected[0]);

         showProducts(data.selected[0],data.node.text,year,make,model,submodel,1);
      }
   })
   .on('ready.jstree', function(e,data){
      //showProducts('','','','','','',0);
      if(year) showProducts('','',year,make,model,submodel,1);
      else showProducts('','',year,make,model,submodel,0);
   })
   ;


   jQuery(document).on('click', '.show-text', function () {

      var $this = $(this);
      var $content = $(this).closest(".more-content");
      if($(this).attr('href')) return;

      var linkText = $this.text().toUpperCase();

      if (linkText === "SHOW ADDITIONAL INFORMATION") {

         $(this).prev('.product-dimensions-div').show();
         $(this).text("Hide Additional Information");

      } else {

         $(this).prev('.product-dimensions-div').hide();
         $(this).text("Show Additional Information");

      };

   });

   /* #region region reset button */
   jQuery(document).on('click', '#reset', function () {
      $("#year-select").val(''); 
      $("#make-select").val(''); 
      $("#model-select").val(''); 
      $("#sub-model-select").val(''); 
      if($("#gs_sticky_ymm").is(":visible")){
         $("#gs_sticky_ymm").click();
      }
   });
   /* #endregionendregion */


   $('#year-select').find('option').remove().end().append("<option value=''>- Year -</option>");
   //Load Available Years on dom ready
   if($('#year-select').length) jQuery.ajax({
      url: ajax_url,
      type: 'post',
      data: {
         action: 'get_semadata',
         type: 'year'
      },
      success: function (data) {
         if (data == '') return;
         var jsonData = JSON.parse(data);
         var yearsAry = jsonData.Years;
         var selectOptions = "";

         if (yearsAry != undefined && yearsAry.length != 0) {
            $('#year-select').find('option').remove().end().append("<option value=''>- Year -</option>");;
            for (var i = 0; i < yearsAry.length; i++) {
               if(yearsAry[i]==year) selectOptions += '<option value = "' + yearsAry[i] + '" selected>' + yearsAry[i] + '</option>';
               else selectOptions += '<option value = "' + yearsAry[i] + '">' + yearsAry[i] + '</option>';

            }

            $(selectOptions).appendTo(yearselect);

         } else {
            $("#search").prop("disabled", true);
         }

      }
   });


   //Query Makes based on year selected
   jQuery(document).on('change', '#year-select', function () {
      var yearselected = $("#year-select option:selected").val();
      //Clear Selections, display loading message   
      $('#make-select').find('option').remove().end().append("<option value=''>- Make -</option>");
      $('#model-select').find('option').remove().end().append("<option value=''>- Model -</option>");
      $('#sub-model-select').find('option').remove().end().append("<option value=''>- Sub-Model -</option>");
      if ($("#year-select").val() != '') {
         //Clear Selections, display loading message   
         $('#make-select').find('option').remove().end().append("<option value=''>- Loading -</option>");
         //$('#model-select').find('option').remove().end().append("<option value=''>- Model -</option>");
         //$('#sub-model-select').find('option').remove().end().append("<option value=''>Select Sub-Model</option>");

         jQuery.ajax({
            url: ajax_url,
            type: 'post',
            data: {
               action: 'get_semadata',
               type: 'make',
               year: yearselected
            },
            success: function (data) {

               var jsonData = JSON.parse(data);
               var makesAry = jsonData.Makes;
               var selectOptions = "<option value=''>- Make -</option>";

               for (var i = 0; i < makesAry.length; i++) {

                  selectOptions += '<option value = "' + makesAry[i] + '">' + makesAry[i] + '</option>';

               }

               $(makeselect).find('option').remove().end().append(selectOptions);

            }
         });

      }

   });

   //Query Models based on Make selected
   jQuery(document).on('change', '#make-select', function () {

      //Clear Selections, display loading message   
      //$('#sub-model-select').find('option').remove().end().append("<option value=''>Select Sub-Model</option>");


      $('#model-select').find('option').remove().end().append("<option value=''>- Model -</option>");
      $('#sub-model-select').find('option').remove().end().append("<option value=''>- Sub-Model -</option>");
      var yearselected = $("#year-select option:selected").text();
      var makeselected = $("#make-select option:selected").val();
      //showProducts('','',yearselected,makeselected,'');
      if ($("#make-select").val() != '') {


         $('#model-select').find('option').remove().end().append("<option value=''>- Loading -</option>");
         jQuery.ajax({
            url: ajax_url,
            type: 'post',
            data: {
               action: 'get_semadata',
               type: 'model',
               year: yearselected,
               make: makeselected
            },
            success: function (data) {

               var jsonData = JSON.parse(data);
               var modelsAry = jsonData.Models;
               var selectOptions = "<option value=''>- Model- </option>";

               for (var i = 0; i < modelsAry.length; i++) {

                  selectOptions += '<option value = "' + modelsAry[i] + '" >' + modelsAry[i] + '</option>';

               }


               $(modelselect).find('option').remove().end().append(selectOptions);

            }
         });

      }

   });
   //Query Sub Models based on Model selected
   jQuery( document ).on( 'change', '#model-select', function() {
     
      $('#sub-model-select').find('option').remove().end().append("<option value=''>- Loading -</option>");
      if($("#make-select").val() != ''){
           var yearselected = $( "#year-select option:selected" ).text();
           var makeselected = $( "#make-select option:selected" ).val(); 
           var modelselected = $( "#model-select option:selected" ).val();   
   
           jQuery.ajax({
           url : ajax_url,
           type : 'post',
           data : {
             action : 'get_semadata',
          type : 'submodel',
             year : yearselected,
             make : makeselected,
             model : modelselected
           },
           success : function( data ) {
              
                  var jsonData = JSON.parse(data);
                  var subModelsAry = jsonData.Submodels;
                  var selectOptions = "<option value=''>- Sub-Model -</option>";
   
                  for(var i = 0; i < subModelsAry.length; i++){
                      selectOptions += '<option value = "'+subModelsAry[i]+'">'+subModelsAry[i]+'</option>';
                  }
         
                  $(submodelselect).find('option').remove().end().append(selectOptions);
         
           }
         });
   
     }
      
   });


   //search products from given year/brand/model/submodel
   jQuery(document).on('click', '#search', function (e) {
      var yearselected = $( "#year-select option:selected" ).val();
      var makeselected = $( "#make-select option:selected" ).val(); 
      var modelselected = $( "#model-select option:selected" ).val();   
      var submodelselected = $( "#sub-model-select option:selected" ).val();   
      if(yearselected){
         showProducts('','',yearselected,makeselected,modelselected,submodelselected,1);
      }

   });

   //text search button
   $("#sema-text-search-input").keyup(function(event) {
      if (event.keyCode === 13) {
         $("#sema-text-search-button").click();
      }
   });

   //text search button
   jQuery(document).on('click', '#sema-text-search-button', function (e) {
      textsearchkeyword = $( "#sema-text-search-input" ).val().trim();
      showProducts('','','','','','',1);

   });
   var sema_product_attribute_max=10;
	function activateFilter(){
		var coll = document.getElementsByClassName("filtercollapsible");
		var i;
		for (i = 0; i < coll.length; i++) {
			coll[i].addEventListener("click", function() {
				this.classList.toggle("filteractive");
				var content = this.nextElementSibling;
				var seemore = this.nextElementSibling.nextElementSibling;
				if (content.style.maxHeight){
				   content.style.maxHeight = null;
               seemore.classList.remove('active');
				} else {
				   //content.style.maxHeight = content.scrollHeight + "px";
               content.style.maxHeight = 30*sema_product_attribute_max + "px";
               if(content.scrollHeight>30*sema_product_attribute_max) seemore.classList.add('active');
				} 
			});
			if(i<3){
            coll[i].click();
         }
		}
		$('.fitler-see-more').on('click', function(){
         var content = this.previousElementSibling;
         if (content.style.maxHeight){
            maxheight = parseInt(content.style.maxHeight);
            if(maxheight>30*sema_product_attribute_max){
               content.style.maxHeight = 30*sema_product_attribute_max + "px";
               this.innerText = 'See More +';
            }else{
               content.style.maxHeight = content.scrollHeight + "px";
               this.innerText = 'See Fewer -';
            }
         } 
      });

		$('input[class="sa_filter_value"]').on('click', function(){
         filter=$(".sa_filter_value:checked").map(function () {return encodeURIComponent(this.value);}).get().join("|;");
			showProducts('','','','','','',1);
      });
      $('.ags-sticky-remove-filter').click(function(e) {
         var filtervalue = $(this).parent().children("#sa_filter_other").attr("filter-value");
         //$(this).parent().hide();
         $("input[class=sa_filter_value][value='"+filtervalue+"']").remove();
         //$(this).parent().remove();
         filter=$(".sa_filter_value:checked").map(function () {return this.value;}).get().join("|;");
         showProducts('','','','','','',1);
      });       
      $('.ags-sticky-remove').click(function(e) {
         if($(this).parent().attr('id')=='filter_category'){
            $(this).parent().hide();
            $('#tree').jstree(true).deselect_all();
            window.catId='';
            showProducts('','','','','','',1);
         }else if($(this).parent().attr('id')=='filter_keyword'){
            $(this).parent().hide();
            window.textsearchkeyword='';
            $('#sema-text-search-input').val('');
            showProducts('','','','','','',1);
         }else if($(this).parent().attr('id')=='filter_YMM'){
            $(this).parent().hide();
            window.year='';window.make='';window.model='';window.submodel='';
            $("#year-select").val("");
            $("#make-select").find('option[value!=""]').remove();
            $("#model-select").find('option[value!=""]').remove();
            $("#sub-model-select").find('option[value!=""]').remove();
            showProducts('','','','','','',1);
         }
      }); 
   }	

	function showProducts(pcatId,pcatname,pyear,pmake,pmodel,psubmodel,set){
		$("#loader-div-out").show();
      //$('#tree').jstree(true).refresh();
		if(pcatId!='') catId=pcatId;
		if(pyear){
			year=pyear;
			make=pmake;
			model=pmodel;
			submodel=psubmodel;
			$("#filter_YMM_value").text(year+' '+make+' '+model+' '+submodel+' ');
			$("#filter_YMM").show();
		}
		if(pcatname){
			$("#filter_category_value").text(pcatname+' ');
			$("#filter_category").show();
      }
		if(textsearchkeyword){
			$("#filter_keyword_value").text(textsearchkeyword+' ');
			$("#filter_keyword").show();
      }
      $('#pagination-product').hide();
		//$.get(ajax_url+'&action=get_semadata&type=productbycat&set=' + set + '&catId=' + catId +'&year='+year+'&make='+make+'&model='+model+'&submodel='+submodel+'&filters='+filter+'&keyword='+textsearchkeyword, function (d) {
      $.get(ajax_url+'?action=get_semadata&type=productbycat&set=' + set + '&catId=' + catId +'&year='+year+'&make='+make+'&model='+model+'&submodel='+submodel+'&filters='+filter+'&keyword='+textsearchkeyword, function (d) {
			var jsonData = JSON.parse(d);
			var pageAry=jsonData.Pagination;
			var filtersAry=jsonData.Filters;
			var filtersCheckedAry=jsonData.Filters_checked;
         var container = $('#pagination-product');
         var filterTags="";
         var filterDisplay="<div class='product-header'>More Filters</div>\r\n";
         var presetAry = jsonData.Preset;
         if(filtersCheckedAry){
         filtersCheckedAry = filtersCheckedAry.split('|;');
            for(var i = 0; i < filtersCheckedAry.length; i++){
               $sFilterChecked = filtersCheckedAry[i];
               $sFilterChecked = $sFilterChecked.split('=');
               filterDisplay += "		<input style='display:none' type='checkbox' class='sa_filter_value' value='"+filtersCheckedAry[i]+"' checked>\r\n";
               filterTags += "<div id='filter_other' class='ags-breadcrumb-filter' >\r\n";
               filterTags += "   <span id='sa_filter_other_label' class='ags_sticky_link_label'>"+$sFilterChecked[0]+": </span>\r\n";
               filterTags += "   <span id='sa_filter_other' class='ags_sticky_link_value' filter-value='"+filtersCheckedAry[i]+"'>"+$sFilterChecked[1]+"</span>\r\n";
               filterTags += "   <a class='ags-sticky-remove-filter'>[x]</a>\r\n";
               filterTags += "</div>\r\n";
            }
         }
         if(presetAry){
            if(jsonData.Filters_checked){
               filter=jsonData.Filters_checked;
            }
            if(presetAry.year){
               year=presetAry.year;make=presetAry.make;model=presetAry.model;
               $("#filter_YMM_value").text(presetAry.year+' '+presetAry.make+' '+presetAry.model+' ');
               $("#filter_YMM").show();
            }
            if(presetAry.catName){
               catId=presetAry.catId;
               $('#tree').jstree(true).select_node(presetAry.catId,true);
               $('#tree').jstree(true).open_node(presetAry.catId);
               text=$('#tree').jstree(true).get_text(presetAry.catName);
               $("#filter_category_value").text(text+' ');
               $("#filter_category").show();
            }
            //$('#tree').jstree(true).refresh_node(presetAry.catName);
         }
         //$("#search-title-div").find('#filter_other').remove().end().append(filterTags);
         $("#search-title-div").find('.ags-breadcrumb-filter').remove();
         $("#search-title-div").append(filterTags);
         filter_maxrow=5;
			for(var i = 0; i < filtersAry.length; i++){
				var attr_valueAry = filtersAry[i].attr_values.split('|;');
				filterDisplay += "	<div class='product-filter'>\r\n";
				filterDisplay += "		<button class='filtercollapsible'>"+filtersAry[i].attr_name+"</button>\r\n";
				filterDisplay += "		<div class='filter-content'>\r\n";
				for(var j = 0; j < attr_valueAry.length; j++){
					filterDisplay += "		<div class='fitler-option'><input type='checkbox' class='sa_filter_value' value='"+filtersAry[i].attr_name+"="+attr_valueAry[j]+"'>"+attr_valueAry[j]+"</div>\r\n";
				}
            filterDisplay += "		</div>\r\n";
            filterDisplay += "		<div class='fitler-see-more"+((attr_valueAry.length>filter_maxrow && i<3)?" active":"")+"'>See More +</div></div>\r\n";
				filterDisplay += "	</div>\r\n";
			}
			filterDisplay += "</div>\r\n";
         $( ".productDiv" ).remove();
			$('#product-filter-list').html(filterDisplay);
         activateFilter();
         //var jsonData = JSON.parse(response);
         //var productsAry = jsonData.Products;
         var allProductsDisplay = "<div class = 'productDiv'><div>--- No parts found matching your vehicle description ---</div></div>";
         var categoryDisplay = '';
         categoryDisplay = buildCategoryDislay(jsonData.Products,''); 
         if(categoryDisplay)	$(categoryDisplay).appendTo($("#search-main")); 
         else{
            $("<div class = 'productDiv'>No products found matching your vehicle description</div>").appendTo($("#search-main"));
            $('#pagination-product').hide();
         }
         $("#loader-div-out").hide();
         $(".search-title-text").html(pageAry.text);
         if(pageAry.count>0){

            container.pagination({
               dataSource: ajax_url+'?action=get_semadata&type=productbycat&set='+set+'&catId='+pageAry.catId+'&year='+year+'&make='+make+'&model='+model+'&submodel='+submodel+'&filters='+filter+'&keyword='+textsearchkeyword,
               locator: 'Products',
               totalNumber: pageAry.count,
               pageSize: pageAry.size,
               pageNumber: pageAry.pageNumber,
               triggerPagingOnInit:false,
               ajax: {
                  beforeSend: function() {
                     $("#loader-div-out").show();
                  }
               },
               
               callback: function(response, pagination) {
                  $( ".productDiv" ).remove();
                  $("#loader-div-out").hide();
                  //var jsonData = JSON.parse(response);
                  //var productsAry = jsonData.Products;
                  var allProductsDisplay = "<div class = 'productDiv'><div>--- No parts found matching your vehicle description ---</div></div>";
                  var categoryDisplay = '';
                  searchtext = $(".search-title-text").html();
                  searchtextArr = searchtext.split("of");
                  var total = searchtextArr[1].replace('results', '').trim();
                  searchtext = (pagination.pageSize*(pagination.pageNumber-1)+1)+" - "+Math.min((pagination.pageSize*pagination.pageNumber),total)+" of"+searchtextArr[1];
                  $(".search-title-text").html(searchtext);
                  categoryDisplay = buildCategoryDislay(response,''); 
                  if(categoryDisplay)	$(categoryDisplay).appendTo($("#search-main")); 

               }
            });
            container.show();

         }
		});
	}

	function buildCategoryDislay(productsAry,displayName){
		var productsDisplay = '';
      //productsDisplay = '<span class = "category-header">' + displayName + '</span>'; 
      productsDisplay = ''; 
		//get indexes of products that qualify
		for(var i = 0; i < productsAry.length; i++){
			//for(var j = 0; j < productsAry[i].PiesAttributes.length; j++){
			// modifed by Steven 8/22/2019
			// when j=0, PiesAttributes[j].PiesSegment is null

         var price = (productsAry[i].price)?("$"+parseFloat(productsAry[i].price).toFixed(2)):"" ;
			productsDisplay += "<div class = 'productDiv'>" + 
									"<div class='product-header'><a class='show-text' href='"+productsAry[i].guid+"'><span class='header-desc'><b>" +  productsAry[i].post_title + "</b></span></a></div>" +
										"<div class='product-content-div'>" + 
										"<div class='product-image-div'><a class='show-text' href='"+productsAry[i].guid+"'><img class='product-image' src='" + productsAry[i].image + "'></a></div>" +
										"<div class='product-info-div'>"+
											"<ul>" +
                                    "<li><b>SKU:</b> " + productsAry[i].sku + "</li>" +
                                    "<li><b>Part Name:</b> " + productsAry[i].post_title + "</li>" +
												"<li><b>Description:</b> " + productsAry[i].post_excerpt + "</li>" + 
												"<li><b>Retail Price:</b> " + price + "</li>" +
											"</ul>" + 
											"<div class='more-content'>"+
											"<a class='show-text' href='"+productsAry[i].guid+"/?page="+productsAry[i].sku+"'>Show Additional Information</a>" +
											"</div>" +
										"</div>" +
									"</div>" + 
								"</div>";  


		}
		return productsDisplay;
			
	}

})


var catId='',filter='';


