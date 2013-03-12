<?php
/*
 * Simply return a localized text or empty string if the key is empty
 * Useful when localize variable which can be empty
 *
 * @param    string       $text          the text key
 * @return   string                      the translation
 */
function __($text) {
	if( empty( $text ) )
		return '';
	else
		return gettext($text);
}


/*
 * Simply echo a localized text
 *
 * @param    string       $text          the text key
 * @return   void
 */
function _e($text) {
	echo __($text);
}


/*
 * Return a color between RGB($r_min,$g_min,$b_min) and RGB($r_max,$g_max,$b_max) according to value $c where min value is $min and max value is $mac
 *
 * @param    string        $c                the value
 * @param    integer       $min=3            the min value corresponding to the min color
 * @param    integer       $max=30           the max value corresponding to the max color
 * @param    integer       $r_min=0          min red
 * @param    integer       $g_min=128        min green
 * @param    integer       $b_min=255        min blue
 * @param    integer       $r_max=255        max red
 * @param    integer       $g_max=0          max green
 * @param    integer       $b_max=0          max blue
 * @return   string                          the css rgb element
 */
function get_color($c,$min=3,$max=30,$r_min=0,$g_min=128,$b_min=255,$r_max=255,$g_max=0,$b_max=0) {
	$from_color = array($r_min,$g_min,$b_min);
	$to_color   = array($r_max,$g_max,$b_max);
	$from       = $min;
	$to         = $max;
	$d          = min($c,$to);
	$d          = max($d,$from);
	$c          = 'rgb(';
	for ($i=0; $i<3; $i++) {
		$a = $from_color[$i]+round(($d-$from)*($to_color[$i]-$from_color[$i])/($to-$from));
		$c.= ($i>0) ? ','.$a : $a;
	}
	return $c.')';
}


/*
 * Return array with Netatmo informations
 *
 * @return   mixed                          an array with all weather station values or an exception object if error happens
 */
function get_netatmo( $scale = '1day' , $scale_inner = '1day' ) {

	global $NAconfig, $NAusername, $NApwd;

	if ( function_exists( 'apc_exists' ) ) {
		if ( apc_exists( NETATMO_CACHE_KEY ) ) {
			$return = @unserialize( apc_fetch( NETATMO_CACHE_KEY ) );
			if ( is_array( $return ) ) {
				if ( count( $return ) > 0 ) {
					return $return;
				}
			}
		}
	}

	$scale       = ( in_array( $scale , explode( ',' , NETATMO_DEVICE_SCALES ) ) ) ? $scale : '1day';
	$scale_inner = ( in_array( $scale_inner , explode( ',' , NETATMO_DEVICE_SCALES ) ) ) ? $inner_scale : $scale;
	$return      = array();

	/*
	Netatmo job
	 */
	$client = new NAApiClient($NAconfig);
	$client->setVariable("username", $NAusername);
	$client->setVariable("password", $NApwd);
	try {
	    $tokens = $client->getAccessToken();
		$refresh_token = $tokens["refresh_token"];
		$access_token  = $tokens["access_token"];
	}
	catch(NAClientException $ex) {
		return $ex;
	}

	$userinfo = array();
	try {
	    $userinfo = $client->api("getuser");
	}
	catch(NAClientException $ex) {
		return $ex;
	}
	$return['user'] = $userinfo;

    $device_id = '';
	try {
	    $deviceList = $client->api("devicelist");
	    if (is_array($deviceList["devices"])) {

	    	foreach ($deviceList["devices"] as $device) {

	    		$device_id = $device["_id"];

				$params = array(
					"scale"     => "max",
					"type"      => NETATMO_DEVICE_TYPE_MAIN,
					"date_end"  => "last",
					"device_id" => $device_id
				);
    			$res = $client->api("getmeasure",'GET',$params);

    			if(isset($res[0]) && isset($res[0]["beg_time"])) {
    				$time = $res[0]["beg_time"];
    				$vals = explode( ',' , NETATMO_DEVICE_TYPE_MAIN );
    				foreach( $res[0]["value"][0] as $key => $value )
						$return[$device_id]['results'][ $vals[$key] ] = $value;
    				$return[$device_id]['name']    = $device["module_name"];
    				$return[$device_id]['station'] = $device["station_name"];
    				$return[$device_id]['type']    = $device["type"];
    				$return[$device_id]['time']    = $res[0]["beg_time"];
    			}

				$params = array(
					"scale"     => $scale,
					"type"      => NETATMO_DEVICE_TYPE_MISC,
					"date_end"  => "last",
					"device_id" => $device_id
				);
    			$res = $client->api("getmeasure",'GET',$params);
    			if(isset($res[0]) && isset($res[0]["beg_time"])) {
    				$vals = explode( ',' , NETATMO_DEVICE_TYPE_MISC );
    				foreach( $res[0]["value"][0] as $key => $value )
						$return[$device_id]['misc'][ $vals[$key] ] = $value;
    			}


	    	}
	    }
	}
	catch(NAClientException $ex) {
		return $ex;
	}

	if ($device_id!='') {
	    if (is_array($deviceList["modules"])) {
	    	foreach ($deviceList["modules"] as $module) {
	    		try {
		    		$module_id = $module["_id"];
		    		$device_id = $module["main_device"];

					$params = array(
						"scale"     => "max",
						"type"      => NETATMO_MODULE_TYPE_MAIN,
						"date_end"  => "last",
						"device_id" => $device_id,
						"module_id" => $module_id
					);

	    			$res = $client->api("getmeasure",'GET',$params);
	    			if(isset($res[0]) && isset($res[0]["beg_time"])) {
	    				$vals = explode( ',' , NETATMO_MODULE_TYPE_MAIN );
   		 				foreach( $res[0]["value"][0] as $key => $value )
							$return[$device_id]['m'][$module_id]['results'][ $vals[$key] ] = $value;
	    				$return[$device_id]['m'][$module_id]['name']    = $module["module_name"];
	    				$return[$device_id]['m'][$module_id]['time']    = $res[0]["beg_time"];
	    			}

					$params = array(
						"scale"     => $scale_inner,
						"type"      => NETATMO_MODULE_TYPE_MISC,
						"date_end"  => "last",
						"device_id" => $device_id,
						"module_id" => $module_id
					);

	    			$res = $client->api("getmeasure",'GET',$params);
	    			if(isset($res[0]) && isset($res[0]["beg_time"])) {
	    				$vals = explode( ',' , NETATMO_MODULE_TYPE_MISC );
   		 				foreach( $res[0]["value"][0] as $key => $value )
							$return[$device_id]['m'][$module_id]['misc'][ $vals[$key] ] = $value;
	    			}
				}
				catch(NAClientException $ex) {
					return $ex;
				}
	    	}
	    }
	}

	if ( function_exists( 'apc_exists' ) ) {
		apc_store( NETATMO_CACHE_KEY , serialize( $return ) , NETATMO_CACHE_TTL );
	}
    return $return;
}



