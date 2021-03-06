<?php 

class Feed {

	 public function __construct( )
    {
    }

    
    public function getFeed($feed_url) {
		if( !empty($feed_url) ) {

			$ch = curl_init();
	        // set url
	        curl_setopt($ch, CURLOPT_URL, $feed_url);
	        //return the transfer as a string
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	        // $output contains the output string
	        $output = curl_exec($ch);
	        // close curl resource to free up system resources
	        curl_close($ch);  

	        // return $output;// give data
			
			// $out_clear =  str_replace("\r\n", ",", $output);
			$out_clear =  str_replace("*", "0", $output);
	        $out1 = explode("\r\n", $out_clear);

// $limit=0;

	        foreach ($out1 as $o) {
	        	// if($limit < 5){
	        		$post_type = "game";
	        		$feed_data_clean =  json_decode($o,true);

	        		if( ( isset( $feed_data_clean['data']['enabled'] ) && $feed_data_clean['data']['enabled'] == 1 ) && 
	        			( isset( $feed_data_clean['data']['playMode']['fun'] ) && $feed_data_clean['data']['playMode']['fun'] == 1 ) ) 
	        		{
	        			$this->addFeedToPostType( $post_type, $feed_data_clean );

	        		}

	        	// }

// $limit++;	
	        }

	        echo "done";
	        
		} else {
			return false; //no url feed
		}

		wp_die();

    }

    public function addFeedToPostType( $post_type = "game", $feed_array = array() ) {

    	$content = (!empty($feed_array['data']['presentation']['description']) && isset($feed_array['data']['presentation']['description'][0]) )? $feed_array['data']['presentation']['description'][0]:'';
    	$args = array(
						'post_type' => "game",
						'post_name' => $feed_array['data']['presentation']['gameName'][0],
					   'post_title' => $feed_array['data']['presentation']['gameName'][0],
					   'post_content' => $content,
					   'post_status' => 'publish',
					   'comment_status' => 'closed',   // if you prefer
					   'ping_status' => 'closed',      // if you prefer
					   'post_modified' => date('Y-m-d h:i:s', strtotime($feed_array['data']['creation']['lastModified'])),
					   'post_date_gmt' => date('Y-m-d h:i:s', strtotime($feed_array['data']['creation']['lastModified'])),
					   // 'post_category' => implode(",", $feed_array['data']['categories']),
					   'tags_input' =>  (!empty($feed_array['data']['tags']))? implode(",", $feed_array['data']['tags']): '',

    				);	

    	if( $post_id = wp_insert_post( $args ) ) {
    		update_post_meta( $post_id, 'feed_custom_creationid', $feed_array['data']['id'] );
    		update_post_meta( $post_id, 'feed_custom_domainid', $feed_array['domainID'] );
    		update_post_meta( $post_id, 'feed_custom_vendor', $feed_array['data']['vendor'] );
    		update_post_meta( $post_id, 'feed_custom_iframe_url', esc_html( $feed_array['data']['url']) );
    		update_post_meta( $post_id, 'feed_custom_software', $feed_array['data']['property']['license']);
    		update_post_meta( $post_id, 'feed_custom_slottypes', $feed_array['data']['categories'][0]);
    		update_post_meta( $post_id, 'feed_custom_slotthemes', $feed_array['data']['presentation']['gameName'][0]);
    		update_post_meta( $post_id, 'feed_custom_slotrtp', $feed_array['data']['theoreticalPayOut']);

    		$this->addPostImage($feed_array['data']['presentation']['thumbnail'][0], $post_id);
    	}

    }

    /*
    *	$image_url = link of image .. without http:
    *	$parent_post_id = $post_id
    */
    public function addPostImage($image_url, $parent_post_id){
    	// $filename should be the path to a file in the upload directory.
    	$uploaded_file = $this->downloadPostImage($image_url);


		$filename = $uploaded_file['filename'];

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $uploaded_file['local_url'] . '/' . $uploaded_file['filename'] . $uploaded_file['type'], 
			'post_mime_type' => $uploaded_file['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		// Insert the attachment.
		$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );
		
		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		// Generate the metadata for the attachment, and update the database record.
		$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		set_post_thumbnail( $parent_post_id, $attach_id );

    }

    public function downloadPostImage($url){
    	// Gives us access to the download_url() and wp_handle_sideload() functions
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		$url = "http:" . $url;
		$timeout_seconds = 5;

		// Download file to temp dir
		$temp_file = download_url( $url, $timeout_seconds );


		$uploaded_file = array();

		if ( !is_wp_error( $temp_file ) ) {

		    // Array based on $_FILE as seen in PHP file uploads
		    $file = array(
		        'name'     => basename($url), // ex: wp-header-logo.png
		        'type'     => 'image/png',
		        'tmp_name' => $temp_file,
		        'error'    => 0,
		        'size'     => filesize($temp_file),
		    );

		    $overrides = array(
		        // Tells WordPress to not look for the POST form
		        // fields that would normally be present as
		        // we downloaded the file from a remote server, so there
		        // will be no form fields
		        // Default is true
		        'test_form' => false,

		        // Setting this to false lets WordPress allow empty files, not recommended
		        // Default is true
		        'test_size' => true,
		    );

		    // Move the temporary file into the uploads directory
		    $results = wp_handle_sideload( $file, $overrides );

		    if ( !empty( $results['error'] ) ) {
		        // Insert any error handling here
		    	//echo "failed to load image";
		    } else {

		        $uploaded_file['filename']  = $results['file']; // Full path to the file
		        $uploaded_file['local_url'] = $results['url'];  // URL to the file in the uploads dir
		        $uploaded_file['type']      = $results['type']; // MIME type of the file

		        // Perform any actions here based in the above results

		    }

		    return $uploaded_file;

		}


    }



}