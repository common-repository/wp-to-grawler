<?php
/*
Plugin Name: WP to Grawler
Version: 0.9.0
Plugin URI: http://grawler.com/
Description: A one click way to share your geolocalized content into a mobile app using the Grawler API.
Author: Cosmix
Author URI: http://cosmix.fr/
*/

add_action('add_meta_boxes', 'wp2grwlr_add_box');
add_action( 'wp_ajax_save_grwlr_credential', 'saveGrwlrCredential_callback' );
add_action( 'wp_ajax_delete_grwlr_credential', 'deleteGrwlrCredential_callback' );
add_action( 'wp_ajax_grwlr_confirm', 'saveGrwlrConfirm' );
add_action( 'admin_init', 'grwlr_admin_init' );

function grwlr_admin_init(){
	wp_enqueue_script( 'leaflet', trailingslashit( plugin_dir_url( __FILE__ ) ).'assets/leaflet.js', array(), '1.0.0', true );
	wp_enqueue_style( 'leaflet', trailingslashit( plugin_dir_url( __FILE__ ) ).'assets/leaflet.css' );
}

function wp2grwlr_add_box(){
		add_meta_box('wp2grwlr_box',__('Grawler', 'wp-to-grawler'),'display_wp2grwlr_box','post','normal','high');
}

