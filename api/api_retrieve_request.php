<?php
	require_once("api_request.php");

	class ApiRetrieveRequest extends ApiDataRetriever {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
	}
	ApiRequest::registerReqType("retrieve","ApiRetrieveRequest");
?>