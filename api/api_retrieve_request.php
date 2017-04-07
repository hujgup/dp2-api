<?php
	require_once("api_request.php");
	require_once("api_filters.php");
	require_once("db_core.php");
	require_once("db_build_select.php");

	class ApiRetrieveRequest extends ApiRequest {
		private static $_filterMap = [];
		private $_filter = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("filter",$json)) {
				$stack[] = "filter";
				$filter = $json["filter"];
				$this->_filter = FilterMap::createFilter($filter,$stack);
				array_pop($stack);
			}
		}
		public function invoke(Database &$db) {
			$result = $db->getAllRows(true);
			if ($this->_filter !== null) {
				$res2 = [];
				foreach ($result as &$row) {
					if ($this->_filter->passes($row)) {
						$res2[] = $row;
					}
				}
				$result = $res2;
			}
			return $result;
		}
	}
	ApiRequest::registerReqType("retrieve","ApiRetrieveRequest");
?>