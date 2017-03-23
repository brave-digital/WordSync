<?php

	/**
	 * BraveWpSync_Change.php
	 *
	 * This class embodies a change between local and remote data items.
	 * Either the remote exists and the local doesnt, in which case this is a CREATE
	 * Or the local exists and the remote doesnt which is a REMOVE
	 * Or a number of fields inside the dataitem are different, in which case it is an UPDATE and the differing field names are listed in $fields
	 *
	 * Date: 2017/01/11
	 * Time: 02:45 PM
	 */
	class BraveWordsync_Change
	{
		const ACTION_NOTHING = 0;
		const ACTION_CREATE = 1;
		const ACTION_REMOVE = 2;
		const ACTION_UPDATE = 3;

		public $action = BraveWordsync_Change::ACTION_NOTHING;
		public $dataitem;
		public $processor;
		public $remotedataitem;
		public $fields;
		public $id = 0;

		public static function actionToJSON($action)
		{
			switch ($action)
			{
				case BraveWordsync_Change::ACTION_NOTHING:
					return "nothing";
					break;

				case BraveWordsync_Change::ACTION_CREATE:
					return "create";
					break;

				case BraveWordsync_Change::ACTION_REMOVE:
					return "remove";
					break;

				case BraveWordsync_Change::ACTION_UPDATE:
					return "update";
					break;

				default:
					return "invalid";
			}

		}

		public static function actionToString($action)
		{
			switch ($action)
			{
				case BraveWordsync_Change::ACTION_NOTHING:
					return "NOTHING";
				break;

				case BraveWordsync_Change::ACTION_CREATE:
					return "CREATE";
				break;

				case BraveWordsync_Change::ACTION_REMOVE:
					return "REMOVE";
				break;

				case BraveWordsync_Change::ACTION_UPDATE:
					return "UPDATE";
					break;

				default:
					return "INVALID ACTION";
			}
		}

		public static function createFromJSON($json)
		{
			if ($json['l'] == '')
			{
				$local = null;
			}
			else
			{
				$local = BraveWordsync_DataItem::createFromJSON($json['l'], true);
			}
			if ($json['r'] == '')
			{
				$remote = null;
			}
			else
			{
				$remote = BraveWordsync_DataItem::createFromJSON($json['r'], false);
			}


			$change = new BraveWordsync_Change($json['a'], $local, $remote, $json['f'], $json['i']);

			return $change;
		}

		/**
		 * BraveWpSync_Change constructor.
		 * @param int $action
		 * @param BraveWordsync_DataItem $dataitem
		 * @param BraveWordsync_DataItem $remotedataitem
		 * @param array $fields
		 * @param int $id
		 */
		public function __construct($action, $dataitem = null, $remotedataitem = null, $fields = array(), $id = 0)
		{
			$this->action   = $action;
			$this->dataitem = $dataitem;
			$this->remotedataitem = $remotedataitem;
			$this->fields = $fields;
			$this->id = $id;
		}

		public function toJSON()
		{
			$res = array('a'=>$this->action,
			             'l' => (!is_null($this->dataitem) ? $this->dataitem->toJSON() : ''),
			             'r' => (!is_null($this->remotedataitem) ? $this->remotedataitem->toJSON() : ''),
			             'f' => $this->fields,
			             'i' => $this->id
			);

			return $res;
		}

		/**
		 * Compares the local and remote data item and compiles a list of exactly what has changed in the data.
		 * Loops through each field and gathers the differences
		 * Handles plain values and deep nested objects and returns an array of differences for each field.
		 *
		 * @param BraveWordsync_Processor $processor
		 * @return array
		 */
		public function differencesToJSON($processor)
		{

			if (is_null($this->dataitem))
			{
				return $this->remotedataitem->getFieldDifferences($this->fields, $this->dataitem, $processor);
			}
			else
			{
				return $this->dataitem->getFieldDifferences($this->fields, $this->remotedataitem, $processor);
			}
		}

		public function toString()
		{
			$str = BraveWordsync_Change::actionToString($this->action);

			switch ($this->action)
			{
				case BraveWordsync_Change::ACTION_CREATE:
					$str .= ' item "'.$this->remotedataitem->key.'"'."\n";
					$str .= print_r($this->remotedataitem->toJSON(), true);
					break;
				case BraveWordsync_Change::ACTION_REMOVE:
					$str .= ' item "'.$this->dataitem->key.'"';
					break;
				case BraveWordsync_Change::ACTION_UPDATE:
					$str .= ' item "'.$this->dataitem->key.'" with fields: '."\n";
					foreach ($this->fields as $field)
					{
						$thisfield = $this->dataitem->getField($field);
						$remotefield = $this->remotedataitem->getField($field);
						$str .= "\t". $field . ' => "'.substr(htmlentities(print_r($thisfield, true)), 0, 50).'" =/= "'.substr(htmlentities(print_r($remotefield, true)), 0, 50).'"'."\n";
					}
					break;
				default:
					$str .= ' Unknown/Undefined Change!';
			}

			return $str. "\n";
		}


	}