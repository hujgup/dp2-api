<?php
	require_once("api_request.php");
	require_once("db_build_insert.php");

	class ApiAddRequestRecord extends ApiRecord {
		public function __construct(&$json,&$stack) {
			if (ApiRequest::isJsonObject($json)) {
				$notFound = $this->getNotFound($json);
				if (count($notFound) === 0) {
					$this->setProduct($json,$stack,9);
					$this->setQuantity($json,$stack,10,11);
					$this->setDateTime($json,$stack,12,13);
				} else {
					ApiRequest::createUndefSetError($notFound,$stack,14);
				}
			} else {
				ApiRequest::createNotObjectError(array_peek($stack),$stack,15);
			}
		}
	}

	class ApiAddRequest extends ApiRequest {
		private $_records = [];
		public function __construct(&$json,&$stack) {
			if (array_key_exists("records",$json)) {
				$records = $json["records"];
				if (ApiRequest::isJsonArray($records)) {
					$stack[] = "records";
					foreach ($records as $i => &$record) {
						$stack[] = $i;
						$this->_records[] = new ApiAddRequestRecord($record,$stack);
						array_pop($stack);
					}
					array_pop($stack);
				} else {
					ApiRequest::createNotArrayError("records",$stack,16);
				}
			} else {
				ApiRequest::createUndefError("records",$stack,17);
			}
		}
		private function addRecord(ApiAddRequestRecord &$record,Database &$db) {
			$insert = new InsertQueryBuilder($db);
			$insert->setTable(Database::SALES)
				->setColumns([Database::SALES_PRODUCT,Database::SALES_QUANTITY,Database::SALES_DATETIME])
				->pushValues([$record->product,$record->quantity,$record->dateTime->iso8601]);
			$insert->query();
		}
		public function invoke(Database &$db) {
			foreach ($this->_records as $record) {
				$this->addRecord($record,$db);
			}
		}
	}
	ApiRequest::registerReqType("add","ApiAddRequest");
?>