function display_wp2grwlr_box(){
		//initialization 
		$apiParameters = (get_option('wp2grwlrApiParam')) ? get_option('wp2grwlrApiParam') : '';
		$available_lang = array('en' => 'English', 'fr' => 'Français'); //officialy supported language by Grawler

		//No config yet => setup
		if($apiParameters == ''){
		?>
			<div id="grwlr-connexionarea">
				<form>	
					<h2>Login / Pwd connexion</h2>
					<input id="grwlr_auth_key" placeholder="login (email)" />
					<input id="grwlr_pwd" value="pwd hello world" type="password" />
					<button id="grwlr_connect_button" type="button">Connect to Grawler</button>
				</form>
				<form>	
					<h2>API Key connexion</h2>
					<input id="grwlr_user" placeholder="User ID (number)" />
					<input id="grwlr_api_key" placeholder="api_key (keychain)" />
					<button id="grwlr_credential_button" type="button">Connect to Grawler</button>
				</form>
			</div>
			<script>
				jQuery('#grwlr_credential_button').on('click',function(){
					  jQuery.post(
					    "http://open.grawler.com/rest/do/validCredentials",
					    { 
					      'user' : jQuery('#grwlr_user').val(),
					      'key': jQuery('#grwlr_api_key').val(),
					    },                   // ajout du POI en base grâce à l'API
					    function(data){  
					    	result = JSON.parse(data);

					    	// Auth error
					    	if(result.code == -1){
					    		alert(result.message);
					    	}
					    	else if(result.code == 1){
					    		//alert('Success :'+result.message);
					    		grwlrSaveApiCredentials(jQuery('#grwlr_user').val(),jQuery('#grwlr_api_key').val());
					    	}
					    }
					)
				});
				jQuery('#grwlr_connect_button').on('click',function(){
					  jQuery.post(
					    "http://open.grawler.com/rest/do/getKey",
					    { 
					      'auth_key' : jQuery('#grwlr_auth_key').val(),
					      'pwd': jQuery('#grwlr_pwd').val(),
					    },                   // ajout du POI en base grâce à l'API
					    function(data){  
					    	result = JSON.parse(data);

					    	// Auth error
					    	if(result.code == -1){
					    		alert(result.message);
					    	}
					    	else if(result.code == 1){
					    		//alert('Success :'+result.message+' id :'+result.user+' key : '+result.api_key);
					    		grwlrSaveApiCredentials(result.user,result.api_key);

					    	}
					    }
					)
				});
				function grwlrSaveApiCredentials(user, key){
					
							var data = {
								'action': 'save_grwlr_credential',
								'grwlr_user': user,
								'grwlr_api_key': key,
							};

							jQuery.post(ajaxurl, data, function(response) {
								jQuery('#grwlr-connexionarea').html("The link to Grawler has been established. Please reload this page in order to display the plugin");
							});
					
				}
			</script>
		<?php	
		}
		else{ //Already setup
			$post_meta = unserialize( get_post_meta(get_the_ID(), 'grwlr_opt', true) ); //check if this content as already been shared
			$defaultSettings = unserialize( get_option('wp2grwlrDefaultSettings') );
			$defaultlang = $defaultSettings['lang']; //previous content language
			$defaultlat = is_array($defaultSettings) ? $defaultSettings['lat'] : '51.505';
			$defaultlng = is_array($defaultSettings) ? $defaultSettings['lng'] : '0.09';

			if(is_array($post_meta)){ //already share //1 == 2){ //
				echo '<h3>This article has been already shared on Grawler</h3>';
			}
			//Already setup, not shared Displaying widget 
			else {
			$apiParameters = unserialize($apiParameters);	
			?>	
				<style>
					.grwlr_required{
						border: 1px solid;
						border-color: red;
					}
				</style>
				<h2>Share to Grawler : <button id="grwlr_auto_complete" type="button" style="font-size: 0.8em;margin-left: 20px;">Auto Complete</button></h2>
				<ul id="grwlr_shareform">
					<li><input id="grwlr_shr_title" placeholder="Title"/>*</li>
					<li id="grwlr_shr_picture_tank"></li>
					<li><textarea id="grwlr_shr_content"></textarea></li>
					<li><input id="grwlr_shr_hash1" placeholder="#hash1" />*<input id="grwlr_shr_hash2" placeholder="#hash2" /></li>
					<li><input id="grwlr_shr_surl" placeholder="http:// (permalink)" /></li>
					<li><input id="grwlr_shr_sn" placeholder="My Blog (Source title)" value="<?php bloginfo('name'); ?>" /></li>
					<li><input id="grwlr_shr_lat" placeholder="lat" />*<input id="grwlr_shr_lng" placeholder="lng" />*</li>
					<li><div id="grwlr_admin_map" style="width: 200px;height: 200px;"></div></li>
					<li><select id="grwlr_lang">
						<option value="nolang">Which langage is it ?</option>
						<?php foreach($available_lang as $key => $value){ 
							$preselected = $defaultlang == $key ? 'selected' : '';
							echo '<option value="'.$key.'" '.$preselected.'>'.$value.'</option>'; } ?>
						</select></li>
					<li><button id="grwlr_share_button" type="button" class="button-primary">Share</button></li>
					<li><span id="grwlr_receiveresponse"></span></li>
				</ul>
				<script>
					jQuery( document ).ready(function() {

								//map initalization
								var map = L.map('grwlr_admin_map').setView([<?php echo $defaultlat,', ',$defaultlng; ?>], 13);
								//http://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png
								L.tileLayer('http://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
								    attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a>',
								    maxZoom: 18
								}).addTo(map);

								//get the click position and add marker
								var marker = '';
								function onMapClick(e) {
									if(marker != '') map.removeLayer(marker); // empty the previous marker

									jQuery('#grwlr_shr_lat').val(e.latlng.lat);
									jQuery('#grwlr_shr_lng').val(e.latlng.lng);

									marker = L.marker(e.latlng).addTo(map);
									
									// recenter the view
	  								map.setView(e.latlng);

								}

								map.on('click', onMapClick);
					});

					jQuery('#grwlr_share_button').on('click',function(){
							jQuery('.grwlr_required').removeClass('grwlr_required');
							jQuery('#grwlr_receiveresponse').html('');

							if(jQuery('#grwlr_shr_title').val() == ''){
								jQuery('#grwlr_shr_title').addClass('grwlr_required');	
								jQuery('#grwlr_receiveresponse').html('<span style="color:#CC2E5E">Missing content</span>');
								return;
							}
							if(jQuery('#grwlr_shr_picture').attr('src') == 'undefined'  || jQuery('#grwlr_shr_picture').attr('src') == ''){
								jQuery('#grwlr_shr_picture').parent().addClass('grwlr_required');
								jQuery('#grwlr_receiveresponse').html('<span style="color:#CC2E5E">Missing image</span>');	
								return;
							}
							if(jQuery('#grwlr_shr_lng').val() == ''  || jQuery('#grwlr_shr_lat').val() == '' ){
								jQuery('#grwlr_shr_lng').addClass('grwlr_required');
								jQuery('#grwlr_shr_lat').addClass('grwlr_required');
								jQuery('#grwlr_receiveresponse').html('<span style="color:#CC2E5E">Localization Missing. Just click on the map !</span>');	
								return;
							}
							if(jQuery('#grwlr_shr_hash1').val() == ''){
								jQuery('#grwlr_shr_hash1').addClass('grwlr_required');	
								jQuery('#grwlr_receiveresponse').html('<span style="color:#CC2E5E">Missing content</span>');
								return;
							}	
							if(jQuery('#grwlr_lang').val() == 'nolang'){
								jQuery('#grwlr_lang').addClass('grwlr_required');	
								jQuery('#grwlr_receiveresponse').html('<span style="color:#CC2E5E">Choose the correct language</span>');
								return;
							}
							
							jQuery('#grwlr_receiveresponse').html('<span style="color:#0099FF">Please wait...</span>');
							jQuery('#grwlr_share_button').attr("disabled", "disabled");

						    jQuery.post(
						      "http://open.grawler.com/rest/do/postPoi/lang/"+jQuery('#grwlr_lang').val(),
						      { 
						        'title' : jQuery('#grwlr_shr_title').val(),
						        'content' : jQuery('#grwlr_shr_content').val(),
						        'hash1' : jQuery('#grwlr_shr_hash1').val(),
						        'hash2' : jQuery('#grwlr_shr_hash2').val(),
						        'user' : '<?php echo $apiParameters['user']; ?>',
						        'key': '<?php echo $apiParameters['api_key']; ?>',
						        'imgurl': jQuery('#grwlr_shr_picture').attr('src'), 
						        'surl': jQuery('#grwlr_shr_surl').val(),
						        'sn': jQuery('#grwlr_shr_sn').val(),
						        'lat' : jQuery('#grwlr_shr_lat').val(),
						        'lng' : jQuery('#grwlr_shr_lng').val()
						      },                   // ajout du POI en base grâce à l'API
						      function(data){  
						      	  jQuery('#grwlr_share_button').removeAttr("disabled");

						      	  result = JSON.parse(data);
						      	  if(result.code == 1){
						      	  		// we save this data localy 
						      	  		var data = {
						      	  			'action': 'grwlr_confirm',
						      	  			'postID': <?php the_ID(); ?>,
						      	  			'slug': result.slug,
						      	  			'lat': jQuery('#grwlr_shr_lat').val(),
						      	  			'lng': jQuery('#grwlr_shr_lng').val(),
						      	  			'lang': jQuery('#grwlr_lang').val(),
						      	  			'defaultlang': '<?php echo $defaultlang; ?>',
						      	  		};

						      	  		jQuery.post(ajaxurl, data, function(response) {
						      	  			jQuery('#grwlr_shareform').html('<li>Post successfully shared!</li>');	
						      	  		});    	  		

						      	  }
						      	  else {
						      	  		jQuery('#grwlr_receiveresponse').html(result.message);	
						      	  		jQuery('#grwlr_receiveresponse').css('background-color','#CC2E5E');
						      	  		if(result.code == -1){
						      	  			jQuery('#grwlr_receiveresponse').after('<li><br /><button id="grwlr_delete_credential" style="background-color:#CC2E5E" type="button" class="button-primary">Change API access parameters</button></li>');	

						      	  			jQuery('#grwlr_delete_credential').on('click',function(){
						      	  					var data = {
						      	  						'action': 'delete_grwlr_credential',
						      	  					};

						      	  					jQuery.post(ajaxurl, data, function(response) {
						      	  						alert(response);
						      	  						if(response == 1) window.location.reload();
						      	  					});	
						      	  			})
						      	  			
						      	  		}
						      	  }
						          
						      }
						  )
					});

					function autoComplete(){
						jQuery('#grwlr_shr_title').val(jQuery('#title').val());
						grabContent = '';
						grabContent = jQuery('#content').val();
						jQuery('#grwlr_shr_content').val(grabContent.replace(/(<([^>]+)>)/ig,""));
						toProcess = jQuery('<div>'+ grabContent +'</div>');
						postImg = toProcess.find('img:first').attr('src');
						jQuery('#grwlr_shr_picture_tank').html('<img id="grwlr_shr_picture" src="'+postImg+'" style="max-width: 100%;">*');
						jQuery('#grwlr_shr_surl').val(jQuery('#sample-permalink').text());
					}
					
					jQuery('#grwlr_auto_complete').on('click',function(){
						autoComplete();
					});
					setTimeout(function() {  autoComplete(); } , 2000);
				</script>
			<?php
			}	
		}

}

function saveGrwlrCredential_callback() { // Save the API credentials for future uses
	global $wpdb;
	update_option('wp2grwlrApiParam', serialize(array('user' => $_POST['grwlr_user'], 'api_key' => $_POST['grwlr_api_key'])));
	die(); // this is required to terminate immediately and return a proper response
}
function deleteGrwlrCredential_callback() { // Save the API credentials for future uses
	global $wpdb;
	delete_option('wp2grwlrApiParam');
	die('1'); // this is required to terminate immediately and return a proper response
}
function saveGrwlrConfirm(){ // Save the share in order to avoid duplicates
	global $wpdb;
	update_post_meta( $_POST['postID'], 'grwlr_opt', serialize(array('slug' => $_POST['slug'], 'lat' => $_POST['lat'], 'lng' => $_POST['lng'])) );

	// save the default language and localization
	update_option('wp2grwlrDefaultSettings', serialize( array('lang' => $_POST['lang'], 'lat' => $_POST['lat'], 'lng' => $_POST['lng'] )));

	die(); // this is required to terminate immediately and return a proper response
}