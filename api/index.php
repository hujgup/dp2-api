<?php
	require_once("db_core.php");
	require_once("api_request.php");
	require_once("api_add_request.php");
	require_once("api_edit_request.php");
	require_once("api_retrieve_request.php");
	require_once("api_trendline_request.php");

	$canSet = true;
	function set_header($str) {
		global $canSet;
		if ($canSet) {
			header($str);
			$canSet = true;
		}
	}
	function print_exc($e,$code,$jsonEscape = false) {
		$msg = "ERR ".$code.": ".$e->getMessage()."<br />Stack trace: ".$e->getFile()."(".$e->getLine().")<br />&nbsp;&nbsp;&nbsp;&nbsp;".str_replace("\n","<br />&nbsp;&nbsp;&nbsp;&nbsp;",$e->getTraceAsString());
		if ($jsonEscape) {
			$msg = str_replace("\"","\\\"",$msg);
			$msg = str_replace("'","\\'",$msg);
		}
		echo $msg;
	}
	function err_handle($errno,$errstr,$errfile,$errline) {
		set_header("HTTP/1.0 500 Internal Server Error");
		echo "ERR 9: Internal code ".$errno."<br />";
		echo $errstr;
		echo "<br />".$errfile."(".$errline.")";
		echo "<br /><br />";
	}
	function exc_handle($e) {
		set_header("HTTP/1.0 500 Internal Server Error");
		echo "ERR 10: UNHANDLED EXCEPTION<br />";
		print_exc($e);
	}
	set_error_handler("err_handle");
	set_exception_handler("exc_handle");

	$key = "request";
	if (isset($_POST[$key])) {
		$json = json_decode(urldecode($_POST[$key]),true);
		if ($json === null) {
			set_header("HTTP/1.0 400 Bad Request");
			echo "ERR 4: POST key \"".$key."\" was not valid JSON.";
		} else {
			$db = null;
			try {
				$db = new Database();
			} catch (Exception $e) {
				set_header("HTTP/1.0 500 Internal Server Error");
				print_exc($e,"5");
			}
			if ($db !== null) {
				if (!ApiRequest::authent($json,$db)) {
					set_header("HTTP/1.0 403 Forbidden");
					echo "ERR 6: Authentication failed.";
				} elseif (count($json) > 1) {
					$req = null;
					try {
						$req = ApiRequest::create($json);
					} catch (ApiRequestCreationException $e) {
						set_header("HTTP/1.0 400 Bad Request");
						print_exc($e,"7/".$e->subcode);
					} catch (Exception $e) {
						set_header("HTTP/1.0 500 Internal Server Error");
						print_exc($e,"8");
					}
					if ($req !== null) {
						echo "[";
						$count = count($req);
						$threw = false;
						for ($i = 0; $i < $count; $i++) {
							if ($i !== 0) {
								echo ",";
							}
							try {
								$response = $req[$i]->invoke($db);
							} catch (DatabaseQueryException $e) {
								set_header("HTTP/1.0 400 Bad Request");
								echo "\"";
								print_exc($e,"11",true);
								echo "\"";
								$threw = true;
								$i = $count;
							} catch (Exception $e) {
								set_header("HTTP/1.0 500 Internal Server Error");
								echo "\"";
								print_exc($e,"12",true);
								echo "\"";
								$threw = true;
								$i = $count;
							}
							if (!$threw) {
								if ($response !== null) {
									echo json_encode($response);
								} else {
									echo "null";
								}
							}
						}
						echo "]";
					}
				}
			}
		}
	} else {
		set_header("HTTP/1.0 400 Bad Request");
		if (strtoupper($_SERVER["REQUEST_METHOD"]) === "POST") {
			$msg = "Required POST key \"".$key."\" was not set.<br />Found ";
			$code = null;
			if (count($_POST) === 0) {
				$msg .= "no keys - is your Content-Type header set to \"application/x-www-form-urlencoded\"?";
				$code = 2;
			} else {
				$msg .= "keys [".implode(", ",array_keys($_POST))."] - check your spelling.";
				$code = 3;
			}
			echo "ERR ".$code.": ".$msg;
		} else {
			echo "ERR 1: Request was not a POST request.";
		}
	}
?>