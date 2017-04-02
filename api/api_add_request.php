<?php
	require_once("api_request.php");
	require_once("db_build_insert.php");

	class ApiAddRequestRecord {
		public $product = null;
		public $quantity = null;
		public $dateTime = null;
		public function __construct(&$json,&$stack) {
			if (ApiRequest::isJsonObject($json)) {
				$notFound = [];
				ApiRequest::verifyExists("product",$json,$notFound);
				ApiRequest::verifyExists("quantity",$json,$notFound);
				ApiRequest::verifyExists("dateTime",$json,$notFound);
				if (count($notFound) === 0) {
					$this->product = $json["product"];
					$this->quantity = $json["quantity"];
					$dateTime = $json["dateTime"];
					if (!is_int($this->product)) {
						ApiRequest::createTypeError("product","integer",$this->product,$stack);
					} elseif (!is_int($this->quantity)) {
						ApiRequest::createTypeError("quantity","integer",$this->quantity,$stack);
					} elseif ($this->quantity <= 0) {
						ApiRequest::createRangeError("quantity","1 or higher",$this->quantity,$stack);
					} elseif (!is_string($dateTime)) {
						ApiRequect::createTypeError("dateTime","string",$dateTime,$stack);
					} else {
						try {
							$this->dateTime = new UtcDateTime($dateTime);
						} catch (Exception $e) {
							ApiRequest::createError("Date/Time: ".$e->getMessage(),$stack);
						}
					}
				} else {
					ApiRequest::createUndefSetError($notFound,$stack);
				}
			} else {
				ApiRequest::createNotObjectError(array_peek($stack),$stack);
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
					foreach ($records as $i => $record) {
						$stack[] = $i;
						$this->_records[] = new ApiAddRequestRecord($record,$stack);
						array_pop($stack);
					}
					array_pop($stack);
				} else {
					ApiRequest::createNotArrayError("records",$stack);
				}
			} else {
				ApiRequest::createUndefError("records",$stack);
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