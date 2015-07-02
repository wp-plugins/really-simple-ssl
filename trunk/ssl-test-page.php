<html>
<head></head>
<body>
<h1>SSL test page</h1>
<p>This page is used purely to test for ssl availability.</p>
<?php
	function is_ssl() {
		if ( isset($_SERVER['HTTPS']) ) {
			if ( 'on' == strtolower($_SERVER['HTTPS']) )
				return true;
			if ( '1' == $_SERVER['HTTPS'] )
				return true;
		} elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
			return true;
		}
		return false;
	}

	function ssl_behind_load_balancer_or_cdn() {
		if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	if (is_ssl() || ssl_behind_load_balancer_or_cdn()) {
	 	echo "#REALLY SIMPLE SSL SUCCESS#";
	}
	else {
		echo "#REALLY SIMPLE SSL DETECTION FAILED#";
	}
?>
</body>
</html>
