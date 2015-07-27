<html>
<head></head>
<body>
<h1>SSL test page</h1>
<p>This page is used purely to test for ssl availability.</p>
<?php
	function server_https_ssl() {
		if ( isset($_SERVER['HTTPS']) ) {
			if ( 'on' == strtolower($_SERVER['HTTPS']) )
				return true;
			if ( '1' == $_SERVER['HTTPS'] )
				return true;
		}
		return false;
	}

	function server_port_ssl() {
		if ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
			return true;
		}
		else {
			return false;
		}
	}

	function ssl_behind_load_balancer() {
		if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	function ssl_behind_cdn() {
		if(!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}


	if (server_https_ssl()) {
	 	echo "#STANDARD-SSL#";
	}
	elseif (ssl_behind_load_balancer()){
		echo "#LOADBALANCER#";
	}
	elseif (ssl_behind_cdn()){
		echo "#CDN#";
	}
	elseif (server_port_ssl()) {
		echo "#SERVERPORT#";
	}
	else {
		echo "#NO KNOWN SSL CONFIGURATION DETECTED#";
	}
?>
</body>
</html>
