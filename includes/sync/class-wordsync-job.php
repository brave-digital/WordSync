<?php

	/**
	 * class-wordsync-job.php
	 *
	 * The Wordsync Job class stores the status and information about a specific sync job.
	 *
	 * Date: 2017/01/17
	 * Time: 02:09 PM
	 */

	define('TRANSIENT', 'bravewordsyncjob-');

	class BraveWordsync_Job
	{

		public static function generateID()
		{
			mt_srand((double)microtime() * 10000);
			return uniqid(mt_rand(), false) . '';
		}

		public static function jobExists($jobid)
		{
			return (get_transient(TRANSIENT.$jobid) !== false);
		}

		const STATUS_NONE = 0;
		const STATUS_WAITING = 1;
		const STATUS_GATHERING = 2;
		const STATUS_REVIEWING = 3;
		const STATUS_UPDATING = 4;
		const STATUS_DONE = 5;

		const PROCESSOR_STATUS_WAITING = 0;
		const PROCESSOR_STATUS_PROCESSING = 1;
		const PROCESSOR_STATUS_DONEPROCESSING = 2;
		const PROCESSOR_STATUS_UPDATING = 3;
		const PROCESSOR_STATUS_DONEUPDATING = 4;


		/** @var BraveWordsync $wordsync */
		protected $wordsync;

		protected $id;
		protected $status = BraveWordsync_Job::STATUS_NONE;
		protected $startdate;
		protected $remoteurl;
		protected $processors = array('processor'=>array('slug'=>'processor', 'status'=>BraveWordsync_Job::PROCESSOR_STATUS_WAITING));


		/**
		 * BraveWpSync_Job constructor.
		 * @param $wordsync
		 * @param $remoteurl
		 * @param array $processors - If the job ID is zero, then the job will be newly created. Pass an array of processor slugs to initalise the job.
		 * @param int $id - The job ID. If non-zero, then the job will be loaded from a transient. No need to supply the $processors array.
		 */
		public function __construct($wordsync, $remoteurl, $processors = array(), $id = 0)
		{
			
			$this->wordsync  = $wordsync;
			$this->remoteurl = $remoteurl;
			$this->id        = ($id == 0 ? BraveWordsync_Job::generateID() : $id);


			$this->status = BraveWordsync_Job::STATUS_WAITING;
			$this->startdate = time();

			if ($id != 0)
			{
				$this->loadJob($id);
			}
			else
			{
				$this->processors = array();
				foreach ($processors as $procslug)
				{
					$this->addProcessor($procslug);
				}
			}

		}

		protected function addProcessor($slug, $status = BraveWordsync_Job::PROCESSOR_STATUS_WAITING, $selects = array())
		{
			$proc = $this->wordsync->getSyncher()->getProcessor($slug);
			if ($proc !== false)
			{
				$this->processors[$slug] = array('slug' => $slug, 'status' => $status, 'proc' => $proc, 'selects'=>$selects);
			}
		}

		public function getID()
		{
			return $this->id;
		}
		public function getRemoteUrl()
		{
			return $this->remoteurl;
		}
		public function getStartDate()
		{
			return $this->startdate;
		}
		public function getStatus()
		{
			return $this->status;
		}

		/**
		 * Compiles and lists all the changes to this job's processors. Easily formatted for json consumption.
		 *
		 * @return array
		 */
		public function getChangesReviewJSON()
		{

			$changes = array();
			foreach ($this->processors as $p)
			{
				if ($this->getProcessorStatus($p['slug']) != BraveWordsync_Job::PROCESSOR_STATUS_DONEPROCESSING) continue;

				$proc = $p['proc'];
				/** @var $proc BraveWordsync_Processor */

				$procdetails = $proc->getDetails();

				$thisproc = array(
					'slug' => $p['slug'],
					'name' => $procdetails['name'],
					'changes' => array()
				);

				foreach ($proc->changes as $change)
				{
					/** @var BraveWordsync_Change $change */

					$changejson = array(
						'action'=> BraveWordsync_Change::actionToJSON($change->action),
						'id'=>$change->id,
						'lname'=> ($change->dataitem ? $proc->getDataItemName($change->dataitem) : ''),
						'lkey'=> ($change->dataitem ? $change->dataitem->key : ''),
						'rname'=> ($change->remotedataitem ? $proc->getDataItemName($change->remotedataitem) : ''),
						'rkey'=> ($change->remotedataitem ? $change->remotedataitem->key : ''),
						'differences'=> $change->differencesToJSON($proc)
					);

					$thisproc['changes'][] = $changejson;
				}

				$changes[] = $thisproc;
			}


			return $changes;
		}


		public function setProcessorStatus($processor, $newstatus = BraveWordsync_Job::PROCESSOR_STATUS_WAITING)
		{
			if (!isset($this->processors[$processor])) return;

			$this->processors[$processor]['status'] = $newstatus;
			$this->saveJob();

		}

		public function getProcessorStatus($processor)
		{
			if (!isset($this->processors[$processor])) return BraveWordsync_Job::PROCESSOR_STATUS_WAITING;

			return $this->processors[$processor]['status'];
		}

		protected function jobToTransient()
		{
			$procs = array();
			foreach ($this->processors as $proc)
			{
				$procs[$proc['slug']] = array('slug'=>$proc['slug'], 'status'=>$proc['status'], 'selects'=>$proc['selects']);
			}

			$trans = array(
				'id'=>$this->id,
				'status'=>$this->status,
				'remoteurl' =>$this->remoteurl,
				'startdate'=>$this->startdate,
				'processors'=>$procs);

			return $trans;
		}

		protected function jobFromTransient($job)
		{
			$this->id = $job['id'];
			$this->status = $job['status'];
			$this->startdate = $job['startdate'];
			$this->remoteurl = $job['remoteurl'];

			$procs = $job['processors'];
			$this->processors = array();
			foreach ($procs as $p)
			{
				$this->addProcessor($p['slug'], $p['status'], $p['selects']);
			}



			return true;
		}

		public function saveJob()
		{
			//load the job from it's transient.
			if (set_transient(TRANSIENT.$this->id, $this->jobToTransient(), 12 * HOUR_IN_SECONDS))
			{

				foreach ($this->processors as $p)
				{
					if ($this->getProcessorStatus($p['slug']) == BraveWordsync_Job::PROCESSOR_STATUS_DONEPROCESSING)
					{
						$proc = $p['proc'];
						if ($proc instanceof BraveWordsync_Processor)
						{
							set_transient(TRANSIENT . $this->id . '-' . $p['slug'], $proc->toJSON(), 12 * HOUR_IN_SECONDS);
						}
					}
				}


				if (!headers_sent())
				{
					setcookie('bravewordsync_currentjob', json_encode($this->toJSON()));
				}


				return true;
			}
			else
			{
				return false;
			}

		}

		public function loadJob($id)
		{
			$this->wordsync->log("Attempting to load job from ID: " . $id);

			$job = get_transient(TRANSIENT.$id);

			if ($job !== false)
			{
				if ($this->jobFromTransient($job))
				{
					foreach ($this->processors as $p)
					{
							$procjson = get_transient(TRANSIENT . $this->id . '-' . $p['slug']);
							if ($procjson !== false)
							{
								/** @var BraveWordsync_Processor $proc */
								$proc = $p['proc'];
								if (!$proc->importFromJSON($procjson))
								{
									$this->wordsync->logError("Error loading processor " . $p['slug'] . "!");
									return false;
								}
							}
					}

					$this->wordsync->log("Loaded job.");
					return true;
				}
			}

			$this->wordsync->logError("Job load failed.");
			return false;
		}

		public function deleteJob()
		{
			delete_transient(TRANSIENT.$this->id);

			foreach ($this->processors as $p)
			{
				delete_transient(TRANSIENT . $this->id . '-' . $p['slug']);
			}

			$this->id = 0;
		}


		/**
		 * Converts all the useful information about this job to an object (ready to be jsonEncoded). This is usually returned to the admin javascript.
		 * @return array
		 */
		protected function toJSON()
		{
			$procs = array();
			foreach ($this->processors as $proc)
			{
				$procs[] = array('slug'=>$proc['slug'], 'status'=>$proc['status'], 'selects'=>$proc['selects']);
			}

			$data = array(
				'id'=>$this->id,
				'status'=> $this->status,
				'processors' => $procs
			);

			return $data;
		}

		/**
		 * Forms a standardised AJAX JSON result which includes useful information about the job - it's id, status, processors, etc.
		 *
		 * @param $success
		 * @param $msg
		 * @param array $extradata
		 * @return array
		 */
		protected function makeJobJSONResult($success, $msg, $extradata = array())
		{
			$data = array_merge($this->toJSON(), $extradata);

			return $this->wordsync->makeResult($success, $msg, $data);
		}

		/**
		 * Performs a single step of the job.
		 *
		 * @param array $params Any additional parameters which are passed to the job from the admin front-end, For example the changes that the user has approved.
		 * @return array
		 */
		public function doJobStep($params = array())
		{
			$this->wordsync->log("Job " . $this->id . " doing job step.");
			try
			{
				switch ($this->status)
				{

					case BraveWordsync_Job::STATUS_WAITING:
						$this->wordsync->log("Job " . $this->id . " WAITING -> GATHERING");

						$this->status = BraveWordsync_Job::STATUS_GATHERING;
						$this->saveJob();
						return $this->makeJobJSONResult(true, 'Starting Up...');
					break;

					case BraveWordsync_Job::STATUS_GATHERING:

						$this->wordsync->log("Job " . $this->id . " GATHERING step:");
						foreach ($this->processors as $proc)
						{
							if ($proc['status'] == BraveWordsync_Job::PROCESSOR_STATUS_WAITING)
							{
								/** @var BraveWordsync_Processor $processor */
								$processor = $proc['proc'];

								$this->wordsync->log("Trying to run processor " . $proc['slug']);

								$canrun = true;
								foreach ($processor->getPreprocessors() as $preprocessor)
								{
									if ($this->getProcessorStatus($preprocessor) != BraveWordsync_Job::PROCESSOR_STATUS_DONEPROCESSING)
									{
										$this->wordsync->log("Skipping because it's prerequisite " . $preprocessor . " has not run yet.");

										$canrun = false;
										break;
									}
								}
								if ($canrun)
								{
									$this->wordsync->log($proc['slug'] . " -> PROCESSING");

									$this->setProcessorStatus($proc['slug'], BraveWordsync_Job::PROCESSOR_STATUS_PROCESSING);

									$result = $this->wordsync->getSyncher()->performProcessor($proc['slug']);

									if ($this->wordsync->checkResult($result))
									{
										$this->wordsync->log($proc['slug'] . " -> DONE (" . $result['msg'] . ')');

										$this->setProcessorStatus($proc['slug'], BraveWordsync_Job::PROCESSOR_STATUS_DONEPROCESSING);

										break; //Only do one processor per Job Step. If there are 3 processors selected, it will take three job steps to complete gathering.
									}
									else
									{
										$this->wordsync->logError($proc['slug'] . " -> ERROR:", $result);

										$this->deleteJob();
										return $this->makeJobJSONResult(false, $result['msg'], $result);
									}
								}
							}
						}

						$alldone = true;
						foreach ($this->processors as $proc)
						{
							if ($proc['status'] != BraveWordsync_Job::PROCESSOR_STATUS_DONEPROCESSING)
							{
								$alldone = false;
							}
						}


							if ($alldone)
							{
								$this->wordsync->log("All processors done! Running post processing...");


								foreach ($this->processors as $p)
								{
									$p['proc']->postProcessChanges();
								}

								$this->wordsync->log("Job " . $this->id . " GATHERING -> REVIEWING");

								$this->status = BraveWordsync_Job::STATUS_REVIEWING;

								$this->saveJob();

								return $this->makeJobJSONResult(true, 'Gathering complete. Now entering review phase.', array('changes'=>$this->getChangesReviewJSON()));
							}
							else
							{

								$this->saveJob();

								return $this->makeJobJSONResult(true, 'Gathering step done. (gathering phase continues...)');
							}
					break;

					case BraveWordsync_Job::STATUS_REVIEWING:

						if (!isset($params['selects']))
						{

							return $this->makeJobJSONResult(false, 'Please select at least one change to perform.');
						}
						else
						{
							$atleastoneselected = false;
							$sels = $params['selects'];
							foreach ($this->processors as &$proc)
							{
								if (isset($sels[$proc['slug']]))
								{
									$proc['selects'] = $sels[$proc['slug']];

									if (is_array($proc['selects']) && count($proc['selects']) > 0)
									{
										$atleastoneselected = true;
									}
									else
									{
										$proc['selects'] = array();
									}
								}
							}

							if (!$atleastoneselected)
							{
								return $this->makeJobJSONResult(false, 'No valid changes were selected.');
							}

							$this->status = BraveWordsync_Job::STATUS_UPDATING;
							$this->saveJob();

							return $this->makeJobJSONResult(true, 'Review step done. Now Updating...');
						}

					break;

					case BraveWordsync_Job::STATUS_UPDATING:

						$this->wordsync->log("Job " . $this->id . " UPDATE step:");

						$iscomplete = true;
						foreach ($this->processors as &$proc)
						{
							/** @var BraveWordsync_Processor $processor */
							$processor = $proc['proc'];

							if ($proc['status'] == BraveWordsync_Job::PROCESSOR_STATUS_DONEPROCESSING)
							{
								$iscomplete = false;

								$this->setProcessorStatus($proc['slug'], BraveWordsync_Job::PROCESSOR_STATUS_UPDATING);

								$res = $processor->performChanges($proc['selects']);
								if (!$this->wordsync->checkResult($res))
								{
									return $res;
								}
								else
								{
									$this->setProcessorStatus($proc['slug'], BraveWordsync_Job::PROCESSOR_STATUS_DONEUPDATING);
								}

								break;
							}
						}

						$this->saveJob();

						if ($iscomplete)
						{
							$this->status = BraveWordsync_Job::STATUS_DONE;
							$this->deleteJob();
						}

						return $this->makeJobJSONResult(true, $iscomplete ? 'Updating step done! Job should now be complete.' : 'Performed an update step.');
					break;

					default:
						return $this->makeJobJSONResult(false, 'Job is invalid or has stalled.');
					break;
				}

			}
			catch(Exception $e)
			{
				$this->wordsync->logError("Error while running job step! ", array('msg' =>$e->getMessage(), 'file' =>$e->getFile(), 'line' =>$e->getLine(), 'trace' =>$e->getTraceAsString()));
				return $this->makeJobJSONResult(false, 'Job encountered an error while processing! '.$e->getMessage());

			}
		}
	}