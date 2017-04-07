<?php
	require_once("api_request.php");
	require_once("api_filters.php");
	require_once("db_core.php");
	require_once("db_build_alter.php");
	require_once("db_build_select.php");

	class ApiEditRequestRecord extends ApiRecord {
		public $doProduct = false;
		public $doQuantity = false;
		public $doDateTime = false;
		public function __construct(&$json,&$stack) {
			if (ApiRequest::isJsonObject($json)) {
				$notFound = $this->getNotFound($json);
				if (count($notFound) < 3) {
					$this->doProduct = array_key_exists("product",$json);
					$this->doQuantity = array_key_exists("quantity",$json);
					$this->doDateTime = array_key_exists("dateTime",$json);
					if ($this->doProduct) {
						$this->setProduct($json,$stack,22);
					}
					if ($this->doQuantity) {
						$this->setQuantity($json,$stack,23,24);
					}
					if ($this->doDateTime) {
						$this->setDateTime($json,$stack,25,26);
					}
				} else {
					ApiRequest::createError("No fields were set to update.",$stack,21);
				}
			} else {
				ApiRequest::createNotObjectError(array_peek($stack),$stack,20);
			}
		}
		private function getArray($vals) {
			$res = [];
			if ($this->doProduct) {
				$res[] = $vals[0];
			}
			if ($this->doQuantity) {
				$res[] = $vals[1];
			}
			if ($this->doDateTime) {
				$res[] = $vals[2];
			}
			return $res;
		}
		public function getColumnArray() {
			return $this->getArray([ApiRecord::PRODUCT_KEY,ApiRecord::QUANTITY_KEY,ApiRecord::DATE_TIME_KEY]);
		}
		public function getValueArray() {
			return $this->getArray([$this->product,$this->quantity,$this->dateTime]);
		}
	}

	class ApiEditRequest extends ApiRequest {
		private $_record = null;
		private $_filter = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("updateTo",$json)) {
				if (array_key_exists("filter",$json)) {
					$updateTo = $json["updateTo"];
					$stack[] = "updateTo";
					$this->_record = new ApiEditRequestRecord($updateTo,$stack);
					array_pop($stack);
					$filter = $json["filter"];
					$stack[] = "filter";
					$this->_filter = FilterMap::createFilter($filter,$stack);
					array_pop($stack);
				}
			} else {
				ApiRequest::createUndefError("updateTo",$stack,18);
			}
		}
		private function editRecords(&$toUpdate,Database &$db) {
			$update = new UpdateQueryBuilder($db);
			$where = $update->setTable(Database::SALES)
				->setColumns($this->_record->getColumnArray())
				->pushValues($this->_record->getValueArray())
				->where();
			$where->component()
				->column(Database::SALES_ID)
				->equals()
				->valueNumber($toUpdate[0]);
			$count = count($toUpdate);
			for ($i = 0; $i < $count; $i++) {
				$where->cmbOr()
					->component()
					->column(Database::SALES_ID)
					->equals()
					->valueNumber($toUpdate[$i]);
			}
			$update->query();
		}
		public function invoke(Database &$db) {
			$rows = $db->getAllRows(false);
			$toUpdate = [];
			$updated = 0;
			foreach ($rows as &$row) {
				if ($this->_filter === null || $this->_filter->passes($row)) {
					$toUpdate[] = $row[Database::SALES_ID];
					$updated++;
				}
			}
			if (count($toUpdate) > 0) {
				$this->editRecords($toUpdate,$db);
			}
			return $updated;
		}
	}
	ApiRequest::registerReqType("edit","ApiEditRequest");
?>