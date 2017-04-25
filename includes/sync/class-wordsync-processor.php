<?php

	/**
	 * class-wordsync-processor.php
	 *
	 * The WordSync Processor class handles the reading of local data, comparing of data and the final updating of the local data for a specific type of data.
	 * This class is virtual and should not be used directly. It must be sub-classed.
	 *
	 * Date: 2016/12/09
	 * Time: 10:36 AM
	 */
	class BraveWordsync_Processor
	{
		/** @var BraveWordsync $wordsync */
		protected $wordsync;
		protected $slug = 'processor';
		protected $version = '1.0';
		protected $details = array('name'=>'Unnamed Processor', 'desc'=>'', 'dashicon'=>'dashicons-admin-page');

		//For Dashicons reference, see https://developer.wordpress.org/resource/dashicons/#admin-generic

		protected $localinfo = array();   //The local GetInfo() data populated from the syncher
		protected $remoteinfo = array();  //The remote GetInfo() data populated from the syncher

		protected $localdata = array(); //An array of the local DataItems
		protected $remotedata = array(); //An array of the remote DataItems

		public $changes = array(); //An array of the changed items.

		protected $keyfield = 'id';                 //The field(s) used to sort/store the data in the associative arrays. This is the key which *uniquely identifies* this data. This is the field name which has been put into the DataItems and not the field in the database necessarily. Can also be an array.
		protected $namefield = 'name';              //The field which can be used to display this dataitem to the user.
		protected $matchfields = array('field');    //The fields already populated in the DataItems thats used to match the local and remote data together. The fields are attempted from first to last, only one has to match unless you set $strictmatchfields. Do NOT include the post/user/term ID in this list as the IDs cannot be used to match.
		protected $valuefields = array('field');    //The fields which can be used to compare if two data items have the same value.
		protected $mapfields = array('id');         //The fields which are mapped from the local to the remote data and made available through the map() function
		protected $preprocessors = array();         //The slugs of all the processors which need to run before this one can.
		protected $fieldnames = array('field'=>'Field'); //An optional list of nice names for each field that can be displayed to the user.
		protected $matchfieldsarestrict = false;    //If true, then ALL $matchfields have to match to make a link between


		private $maps = array();
		private $syncher = null; //Cache the syncer class for faster access.

		/**
		 * BraveWpsync_Processor constructor.
		 * @param BraveWordsync $wordsync
		 * @param BraveWordsync_Syncer $syncher
		 */
		public function __construct($wordsync, $syncher)
		{
			$this->wordsync = $wordsync;
			$this->syncher  = $syncher;
		}

		public function getDetails()
		{
			return array_merge(array('version'=>$this->version, 'preprocessors'=>$this->preprocessors), $this->details);
		}

		public function setWpsyncInfo($localinfo, $remoteinfo)
		{
			$this->localinfo = $localinfo;
			$this->remoteinfo = $remoteinfo;
		}

		public function makeDataKey($datafields)
		{
			if (is_array($this->keyfield))
			{
				$keys = array();
				foreach ($this->keyfield as $kf)
				{
					$keys[] = $datafields[$kf];
				}

				return implode("|", $keys);
			}
			else
			{
				return $datafields[$this->keyfield];
			}
		}

		/**
		 * Runs a query on the WPDB
		 *
		 * @param $table
		 * @param array $conditions
		 * @param array $exclusions
		 * @return array|mixed|null|object
		 */
		public function sqlQuery($table, $conditions = array(), $exclusions = array())
		{
			/** @var WPDB $wpdb */
			global $wpdb;

			$sql = 'SELECT * FROM '.$table;

			$where = array();
			foreach ($conditions as $key=>$val)
			{
				if (!is_numeric($val)) $val = '"'.$val.'"';
				$where[] = $key . ' = '.$val;
			}


			foreach ($exclusions as $key=>$exc)
			{
				if (!is_array($exc) || count($exc) == 0) continue;

				$exc = array_map(function($item) { return is_numeric($item) ? esc_sql($item) : '"'.esc_sql($item).'"'; }, $exc);
				$where[] = $key . ' NOT IN ('.implode(',', $exc).')';
			}

			if (count($where) > 0)
			{
				$sql .= ' WHERE '. implode(' AND ', $where);
			}

			//$this->wpsync->log("Executing SQL: ".$sql);
			$result = $wpdb->get_results($sql, ARRAY_A);

			return $result;

		}

		/**
		 * Returns the array of preprocessor slugs.
		 * @return array
		 */
		public function getPreprocessors()
		{
			return $this->preprocessors;
		}

		/**
		 * Returns the BraveWpSync_Processor associated with this slug
		 * @param $slug
		 * @return bool|BraveWordsync_Processor
		 */
		public function getProcessor($slug)
		{
			return $this->syncher->getProcessor($slug);
		}

		public function isLocalDataLoaded()
		{
			return (count($this->localdata) > 0);
		}

		public function isRemoteDataLoaded()
		{
			return (count($this->remotedata) > 0);
		}

		public function getRemoteDataitem($key)
		{
			foreach ($this->remotedata as $dataitem)
			{
				/** @var BraveWordsync_DataItem $dataitem */

				if ($dataitem->key == $key)
				{
					return $dataitem;
				}
			}

			return null;
		}

		public function getLocalDataitem($key)
		{
			foreach ($this->localdata as $dataitem)
			{
				/** @var BraveWordsync_DataItem $dataitem */

				if ($dataitem->key == $key)
				{
					return $dataitem;
				}
			}

			return null;
		}

		/**
		 * Maps a remote field value to a local one but only once the local and remote data have been compared.
		 *
		 * @param $field
		 * @param $remotevalue
		 * @return false|mixed Returns the local value which corresponds to $remotevalue, or the $remotevalue when no mapping exists.
		 * @throws Exception
		 */
		public function map($field, $remotevalue)
		{

			if (!isset($this->maps[$field]))
			{
				$this->wordsync->logWarning("The requested map field " . $field . " was not declared as a mapped field in the " . $this->slug . " processor.");
				return $remotevalue;
				//throw new Exception("The requested map field ".$field." was not declared as a mapped field in the ".$this->slug." processor.");
			}

			//$this->wpsync->log("Looking to map ".$field.":", $this->maps[$field]);

			foreach ($this->maps[$field] as $mapping)
			{
				//$mapping is array(local, remote)
				if ($mapping[1] == $remotevalue)
				{
					return $mapping[0];
				}
			}

			return $remotevalue;
		}

		public function getSlug()
		{
			return $this->slug;
		}

		/**
		 * Gets the display name of the supplied data item, based on this processor's name field.
		 * @param BraveWordsync_DataItem $dataitem
		 * @return mixed
		 */
		public function getDataItemName($dataitem)
		{
			return $dataitem->getField($this->namefield);
		}

		public function getFieldName($field)
		{
			return (isset($this->fieldnames[$field]) ? $this->fieldnames[$field] : $field);
		}

		/**
		 * Gather the local information from this Wordpress install and create DataItems for each piece of data. Filling the $this->localdata field with an array of DataItem classes.
 		 * Must SET the $this->localdata array too.
		 * Returns a MakeResult object.
		 */
		public function getLocalData()
		{
			//To be overwritten by descendants.

			$this->localdata = array();
			return $this->wordsync->makeResult(false, 'Warning, your processor needs to overwrite the getLocalData function.');
		}

		public function getRemoteData()
		{
			$result = $this->wordsync->getSyncher()->sendRemoteCommand("pull", array("processor" =>$this->getSlug()));

			if ($result['success'] && isset($result['data']) && is_array($result['data']))
			{
				$this->JSONToRemoteData($result['data']);

				return $this->wordsync->makeResult(true);
			}
			else
			{
				if (isset($result['success']))
				{
					return $result;
				}
				else
				{
					return $this->wordsync->makeResult(false, 'Communication failure - Unable to retrieve remote data or data was not transmitted in the correct format.', array('res' =>$result));
				}
			}
		}

		/**
		 * Outputs a flat associative array of the $this->localdata data.
		 *
		 * @return array
		 */
		public function localDataToJSON()
		{
			$json = array();

			//Load the local data if it hasnt been already.
			if (!$this->isLocalDataLoaded()) $this->getLocalData();

			foreach ($this->localdata as $dataitem)
			{
				if ($dataitem instanceof BraveWordsync_DataItem)
				{
					$json[] = $dataitem->toJSON();
				}
			}

			return $json;
		}

		/**
		 * Recieves remote data in the form of raw associative arrays and loads them into Data Items into the processor.
		 * @param $remotedata
		 */
		protected function JSONToRemoteData($remotedata)
		{
			$this->remotedata = array();

			try
			{

			foreach ($remotedata as $json)
			{

				$dataitem = new BraveWordsync_DataItem($json['f'], $json['k'], false);
				$dataitem->runRemotePreprocessor(array(&$this, 'remotePreprocessor'));
				$this->remotedata[] = $dataitem;
			}

			}
			catch (Exception $e)
			{
				$this->wordsync->logError("Caught an exception while " . $this->slug . " processor tried to load data from remote json! ", array('msg' =>$e->getMessage(), 'file' =>$e->getFile(), 'line' =>$e->getLine(), 'trace' =>$e->getTraceAsString()));
			}
		}

		/**
		 * Adds a mapping entry to the mapping table for all the mapfields of the two specified data items.
		 *
		 * @param BraveWordsync_DataItem $localdataitem
		 * @param BraveWordsync_DataItem $remotedataitem
		 */
		protected function addMapping($localdataitem, $remotedataitem)
		{
			//Save the mapping fields between the two:
			foreach ($this->mapfields as $mapfield)
			{
				if (!isset($this->maps[$mapfield])) $this->maps[$mapfield] = array();

				$a = $localdataitem->getField($mapfield);
				$b = $remotedataitem->getField($mapfield);


				if ($a != $b)
				{
					//Check for duplicate entries.
					foreach ($this->maps[$mapfield] as $thismap)
					{
						if ($thismap[0] == $a && $thismap[1] == $b) continue;
					}

					$this->maps[$mapfield][] = array($a, $b);
				}
			}
		}

		public function addNewLocalData($fielddata, $mapToRemoteDataItem = null)
		{
			$di = new BraveWordsync_DataItem($fielddata, $this->makeDataKey($fielddata));
			$this->localdata[] = $di;

			if (!is_null($mapToRemoteDataItem))
			{
				$this->addMapping($di, $mapToRemoteDataItem);
			}
		}


		/**
		 * Compares the local and remote data items and performs two operations:
		 * 1. Local data items are matched to the remote items, forming links between the two.
		 * 2. A list of changes is made by comparing the values of both and marking each Addition, Deletion or Update as a new change.
		 *
		 * After running this function, this Processor's changes[] array is now populated.
		 */
		public function compareLocalAndRemote()
		{

			$this->wordsync->log('Matching local and remote ' . $this->getSlug());
			$this->wordsync->log("There are " . count($this->localdata) . " local data items.");
			$this->wordsync->log("There are " . count($this->remotedata) . " remote data items.");

			$this->changes = array();
			$this->maps = array();


			foreach ($this->localdata as $dataitem)
			{

				/** @var BraveWordsync_DataItem $dataitem */
				$dataitem->remotekey = '';

				$found = false;

				$matchedremote = null;

				foreach ($this->remotedata as &$remotedataitem)
				{
					/** @var BraveWordsync_DataItem $remotedataitem */
					if ($remotedataitem->remotekey != '') continue;

					if ($dataitem->key == $remotedataitem->key || $dataitem->matchWithRemote($remotedataitem, $this->matchfields, $this->matchfieldsarestrict))
					{
						//Establish the connection between the two.
						$dataitem->remotekey = $remotedataitem->key;
						$remotedataitem->remotekey = $dataitem->key;
						$found = true;
						$matchedremote = $remotedataitem;
						break;
					}
				}
				unset($remotedataitem);


				if ($found) //We have found a match between the local data item and a remote one.
				{
					//Save the mapping fields between the two:
					$this->addMapping($dataitem, $matchedremote);


					//Check if the values of the data is the same:
					if (!$dataitem->matchWithRemote($matchedremote, $this->valuefields, true))
					{
						//Since their value fields dont match exactly, mark this as an update.
						//Get a list of all the fields of this item which do not match the fields of the remote item.
						$unmatchedfields = $dataitem->getListOfUnmatchedFields($matchedremote, $this->valuefields);

						$this->createChange(BraveWordsync_Change::ACTION_UPDATE, $dataitem, $matchedremote, $unmatchedfields);
					}
					//else they are both exactly equal so do nothing.

				}
				else
				{
					//No matching remote data was found for this local data item. Mark it for deletion.
					$this->createChange(BraveWordsync_Change::ACTION_REMOVE, $dataitem, null, $this->valuefields);
				}
			}

			foreach ($this->remotedata as $remotedataitem)
			{
				if ($remotedataitem->remotekey == '')
				{
					//This remote data item has no local data item matched with it. So it should be added.
					$this->createChange(BraveWordsync_Change::ACTION_CREATE, null, $remotedataitem, $this->valuefields);
				}
			}


		}

		private function createChange($action, $dataitem, $remotedataitem, $fields = array())
		{
			//$this->wpsync->log("Creating new ".BraveWpSync_Change::actionToString($action). " action for data items:", array('L'=>$dataitem, 'R'=>$remotedataitem, 'Fields'=>$fields));
			$change = new BraveWordsync_Change($action, $dataitem, $remotedataitem, $fields, count($this->changes));
			$this->changes[] = $change;
		}

		public function toJSON()
		{
			$result = array('changes' => array(), 'maps'=>$this->maps);

			foreach ($this->changes as $change)
			{
				/** @var BraveWordsync_Change $change */
				$result['changes'][] = $change->toJSON();
			}

			return $result;
		}

		public function importFromJSON($json)
		{
			if (!isset($json['maps']) || !isset($json['changes'])) return false;

			$this->maps = $json['maps'];
			$this->changes = array();
			foreach ($json['changes'] as $jsonchange)
			{
				$this->changes[] = BraveWordsync_Change::createFromJSON($jsonchange);
			}

			return true;
		}


		/**
		 * Goes through all the changes created so far and runs the remotePostprocessor function on them so that certain fields can be mapped and the change could be discarded if the data ends up being equal.
		 * When this function is called, all processors will have maps available (Except for data items which have yet to be created).
		 */
		public function postProcessChanges()
		{
			$markedfordeletion = array();
			foreach ($this->changes as $change)
			{
				/** @var BraveWordsync_Change $change */

				if (is_null($change->remotedataitem)) continue;

				foreach ($change->fields as $field)
				{
					$oldval = $change->remotedataitem->getField($field);
					$val = $this->remotePostprocessor($field, $oldval);

					//No way of telling if the value changed (unless you want to do a deep object matching function) so lets just set the new value regardless.
					$change->remotedataitem->setField($field, $val);
				}

				if (!is_null($change->dataitem) && $change->action == BraveWordsync_Change::ACTION_UPDATE)
				{
					//There is an assigned local data item being updated:

					//$change->dataitem->getListOfUnmatchedFields()
					$unmatchedfields = $change->dataitem->getListOfUnmatchedFields($change->remotedataitem, $this->valuefields);

					$matchesall = count($unmatchedfields) == 0;

					if ($matchesall)
					{
						//The two data items which were marked as being different are now exactly the same, as their outstanding fields match.
						//Thus, mark this change for deletion.
						$markedfordeletion[] = $change;
					}
					else
					{
						//Update the list of fields needing to be updated.
						$change->fields = $unmatchedfields;
					}
				}
			}


			if (count($markedfordeletion) > 0)
			{
				//Rebuild the changes array, leaving out each change which appears in the $markedfordeletion array.

				$this->wordsync->log("Removed " . count($markedfordeletion) . " change(s) because the PostProcessing cleared up all discrepancies...");

				$newchanges = array();
				foreach ($this->changes as $change)
				{
					$found = false;
					foreach ($markedfordeletion as $del)
					{
						if ($change === $del)
						{
							$found = true;
							break;
						}
					}

					if (!$found)
					{
						$newchanges[] = $change;
					}
				}

				$this->changes = $newchanges;
			}

			$this->wordsync->log($this->slug . " PostProcessing Complete.");
		}

		/**
		 * To be overwritten by decendants, this function takes a remote dataitem's field and runs whatever preprocessing required by the current processor.
		 * Eg. a settings preprocessor would change all instances of the remote SITEURL with the current SITEURL to avoid unnecessary differences showing up on the list.
		 *
		 * @param $field - the name of the field being sent.
		 * @param $value - the value of that field.
		 * @param $type  - the type of the field. See BraveWpSync_DataItem::TYPE constants
		 * @return mixed - The value of the field after perhaps being altered by this function.
		 */
		public function remotePreprocessor($field, $value, $type)
		{
			//To be overwritten by decendants.
			return $value;
		}

		public function remotePostprocessor($field, $value)
		{
			//To be overwritten by decendants
			return $value;
		}

		/**
		 * Main processing function for this processor.
		 * Loops through the selectedids array and performs only the changes who's ids appear on that list.
		 *
		 * @param $selectedids
		 * @return array
		 */
		public function performChanges($selectedids)
		{

			$this->wordsync->log($this->slug . ": Performing Before Processing Routines...");
			$this->beforeProcessing();

			//Build a changelist sorted by change action type:
			$changelist = array(BraveWordsync_Change::ACTION_CREATE=>array(),BraveWordsync_Change::ACTION_UPDATE=>array(),BraveWordsync_Change::ACTION_REMOVE=>array());
			foreach ($this->changes as $change)
			{
				/** @var BraveWordsync_Change $change */
				if (in_array($change->id, $selectedids))
				{
					$changelist[$change->action][] = $change;
				}
			}

			//This array dictates the order in which the changes are processed.
			//By default all updates should happen first then removals then creates. This is to ensure that all data is removed first before creating new data, in case the same email addresses / user ids / unique numbers are used.
			$executionorder = array(
				BraveWordsync_Change::ACTION_UPDATE,
				BraveWordsync_Change::ACTION_REMOVE,
				BraveWordsync_Change::ACTION_CREATE
			);

			//Execute the changes in $executionorder's order:
			foreach ($executionorder as $changetype)
			{
				foreach ($changelist[$changetype] as $change)
				{
					switch ($change->action)
					{
						case BraveWordsync_Change::ACTION_CREATE:
							$res = $this->performCreateAction($change);
							break;

						case BraveWordsync_Change::ACTION_REMOVE:
							$res = $this->performRemoveAction($change);
							break;

						case BraveWordsync_Change::ACTION_UPDATE:
							$res = $this->performUpdateAction($change);
							break;

						default:
							$res = $this->wordsync->makeResult(false, 'Unknown change action type!', $change);
					}

					if (!$this->wordsync->checkResult($res))
					{
						//An error occured. Print to log and output to screen.
						$this->wordsync->logError("Error while performing ".$this->slug." " . BraveWordsync_Change::actionToString($change->action) . " change " . $change->id . ".", array('change' =>$change, 'result' =>$res));
						$res['msg'] = $res['msg']."\n"." Error while performing ".$this->slug." ". BraveWordsync_Change::actionToString($change->action) . " change " . $change->id . ".";
						return $res;
					}
					else
					{
						$this->wordsync->log("Processor " . $this->slug . " performed " . BraveWordsync_Change::actionToString($change->action) . " change " . $change->id . " successfully!");
					}
				}

			}

			$this->wordsync->log($this->slug . ": Performing After Processing Routines...");
			$this->afterProcessing();

			$this->wordsync->log($this->slug . ": DONE PROCESSING.");
			return $this->wordsync->makeResult(true, "All selected changes made.");
		}

		/**
		 * @param BraveWordsync_Change $change
		 * @return array
		 */
		protected function performRemoveAction($change)
		{
			//To be Overwritten by decendants
			return $this->wordsync->makeResult(false, "This processor does not define what happens when performing a change to the data. Please implement the performRemoveChange() function in your subclass.");
		}

		/**
		 * @param BraveWordsync_Change $change
		 * @return array
		 */
		protected function performCreateAction($change)
		{
			//To be Overwritten by decendants
			return $this->wordsync->makeResult(false, "This processor does not define what happens when performing a change to the data. Please implement the performCreateChange() function in your subclass.");
		}

		/**
		 * @param BraveWordsync_Change $change
		 * @return array
		 */
		protected function performUpdateAction($change)
		{
			//To be Overwritten by decendants
			return $this->wordsync->makeResult(false, "This processor does not define what happens when performing a change to the data. Please implement the performUpdateChange() function in your subclass.");
		}

		/**
		 * Called before this processor has run. Descendants can use this function to perform any sort of setup routines needed before execution.
		 */
		protected function beforeProcessing()
		{
			//To be Overwritten by decendants
		}

		/**
		 * Called after this processor has ran. Descendants can use this function to perform any sort of clean up routines after they have run.
		 */
		protected function afterProcessing()
		{
			//To be Overwritten by decendants
		}

	}