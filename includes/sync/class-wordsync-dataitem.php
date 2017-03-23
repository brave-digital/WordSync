<?php

	/**
	 * BraveWpSync_DataItem.php
	 *
	 * Date: 2016/12/09
	 * Time: 10:43 AM
	 */
	class BraveWordsync_DataItem
	{
		const TYPE_NONE = 'none';
		const TYPE_SIMPLE = 'simple';
		const TYPE_ARRAY = 'array';

		protected $isLocal = true;

		protected $fields;
		public $key;
		public $remotekey;


		public static function createFromJSON($json, $islocal = true)
		{
			$dataitem = new BraveWordsync_DataItem($json['f'], $json['k'], $islocal);
			$dataitem->remotekey = $json['rk'];

			return $dataitem;
		}

		/**
		 * BraveWpSync_DataItem constructor.
		 * @param $fields - an asssociative array of fields which
		 * @param $key - The unique value which identifies this data item.
		 * @param bool $isLocal
		 */
		public function __construct($fields, $key, $isLocal = true)
		{
			$this->isLocal = $isLocal;
			$this->fields = $fields;
			$this->key = $key;
			$this->remotekey = '';
		}

		private static function performStringDifference($leftval, $rightval)
		{
			$opcodes = FineDiff::getDiffOpcodes($leftval, $rightval, FineDiff::$characterGranularity);

			$leftdiff = FineDiff::renderDiffToHTMLFromOpcodes($leftval, $opcodes);

			$opcodes = FineDiff::getDiffOpcodes($rightval, $leftval, FineDiff::$characterGranularity);

			$rightdiff = FineDiff::renderDiffToHTMLFromOpcodes($rightval, $opcodes);

			return array($leftdiff, $rightdiff);
		}

		public function getFieldSlugs()
		{
			return array_keys($this->fields);
		}

		public function setField($field, $value)
		{
			$this->fields[$field] = $value;
		}

		public function getField($field)
		{
			return $this->fields[$field];
		}

		public function getFields($exclude = array(), $extra = array())
		{
			$fields = array_merge($this->fields, $extra);

			foreach ($exclude as $ex)
			{
				unset($fields[$ex]);
			}

			return $fields;
		}

		public function fieldType($field)
		{
			if (!isset($this->fields[$field]))
			{
				return BraveWordsync_DataItem::TYPE_NONE;
			}
			else
			{
				$fld = $this->fields[$field];
				if (is_array($fld))
				{
					return BraveWordsync_DataItem::TYPE_ARRAY;
				}
				else
				{
					return BraveWordsync_DataItem::TYPE_SIMPLE;
				}
			}
		}

		public function hasField($field)
		{
			return isset($this->fields[$field]);
		}

		/**
		 * @param callable $remotepreprocessor
		 */
		public function runRemotePreprocessor($remotepreprocessor)
		{
			if (is_callable($remotepreprocessor))
			{
				foreach ($this->fields as $key=>$field)
				{
					$this->fields[$key] = call_user_func($remotepreprocessor, $key, $field, $this->fieldType($key));
				}
			}
		}

		/**
		 * Scans an array / simple variable recursively, making sure that each property is exactly equal.
		 *
		 * @param $object
		 * @param $remoteobject
		 * @return bool - true if $object and $remoteobject are equal.
		 */
		protected function deepMatch($object, $remoteobject)
		{
			if (is_array($object))
			{
				if (!is_array($remoteobject)) return false;

				if (count($object) == count($remoteobject))
				{
					$matched = true;
					foreach ($object as $key=>$value)
					{
						if (!isset($remoteobject[$key]))
						{
							$matched = false;
							break;
						}

						$matched = $this->deepMatch($object[$key], $remoteobject[$key]);

						if (!$matched) break;
					}

					return $matched;
				}

				return false;
			}
			else
			{
				return $object === $remoteobject;
			}
		}

		/**
		 * @param $field
		 * @param $remoteDataItem
		 * @return bool
		 */
		protected function matchFieldWithRemote($field, $remoteDataItem)
		{
			$matched = false;

			/** @var BraveWordsync_DataItem $remoteDataItem */
			if ($this->hasField($field) && $remoteDataItem->hasField($field))
			{
				$thistype = $this->fieldType($field);
				$remotetype = $remoteDataItem->fieldType($field);

				//Check that the field's types match.
				if ($thistype == $remotetype && $thistype != BraveWordsync_DataItem::TYPE_NONE)
				{
					$thisfield = $this->getField($field);
					$remotefield = $remoteDataItem->getField($field);


					if ($thistype == BraveWordsync_DataItem::TYPE_SIMPLE)
					{
						//If this is a simple field, then just check that it's value is equal to the remote's value.
						$matched = $thisfield === $remotefield;
					}
					else if ($thistype == BraveWordsync_DataItem::TYPE_ARRAY)
					{
						//If this is an array, loop through the array fields and check that they exactly match the remote's value.
						$matched = $this->deepMatch($thisfield, $remotefield);
					}
				}
			}

			return $matched;
		}

		/**
		 * Goes through the fields of each item and compares their match fields. At least one must match completely unless $mustmatchall is true in which case all specified fields must match completely.
		 *
		 * @param $remoteDataItem
		 * @param $matchfields
		 * @param bool $mustmatchall
		 * @return bool - True if the items match, or false if not.
		 * @throws Exception
		 */
		public function matchWithRemote($remoteDataItem, $matchfields, $mustmatchall = false)
		{
			/** @var BraveWordsync_DataItem $remoteDataItem */
			if ($this->isLocal == $remoteDataItem->isLocal)
			{
				throw new Exception("Cant compare two data items from the same wordpress install. One must be local and the other must be remote.");
			}

			if (!is_array($matchfields)) $matchfields = array($matchfields);

			$found = false;
			if ($mustmatchall) $found = true;

			//Loop through all the match fields.
			foreach ($matchfields as $field)
			{
				$thisfound = $this->matchFieldWithRemote($field, $remoteDataItem);

				if ($mustmatchall)
				{
					$found = $found && $thisfound;
				}
				else
				{
					$found = $thisfound;
					if ($found) break;
				}
			}

			return $found;
		}

		/**
		 * Compares this dataitem with a remote one and returns an array of all the fields which do not match values exactly.
		 *
		 * @param $remoteDataItem
		 * @param array $specificfieldstomatch - If not an empty array, compares only the fields specified here. Otherwise compares all fields.
		 * @return array
		 */
		public function getListOfUnmatchedFields($remoteDataItem, $specificfieldstomatch = array())
		{
			/** @var BraveWordsync_DataItem $remoteDataItem */
			$unmatched = array();

			if (count($specificfieldstomatch) == 0) $specificfieldstomatch = $this->getFieldSlugs();

			foreach ($specificfieldstomatch as $fieldname)
			{
				$matched = $this->matchFieldWithRemote($fieldname, $remoteDataItem);

				if (!$matched) $unmatched[] = $fieldname;
			}

			return $unmatched;
		}

		protected function returnObjectDifference($field, $key, $localdata, $remotedata)
		{
			$local = ($this->isLocal ? 'l' : 'r');
			$remote = ($this->isLocal ? 'r' : 'l');

			return array(
				'f'=>$field,
				'k'=>$key,
				$local => $localdata,
				$remote => $remotedata
			);
		}

		protected function objectValuetoString($value, $forceshowtype = false)
		{
			if (is_string($value) && strlen($value) > 50) $value = substr($value, 0, 48).'...';

			if (empty($value) || $forceshowtype)
			{
				return gettype($value) . '('.$value.')';
			}
			else if (is_null($value))
			{
				return "Doesn't Exist";
			}
			else
			{
				return $value;
			}
		}

		protected function getObjectDifferences($field, $thiskey, $object, $remoteobject)
		{
			$keyjoiner = ' > ';

			$result = array();

			if (gettype($remoteobject) != gettype($object))
			{
				//Objects do not have the same type, or one of them is null.
				$result[] = $this->returnObjectDifference($field, $thiskey, $this->objectValuetoString($object, true),  $this->objectValuetoString($remoteobject, true));
			}
			else
			{

				if (is_array($object))
				{
					//In the case of arrays, loop through the array and check all properties individually.

					foreach ($object as $key => $value)
					{
						if (!isset($remoteobject[$key]))
						{
							//Property exists on local object only.
							$result[] = $this->returnObjectDifference($field,$thiskey.(!empty($thiskey) ? $keyjoiner : '').$key, $this->objectValuetoString($value), 'NULL');
						}
						else
						{
							//Compare properties.
							$thisres = $this->getObjectDifferences($field, $thiskey.(!empty($thiskey) ? $keyjoiner : '').$key, $this->objectValuetoString($object[$key]), $this->objectValuetoString($remoteobject[$key]));

							foreach ($thisres as $res)
							{
								$result[] = $res;
							}
						}

					}

					foreach ($remoteobject as $key => $value)
					{
						if (!isset($object[$key]))
						{
							//Field exists on remote object only.
							$result[] = $this->returnObjectDifference($field, $thiskey.(!empty($thiskey) ? $keyjoiner : '').$key, 'NULL', $this->objectValuetoString($value));
						}
					}

					return $result;
				}
				else
				{
					if ($object !== $remoteobject)
					{
						//Object values are not equal.

						if (is_string($object) && is_string($remoteobject) && strlen($object) > 50 && strlen($remoteobject) > 50)
						{
							$res = BraveWordsync_DataItem::performStringDifference($object, $remoteobject);

							$object = $res[0];
							$remoteobject = $res[1];
						}
						else
						{
							$object = $this->objectValuetoString($object);
							$remoteobject = $this->objectValuetoString($remoteobject);
						}

						$result[] = $this->returnObjectDifference($field,$thiskey, $object,$remoteobject);
					}
					else
					{
						//Objects are equal!
						//Dont need to do anything.
					}
				}
			}

			return $result;
		}

		/**
		 * @param $fields - array of field slugs.
		 * @param $remoteDataItem - remote data item.
		 * @param null|BraveWordsync_Processor $processor
		 * @return array - array of differences.
		 */
		public function getFieldDifferences($fields, $remoteDataItem, $processor = null)
		{
			/** @var BraveWordsync_DataItem $remoteDataItem */
			$result = array();

			if (is_null($remoteDataItem))
			{
				return $result;
			}


			if (!is_array($fields)) $fields = array($fields);


			foreach ($fields as $field)
			{
				$diffs = $this->getObjectDifferences($field, '', ($this->hasField($field) ? $this->getField($field) : null), (!is_null($remoteDataItem) && $remoteDataItem->hasField($field) ? $remoteDataItem->getField($field) : null));
				$result = array_merge($result, $diffs);
			}

			if (!is_null($processor))
			{
				foreach ($result as &$diff)
				{
					$diff['fn'] = $processor->getFieldName($diff['f']);
				}
				unset($diff);
			}

			return $result;
		}

		public function toJSON()
		{
			return array(
				'k'=>$this->key,
				'f'=>$this->fields,
				'rk'=>$this->remotekey,
			);
		}

	}